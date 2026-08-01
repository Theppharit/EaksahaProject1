"""เกณฑ์ราคา · รอบจ่ายเงิน (ผจก.) และ ออกบิล · ประวัติการจ่าย (บัญชี)"""

from django.contrib import messages
from django.contrib.auth.decorators import login_required
from django.core.exceptions import PermissionDenied
from django.db.models import Sum
from django.shortcuts import get_object_or_404, redirect, render
from django.utils import timezone

from project.accounts.models import Role
from project.fleet.models import TruckOwner
from project.jobs.models import Booking, BookingStatus

from .forms import BillForm, PaymentCycleForm, PriceRateForm
from .models import Bill, BillItem, BillStatus, PaymentCycle, PriceRate


def _need(user, *roles):
    if not (user.is_superuser or user.role in roles):
        raise PermissionDenied("ตำแหน่งของคุณไม่มีสิทธิ์เข้าหน้านี้")


# ============================================================
# ผจก.สาขา — เกณฑ์ราคาและรอบจ่ายเงิน
# ============================================================
@login_required
def rates(request):
    _need(request.user, Role.SHR, Role.ADMIN)

    rate_form = PriceRateForm(request.POST or None, prefix="rate")
    cycle_form = PaymentCycleForm(request.POST or None, prefix="cycle")

    if request.method == "POST":
        if "save_rate" in request.POST and rate_form.is_valid():
            obj = rate_form.save(commit=False)
            obj.created_by = request.user
            obj.save()
            messages.success(request, "บันทึกเกณฑ์ราคาใหม่แล้ว")
            return redirect("billing:rates")
        if "save_cycle" in request.POST and cycle_form.is_valid():
            cycle_form.save()
            messages.success(request, "บันทึกรอบจ่ายเงินแล้ว")
            return redirect("billing:rates")

    return render(request, "billing/rates.html", {
        "rates": PriceRate.objects.all(),
        "cycles": PaymentCycle.objects.all(),
        "rate_form": rate_form,
        "cycle_form": cycle_form,
        "current": PriceRate.objects.filter(is_active=True).first(),
    })


# ============================================================
# งานบัญชี — ออกเลขบิล
# ============================================================
@login_required
def bill_list(request):
    _need(request.user, Role.ACCOUNTING, Role.ADMIN)

    # งานที่ปิดแล้วและยังไม่ถูกวางบิล — จัดกลุ่มตามเจ้าของรถ
    unbilled = (
        Booking.objects.filter(status=BookingStatus.CLOSED, bill_item__isnull=True)
        .select_related("truck_owner", "driver", "customer")
        .order_by("truck_owner", "closed_at")
    )
    groups = {}
    for b in unbilled:
        groups.setdefault(b.truck_owner, []).append(b)

    return render(request, "billing/bill_list.html", {
        "bills": Bill.objects.select_related("truck_owner"),
        "groups": [
            {"owner": o, "jobs": js, "total": sum(j.total_price for j in js)}
            for o, js in groups.items() if o
        ],
    })


@login_required
def bill_create(request, owner_pk):
    """ออกบิล — รวมงานที่ปิดแล้วของเจ้าของรถรายนี้เป็นบิลใบเดียว"""
    _need(request.user, Role.ACCOUNTING, Role.ADMIN)
    owner = get_object_or_404(TruckOwner, pk=owner_pk)

    jobs = list(
        Booking.objects.filter(
            truck_owner=owner, status=BookingStatus.CLOSED, bill_item__isnull=True
        )
    )
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
        "owner": owner, "jobs": jobs, "total": sum(j.total_price for j in jobs),
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


@login_required
def payment_history(request):
    _need(request.user, Role.ACCOUNTING, Role.ADMIN, Role.TRAILER)
    qs = Bill.objects.select_related("truck_owner").filter(status=BillStatus.PAID)
    if request.user.role == Role.TRAILER:
        qs = qs.filter(truck_owner__user=request.user)

    by_month = {}
    for b in qs:
        key = (b.paid_at or b.created_at).strftime("%Y-%m")
        by_month.setdefault(key, []).append(b)

    return render(request, "billing/payment_history.html", {
        "months": [
            {"label": k, "bills": v, "total": sum(x.total_amount for x in v)}
            for k, v in sorted(by_month.items(), reverse=True)
        ],
        "grand_total": qs.aggregate(s=Sum("total_amount"))["s"] or 0,
    })
