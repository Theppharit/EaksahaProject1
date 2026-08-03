"""
สถิติสำหรับแดชบอร์ด
------------------------------------------------------------
ไฟล์นี้ทำ 2 อย่าง
  1. นับ/รวมยอดจากใบจอง ตามช่วงเวลาที่เลือก
  2. แปลงตัวเลขเป็น "พิกัดของเส้นกราฟ" ให้ template วาดเป็น SVG ได้เลย

ทำไมไม่ใช้ไลบรารีกราฟ (Chart.js ฯลฯ)?
  - ไม่ต้องโหลดไฟล์เพิ่ม หน้าเบา เปิดเร็วบนมือถือหน้างาน
  - ไม่ต้องต่อเน็ตออกนอก ใช้ในเซิร์ฟเวอร์ปิดได้
  - กราฟถูกวาดตั้งแต่ฝั่งเซิร์ฟเวอร์ → สั่งพิมพ์แล้วติดไปด้วย

หมายเหตุเรื่องเวลา:
  จงใจไม่ใช้ TruncDay/TruncMonth ของฐานข้อมูล เพราะบน SQLite + Windows
  ต้องพึ่งแพ็กเกจ tzdata เพิ่ม ถ้าไม่มีจะพังตอนรัน
  → ดึงค่าดิบมาแล้วจัดกลุ่มด้วย Python แทน ปลอดภัยกว่าและผลลัพธ์เท่ากัน
"""

from datetime import date, datetime, time, timedelta
from decimal import Decimal

from django.utils import timezone

from .models import BookingStatus

# ============================================================
# ช่วงเวลาที่เลือกได้ (ผู้บริหารใช้เต็มชุด โรลอื่นใช้ค่าเริ่มต้น)
# ============================================================
PERIOD_CHOICES = [
    ("day", "วันนี้"),
    ("week", "7 วัน"),
    ("month", "เดือนนี้"),
    ("q3", "3 เดือน"),
    ("h6", "6 เดือน"),
    ("y1", "1 ปี"),
]
DEFAULT_PERIOD = "month"

TH_MONTHS = [
    "ม.ค.", "ก.พ.", "มี.ค.", "เม.ย.", "พ.ค.", "มิ.ย.",
    "ก.ค.", "ส.ค.", "ก.ย.", "ต.ค.", "พ.ย.", "ธ.ค.",
]


def _aware(d, end=False):
    """วันที่ (date) → เวลาแบบมี timezone ต้นวัน/ท้ายวัน"""
    naive = datetime.combine(d, time.max if end else time.min)
    return timezone.make_aware(naive, timezone.get_current_timezone())


def aware_start(d):
    """ต้นวันแบบมี timezone — ให้โมดูลอื่นเรียกใช้ได้โดยไม่ต้องแตะฟังก์ชัน _ ข้างใน"""
    return _aware(d)


def aware_end(d):
    """ท้ายวันแบบมี timezone"""
    return _aware(d, end=True)


def _parse_date(text):
    try:
        return date.fromisoformat(text)
    except (TypeError, ValueError):
        return None


def resolve_period(params):
    """
    อ่านค่าจาก querystring แล้วคืนช่วงเวลาที่จะใช้
    รองรับทั้งปุ่มสำเร็จรูป (?period=month) และเลือกวันเอง (?from=&to=)
    """
    today = timezone.localdate()
    custom_from = _parse_date(params.get("from"))
    custom_to = _parse_date(params.get("to"))

    if custom_from and custom_to and custom_from <= custom_to:
        span = (custom_to - custom_from).days
        bucket = "day" if span <= 45 else ("week" if span <= 200 else "month")
        return {
            "key": "custom",
            "label": f"{custom_from:%d/%m/%y} – {custom_to:%d/%m/%y}",
            "start": custom_from,
            "end": custom_to,
            "bucket": bucket,
            "from": custom_from.isoformat(),
            "to": custom_to.isoformat(),
        }

    key = params.get("period") or DEFAULT_PERIOD
    if key not in dict(PERIOD_CHOICES):
        key = DEFAULT_PERIOD

    if key == "day":
        start, bucket = today, "hour"
    elif key == "week":
        start, bucket = today - timedelta(days=6), "day"
    elif key == "month":
        start, bucket = today.replace(day=1), "day"
    elif key == "q3":
        start, bucket = today - timedelta(days=89), "week"
    elif key == "h6":
        start, bucket = today - timedelta(days=181), "month"
    else:  # y1
        start, bucket = today - timedelta(days=364), "month"

    return {
        "key": key,
        "label": dict(PERIOD_CHOICES)[key],
        "start": start,
        "end": today,
        "bucket": bucket,
        "from": "",
        "to": "",
    }


# ============================================================
# จัดกลุ่มตามช่วงเวลา
# ============================================================
def _bucket_key(local_dt, bucket):
    if bucket == "hour":
        return local_dt.hour
    d = local_dt.date()
    if bucket == "day":
        return d
    if bucket == "week":
        return d - timedelta(days=d.weekday())  # วันจันทร์ของสัปดาห์นั้น
    return d.replace(day=1)  # month


def _bucket_list(period):
    """ช่องเวลาทั้งหมดในช่วงที่เลือก เรียงจากเก่าไปใหม่ พร้อมป้ายกำกับ"""
    bucket, start, end = period["bucket"], period["start"], period["end"]

    if bucket == "hour":
        return [(h, f"{h:02d}") for h in range(24)]

    out = []
    if bucket == "day":
        d = start
        while d <= end:
            out.append((d, f"{d.day}"))
            d += timedelta(days=1)
    elif bucket == "week":
        d = start - timedelta(days=start.weekday())
        while d <= end:
            out.append((d, f"{d.day}/{d.month}"))
            d += timedelta(days=7)
    else:  # month
        d = start.replace(day=1)
        while d <= end:
            out.append((d, TH_MONTHS[d.month - 1]))
            d = (d.replace(day=28) + timedelta(days=8)).replace(day=1)
    return out


def timeseries(qs, period, date_field="appointment_at"):
    """
    คืนลิสต์ตามช่วงเวลา: [{"label", "count", "amount"}, ...]
      count  = จำนวนใบจองในช่วงนั้น
      amount = ยอดเงินของงานที่ "ปิดงานแล้ว" เท่านั้น (เงินที่เกิดขึ้นจริง)
    """
    lo = _aware(period["start"])
    hi = _aware(period["end"], end=True)

    rows = qs.filter(**{f"{date_field}__gte": lo, f"{date_field}__lte": hi}).values_list(
        date_field, "status", "price", "extra_charge"
    )

    counts, amounts = {}, {}
    for dt, status, price, extra in rows:
        k = _bucket_key(timezone.localtime(dt), period["bucket"])
        counts[k] = counts.get(k, 0) + 1
        if status == BookingStatus.CLOSED:
            amounts[k] = amounts.get(k, Decimal("0")) + (price or 0) + (extra or 0)

    return [
        {
            "label": label,
            "count": counts.get(key, 0),
            "amount": float(amounts.get(key, 0)),
        }
        for key, label in _bucket_list(period)
    ]


# ============================================================
# สัดส่วนตามสถานะ (กราฟโดนัท)
# ============================================================
STATUS_COLOR = {
    BookingStatus.PENDING_ACCEPT: "var(--color-status-wait)",
    BookingStatus.ACCEPTED: "var(--color-status-go)",
    BookingStatus.PENDING_APPROVAL: "var(--color-status-wait)",
    BookingStatus.SHR_APPROVED: "var(--color-status-review)",
    BookingStatus.ACCOUNTING_REVIEW: "var(--color-status-review)",
    BookingStatus.APPROVED: "var(--color-status-done)",
    BookingStatus.IN_PROGRESS: "var(--color-accent)",
    BookingStatus.CLOSED: "var(--color-status-cancel)",
    BookingStatus.CANCELLED: "var(--color-status-urgent)",
}


def _breakdown_from_counts(raw):
    """แปลงตัวนับดิบ → รายการพร้อมป้ายชื่อ สัดส่วน และสี (ตัดสถานะที่เป็น 0 ทิ้ง)"""
    total = sum(raw.values())
    labels = dict(BookingStatus.choices)
    return [
        {
            "status": s,
            "label": labels[s],
            "value": n,
            "pct": round(n * 100 / total, 1) if total else 0,
            "color": STATUS_COLOR.get(s, "var(--color-ink-soft)"),
        }
        for s, n in raw.items()
        if n
    ]


def status_breakdown(qs, period=None, date_field="appointment_at"):
    """นับใบจองแยกตามสถานะจาก queryset"""
    if period:
        qs = qs.filter(
            **{
                f"{date_field}__gte": _aware(period["start"]),
                f"{date_field}__lte": _aware(period["end"], end=True),
            }
        )

    raw = dict.fromkeys(BookingStatus.values, 0)
    for status in qs.values_list("status", flat=True):
        raw[status] = raw.get(status, 0) + 1
    return _breakdown_from_counts(raw)


def status_breakdown_of(bookings):
    """เหมือน status_breakdown แต่รับลิสต์ที่ดึงมาแล้ว — ไม่ยิง query ซ้ำ"""
    raw = dict.fromkeys(BookingStatus.values, 0)
    for b in bookings:
        raw[b.status] = raw.get(b.status, 0) + 1
    return _breakdown_from_counts(raw)


# ============================================================
# อันดับ (เจ้าของรถ / เซลล์)
# ============================================================
def ranking(qs, period, group="truck_owner", limit=5):
    """
    อันดับตามจำนวนงาน พร้อมยอดเงินของงานที่ปิดแล้ว
    group: "truck_owner" (เจ้าของรถ) หรือ "sales" (เซลล์)
    """
    name_field = "truck_owner__name" if group == "truck_owner" else "sales__username"
    full_field = None if group == "truck_owner" else "sales__first_name"

    fields = [name_field, "status", "price", "extra_charge"]
    if full_field:
        fields.insert(1, full_field)

    rows = qs.filter(
        appointment_at__gte=_aware(period["start"]),
        appointment_at__lte=_aware(period["end"], end=True),
    ).values_list(*fields)

    bucket = {}
    for row in rows:
        name = row[0]
        if not name:
            continue
        if full_field and row[1]:
            name = row[1]
        status, price, extra = row[-3], row[-2], row[-1]
        item = bucket.setdefault(name, {"label": name, "value": 0, "amount": Decimal("0")})
        item["value"] += 1
        if status == BookingStatus.CLOSED:
            item["amount"] += (price or 0) + (extra or 0)

    items = sorted(bucket.values(), key=lambda x: -x["value"])[:limit]
    top = items[0]["value"] if items else 0
    for it in items:
        it["pct"] = round(it["value"] * 100 / top, 1) if top else 0
        it["amount"] = f"{it['amount']:,.0f}"
    return items


# ============================================================
# แปลงตัวเลข → พิกัด SVG
# ============================================================
# เพดานแกน Y ที่ยอมให้ใช้ — ทุกค่าหารด้วย 4 ลงตัว
# เส้นตาราง 4 เส้นจึงได้ตัวเลขกลม ๆ เสมอ (ไม่มี 0.5 หรือ 3.75 โผล่มา)
_Y_STEPS = [4, 8, 12, 20, 40, 80, 120, 200, 400, 800, 1200, 2000, 4000, 8000,
            12000, 20000, 40000, 80000, 120000, 200000, 400000, 800000,
            1200000, 2000000, 4000000, 8000000, 12000000, 20000000]


def _nice_max(value):
    """ปัดเพดานแกน Y ขึ้นเป็นเลขกลม ๆ เพื่อให้เส้นตารางอ่านง่าย"""
    for step in _Y_STEPS:
        if value <= step:
            return step
    return int(value) or 4


def line_chart(series, key="count", width=680, height=210):
    """
    สร้างพิกัดกราฟเส้น (พร้อมพื้นที่ใต้เส้น) จากผลของ timeseries()
    ใช้ viewBox คงที่ แล้วให้ CSS ยืดเต็มความกว้าง — คมทุกขนาดจอ
    """
    pad_l, pad_r, pad_t, pad_b = 42, 12, 14, 26
    x0, y0 = pad_l, height - pad_b
    plot_w = width - pad_l - pad_r
    plot_h = height - pad_t - pad_b

    values = [row[key] for row in series] or [0]
    ymax = _nice_max(max(values))
    n = len(series)
    step = plot_w / (n - 1) if n > 1 else 0

    points = []
    for i, row in enumerate(series):
        x = x0 + i * step if n > 1 else x0 + plot_w / 2
        y = y0 - (row[key] / ymax) * plot_h if ymax else y0
        points.append(
            {
                "x": round(x, 1),
                "y": round(y, 1),
                "label": row["label"],
                "value": row[key],
                "display": f"{row[key]:,.0f}",
            }
        )

    poly = " ".join(f"{p['x']},{p['y']}" for p in points)
    area = ""
    if points:
        area = (
            f"M {points[0]['x']},{y0} "
            + " ".join(f"L {p['x']},{p['y']}" for p in points)
            + f" L {points[-1]['x']},{y0} Z"
        )

    # เส้นตารางแนวนอน 5 เส้น (0 ถึงเพดาน) — ty คือตำแหน่งตัวเลขให้อยู่กลางเส้นพอดี
    grid = [
        {
            "y": round(y0 - i * plot_h / 4, 1),
            "ty": round(y0 - i * plot_h / 4 + 4, 1),
            "label": f"{ymax * i / 4:,.0f}",
        }
        for i in range(5)
    ]

    # ป้ายแกน X — โชว์แค่บางอันไม่ให้ทับกัน
    every = max(1, round(n / 8))
    xticks = [
        {"x": p["x"], "label": p["label"]}
        for i, p in enumerate(points)
        if i % every == 0 or i == n - 1
    ]

    return {
        "width": width,
        "height": height,
        "baseline": y0,
        "x_start": x0,
        "x_end": width - pad_r,
        "label_y": height - 8,
        "axis_x": pad_l - 8,
        "poly": poly,
        "area": area,
        "points": points,
        "grid": grid,
        "xticks": xticks,
        "ymax": ymax,
        "total": f"{sum(values):,.0f}",
        "is_empty": not any(values),
    }


def donut_chart(items, size=170, stroke=26):
    """
    สร้างวงแหวนจากสัดส่วน — ใช้เทคนิค stroke-dasharray บนวงกลมวงเดียว
    ต่อกันทีละชิ้นด้วย stroke-dashoffset (ไม่ต้องคำนวณ path arc ให้ปวดหัว)
    """
    radius = (size - stroke) / 2
    circumference = 2 * 3.141592653589793 * radius
    total = sum(i["value"] for i in items)

    segments, offset = [], 0.0
    for it in items:
        frac = (it["value"] / total) if total else 0
        length = frac * circumference
        segments.append(
            {
                **it,
                "dash": f"{length:.2f} {circumference - length:.2f}",
                "offset": f"{-offset:.2f}",
            }
        )
        offset += length

    return {
        "size": size,
        "center": size / 2,
        "center_text_y": size / 2 - 2,
        "center_sub_y": size / 2 + 16,
        "radius": radius,
        "stroke": stroke,
        "segments": segments,
        "total": total,
        "is_empty": total == 0,
    }
