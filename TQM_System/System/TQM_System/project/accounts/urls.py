"""
เส้นทาง URL ของระบบผู้ใช้ — namespace "accounts"
รวมหน้าลืมรหัสผ่านของ Django (4 ขั้นตอน)
"""

from django.contrib.auth import views as auth_views
from django.urls import path

from . import views

app_name = "accounts"

urlpatterns = [
    # ── เข้า/ออกระบบ ──────────────────────────────
    path("login/", views.TQMLoginView.as_view(), name="login"),
    path("logout/", auth_views.LogoutView.as_view(), name="logout"),

    # ── ลืมรหัสผ่าน (ใช้ของ Django ทั้งชุด) ─────────
    path("password/reset/", auth_views.PasswordResetView.as_view(
        template_name="registration/password_reset_form.html",
        email_template_name="registration/password_reset_email.txt",
        success_url="/password/reset/sent/",
    ), name="password_reset"),
    path("password/reset/sent/", auth_views.PasswordResetDoneView.as_view(
        template_name="registration/password_reset_done.html",
    ), name="password_reset_done"),
    path("password/reset/<uidb64>/<token>/", auth_views.PasswordResetConfirmView.as_view(
        template_name="registration/password_reset_confirm.html",
        success_url="/password/reset/complete/",
    ), name="password_reset_confirm"),
    path("password/reset/complete/", auth_views.PasswordResetCompleteView.as_view(
        template_name="registration/password_reset_complete.html",
    ), name="password_reset_complete"),

    # ── ทางแยกหลัง login ──────────────────────────
    path("", views.dashboard_router, name="dashboard"),

    # ── แดชบอร์ดรายตำแหน่ง ────────────────────────
    path("d/requester/", views.dashboard_requester, name="dashboard_requester"),
    path("d/sales/", views.dashboard_sales, name="dashboard_sales"),
    path("d/trailer/", views.dashboard_trailer, name="dashboard_trailer"),
    path("d/shr/", views.dashboard_shr, name="dashboard_shr"),
    path("d/accounting/", views.dashboard_accounting, name="dashboard_accounting"),
    path("d/executive/", views.dashboard_executive, name="dashboard_executive"),
    path("d/admin/", views.dashboard_admin, name="dashboard_admin"),
]
