"""
เส้นทาง URL ของระบบผู้ใช้
------------------------------------------------------------
ใช้ namespace "accounts" → อ้างถึงด้วย {% url 'accounts:login' %}
"""

from django.contrib.auth.views import LogoutView
from django.urls import path

from . import views

app_name = "accounts"

urlpatterns = [
    # ── เข้า/ออกระบบ ──────────────────────────────
    path("login/", views.TQMLoginView.as_view(), name="login"),
    path("logout/", LogoutView.as_view(), name="logout"),
    # ── ทางแยกหลัง login ──────────────────────────
    path("", views.dashboard_router, name="dashboard"),
    # ── แดชบอร์ดรายตำแหน่ง ────────────────────────
    # ชื่อ URL ต้องเป็น dashboard_<role> ให้ตรงกับ User.dashboard_url_name
    path("d/requester/", views.dashboard_requester, name="dashboard_requester"),
    path("d/sales/", views.dashboard_sales, name="dashboard_sales"),
    path("d/trailer/", views.dashboard_trailer, name="dashboard_trailer"),
    path("d/shr/", views.dashboard_shr, name="dashboard_shr"),
    path("d/accounting/", views.dashboard_accounting, name="dashboard_accounting"),
    path("d/executive/", views.dashboard_executive, name="dashboard_executive"),
    path("d/admin/", views.dashboard_admin, name="dashboard_admin"),
]
