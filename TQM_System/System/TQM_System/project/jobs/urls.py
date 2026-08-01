from django.urls import path

from . import views

app_name = "jobs"

urlpatterns = [
    path("", views.booking_list, name="list"),
    path("history/", views.booking_history, name="history"),
    path("pending/", views.booking_pending, name="pending"),
    path("new/", views.booking_create, name="create"),
    path("<int:pk>/", views.booking_detail, name="detail"),
    path("<int:pk>/edit/", views.booking_edit, name="edit"),
    path("<int:pk>/accept/", views.booking_accept, name="accept"),
    path("<int:pk>/action/", views.booking_action, name="action"),
]
