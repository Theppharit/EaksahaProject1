"""รายงานเชิงลึกของผู้บริหาร — เลือกตัวกรองก่อนจึงแสดงผล (ตามสเปก)"""

from django.contrib.auth.decorators import login_required
from django.core.exceptions import PermissionDenied
from django.db.models import Count, Sum
from django.shortcuts import render
from django.utils import timezone

from project.accounts.models import Role, User
from project.fleet.models import Driver
from project.jobs.models import Booking, BookingStatus

RANGES = [("7", "7 วัน"), ("30", "30 วัน"), ("90", "3 เดือน"),
          ("180", "6 เดือน"), ("365", "1 ปี")]


@login_required
def deep_report(request):
    if not (request.user.is_superuser or request.user.role in (Role.EXECUTIVE, Role.ADMIN, Role.SHR)):
        raise PermissionDenied("เฉพาะผู้บริหารและผู้จัดการเท่านั้น")

    days = request.GET.get("days", "30")
    driver_id = request.GET.get("driver") or ""
    sales_id = request.GET.get("sales") or ""
    status = request.GET.get("status") or ""
    submitted = "go" in request.GET

    qs = Booking.objects.with_related()
    result = None

    if submitted:
        try:
            since = timezone.now() - timezone.timedelta(days=int(days))
            qs = qs.filter(created_at__gte=since)
        except (TypeError, ValueError):
            pass
        if driver_id:
            qs = qs.filter(driver_id=driver_id)
        if sales_id:
            qs = qs.filter(sales_id=sales_id)
        if status:
            qs = qs.filter(status=status)

        by_status = list(
            qs.values("status").annotate(n=Count("id"), total=Sum("price")).order_by("-n")
        )
        labels = dict(BookingStatus.choices)
        for row in by_status:
            row["label"] = labels.get(row["status"], row["status"])

        result = {
            "rows": qs[:200],
            "count": qs.count(),
            "revenue": qs.aggregate(s=Sum("price"))["s"] or 0,
            "by_status": by_status,
        }

    return render(request, "reports/deep.html", {
        "ranges": RANGES, "days": days, "submitted": submitted,
        "drivers": Driver.objects.select_related("owner"),
        "sales_users": User.objects.filter(role=Role.SALES),
        "statuses": BookingStatus.choices,
        "driver_id": driver_id, "sales_id": sales_id, "status": status,
        "result": result,
    })
