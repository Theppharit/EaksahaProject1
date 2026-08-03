from django.urls import path

from . import views

app_name = "jobs"

urlpatterns = [
    path("", views.booking_list, name="list"),
    path("history/", views.booking_history, name="history"),
    path("pending/", views.approvals, name="pending"),
    path("<int:pk>/approve/", views.approval_action, name="approval_action"),
    path("available/", views.booking_available, name="available"),
    path("new/", views.booking_create, name="create"),
    path("<int:pk>/", views.booking_detail, name="detail"),
    path("<int:pk>/slip/", views.booking_slip, name="slip"),
    path("<int:pk>/edit/", views.booking_edit, name="edit"),
    path("<int:pk>/accept/", views.booking_accept, name="accept"),
    path("<int:pk>/action/", views.booking_action, name="action"),
    path("<int:pk>/request/", views.request_create, name="request"),
    path("<int:pk>/work/", views.job_work, name="work"),
    path("<int:pk>/work/<str:code>/done/", views.job_step_done, name="step_done"),
    # คำขอแก้ไข/ยกเลิก — pk ตรงนี้คือ id ของคำขอ ไม่ใช่ของใบจอง
    path("requests/<int:pk>/decide/", views.request_decide, name="request_decide"),
]
