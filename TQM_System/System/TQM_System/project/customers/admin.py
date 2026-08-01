from django.contrib import admin

from .models import Customer, CustomerVehicle


class CustomerVehicleInline(admin.TabularInline):
    model = CustomerVehicle
    extra = 1


@admin.register(Customer)
class CustomerAdmin(admin.ModelAdmin):
    list_display = ("code", "name", "type", "phone", "is_active")
    list_filter = ("type", "is_active")
    search_fields = ("code", "name", "phone", "tax_id")
    inlines = [CustomerVehicleInline]


@admin.register(CustomerVehicle)
class CustomerVehicleAdmin(admin.ModelAdmin):
    list_display = ("plate", "brand", "model", "color", "customer")
    search_fields = ("plate", "brand", "model", "customer__name")
    autocomplete_fields = ("customer",)
