from django.apps import AppConfig


class AccountsConfig(AppConfig):
    default_auto_field = "django.db.models.BigAutoField"
    name = "project.accounts"
    # label สั้น ๆ เพื่อให้ AUTH_USER_MODEL = "accounts.User" ใช้ได้
    label = "accounts"
    verbose_name = "ผู้ใช้งานและสิทธิ์"
