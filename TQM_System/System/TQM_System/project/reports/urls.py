from django.urls import path

from . import views

app_name = "reports"

urlpatterns = [path("deep/", views.deep_report, name="deep")]
