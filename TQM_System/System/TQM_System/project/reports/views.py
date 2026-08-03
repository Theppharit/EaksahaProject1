"""
รายงานเชิงลึกของผู้บริหาร
------------------------------------------------------------
เลือกตัวกรองได้หลายชั้นพร้อมกัน แล้วดูผลเป็นตัวเลขสรุป กราฟ และตาราง

ตัวกรองทั้งหมดส่งผ่าน querystring → ก๊อปลิงก์ส่งให้คนอื่นดูมุมเดียวกันได้
และกดปุ่มย้อนกลับของเบราว์เซอร์เพื่อกลับไปมุมก่อนหน้าได้ด้วย
"""

from datetime import date, datetime, time, timedelta

from django.contrib.auth.decorators import login_required
from django.core.exceptions import PermissionDenied
from django.shortcuts import render
from django.utils import timezone

from project.accounts.models import Role, User
from project.fleet.models import Driver, TruckOwner
from project.jobs import stats
from project.jobs.models import Booking, BookingStatus

# มุมที่เลือกจัดกลุ่มผลลัพธ์ได้
GROUP_BY = [
    ("driver", "คนขับ"),
    ("truck_owner", "เจ้าของรถ"),
    ("created_by", "ผู้จองคิว"),
    ("sales", "เซลล์"),
    ("status", "สถานะ"),
]

# ผู้บริหารดูตั้งแต่ "รับงานแล้ว" เป็นต้นไป (ตามสเปก)
# ก่อนหน้านั้นยังไม่มีใครรับงาน ยังไม่นับเป็นงานจริง
REPORT_STATUSES = [
    BookingStatus.ACCEPTED,
    BookingStatus.PENDING_APPROVAL,
    BookingStatus.SHR_APPROVED,
    BookingStatus.ACCOUNTING_REVIEW,
    BookingStatus.APPROVED,
    BookingStatus.IN_PROGRESS,
    BookingStatus.CLOSED,
    BookingStatus.CANCELLED,
]

ROW_LIMITS = [50, 100, 200, 500]


def _month_range(year_no, month_no):
    """ช่วงเวลาต้นเดือน–สิ้นเดือนแบบมี timezone (เลี่ยง __month ที่ต้องพึ่ง tzdata)"""
    tz = timezone.get_current_timezone()
    first = date(year_no, month_no, 1)
    nxt = date(year_no + 1, 1, 1) if month_no == 12 else date(year_no, month_no + 1, 1)
    return (
        timezone.make_aware(datetime.combine(first, time.min), tz),
        timezone.make_aware(datetime.combine(nxt - timedelta(days=1), time.max), tz),
    )


def _month_options(count=12):
    today = timezone.localdate()
    out, y, m = [], today.year, today.month
    for _ in range(count):
        out.append({"value": f"{y:04d}-{m:02d}", "label": f"{m:02d}/{y + 543}"})
        m -= 1
        if m == 0:
            y, m = y - 1, 12
    return out


def _group_rows(bookings, group_by):
    """
    จัดกลุ่มผลลัพธ์ตามมุมที่เลือก
    ทำใน Python เพราะต้องรวมเงินจาก 2 ฟิลด์ (price + extra_charge)
    """
    labels = dict(BookingStatus.choices)
    buckets = {}

    for b in bookings:
        if group_by == "driver":
            name = b.driver.name if b.driver else "— ไม่ระบุคนขับ —"
        elif group_by == "truck_owner":
            name = b.truck_owner.name if b.truck_owner else "— ยังไม่มีผู้รับงาน —"
        elif group_by == "created_by":
            name = b.created_by.get_full_name() or b.created_by.username
        elif group_by == "sales":
            name = (
                (b.sales.get_full_name() or b.sales.username) if b.sales else "— ไม่ระบุเซลล์ —"
            )
        else:
            name = labels.get(b.status, b.status)

        row = buckets.setdefault(name, {"label": name, "value": 0, "closed": 0, "amount": 0})
        row["value"] += 1
        if b.status == BookingStatus.CLOSED:
            row["closed"] += 1
            row["amount"] += b.total_price

    rows = sorted(buckets.values(), key=lambda r: -r["value"])
    top = rows[0]["value"] if rows else 0
    for r in rows:
        r["pct"] = round(r["value"] * 100 / top, 1) if top else 0
        r["amount"] = f"{r['amount']:,.0f}"
    return rows


@login_required
def deep_report(request):
    user = request.user
    if not (user.is_superuser or user.role in (Role.EXECUTIVE, Role.ADMIN, Role.SHR)):
        raise PermissionDenied("เฉพาะผู้บริหาร ผู้จัดการสาขา และแอดมินเท่านั้น")

    params = request.GET
    period = stats.resolve_period(params)

    # ── อ่านตัวกรองจาก querystring ──────────────
    picked = {
        "driver": params.get("driver", ""),
        "truck_owner": params.get("truck_owner", ""),
        "created_by": params.get("created_by", ""),
        "sales": params.get("sales", ""),
        "status": params.get("status", ""),
        "pay_month": params.get("pay_month", ""),
    }

    group_by = params.get("group_by", "driver")
    if group_by not in dict(GROUP_BY):
        group_by = "driver"

    try:
        limit = int(params.get("limit") or 100)
    except (TypeError, ValueError):
        limit = 100
    if limit not in ROW_LIMITS:
        limit = 100

    # ── ประกอบ query ตามตัวกรอง ────────────────
    qs = Booking.objects.with_related().filter(status__in=REPORT_STATUSES)

    if picked["driver"]:
        qs = qs.filter(driver_id=picked["driver"])
    if picked["truck_owner"]:
        qs = qs.filter(truck_owner_id=picked["truck_owner"])
    if picked["created_by"]:
        qs = qs.filter(created_by_id=picked["created_by"])
    if picked["sales"]:
        qs = qs.filter(sales_id=picked["sales"])

    if picked["status"] in REPORT_STATUSES:
        qs = qs.filter(status=picked["status"])
    else:
        picked["status"] = ""

    # กรองตามรอบจ่ายเงิน = งานที่อยู่ในบิลซึ่งจ่ายในเดือนนั้น
    if picked["pay_month"]:
        try:
            y, m = (int(x) for x in picked["pay_month"].split("-"))
            qs = qs.filter(bill_item__bill__paid_at__range=_month_range(y, m))
        except (TypeError, ValueError):
            picked["pay_month"] = ""

    # ── คำนวณผล ────────────────────────────────
    in_period = list(
        qs.filter(
            appointment_at__gte=stats.aware_start(period["start"]),
            appointment_at__lte=stats.aware_end(period["end"]),
        )
    )

    closed = [b for b in in_period if b.status == BookingStatus.CLOSED]
    revenue = sum(b.total_price for b in closed)
    cancelled = sum(1 for b in in_period if b.status == BookingStatus.CANCELLED)

    series = stats.timeseries(qs, period)

    return render(request, "reports/deep.html", {
        # ตัวเลือกในฟอร์ม
        "drivers": Driver.objects.select_related("owner").order_by("owner__name", "name"),
        "owners": TruckOwner.objects.filter(is_active=True),
        "requesters": User.objects.filter(role=Role.REQUESTER).order_by("username"),
        "sales_users": User.objects.filter(role=Role.SALES).order_by("username"),
        "statuses": [(v, dict(BookingStatus.choices)[v]) for v in REPORT_STATUSES],
        "pay_months": _month_options(),
        "group_choices": GROUP_BY,
        "limit_choices": ROW_LIMITS,
        # ค่าที่เลือกอยู่
        "picked": picked,
        "group_by": group_by,
        "limit": limit,
        "period": period,
        "period_choices": stats.PERIOD_CHOICES,
        # ผลลัพธ์
        "summary": {
            "total": len(in_period),
            "closed": len(closed),
            "cancelled": cancelled,
            "revenue": f"{revenue:,.0f}",
            "avg": f"{revenue / len(closed):,.0f}" if closed else "0",
        },
        "trend": stats.line_chart(series, "count"),
        "money": stats.line_chart(series, "amount"),
        "donut": stats.donut_chart(stats.status_breakdown_of(in_period)),
        "breakdown": _group_rows(in_period, group_by),
        "rows": sorted(in_period, key=lambda b: b.appointment_at, reverse=True)[:limit],
        "has_filter": any(picked.values()),
    })
