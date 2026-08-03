"""เกณฑ์ราคา · รอบจ่ายเงิน (ผจก.) และ ออกบิล · ประวัติการจ่าย (บัญชี)"""

from django.contrib import messages
from django.contrib.auth.decorators import login_required
from django.core.exceptions import PermissionDenied
from django.db.models import Sum
from django.shortcuts import get_object_or_404, redirect, render
from datetime import date, datetime, time, timedelta

from django.utils import timezone

from project.accounts.models import Role
from project.fleet.models import TruckOwner
from project.jobs.models import Booking, BookingStatus

from .forms import BillForm, PaymentCycleForm, PriceRateForm
from .models import Bill, BillItem, BillStatus, PaymentCycle, PriceRate


def _month_range(year_no, month_no):
    """
    ช่วงเวลาต้นเดือน–สิ้นเดือน แบบมี timezone

    จงใจไม่ใช้ closed_at__year / __month เพราะบน SQLite + Windows
    ตัวกรองพวกนี้ต้องพึ่งแพ็กเกจ tzdata ถ้าไม่มีจะพังตอนรัน
    เทียบเป็นช่วงเวลาตรง ๆ แบบนี้ปลอดภัยกว่าและได้ผลเหมือนกัน
    """
    tz = timezone.get_current_timezone()
    first = date(year_no, month_no, 1)
    nxt = date(year_no + 1, 1, 1) if month_no == 12 else date(year_no, month_no + 1, 1)
    lo = timezone.make_aware(datetime.combine(first, time.min), tz)
    hi = timezone.make_aware(datetime.combine(nxt - timedelta(days=1), time.max), tz)
    return lo, hi


def _need(user, *roles):
    if not (user.is_superuser or user.role in roles):
        raise PermissionDenied("ตำแหน่งของคุณไม่มีสิทธิ์เข้าหน้านี้")


# ============================================================
# ผจก.สาขา — เกณฑ์ราคาและรอบจ่ายเงิน
# ============================================================
@login_required
def rates(request):
    """
    เกณฑ์ราคา + รอบจ่ายเงิน
    เก็บเรทเป็นหลายแถวโดยมีวันเริ่มใช้ เพื่อให้ย้อนดูได้ว่างานเดือนก่อนคิดด้วยเรทไหน
    (สำคัญมากเวลาลูกค้าทักท้วงย้อนหลัง)
    """
    _need(request.user, Role.SHR, Role.ADMIN)

    rate_form = PriceRateForm(request.POST or None, prefix="rate")
    cycle_form = PaymentCycleForm(request.POST or None, prefix="cycle")

    if request.method == "POST":
        if "save_rate" in request.POST and rate_form.is_valid():
            obj = rate_form.save(commit=False)
            obj.created_by = request.user
            obj.save()
            if obj.is_active:
                # เปิดใช้เรทใหม่ = ปิดเรทเก่าอัตโนมัติ กันมีเรทใช้งานอยู่พร้อมกันหลายอัน
                PriceRate.objects.exclude(pk=obj.pk).update(is_active=False)
            messages.success(request, f"บันทึกเกณฑ์ราคา “{obj.name}” แล้ว")
            return redirect("billing:rates")

        if "save_cycle" in request.POST and cycle_form.is_valid():
            obj = cycle_form.save()
            if obj.is_active:
                PaymentCycle.objects.exclude(pk=obj.pk).update(is_active=False)
            messages.success(request, "บันทึกรอบจ่ายเงินแล้ว")
            return redirect("billing:rates")

    current = PriceRate.objects.filter(is_active=True).first()

    # ตัวอย่างราคาที่ระยะทางต่าง ๆ — ให้เห็นภาพก่อนกดบันทึกเรทจริง
    preview = []
    if current:
        preview = [
            {"km": km, "price": f"{current.calculate(km):,.0f}"}
            for km in (5, 10, 20, 50, 100)
        ]

    return render(request, "billing/rates.html", {
        "rates": PriceRate.objects.all(),
        "cycles": PaymentCycle.objects.all(),
        "rate_form": rate_form,
        "cycle_form": cycle_form,
        "current": current,
        "current_cycle": PaymentCycle.objects.filter(is_active=True).first(),
        "preview": preview,
    })


# ============================================================
# งานบัญชี — ออกเลขบิล
# ============================================================
@login_required
def bill_list(request):
    """
    ออกเลขบิล — งานที่ปิดแล้วและยังไม่ถูกวางบิล
    แจกแจง "รายคนขับ" ตามสเปก แล้วรวมเป็นยอดของเจ้าของรถแต่ละราย
    (บิลออกในนามเจ้าของรถ เพราะเป็นคู่สัญญาและเป็นเจ้าของบัญชีที่รับเงิน)
    """
    _need(request.user, Role.ACCOUNTING, Role.ADMIN)

    # เลือกดูรอบเดือนไหน — ไม่เลือก = เดือนปัจจุบัน
    today = timezone.localdate()
    month = request.GET.get("month") or f"{today:%Y-%m}"
    try:
        year_no, month_no = (int(x) for x in month.split("-"))
        datetime(year_no, month_no, 1)
    except (TypeError, ValueError):
        year_no, month_no = today.year, today.month
        month = f"{today:%Y-%m}"

    unbilled = (
        Booking.objects.filter(
            status=BookingStatus.CLOSED,
            bill_item__isnull=True,
            closed_at__range=_month_range(year_no, month_no),
        )
        .select_related("truck_owner", "driver", "customer")
        .order_by("truck_owner", "driver", "closed_at")
    )

    # จัดกลุ่ม 2 ชั้นใน Python: เจ้าของรถ → คนขับ
    owners = {}
    for b in unbilled:
        if not b.truck_owner:
            continue
        group = owners.setdefault(
            b.truck_owner_id, {"owner": b.truck_owner, "drivers": {}, "total": 0, "jobs": 0}
        )
        key = b.driver_id or 0
        line = group["drivers"].setdefault(
            key, {"driver": b.driver, "jobs": [], "total": 0}
        )
        line["jobs"].append(b)
        line["total"] += b.total_price
        group["total"] += b.total_price
        group["jobs"] += 1

    groups = [
        {**g, "drivers": sorted(g["drivers"].values(), key=lambda x: -x["total"])}
        for g in sorted(owners.values(), key=lambda x: -x["total"])
    ]

    # ตัวเลือกเดือนย้อนหลัง 12 เดือน
    months = []
    y, m = today.year, today.month
    for _ in range(12):
        months.append({"value": f"{y:04d}-{m:02d}", "label": f"{m:02d}/{y + 543}"})
        m -= 1
        if m == 0:
            y, m = y - 1, 12

    return render(request, "billing/bill_list.html", {
        "bills": Bill.objects.select_related("truck_owner")[:30],
        "groups": groups,
        "month": month,
        "months": months,
        "grand_total": sum(g["total"] for g in groups),
    })


@login_required
def bill_create(request, owner_pk):
    """ออกบิล — รวมงานที่ปิดแล้วของเจ้าของรถรายนี้เป็นบิลใบเดียว"""
    _need(request.user, Role.ACCOUNTING, Role.ADMIN)
    owner = get_object_or_404(TruckOwner, pk=owner_pk)

    # ยึดเดือนเดียวกับที่หน้ารายการกำลังเปิดอยู่ จะได้ไม่เผลอรวมงานเดือนก่อนเข้ามาด้วย
    month = request.GET.get("month", "")
    jobs_qs = Booking.objects.filter(
        truck_owner=owner, status=BookingStatus.CLOSED, bill_item__isnull=True
    )
    try:
        year_no, month_no = (int(x) for x in month.split("-"))
        jobs_qs = jobs_qs.filter(closed_at__range=_month_range(year_no, month_no))
        month_label = f"{month_no:02d}/{year_no + 543}"
    except (TypeError, ValueError):
        month, month_label = "", "ทุกงานที่ยังไม่วางบิล"

    jobs = list(jobs_qs.select_related("driver", "customer").order_by("closed_at"))
    if not jobs:
        messages.warning(request, "ไม่มีงานที่ปิดแล้วรอวางบิลของเจ้าของรถรายนี้")
        return redirect("billing:bill_list")

    if request.method == "POST":
        seq = Bill.objects.count() + 1
        bill = Bill.objects.create(
            bill_no=f"BILL-{timezone.localtime():%y%m}-{seq:04d}",
            truck_owner=owner,
            period_start=min(j.closed_at.date() for j in jobs),
            period_end=max(j.closed_at.date() for j in jobs),
            issued_by=request.user,
            issued_at=timezone.now(),
            status=BillStatus.ISSUED,
        )
        BillItem.objects.bulk_create(
            [BillItem(bill=bill, booking=j, amount=j.total_price) for j in jobs]
        )
        bill.recalculate_total()
        messages.success(request, f"ออกบิล {bill.bill_no} เรียบร้อย ({len(jobs)} งาน)")
        return redirect("billing:bill_detail", pk=bill.pk)

    return render(request, "billing/bill_create.html", {
        "owner": owner,
        "jobs": jobs,
        "total": sum(j.total_price for j in jobs),
        "month": month,
        "month_label": month_label,
    })


@login_required
def bill_detail(request, pk):
    _need(request.user, Role.ACCOUNTING, Role.ADMIN, Role.TRAILER, Role.SHR, Role.EXECUTIVE)
    bill = get_object_or_404(Bill.objects.select_related("truck_owner"), pk=pk)

    # เจ้าของรถเห็นได้เฉพาะบิลของตัวเอง
    if request.user.role == Role.TRAILER and bill.truck_owner.user_id != request.user.id:
        raise PermissionDenied

    form = BillForm(request.POST or None, instance=bill)
    if request.method == "POST" and form.is_valid():
        obj = form.save(commit=False)
        if obj.po_no and obj.status != BillStatus.PAID:
            obj.status = BillStatus.PAID
            obj.paid_at = timezone.now()
        obj.save()
        messages.success(request, "บันทึกเลข PO เรียบร้อย — บิลนี้ถือว่าจ่ายแล้ว")
        return redirect("billing:bill_detail", pk=pk)

    return render(request, "billing/bill_detail.html", {
        "bill": bill, "items": bill.items.select_related("booking__customer", "booking__driver"),
        "form": form, "can_edit": request.user.role in (Role.ACCOUNTING, Role.ADMIN),
    })


TH_MONTHS = ["ม.ค.", "ก.พ.", "มี.ค.", "เม.ย.", "พ.ค.", "มิ.ย.",
             "ก.ค.", "ส.ค.", "ก.ย.", "ต.ค.", "พ.ย.", "ธ.ค."]


@login_required
def payment_history(request):
    """ประวัติการจ่ายเงิน — จัดกลุ่มรายเดือนตามวันที่จ่ายจริง"""
    _need(request.user, Role.ACCOUNTING, Role.ADMIN, Role.TRAILER)

    qs = Bill.objects.select_related("truck_owner").filter(status=BillStatus.PAID)
    if request.user.role == Role.TRAILER:
        qs = qs.filter(truck_owner__user=request.user)  # เจ้าของรถเห็นแค่ของตัวเอง

    by_month = {}
    for b in qs:
        stamp = timezone.localtime(b.paid_at or b.created_at)
        key = (stamp.year, stamp.month)
        by_month.setdefault(key, []).append(b)

    months = [
        {
            "label": f"{TH_MONTHS[m - 1]} {y + 543}",
            "bills": sorted(v, key=lambda x: x.paid_at or x.created_at, reverse=True),
            "count": len(v),
            "total": sum(x.total_amount for x in v),
        }
        for (y, m), v in sorted(by_month.items(), reverse=True)
    ]

    return render(request, "billing/payment_history.html", {
        "months": months,
        "grand_total": qs.aggregate(s=Sum("total_amount"))["s"] or 0,
        "paid_count": qs.count(),
    })
