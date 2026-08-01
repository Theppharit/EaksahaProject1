from django.contrib import admin

from .models import Driver, Truck, TruckOwner


class TruckInline(admin.TabularInline):
    model = Truck
    extra = 1
    fields = ("plate", "type", "brand", "is_active")


@admin.register(TruckOwner)
class TruckOwnerAdmin(admin.ModelAdmin):
    list_display = ("name", "phone", "truck_count", "driver_count", "is_active")
    list_filter = ("is_active",)
    search_fields = ("name", "phone", "tax_id")
    autocomplete_fields = ("user",)
    inlines = [TruckInline]

    def get_queryset(self, request):
        # นับรถ/คนขับด้วย query เดียว ไม่ยิงทีละแถว
        from django.db.models import Count

        return (
            super()
            .get_queryset(request)
            .annotate(_trucks=Count("trucks", distinct=True), _drivers=Count("drivers", distinct=True))
        )

    @admin.display(description="จำนวนรถ", ordering="_trucks")
    def truck_count(self, obj):
        return obj._trucks

    @admin.display(description="จำนวนคนขับ", ordering="_drivers")
    def driver_count(self, obj):
        return obj._drivers


@admin.register(Truck)
class TruckAdmin(admin.ModelAdmin):
    list_display = ("plate", "type", "owner", "brand", "insurance_expiry", "is_active")
    list_filter = ("type", "is_active", "owner")
    search_fields = ("plate", "brand", "owner__name")
    autocomplete_fields = ("owner",)


@admin.register(Driver)
class DriverAdmin(admin.ModelAdmin):
    list_display = (
        "name", "phone", "owner", "truck", "is_backup", "is_available", "approval_status",
    )
    list_filter = ("approval_status", "is_available", "is_backup", "owner")
    list_editable = ("is_available", "approval_status")
    search_fields = ("name", "phone", "license_no")
    autocomplete_fields = ("owner", "truck")
