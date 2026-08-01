"""หน้าจัดการผู้ใช้และสีประจำตำแหน่ง ในระบบ Django Admin"""

from django.contrib import admin
from django.contrib.auth.admin import UserAdmin as BaseUserAdmin
from django.utils.html import format_html

from .models import RoleTheme, User


@admin.register(User)
class UserAdmin(BaseUserAdmin):
    list_display = ("username", "get_full_name", "role", "is_active", "last_login")
    list_filter = ("role", "is_active", "is_staff")
    search_fields = ("username", "first_name", "last_name", "email", "phone")
    ordering = ("username",)

    # เพิ่มช่อง role/phone เข้าไปในฟอร์มแก้ไขผู้ใช้
    fieldsets = BaseUserAdmin.fieldsets + (
        ("ข้อมูล TQM", {"fields": ("role", "phone")}),
    )
    # และในฟอร์มสร้างผู้ใช้ใหม่
    add_fieldsets = BaseUserAdmin.add_fieldsets + (
        ("ข้อมูล TQM", {"fields": ("role", "phone")}),
    )

    @admin.display(description="ชื่อ-นามสกุล")
    def get_full_name(self, obj):
        return obj.get_full_name() or "—"


@admin.register(RoleTheme)
class RoleThemeAdmin(admin.ModelAdmin):
    """แอดมินตั้งสีหลักของแต่ละตำแหน่งได้จากตรงนี้ ไม่ต้องแก้โค้ด"""

    list_display = ("role", "accent_color", "swatch")
    list_editable = ("accent_color",)

    @admin.display(description="ตัวอย่างสี")
    def swatch(self, obj):
        return format_html(
            '<span style="display:inline-block;width:64px;height:20px;'
            'border-radius:4px;border:1px solid #ccc;background:{}"></span>',
            obj.accent_color,
        )
