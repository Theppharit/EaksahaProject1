"""
ปฏิทินวันนัดหมาย
------------------------------------------------------------
สร้างตารางเดือนแบบ 7 คอลัมน์ (จันทร์–อาทิตย์) พร้อมใบจองของแต่ละวัน
วาดเสร็จตั้งแต่ฝั่งเซิร์ฟเวอร์ กดดูรายละเอียดได้โดยไม่ต้องโหลดหน้าใหม่

ตั้งชื่อไฟล์ว่า calendars.py (มี s) จงใจ — กัน import ชนกับ
โมดูล calendar ของ Python เอง ซึ่งไฟล์นี้เรียกใช้อยู่
"""

import calendar as _calendar
from datetime import date, datetime, time, timedelta

from django.utils import timezone

TH_MONTH_FULL = [
    "มกราคม", "กุมภาพันธ์", "มีนาคม", "เมษายน", "พฤษภาคม", "มิถุนายน",
    "กรกฎาคม", "สิงหาคม", "กันยายน", "ตุลาคม", "พฤศจิกายน", "ธันวาคม",
]
DOW = ["จ.", "อ.", "พ.", "พฤ.", "ศ.", "ส.", "อา."]


def resolve_month(params):
    """อ่านเดือนที่ขอดูจาก querystring (?y=2026&m=8) ไม่ระบุ = เดือนปัจจุบัน"""
    today = timezone.localdate()
    try:
        year = int(params.get("y") or today.year)
        month = int(params.get("m") or today.month)
        if not 1 <= month <= 12:
            raise ValueError
        date(year, month, 1)
    except (TypeError, ValueError):
        year, month = today.year, today.month
    return year, month


def _shift(year, month, delta):
    index = (year * 12 + month - 1) + delta
    return index // 12, index % 12 + 1


def month_calendar(qs, year, month, limit_per_day=2):
    """
    คืนโครงปฏิทิน 1 เดือน พร้อมใบจองในแต่ละวัน

    qs   = ใบจองที่ผู้ใช้คนนี้เห็นได้ (กรองสิทธิ์มาแล้วจากฝั่ง caller)
    ผลลัพธ์พร้อมส่งเข้า partials/_calendar.html ได้เลย
    """
    tz = timezone.get_current_timezone()
    first = date(year, month, 1)
    last = date(year, month, _calendar.monthrange(year, month)[1])

    lo = timezone.make_aware(datetime.combine(first - timedelta(days=7), time.min), tz)
    hi = timezone.make_aware(datetime.combine(last + timedelta(days=7), time.max), tz)

    by_day = {}
    for booking in qs.filter(appointment_at__gte=lo, appointment_at__lte=hi).order_by(
        "appointment_at"
    ):
        key = timezone.localtime(booking.appointment_at).date()
        by_day.setdefault(key, []).append(booking)

    today = timezone.localdate()
    weeks = []
    days_with_items = []

    for week in _calendar.Calendar(firstweekday=0).monthdatescalendar(year, month):
        row = []
        for day in week:
            items = by_day.get(day, [])
            cell = {
                "date": day,
                "day": day.day,
                "key": day.isoformat(),
                "in_month": day.month == month,
                "is_today": day == today,
                "count": len(items),
                "items": items,
                "shown": items[:limit_per_day],
                "more": max(0, len(items) - limit_per_day),
            }
            row.append(cell)
            if items and cell["in_month"]:
                days_with_items.append(cell)
        weeks.append(row)

    prev_y, prev_m = _shift(year, month, -1)
    next_y, next_m = _shift(year, month, +1)

    return {
        "year": year,
        "month": month,
        "title": f"{TH_MONTH_FULL[month - 1]} {year + 543}",
        "dow": DOW,
        "weeks": weeks,
        "days_with_items": days_with_items,
        "prev": {"y": prev_y, "m": prev_m},
        "next": {"y": next_y, "m": next_m},
        "is_current": (year, month) == (today.year, today.month),
        "total": sum(len(c["items"]) for w in weeks for c in w if c["in_month"]),
    }
