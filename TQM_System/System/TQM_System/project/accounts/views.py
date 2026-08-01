"""
หน้า login และแดชบอร์ดของทั้ง 7 ตำแหน่ง
------------------------------------------------------------
ตอนนี้แดชบอร์ดยังเป็นหน้าเปล่า (ข้อความต้อนรับ + ปุ่มออกจากระบบ)
ไว้ค่อยเติมเนื้อหาจริงตามเอกสารสเปกทีละหน้า
"""

from functools import wraps

from django.contrib import messages
from django.contrib.auth.decorators import login_required
from django.contrib.auth.views import LoginView
from django.shortcuts import redirect, render

from .forms import EmployeeLoginForm
from .models import Role


class TQMLoginView(LoginView):
    """หน้าเข้าสู่ระบบ — ถ้าล็อกอินอยู่แล้วให้เด้งไปแดชบอร์ดเลย"""

    template_name = "registration/login.html"
    authentication_form = EmployeeLoginForm
    redirect_authenticated_user = True


@login_required
def dashboard_router(request):
    """
    ทางแยกหลัง login — ดูตำแหน่งของผู้ใช้แล้วส่งไปแดชบอร์ดที่ถูกต้อง
    เป็นปลายทางของ LOGIN_REDIRECT_URL
    """
    return redirect(request.user.dashboard_url_name)


def role_required(role):
    """
    ตัวครอบ view: ให้เข้าได้เฉพาะเจ้าของตำแหน่งนั้น
    superuser เข้าได้ทุกหน้าเพื่อความสะดวกตอนตรวจงาน

    ⚠ นี่คือการกันสิทธิ์ "ฝั่งเซิร์ฟเวอร์" ซึ่งเป็นด่านจริง
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
# แดชบอร์ด 7 ตำแหน่ง
# ------------------------------------------------------------
# แยกเป็นคนละ view และคนละ template ตั้งแต่แรก
# เพราะเนื้อหาจริงของแต่ละตำแหน่งต่างกันมาก (ตามเอกสารสเปก)
# ============================================================


@role_required(Role.REQUESTER)
def dashboard_requester(request):
    return render(request, "dashboard/requester.html")


@role_required(Role.SALES)
def dashboard_sales(request):
    return render(request, "dashboard/sales.html")


@role_required(Role.TRAILER)
def dashboard_trailer(request):
    return render(request, "dashboard/trailer.html")


@role_required(Role.SHR)
def dashboard_shr(request):
    return render(request, "dashboard/shr.html")


@role_required(Role.ACCOUNTING)
def dashboard_accounting(request):
    return render(request, "dashboard/accounting.html")


@role_required(Role.EXECUTIVE)
def dashboard_executive(request):
    return render(request, "dashboard/executive.html")


@role_required(Role.ADMIN)
def dashboard_admin(request):
    return render(request, "dashboard/admin.html")
