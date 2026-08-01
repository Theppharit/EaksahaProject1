from django.urls import path

from . import views

app_name = "fleet"

urlpatterns = [
    path("drivers/", views.driver_list, name="driver_list"),
    path("drivers/new/", views.driver_form, name="driver_add"),
    path("drivers/<int:pk>/", views.driver_detail, name="driver_detail"),
    path("drivers/<int:pk>/edit/", views.driver_form, name="driver_edit"),
    path("drivers/<int:pk>/toggle/", views.driver_toggle, name="driver_toggle"),
]
