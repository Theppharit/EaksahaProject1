from django.urls import path

from . import views

app_name = "adminpanel"

urlpatterns = [
    path("support/shr/", views.support_shr, name="support_shr"),
    path("support/requester/", views.support_requester, name="support_requester"),
    path("support/trailer/", views.support_trailer, name="support_trailer"),
    path("support/accounting/", views.support_accounting, name="support_accounting"),
    # โหมดสวมสิทธิ์ — เข้า/ออกระบบในนามผู้ใช้คนอื่น
    path("support/enter/<int:pk>/", views.impersonate_start, name="impersonate_start"),
    path("support/exit/", views.impersonate_stop, name="impersonate_stop"),
    path("permissions/", views.permissions, name="permissions"),
    path("docs/upload/", views.doc_upload, name="doc_upload"),
]
