from django.urls import path

from . import views

app_name = "core"

urlpatterns = [
    path("notifications/", views.notifications, name="notifications"),
    path("notifications/read-all/", views.notifications_read_all, name="notifications_read_all"),
    path("notifications/<int:pk>/open/", views.notification_open, name="notification_open"),
    path("notifications/<int:pk>/ack/", views.notification_ack, name="notification_ack"),
    path("role-change/<int:pk>/", views.role_change_decide, name="role_change_decide"),
    path("docs/", views.docs, name="docs"),
    path("profile/", views.profile, name="profile"),
    path("verify/", views.verify_identity, name="verify_identity"),
]
