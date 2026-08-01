from django.urls import path

from . import views

app_name = "billing"

urlpatterns = [
    path("rates/", views.rates, name="rates"),
    path("bills/", views.bill_list, name="bill_list"),
    path("bills/new/<int:owner_pk>/", views.bill_create, name="bill_create"),
    path("bills/<int:pk>/", views.bill_detail, name="bill_detail"),
    path("payments/", views.payment_history, name="payment_history"),
]
