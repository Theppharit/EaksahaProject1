from django.contrib import admin

from .models import Bill, BillItem, PaymentCycle, PriceRate


@admin.register(PriceRate)
class PriceRateAdmin(admin.ModelAdmin):
    list_display = ("name", "base_price", "included_km", "price_per_km", "effective_from", "is_active")
    list_filter = ("is_active",)
    date_hierarchy = "effective_from"


@admin.register(PaymentCycle)
class PaymentCycleAdmin(admin.ModelAdmin):
    list_display = ("name", "times_per_month", "pay_day_1", "pay_day_2", "is_active")
    list_filter = ("is_active",)


class BillItemInline(admin.TabularInline):
    model = BillItem
    extra = 0
    autocomplete_fields = ("booking",)


@admin.register(Bill)
class BillAdmin(admin.ModelAdmin):
    list_display = ("bill_no", "truck_owner", "period_start", "period_end", "total_amount", "status", "po_no")
    list_filter = ("status", "truck_owner")
    search_fields = ("bill_no", "po_no", "truck_owner__name")
    autocomplete_fields = ("truck_owner", "issued_by")
    inlines = [BillItemInline]

    def save_related(self, request, form, formsets, change):
        super().save_related(request, form, formsets, change)
        form.instance.recalculate_total()  # รวมยอดใหม่ทุกครั้งที่แก้รายการย่อย


@admin.register(BillItem)
class BillItemAdmin(admin.ModelAdmin):
    list_display = ("bill", "booking", "amount")
    search_fields = ("bill__bill_no", "booking__booking_no")
    autocomplete_fields = ("bill", "booking")
