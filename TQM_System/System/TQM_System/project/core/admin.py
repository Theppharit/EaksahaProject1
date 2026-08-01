from django.contrib import admin

from .models import Notification, SystemDoc


@admin.register(Notification)
class NotificationAdmin(admin.ModelAdmin):
    list_display = ("created_at", "user", "kind", "title", "is_read", "need_ack")
    list_filter = ("kind", "is_read", "need_ack")
    search_fields = ("title", "body", "user__username")
    autocomplete_fields = ("user",)


@admin.register(SystemDoc)
class SystemDocAdmin(admin.ModelAdmin):
    list_display = ("title", "role_labels", "uploaded_by", "created_at")
    search_fields = ("title", "description")
    autocomplete_fields = ("uploaded_by",)
