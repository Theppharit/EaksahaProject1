"""
หน้า login และแดชบอร์ดของทั้ง 7 ตำแหน่ง
------------------------------------------------------------
แดชบอร์ดทุกตำแหน่งใช้ template เดียวกัน (dashboard/index.html)
ต่างกันที่ "ข้อมูลที่เห็น" ซึ่งถูกกรองด้วย Booking.objects.for_user()
"""

from functools import wraps

from django.contrib import messages
from django.contrib.auth.decorators import login_required
from django.contrib.auth.views import LoginView
from django.shortcuts import redirect, render

from project.jobs.models import Booking, BookingStatus
from project.jobs.services import dashboard_stats

from .forms import EmployeeLoginForm
from .models import Role


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


# ป้ายกำกับตัวเลข 4 ช่องบนหัวแดชบอร์ด — ปรับข้อความตามมุมมองของแต่ละตำแหน่ง
STAT_LABELS = {
    Role.REQUESTER: ("คิวที่ยังไม่ปิดงาน", "รอดำเนินการ", "กำลังดำเนินงาน", "เสร็จสิ้นแล้ว"),
    Role.SALES: ("คิวที่ดูแลอยู่", "รออนุมัติ", "กำลังดำเนินงาน", "ปิดงานแล้ว"),
    Role.TRAILER: ("งานที่ยังไม่ปิด", "รอรับ/รออนุมัติ", "กำลังดำเนินงาน", "ปิดงานแล้ว"),
    Role.SHR: ("ใบจองที่ยังไม่ปิด", "รออนุมัติ", "กำลังดำเนินงาน", "ปิดงานแล้ว"),
    Role.ACCOUNTING: ("ใบจองในระบบ", "รอตรวจสอบ", "กำลังดำเนินงาน", "ปิดงานแล้ว"),
    Role.EXECUTIVE: ("งานที่ยังไม่ปิด", "รออนุมัติ", "กำลังดำเนินงาน", "ปิดงานแล้ว"),
    Role.ADMIN: ("ใบจองที่ยังไม่ปิด", "รออนุมัติ", "กำลังดำเนินงาน", "ปิดงานแล้ว"),
}


def _dashboard(request, template):
    """ตัวช่วยที่ทุกแดชบอร์ดใช้ร่วมกัน — ตัวเลขสรุป + 10 รายการล่าสุด"""
    user = request.user
    stats = dashboard_stats(user)
    labels = STAT_LABELS.get(user.role, STAT_LABELS[Role.ADMIN])

    recent = Booking.objects.with_related().for_user(user).open()[:10]

    return render(
        request,
        template,
        {
            "stat_cards": [
                {"label": labels[0], "value": stats["open"], "key": "open"},
                {"label": labels[1], "value": stats["waiting"], "key": "waiting"},
                {"label": labels[2], "value": stats["in_progress"], "key": "in_progress"},
                {"label": labels[3], "value": stats["closed"], "key": "closed"},
            ],
            "recent_bookings": recent,
            "can_create": user.role in (Role.REQUESTER, Role.SHR, Role.ADMIN),
            "status_in_progress": BookingStatus.IN_PROGRESS,
        },
    )


# ============================================================
# แดชบอร์ด 7 ตำแหน่ง
# แยก view/template ตั้งแต่แรก เพราะเนื้อหาจริงจะต่างกันมาก
# ============================================================


@role_required(Role.REQUESTER)
def dashboard_requester(request):
    return _dashboard(request, "dashboard/requester.html")


@role_required(Role.SALES)
def dashboard_sales(request):
    return _dashboard(request, "dashboard/sales.html")


@role_required(Role.TRAILER)
def dashboard_trailer(request):
    return _dashboard(request, "dashboard/trailer.html")


@role_required(Role.SHR)
def dashboard_shr(request):
    return _dashboard(request, "dashboard/shr.html")


@role_required(Role.ACCOUNTING)
def dashboard_accounting(request):
    return _dashboard(request, "dashboard/accounting.html")


@role_required(Role.EXECUTIVE)
def dashboard_executive(request):
    return _dashboard(request, "dashboard/executive.html")


@role_required(Role.ADMIN)
def dashboard_admin(request):
    return _dashboard(request, "dashboard/admin.html")
