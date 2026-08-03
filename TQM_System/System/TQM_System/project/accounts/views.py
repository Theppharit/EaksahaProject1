"""
หน้า login และแดชบอร์ดของทั้ง 7 ตำแหน่ง
------------------------------------------------------------
แต่ละตำแหน่งเห็นไม่เหมือนกัน แต่ประกอบจากชิ้นส่วนชุดเดียวกัน:
    กราฟแนวโน้ม · กราฟสัดส่วนสถานะ · แถบอันดับ · ปฏิทิน · ตารางใบจอง

ข้อมูลทุกชิ้นถูกกรองด้วย Booking.objects.for_user() ตั้งแต่ต้นทาง
→ ต่อให้ template เผลอโชว์ ก็ไม่มีข้อมูลของคนอื่นให้โชว์
"""

from functools import wraps

from django.contrib import messages
from django.contrib.auth.decorators import login_required
from django.contrib.auth.views import LoginView
from django.shortcuts import redirect, render

from project.jobs import services, stats
from project.jobs.calendars import month_calendar, resolve_month
from project.jobs.models import Booking, BookingStatus

from .forms import EmployeeLoginForm
from .models import Role

# จำนวนแถวสูงสุดในตารางบนแดชบอร์ด (ดูครบให้กดปุ่ม "ดูทั้งหมด")
TABLE_LIMIT = 25


class TQMLoginView(LoginView):
    """หน้าเข้าสู่ระบบ — ถ้าล็อกอินอยู่แล้วให้เด้งไปแดชบอร์ดเลย"""

    template_name = "registration/login.html"
    authentication_form = EmployeeLoginForm
    redirect_authenticated_user = True


@login_required
def dashboard_router(request):
    """ทางแยกหลัง login — ดูตำแหน่งแล้วส่งไปแดชบอร์ดที่ถูกต้อง"""
    return redirect(request.user.dashboard_url_name)


def role_required(role):
    """
    ให้เข้าได้เฉพาะเจ้าของตำแหน่งนั้น (superuser เข้าได้ทุกหน้า)
    ⚠ นี่คือการกันสิทธิ์ฝั่งเซิร์ฟเวอร์ ซึ่งเป็นด่านจริง
      การซ่อนเมนูด้วย CSS เป็นแค่ความสวยงาม ไม่ใช่การป้องกัน
    """

    def decorator(view_func):
        @login_required
        @wraps(view_func)
        def wrapper(request, *args, **kwargs):
            if request.user.role != role and not request.user.is_superuser:
                messages.warning(request, "คุณไม่มีสิทธิ์เข้าถึงหน้านั้น")
                return redirect("accounts:dashboard")
            return view_func(request, *args, **kwargs)

        return wrapper

    return decorator


# ============================================================
# ชิ้นส่วนที่แดชบอร์ดหลายตำแหน่งใช้ร่วมกัน
# ============================================================
STAT_LABELS = {
    Role.REQUESTER: ("คิวที่ยังไม่ปิดงาน", "รอดำเนินการ", "กำลังดำเนินงาน", "ปิดงานแล้ว"),
    Role.SALES: ("คิวที่ดูแลอยู่", "รออนุมัติ", "กำลังดำเนินงาน", "ปิดงานแล้ว"),
    Role.TRAILER: ("งานที่ยังไม่ปิด", "รอรับ/รออนุมัติ", "กำลังดำเนินงาน", "ปิดงานแล้ว"),
    Role.SHR: ("ใบจองที่ยังไม่ปิด", "รออนุมัติ", "กำลังดำเนินงาน", "ปิดงานแล้ว"),
    Role.ACCOUNTING: ("ใบจองในระบบ", "รอตรวจสอบ", "กำลังดำเนินงาน", "ปิดงานแล้ว"),
    Role.EXECUTIVE: ("งานที่ยังไม่ปิด", "รออนุมัติ", "กำลังดำเนินงาน", "ปิดงานแล้ว"),
    Role.ADMIN: ("ใบจองที่ยังไม่ปิด", "รออนุมัติ", "กำลังดำเนินงาน", "ปิดงานแล้ว"),
}

STAT_KEYS = ("open", "waiting", "in_progress", "closed")


def _stat_cards(user):
    """ตัวเลขสรุป 4 ช่องบนหัวแดชบอร์ด"""
    values = services.dashboard_stats(user)
    labels = STAT_LABELS.get(user.role, STAT_LABELS[Role.ADMIN])
    return [
        {"label": labels[i], "value": values[key], "key": key} for i, key in enumerate(STAT_KEYS)
    ]


def _charts(request, qs, with_money=False, rank_by=None):
    """
    ชุดกราฟมาตรฐาน — แนวโน้มจำนวนงาน + สัดส่วนสถานะ
      with_money = เพิ่มกราฟยอดเงิน (บัญชี / ผู้บริหาร)
      rank_by    = "truck_owner" หรือ "sales" ถ้าอยากได้แถบอันดับด้วย
    """
    period = stats.resolve_period(request.GET)
    series = stats.timeseries(qs, period)

    ctx = {
        "period": period,
        "period_choices": stats.PERIOD_CHOICES,
        "trend": stats.line_chart(series, "count"),
        "donut": stats.donut_chart(stats.status_breakdown(qs, period)),
    }
    if with_money:
        ctx["money"] = stats.line_chart(series, "amount")
    if rank_by:
        ctx["ranks"] = stats.ranking(qs, period, group=rank_by)
        ctx["rank_title"] = (
            "เจ้าของรถที่รับงานมากที่สุด" if rank_by == "truck_owner" else "เซลล์ที่มีงานมากที่สุด"
        )
    return ctx


def _calendar(request, qs):
    """ปฏิทินวันนัดหมายของเดือนที่กำลังดูอยู่"""
    year, month = resolve_month(request.GET)
    return {"cal": month_calendar(qs.with_related(), year, month)}


def _table(user, qs, limit=TABLE_LIMIT):
    """
    ตารางใบจอง — แนบข้อมูล "ขอแก้ไข/ขอยกเลิกได้ไหม" ติดไปกับแต่ละแถว
    คำนวณครั้งเดียวที่นี่ template จะได้ไม่ต้องไปคิดเอง
    """
    rows = list(qs.prefetch_related("requests")[:limit])
    for booking in rows:
        booking.ui_pending_request = services.pending_request(booking)
        booking.ui_can_request = services.can_request(booking, user)
    return rows


def _base(request):
    return {
        "can_create": request.user.role in (Role.REQUESTER, Role.SHR, Role.ADMIN),
        "table_limit": TABLE_LIMIT,
    }


# ============================================================
# 1. ผู้จองคิว
#    กราฟ · ปฏิทิน · ตารางคิวของตัวเองที่ยังไม่ปิด (ขอแก้ไข/ยกเลิกได้)
# ============================================================
@role_required(Role.REQUESTER)
def dashboard_requester(request):
    visible = Booking.objects.for_user(request.user)
    open_qs = visible.with_related().open().order_by("appointment_at")

    return render(
        request,
        "dashboard/requester.html",
        {
            **_base(request),
            **_charts(request, visible),
            **_calendar(request, visible),
            "stat_cards": _stat_cards(request.user),
            "bookings": _table(request.user, open_qs),
            "show_request_buttons": True,
        },
    )


# ============================================================
# 2. ผู้จัดการสาขา
#    กราฟ · ปฏิทินของทุกคน · ตารางทุกคิวที่ยังไม่ปิด · คำขอที่รอพิจารณา
# ============================================================
@role_required(Role.SHR)
def dashboard_shr(request):
    visible = Booking.objects.for_user(request.user)
    open_qs = visible.with_related().open().order_by("appointment_at")

    return render(
        request,
        "dashboard/shr.html",
        {
            **_base(request),
            **_charts(request, visible, rank_by="truck_owner"),
            **_calendar(request, visible),
            "stat_cards": _stat_cards(request.user),
            "bookings": _table(request.user, open_qs),
            "open_requests": services.open_requests_for(request.user),
        },
    )


# ============================================================
# 3. เจ้าของรถสไลด์
#    บล็อกเงิน · บล็อกคิวรอรับ · แจ้งเตือนงานวันนี้ · ตารางงานของตัวเอง
# ============================================================
@role_required(Role.TRAILER)
def dashboard_trailer(request):
    user = request.user
    owner = getattr(user, "truck_owner", None)

    mine = (
        Booking.objects.with_related().filter(truck_owner=owner)
        if owner
        else Booking.objects.none()
    )
    open_qs = mine.open().order_by("appointment_at")

    return render(
        request,
        "dashboard/trailer.html",
        {
            **_base(request),
            **services.trailer_alerts(user),
            "summary": services.trailer_summary(user),
            "available_count": services.available_jobs().count(),
            "bookings": _table(user, open_qs),
            "show_request_buttons": True,
        },
    )


# ============================================================
# 4. งานบัญชี
#    กราฟ (มียอดเงิน) · ตารางตั้งแต่ ผจก.อนุมัติเป็นต้นไป
# ============================================================
@role_required(Role.ACCOUNTING)
def dashboard_accounting(request):
    visible = Booking.objects.for_user(request.user)
    rows = visible.with_related().order_by("-appointment_at")

    return render(
        request,
        "dashboard/accounting.html",
        {
            **_base(request),
            **_charts(request, visible, with_money=True, rank_by="truck_owner"),
            "stat_cards": _stat_cards(request.user),
            "bookings": _table(request.user, rows),
        },
    )


# ============================================================
# 5. ผู้บริหาร
#    กราฟเลือกช่วงเวลาเองได้ · ตารางแยกตามสถานะ ปรับจำนวนย้อนหลังได้
# ============================================================
LIMIT_CHOICES = [25, 50, 100, 200]


@role_required(Role.EXECUTIVE)
def dashboard_executive(request):
    visible = Booking.objects.for_user(request.user)

    status = request.GET.get("status", "")
    if status not in BookingStatus.values:
        status = ""

    try:
        limit = int(request.GET.get("limit") or 50)
    except (TypeError, ValueError):
        limit = 50
    if limit not in LIMIT_CHOICES:
        limit = 50

    rows = visible.with_related().order_by("-created_at")
    if status:
        rows = rows.filter(status=status)

    # จำนวนของแต่ละสถานะ ไว้โชว์บนแท็บ — นับรอบเดียวใน Python
    counts = {}
    for value in visible.values_list("status", flat=True):
        counts[value] = counts.get(value, 0) + 1

    tabs = [{"value": "", "label": "ทั้งหมด", "count": sum(counts.values())}] + [
        {"value": value, "label": label, "count": counts.get(value, 0)}
        for value, label in BookingStatus.choices
    ]

    period = stats.resolve_period(request.GET)
    return render(
        request,
        "dashboard/executive.html",
        {
            **_base(request),
            **_charts(request, visible, with_money=True, rank_by="truck_owner"),
            "ranks_sales": stats.ranking(visible, period, group="sales"),
            "stat_cards": _stat_cards(request.user),
            "bookings": _table(request.user, rows, limit=limit),
            "tabs": tabs,
            "current_status": status,
            "limit": limit,
            "limit_choices": LIMIT_CHOICES,
        },
    )


# ============================================================
# 6. แอดมิน — เหมือน ผจก. แต่เน้นดูแลระบบ
# ============================================================
@role_required(Role.ADMIN)
def dashboard_admin(request):
    visible = Booking.objects.for_user(request.user)
    open_qs = visible.with_related().open().order_by("appointment_at")

    return render(
        request,
        "dashboard/admin.html",
        {
            **_base(request),
            **_charts(request, visible, rank_by="truck_owner"),
            **_calendar(request, visible),
            "stat_cards": _stat_cards(request.user),
            "bookings": _table(request.user, open_qs),
            "open_requests": services.open_requests_for(request.user),
        },
    )


# ============================================================
# 7. เซลล์ — เห็นเฉพาะใบที่ระบุชื่อตัวเอง
# ============================================================
@role_required(Role.SALES)
def dashboard_sales(request):
    visible = Booking.objects.for_user(request.user)
    open_qs = visible.with_related().open().order_by("appointment_at")

    return render(
        request,
        "dashboard/sales.html",
        {
            **_base(request),
            **_charts(request, visible),
            **_calendar(request, visible),
            "stat_cards": _stat_cards(request.user),
            "bookings": _table(request.user, open_qs),
        },
    )
