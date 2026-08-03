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


def _first_error(exc):
    """ดึงข้อความอ่านรู้เรื่องออกจาก ValidationError / PermissionDenied"""
    messages_list = getattr(exc, "messages", None)
    return "; ".join(messages_list) if messages_list else str(exc)


def _back(request, fallback_pk):
    """
    กลับไปหน้าที่กดปุ่มมา — รับเฉพาะ path ภายในเว็บนี้
    (กันคนยิงฟอร์มพร้อม next=http://เว็บอื่น เพื่อหลอกผู้ใช้)
    """
    nxt = request.POST.get("next", "")
    if nxt.startswith("/") and not nxt.startswith("//"):
        return redirect(nxt)
    return redirect("jobs:detail", pk=fallback_pk)


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


# สถานะที่แต่ละตำแหน่งเห็นได้ในหน้าประวัติ (ตามสเปก)
# เซลล์ไม่ต้องเห็นขั้นตอนภายในอย่าง "รอรับงาน" หรือ "งานบัญชีตรวจสอบ"
HISTORY_STATUSES = {
    # เจ้าของรถสไลด์ดูเฉพาะงานที่จบแล้ว — งานที่ยังวิ่งอยู่ดูได้จากหน้าหลัก
    Role.TRAILER: [
        BookingStatus.CLOSED,
        BookingStatus.CANCELLED,
    ],
    Role.SALES: [
        BookingStatus.ACCEPTED,
        BookingStatus.PENDING_APPROVAL,
        BookingStatus.APPROVED,
        BookingStatus.IN_PROGRESS,
        BookingStatus.CLOSED,
        BookingStatus.CANCELLED,
    ],
}
HISTORY_LIMITS = [25, 50, 100, 200]
DEFAULT_HISTORY_LIMIT = 50


def _history_statuses(user):
    """สถานะที่ผู้ใช้คนนี้เลือกดูได้ — ไม่ระบุไว้ = เห็นครบทุกสถานะ"""
    return HISTORY_STATUSES.get(user.role, list(BookingStatus.values))


@login_required
def booking_history(request):
    """
    ประวัติการจองคิว — ดูได้ทุกสถานะ เลือกกรองทีละสถานะได้
    และปรับจำนวนย้อนหลังได้ (เริ่มต้น 50 รายการ)
    """
    allowed = _history_statuses(request.user)

    status = request.GET.get("status", "")
    if status not in allowed:
        status = ""

    try:
        limit = int(request.GET.get("limit") or DEFAULT_HISTORY_LIMIT)
    except (TypeError, ValueError):
        limit = DEFAULT_HISTORY_LIMIT
    if limit not in HISTORY_LIMITS:
        limit = DEFAULT_HISTORY_LIMIT

    base = _visible(request.user).filter(status__in=allowed)

    # จำนวนของแต่ละสถานะ — นับครั้งเดียวใน Python แล้วแจกลงแท็บ
    counts = {}
    for value in base.values_list("status", flat=True):
        counts[value] = counts.get(value, 0) + 1

    labels = dict(BookingStatus.choices)
    tabs = [{"value": "", "label": "ทั้งหมด", "count": sum(counts.values())}] + [
        {"value": v, "label": labels[v], "count": counts.get(v, 0)} for v in allowed
    ]

    rows = base if not status else base.filter(status=status)

    return render(
        request,
        "jobs/booking_history.html",
        {
            "bookings": list(rows.order_by("-created_at")[:limit]),
            "tabs": tabs,
            "current_status": status,
            "limit": limit,
            "limit_choices": HISTORY_LIMITS,
            "total_shown": min(limit, (counts.get(status) if status else sum(counts.values())) or 0),
        },
    )


@login_required
def approvals(request):
    """
    รายการรออนุมัติ — ใช้หน้าเดียวกันทั้ง ผจก.สาขา และงานบัญชี
    ผจก.จะเห็นคำขอแก้ไข/ยกเลิกเพิ่มมาด้วย (สเปกกำหนดให้ ผจก.เป็นคนตัดสิน)
    """
    config = services.approval_config(request.user)
    if config is None:
        raise PermissionDenied("ตำแหน่งของคุณไม่มีสิทธิ์เข้าหน้านี้")

    bookings = list(
        _visible(request.user)
        .filter(status__in=config["statuses"])
        .order_by("appointment_at")
    )
    for booking in bookings:
        booking.ui_actions = services.approval_actions(booking, request.user)

    return render(
        request,
        "jobs/approvals.html",
        {
            "config": config,
            "bookings": bookings,
            "open_requests": services.open_requests_for(request.user)
            if config["show_requests"]
            else [],
        },
    )


@login_required
def approval_action(request, pk):
    """
    ปุ่มอนุมัติ / ตีกลับ บนหน้ารออนุมัติ
    ⚠ อนุมัติต้องกรอกรหัสผ่านยืนยันตัวตนทุกครั้ง — ตรวจก่อนแตะฐานข้อมูล
    """
    if request.method != "POST":
        return redirect("jobs:pending")

    booking = get_object_or_404(_visible(request.user), pk=pk)
    target = request.POST.get("target", "")
    note = request.POST.get("note", "")

    try:
        # ตีกลับ/ยกเลิก ไม่ต้องยืนยันตัวตน (ไม่ใช่การอนุมัติ และมีหมายเหตุบังคับอยู่แล้ว)
        is_approval = (booking.status, target) not in services.SEND_BACK and target != BookingStatus.CANCELLED
        if is_approval:
            services.verify_password(request.user, request.POST.get("password", ""))

        services.perform_action(booking, request.user, target, note)
        messages.success(
            request, f"{booking.booking_no} — สถานะปัจจุบัน: {booking.get_status_display()}"
        )
    except (PermissionDenied, ValidationError) as e:
        messages.error(request, f"{booking.booking_no}: {_first_error(e)}")

    return _back(request, pk)


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
            "can_request": services.can_request(booking, request.user),
            "pending_request": services.pending_request(booking),
            "can_edit": services.can_edit_booking(booking, request.user),
            "booking_requests": booking.requests.select_related("created_by", "decided_by"),
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
        messages.error(request, _first_error(e))

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
            messages.error(request, _first_error(e))

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

    # ผจก./แอดมินแก้ได้เลย ส่วนผู้จองคิวกับเจ้าของรถต้องมี "คำขอแก้ไข" ที่อนุมัติแล้ว
    if not services.can_edit_booking(booking, request.user):
        messages.warning(
            request,
            "ใบจองนี้แก้ไขไม่ได้ — กรุณากดปุ่ม “ขอแก้ไข” เพื่อส่งให้ผู้จัดการอนุมัติก่อน",
        )
        return redirect("jobs:detail", pk=pk)

    form = BookingForm(request.POST or None, instance=booking)
    if request.method == "POST" and form.is_valid():
        form.save()
        services.consume_edit_request(booking)
        messages.success(request, "บันทึกการแก้ไขแล้ว")
        return redirect("jobs:detail", pk=pk)

    return render(request, "jobs/booking_form.html", {"form": form, "booking": booking})


# ============================================================
# คำขอแก้ไข / คำขอยกเลิก
# ============================================================
@login_required
def request_create(request, pk):
    """ผู้จองคิว / เจ้าของรถ กดขอแก้ไขหรือขอยกเลิก"""
    booking = get_object_or_404(_visible(request.user), pk=pk)
    if request.method != "POST":
        return redirect("jobs:detail", pk=pk)

    try:
        services.create_request(
            booking,
            request.user,
            kind=request.POST.get("kind", ""),
            reason=request.POST.get("reason", ""),
        )
        messages.success(request, "ส่งคำขอให้ผู้จัดการแล้ว รอผลการพิจารณาได้เลย")
    except (PermissionDenied, ValidationError) as e:
        messages.error(request, _first_error(e))

    return _back(request, pk)


@login_required
def request_decide(request, pk):
    """ผู้จัดการ / แอดมิน กดอนุมัติหรือไม่อนุมัติคำขอ"""
    from .models import BookingRequest

    req = get_object_or_404(BookingRequest.objects.select_related("booking"), pk=pk)
    if request.method != "POST":
        return redirect("accounts:dashboard")

    try:
        services.decide_request(
            req,
            request.user,
            approve=request.POST.get("decision") == "approve",
            note=request.POST.get("note", ""),
        )
        messages.success(request, "บันทึกผลการพิจารณาแล้ว")
    except (PermissionDenied, ValidationError) as e:
        messages.error(request, _first_error(e))

    return _back(request, req.booking_id)


# ============================================================
# คิวงานที่ยังไม่มีใครรับ (เจ้าของรถสไลด์)
# ============================================================
@login_required
def booking_available(request):
    if request.user.role != Role.TRAILER and not request.user.is_superuser:
        raise PermissionDenied("เฉพาะเจ้าของรถสไลด์เท่านั้น")

    return render(
        request,
        "jobs/booking_list.html",
        {
            "page_obj": _paginate(request, services.available_jobs()),
            "statuses": [],
            "current_status": "",
            "keyword": "",
            "page_title": "คิวงานที่รอผู้รับ",
            "can_create": False,
        },
    )


# ============================================================
# ใบจองคิว (พิมพ์ได้) และ 5 ขั้นตอนการดำเนินงาน
# ============================================================
@login_required
def booking_slip(request, pk):
    """ใบจองคิว — หน้าสำหรับดู/พิมพ์ ตามที่สเปกเรียกว่า 'ใบจองคิว'"""
    booking = get_object_or_404(_visible(request.user), pk=pk)
    return render(request, "jobs/booking_slip.html", {"booking": booking})


@login_required
def job_work(request, pk):
    """หน้าดำเนินงาน 5 ขั้นตอน — ออกแบบให้กดง่ายบนมือถือหน้างาน"""
    booking = get_object_or_404(_visible(request.user), pk=pk)

    if request.user.role != Role.TRAILER and not request.user.is_superuser:
        raise PermissionDenied("เฉพาะเจ้าของรถสไลด์เท่านั้น")

    steps = list(booking.steps.all())
    done = {s.code for s in steps if s.is_done}
    order = [s.code for s in steps]
    next_code = next((c for c in order if c not in done), None)

    return render(request, "jobs/job_work.html", {
        "booking": booking, "steps": steps, "next_code": next_code,
        "all_done": bool(steps) and len(done) == len(steps),
    })


@login_required
def job_step_done(request, pk, code):
    booking = get_object_or_404(_visible(request.user), pk=pk)
    if request.method != "POST":
        return redirect("jobs:work", pk=pk)
    try:
        services.complete_step(
            booking, request.user, code,
            note=request.POST.get("note", ""),
            photos=request.FILES.getlist("photos"),
        )
        messages.success(request, "บันทึกขั้นตอนเรียบร้อย")
    except (PermissionDenied, ValidationError) as e:
        messages.error(request, _first_error(e))
    return redirect("jobs:work", pk=pk)
