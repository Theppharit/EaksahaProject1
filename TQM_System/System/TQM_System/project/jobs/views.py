"""
หน้าจอของระบบใบจอง
------------------------------------------------------------
view ในไฟล์นี้สั้นทุกตัวโดยตั้งใจ — ตรรกะธุรกิจอยู่ใน services.py
หน้าที่ของ view คือ รับ request → เรียก service → ส่งผลไป template
"""

from django.contrib import messages
from django.contrib.auth.decorators import login_required
from django.core.exceptions import PermissionDenied, ValidationError
from django.core.paginator import Paginator
from django.shortcuts import get_object_or_404, redirect, render

from project.accounts.models import Role

from . import services
from .forms import AcceptJobForm, BookingForm
from .models import Booking, BookingStatus

PAGE_SIZE = 20

# ตำแหน่งที่สร้างใบจองได้ (ตามสเปก: ผู้จองคิว และ ผจก.สาขา)
CAN_CREATE = [Role.REQUESTER, Role.SHR, Role.ADMIN]


def _visible(user):
    """ใบจองที่ผู้ใช้คนนี้เห็นได้ — ด่านกันข้อมูลรั่วฝั่งเซิร์ฟเวอร์"""
    return Booking.objects.with_related().for_user(user)


def _paginate(request, qs):
    return Paginator(qs, PAGE_SIZE).get_page(request.GET.get("page"))


# ============================================================
# รายการใบจอง
# ============================================================
@login_required
def booking_list(request):
    qs = _visible(request.user).open()

    status = request.GET.get("status")
    if status in BookingStatus.values:
        qs = qs.filter(status=status)

    keyword = request.GET.get("q", "").strip()
    if keyword:
        qs = qs.filter(booking_no__icontains=keyword) | qs.filter(
            customer__name__icontains=keyword
        )

    return render(
        request,
        "jobs/booking_list.html",
        {
            "page_obj": _paginate(request, qs),
            "statuses": BookingStatus.choices,
            "current_status": status or "",
            "keyword": keyword,
            "page_title": "ใบจองที่ยังไม่ปิดงาน",
            "can_create": request.user.role in CAN_CREATE,
        },
    )


@login_required
def booking_history(request):
    """ประวัติ — เฉพาะที่ปิดงานหรือยกเลิกแล้ว ย้อนหลังตามจำนวนที่เลือก"""
    limit = int(request.GET.get("limit") or 50)
    qs = _visible(request.user).filter(
        status__in=[BookingStatus.CLOSED, BookingStatus.CANCELLED]
    )[:limit]

    return render(
        request,
        "jobs/booking_list.html",
        {
            "page_obj": _paginate(request, list(qs)),
            "statuses": [],
            "current_status": "",
            "keyword": "",
            "page_title": f"ประวัติการจองคิว (ล่าสุด {limit} รายการ)",
            "is_history": True,
            "limit": limit,
            "can_create": False,
        },
    )


@login_required
def booking_pending(request):
    """รายการรออนุมัติ — ผจก.สาขา และงานบัญชี"""
    if request.user.role not in (Role.SHR, Role.ACCOUNTING, Role.ADMIN) and not request.user.is_superuser:
        raise PermissionDenied

    waiting = (
        [BookingStatus.PENDING_APPROVAL]
        if request.user.role == Role.SHR
        else [BookingStatus.SHR_APPROVED, BookingStatus.ACCOUNTING_REVIEW]
    )
    qs = _visible(request.user).filter(status__in=waiting)

    return render(
        request,
        "jobs/booking_list.html",
        {
            "page_obj": _paginate(request, qs),
            "statuses": [],
            "current_status": "",
            "keyword": "",
            "page_title": "รายการรออนุมัติ",
            "can_create": False,
        },
    )


# ============================================================
# รายละเอียดใบจอง + ปุ่มดำเนินการ
# ============================================================
@login_required
def booking_detail(request, pk):
    booking = get_object_or_404(_visible(request.user), pk=pk)

    accept_form = None
    if booking.status == BookingStatus.PENDING_ACCEPT and request.user.role == Role.TRAILER:
        owner = getattr(request.user, "truck_owner", None)
        accept_form = AcceptJobForm(owner=owner)

    return render(
        request,
        "jobs/booking_detail.html",
        {
            "booking": booking,
            "actions": services.available_actions(booking, request.user),
            "logs": booking.status_logs.select_related("by"),
            "steps": booking.steps.all(),
            "accept_form": accept_form,
        },
    )


@login_required
def booking_action(request, pk):
    """ปุ่มเปลี่ยนสถานะทุกปุ่มยิงมาที่นี่"""
    if request.method != "POST":
        return redirect("jobs:detail", pk=pk)

    booking = get_object_or_404(_visible(request.user), pk=pk)
    target = request.POST.get("target", "")
    note = request.POST.get("note", "")

    try:
        services.perform_action(booking, request.user, target, note)
        messages.success(request, f"บันทึกแล้ว — สถานะปัจจุบัน: {booking.get_status_display()}")
    except (PermissionDenied, ValidationError) as e:
        messages.error(request, getattr(e, "message", None) or "; ".join(e.messages) if hasattr(e, "messages") else str(e))

    return redirect("jobs:detail", pk=pk)


@login_required
def booking_accept(request, pk):
    """เจ้าของรถกดรับงาน — เลือกรถและคนขับพร้อมกัน"""
    booking = get_object_or_404(_visible(request.user), pk=pk)
    owner = getattr(request.user, "truck_owner", None)

    if request.user.role != Role.TRAILER or owner is None:
        raise PermissionDenied("เฉพาะเจ้าของรถสไลด์เท่านั้น")

    form = AcceptJobForm(request.POST or None, owner=owner)
    if request.method == "POST" and form.is_valid():
        try:
            services.accept_booking(
                booking, request.user,
                truck=form.cleaned_data["truck"],
                driver=form.cleaned_data["driver"],
                note=form.cleaned_data["note"],
            )
            messages.success(request, "รับงานเรียบร้อย กรุณากรอกรายละเอียดการนัดหมาย")
            return redirect("jobs:detail", pk=pk)
        except (PermissionDenied, ValidationError) as e:
            messages.error(request, "; ".join(e.messages) if hasattr(e, "messages") else str(e))

    return render(request, "jobs/booking_accept.html", {"booking": booking, "form": form})


# ============================================================
# สร้าง / แก้ไขใบจอง
# ============================================================
@login_required
def booking_create(request):
    if request.user.role not in CAN_CREATE and not request.user.is_superuser:
        raise PermissionDenied("ตำแหน่งของคุณสร้างใบจองไม่ได้")

    form = BookingForm(request.POST or None)
    if request.method == "POST" and form.is_valid():
        booking = form.save(commit=False)
        booking.created_by = request.user
        booking.save()
        messages.success(request, f"สร้างใบจอง {booking.booking_no} เรียบร้อย")
        return redirect("jobs:detail", pk=booking.pk)

    return render(request, "jobs/booking_form.html", {"form": form, "is_create": True})


@login_required
def booking_edit(request, pk):
    booking = get_object_or_404(_visible(request.user), pk=pk)

    # แก้ได้เฉพาะก่อนเข้าสถานะกำลังดำเนินการ (ตามสเปก)
    locked = [BookingStatus.IN_PROGRESS, BookingStatus.CLOSED, BookingStatus.CANCELLED]
    if booking.status in locked:
        messages.warning(request, "ใบจองนี้เลยขั้นตอนที่แก้ไขได้แล้ว")
        return redirect("jobs:detail", pk=pk)

    form = BookingForm(request.POST or None, instance=booking)
    if request.method == "POST" and form.is_valid():
        form.save()
        messages.success(request, "บันทึกการแก้ไขแล้ว")
        return redirect("jobs:detail", pk=pk)

    return render(request, "jobs/booking_form.html", {"form": form, "booking": booking})
