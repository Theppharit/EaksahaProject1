"""หน้าคนขับรถ — เจ้าของรถจัดการของตัวเอง / ผจก.ดูได้ทุกกลุ่ม"""

from django.contrib import messages
from django.contrib.auth.decorators import login_required
from django.core.exceptions import PermissionDenied
from django.shortcuts import get_object_or_404, redirect, render

from project.accounts.models import Role

from .forms import DriverForm
from .models import Driver, TruckOwner


def _scope(user):  # noqa: D401
    """เจ้าของรถเห็นเฉพาะคนขับของตัวเอง ผจก./แอดมิน/ผู้บริหารเห็นทุกกลุ่ม"""
    qs = Driver.objects.select_related("owner", "truck")
    if user.is_superuser or user.role in (Role.SHR, Role.ADMIN, Role.EXECUTIVE):
        return qs
    if user.role == Role.TRAILER:
        return qs.filter(owner__user=user)
    raise PermissionDenied("ตำแหน่งของคุณไม่มีสิทธิ์ดูข้อมูลคนขับ")


def _active_job(driver):
    """
    งานที่คนขับคนนี้ยังทำค้างอยู่ (รับงานแล้วแต่ยังไม่ปิด/ไม่ยกเลิก)
    ใช้ 2 ที่: กันกดเปลี่ยนเป็น "ว่าง" กลางคัน และกันลบคนขับที่มีงานค้าง
    """
    from project.jobs.models import Booking

    return (
        Booking.objects.filter(driver=driver)
        .open()
        .select_related("customer")
        .order_by("appointment_at")
        .first()
    )


def _my_owner(user):
    return TruckOwner.objects.filter(user=user).first()


# ตำแหน่งที่ดูคนขับได้ทุกเจ้า (ตรงกับ _scope ด้านบน)
SEE_ALL_ROLES = (Role.SHR, Role.ADMIN, Role.EXECUTIVE)


def _visible_owners(user):
    """เจ้าของรถที่ผู้ใช้คนนี้ดูได้ — เจ้าของรถเห็นแค่ตัวเอง ที่เหลือเห็นทุกเจ้า"""
    if user.is_superuser or user.role in SEE_ALL_ROLES:
        return TruckOwner.objects.filter(is_active=True)
    if user.role == Role.TRAILER:
        return TruckOwner.objects.filter(user=user)
    raise PermissionDenied("ตำแหน่งของคุณไม่มีสิทธิ์ดูข้อมูลคนขับ")


@login_required
def driver_list(request):
    """
    คนขับรถทั้งหมด — จัดกลุ่มตามเจ้าของรถ
    คนขับหลักแสดงเป็นการ์ดทันที ส่วนคนขับเสริมพับไว้ กดกางดูได้
    ข้อมูลคนขับกับรถที่รับผิดชอบอยู่ในการ์ดใบเดียวกัน จะได้ไม่ต้องกดเข้าไปดูทีละคน
    """
    owners = _visible_owners(request.user)

    # ดรอปดาวน์เลือกเจ้าของรถ — ไม่เลือก = ดูทุกเจ้า
    selected = request.GET.get("owner", "")
    if selected and owners.filter(pk=selected).exists():
        shown_owners = owners.filter(pk=selected)
    else:
        selected = ""
        shown_owners = owners

    keyword = request.GET.get("q", "").strip()
    status = request.GET.get("status", "")

    drivers = Driver.objects.select_related("owner", "truck").filter(owner__in=shown_owners)
    if keyword:
        drivers = drivers.filter(name__icontains=keyword)
    if status == "free":
        drivers = drivers.filter(is_available=True)
    elif status == "busy":
        drivers = drivers.filter(is_available=False)

    drivers = list(drivers)

    # คนขับที่ยังมีงานค้างอยู่ — ดึงทีเดียวทั้งหน้า ไม่ยิงทีละคน (กัน N+1)
    from project.jobs.models import Booking

    busy_ids = set(
        Booking.objects.filter(driver__in=drivers).open().values_list("driver_id", flat=True)
    )

    is_owner_view = request.user.role == Role.TRAILER

    # จัดกลุ่มใน Python — query เดียวจบ ไม่ยิงซ้ำต่อเจ้าของรถ
    buckets = {}
    for d in drivers:
        d.ui_locked = d.pk in busy_ids   # กำลังรับงานอยู่ → เปลี่ยนเป็นว่างเองไม่ได้
        d.ui_manage = is_owner_view       # เจ้าของรถเท่านั้นที่เห็นปุ่มจัดการ
        g = buckets.setdefault(d.owner_id, {"owner": d.owner, "main": [], "backup": []})
        (g["backup"] if d.is_backup else g["main"]).append(d)

    groups = [
        {
            **g,
            "total": len(g["main"]) + len(g["backup"]),
            "free": sum(1 for d in g["main"] + g["backup"] if d.is_available),
        }
        for g in sorted(buckets.values(), key=lambda x: x["owner"].name)
    ]

    return render(request, "fleet/driver_list.html", {
        "groups": groups,
        "owners": owners,
        "selected_owner": selected,
        "keyword": keyword,
        "status": status,
        "can_add": is_owner_view and _my_owner(request.user),
        "owner_mode": is_owner_view,
        "total_drivers": sum(g["total"] for g in groups),
    })


@login_required
def driver_detail(request, pk):
    driver = get_object_or_404(_scope(request.user), pk=pk)
    is_owner = request.user.role == Role.TRAILER and driver.owner.user_id == request.user.id

    return render(request, "fleet/driver_detail.html", {
        "driver": driver,
        "jobs": driver.bookings.select_related("customer")[:10],
        "can_edit": is_owner,
        # ลบได้เฉพาะคนขับเสริมของตัวเอง และต้องไม่เคยมีงานผูกอยู่
        # (FK ตั้งเป็น PROTECT ไว้ ถ้ามีงานแล้วลบจะพังทั้งประวัติ)
        "can_delete": is_owner and driver.is_backup and not driver.bookings.exists(),
        "job_count": driver.bookings.count(),
        "active_job": _active_job(driver),
    })


@login_required
def driver_form(request, pk=None):
    """เพิ่ม / แก้ไขคนขับ — เฉพาะเจ้าของรถ"""
    owner = _my_owner(request.user)
    if request.user.role != Role.TRAILER or owner is None:
        raise PermissionDenied("เฉพาะเจ้าของรถสไลด์เท่านั้นที่จัดการคนขับได้")

    driver = get_object_or_404(_scope(request.user), pk=pk) if pk else None
    form = DriverForm(request.POST or None, request.FILES or None, instance=driver, owner=owner)

    if request.method == "POST" and form.is_valid():
        obj = form.save(commit=False)
        obj.owner = owner
        obj.save()
        messages.success(request, "บันทึกข้อมูลคนขับเรียบร้อย")
        return redirect("fleet:driver_detail", pk=obj.pk)

    return render(request, "fleet/driver_form.html", {"form": form, "driver": driver})


@login_required
def driver_toggle(request, pk):
    """
    สลับสถานะว่าง / ไม่ว่าง — ปุ่มใหญ่กดง่ายบนมือถือ

    เริ่มต้นทุกคนคือ "ว่าง" พอกดรับงานระบบจะตั้งเป็น "ไม่ว่าง" ให้อัตโนมัติ
    และล็อกไว้จนกว่าจะกดปิดงาน — กันเผลอกดว่างแล้วโดนจ่ายงานซ้อน
    """
    driver = get_object_or_404(_scope(request.user), pk=pk)
    if request.user.role != Role.TRAILER or driver.owner.user_id != request.user.id:
        raise PermissionDenied("แก้ไขได้เฉพาะคนขับในกลุ่มของคุณเท่านั้น")

    job = _active_job(driver)
    if not driver.is_available and job:
        messages.warning(
            request,
            f"{driver.name} กำลังรับงาน {job.booking_no} อยู่ — "
            f"ระบบจะเปลี่ยนเป็น “ว่าง” ให้เองเมื่อคุณกดปิดงาน",
        )
        return redirect(request.META.get("HTTP_REFERER") or "fleet:driver_list")

    driver.is_available = not driver.is_available
    driver.save(update_fields=["is_available"])
    messages.success(
        request,
        f"เปลี่ยนสถานะ {driver.name} เป็น {'ว่าง' if driver.is_available else 'ไม่ว่าง'} แล้ว",
    )
    return redirect(request.META.get("HTTP_REFERER") or "fleet:driver_list")


@login_required
def driver_delete(request, pk):
    """
    ลบคนขับเสริม — เฉพาะเจ้าของรถ และเฉพาะคนที่ยังไม่เคยมีงานผูกอยู่

    ทำไมลบคนที่เคยมีงานไม่ได้: ใบจองอ้างถึงคนขับแบบ PROTECT
    ถ้าลบได้ ประวัติงานเก่าจะเสียหาย ตอบลูกค้าย้อนหลังไม่ได้
    กรณีคนขับลาออก ให้กดเป็น "ไม่ว่าง" ไว้แทน
    """
    driver = get_object_or_404(_scope(request.user), pk=pk)

    if request.user.role != Role.TRAILER or driver.owner.user_id != request.user.id:
        raise PermissionDenied("ลบได้เฉพาะคนขับในกลุ่มของคุณเท่านั้น")
    if request.method != "POST":
        return redirect("fleet:driver_detail", pk=pk)

    if not driver.is_backup:
        messages.error(request, "ลบได้เฉพาะคนขับเสริมเท่านั้น")
        return redirect("fleet:driver_detail", pk=pk)

    if driver.bookings.exists():
        messages.error(
            request,
            f"{driver.name} มีประวัติงานอยู่ในระบบ ลบไม่ได้ "
            f"เพราะจะทำให้ประวัติใบจองเสียหาย — ถ้าไม่ได้ทำงานแล้วให้กดเป็น “ไม่ว่าง” แทน",
        )
        return redirect("fleet:driver_detail", pk=pk)

    name = driver.name
    driver.delete()
    messages.success(request, f"ลบคนขับเสริม {name} แล้ว")
    return redirect("fleet:driver_list")
