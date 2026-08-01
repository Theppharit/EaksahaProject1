from django.apps import AppConfig


class CustomersConfig(AppConfig):
    default_auto_field = "django.db.models.BigAutoField"
    name = "project.customers"
    label = "customers"
    verbose_name = "ลูกค้า"
