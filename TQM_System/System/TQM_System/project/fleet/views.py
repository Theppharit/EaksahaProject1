"""หน้าคนขับรถ — เจ้าของรถจัดการของตัวเอง / ผจก.ดูได้ทุกกลุ่ม"""

from django.contrib import messages
from django.contrib.auth.decorators import login_required
from django.core.exceptions import PermissionDenied
from django.shortcuts import get_object_or_404, redirect, render

from project.accounts.models import Role

from .forms import DriverForm
from .models import Driver, TruckOwner


def _scope(user):
    """เจ้าของรถเห็นเฉพาะคนขับของตัวเอง ผจก./แอดมิน/ผู้บริหารเห็นทุกกลุ่ม"""
    qs = Driver.objects.select_related("owner", "truck")
    if user.is_superuser or user.role in (Role.SHR, Role.ADMIN, Role.EXECUTIVE):
        return qs
    if user.role == Role.TRAILER:
        return qs.filter(owner__user=user)
    raise PermissionDenied("ตำแหน่งของคุณไม่มีสิทธิ์ดูข้อมูลคนขับ")


def _my_owner(user):
    return TruckOwner.objects.filter(user=user).first()


@login_required
def driver_list(request):
    drivers = _scope(request.user)

    keyword = request.GET.get("q", "").strip()
    if keyword:
        drivers = drivers.filter(name__icontains=keyword)

    status = request.GET.get("status")
    if status == "free":
        drivers = drivers.filter(is_available=True)
    elif status == "busy":
        drivers = drivers.filter(is_available=False)

    return render(request, "fleet/driver_list.html", {
        "drivers": drivers, "keyword": keyword, "status": status or "",
        "can_add": request.user.role == Role.TRAILER and _my_owner(request.user),
        "groups": TruckOwner.objects.all() if request.user.role != Role.TRAILER else None,
    })


@login_required
def driver_detail(request, pk):
    driver = get_object_or_404(_scope(request.user), pk=pk)
    return render(request, "fleet/driver_detail.html", {
        "driver": driver,
        "jobs": driver.bookings.select_related("customer")[:10],
        "can_edit": request.user.role == Role.TRAILER,
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
    """สลับสถานะว่าง / ไม่ว่าง — ปุ่มใหญ่กดง่ายบนมือถือ"""
    driver = get_object_or_404(_scope(request.user), pk=pk)
    if request.user.role != Role.TRAILER:
        raise PermissionDenied
    driver.is_available = not driver.is_available
    driver.save(update_fields=["is_available"])
    messages.success(request, f"เปลี่ยนสถานะ {driver.name} เป็น "
                              f"{'ว่าง' if driver.is_available else 'ไม่ว่าง'} แล้ว")
    return redirect(request.META.get("HTTP_REFERER") or "fleet:driver_list")
