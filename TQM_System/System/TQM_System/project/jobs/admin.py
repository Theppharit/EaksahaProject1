from django.contrib import admin
from django.utils.html import format_html

from .models import (
    Booking,
    BookingRequest,
    BookingStatus,
    BookingStatusLog,
    JobPhoto,
    JobStep,
)

# สีป้ายสถานะในหน้า admin ให้กวาดตาหางานด่วนได้เร็ว
STATUS_COLORS = {
    BookingStatus.PENDING_ACCEPT: "#f59e0b",
    BookingStatus.ACCEPTED: "#2563eb",
    BookingStatus.PENDING_APPROVAL: "#f59e0b",
    BookingStatus.SHR_APPROVED: "#7c3aed",
    BookingStatus.ACCOUNTING_REVIEW: "#0891b2",
    BookingStatus.APPROVED: "#059669",
    BookingStatus.IN_PROGRESS: "#2563eb",
    BookingStatus.CLOSED: "#6b7280",
    BookingStatus.CANCELLED: "#dc2626",
}


class JobStepInline(admin.TabularInline):
    model = JobStep
    extra = 0
    fields = ("code", "is_done", "done_at", "done_by", "note")
    readonly_fields = ("done_at",)


class StatusLogInline(admin.TabularInline):
    model = BookingStatusLog
    extra = 0
    fields = ("created_at", "from_status", "to_status", "by", "note")
    readonly_fields = fields
    can_delete = False
    ordering = ("-created_at",)

    def has_add_permission(self, request, obj=None):
        return False  # log ต้องเกิดจากการเปลี่ยนสถานะจริงเท่านั้น


@admin.register(Booking)
class BookingAdmin(admin.ModelAdmin):
    list_display = (
        "booking_no", "customer", "appointment_at", "truck_owner",
        "driver", "status_badge", "total_display",
    )
    list_filter = ("status", "appointment_at", "truck_owner", "sales")
    search_fields = (
        "booking_no", "customer__name", "customer__phone",
        "vehicle__plate", "pickup_address", "dropoff_address",
    )
    date_hierarchy = "appointment_at"
    autocomplete_fields = ("customer", "vehicle", "created_by", "sales", "truck_owner", "truck", "driver")
    readonly_fields = ("booking_no", "accepted_at", "closed_at", "created_at", "updated_at")
    inlines = [JobStepInline, StatusLogInline]

    fieldsets = (
        ("ข้อมูลใบจอง", {"fields": ("booking_no", "status", "customer", "vehicle")}),
        ("ผู้เกี่ยวข้อง", {"fields": ("created_by", "sales")}),
        ("การเดินทาง", {"fields": ("appointment_at", "pickup_address", "dropoff_address", "distance_km", "note")}),
        ("ผู้รับงาน", {"fields": ("truck_owner", "truck", "driver", "accepted_at")}),
        ("ค่าใช้จ่าย", {"fields": ("price", "extra_charge")}),
        ("เวลา", {"fields": ("closed_at", "cancelled_reason", "created_at", "updated_at"), "classes": ("collapse",)}),
    )

    def get_queryset(self, request):
        # ดึงตารางที่เกี่ยวข้องมาพร้อมกัน กันปัญหา N+1 ในหน้ารายการ
        return super().get_queryset(request).with_related()

    @admin.display(description="สถานะ", ordering="status")
    def status_badge(self, obj):
        return format_html(
            '<span style="background:{};color:#fff;padding:2px 8px;'
            'border-radius:10px;font-size:11px;white-space:nowrap">{}</span>',
            STATUS_COLORS.get(obj.status, "#6b7280"),
            obj.get_status_display(),
        )

    @admin.display(description="ยอดรวม")
    def total_display(self, obj):
        return f"{obj.total_price:,.2f}"


@admin.register(JobStep)
class JobStepAdmin(admin.ModelAdmin):
    list_display = ("booking", "code", "is_done", "done_at", "done_by")
    list_filter = ("code", "is_done")
    search_fields = ("booking__booking_no",)
    autocomplete_fields = ("booking", "done_by")


@admin.register(JobPhoto)
class JobPhotoAdmin(admin.ModelAdmin):
    list_display = ("step", "angle", "uploaded_at")
    list_filter = ("uploaded_at",)


@admin.register(BookingStatusLog)
class BookingStatusLogAdmin(admin.ModelAdmin):
    list_display = ("created_at", "booking", "from_status", "to_status", "by", "note")
    list_filter = ("to_status", "created_at")
    search_fields = ("booking__booking_no", "note")
    readonly_fields = ("booking", "from_status", "to_status", "by", "note", "created_at")

    def has_add_permission(self, request):
        return False

    def has_change_permission(self, request, obj=None):
        return False  # ประวัติต้องแก้ไม่ได้ ไม่งั้นไม่มีค่าเป็นหลักฐาน


@admin.register(BookingRequest)
class BookingRequestAdmin(admin.ModelAdmin):
    """คำขอแก้ไข/ยกเลิก — ปกติพิจารณาจากหน้าแดชบอร์ด ที่นี่ไว้ตรวจย้อนหลัง"""

    list_display = ("created_at", "booking", "kind", "status", "created_by", "decided_by")
    list_filter = ("kind", "status", "created_at")
    search_fields = ("booking__booking_no", "reason", "decision_note")
    autocomplete_fields = ("booking",)
    readonly_fields = ("created_at", "decided_at")
