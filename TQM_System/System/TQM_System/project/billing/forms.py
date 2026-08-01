from django import forms

from .models import Bill, PaymentCycle, PriceRate

I = {"class": "input"}


class PriceRateForm(forms.ModelForm):
    class Meta:
        model = PriceRate
        fields = ["name", "base_price", "included_km", "price_per_km", "effective_from", "is_active"]
        widgets = {
            "name": forms.TextInput(attrs={**I, "placeholder": "เช่น เรทปี 2569"}),
            "base_price": forms.NumberInput(attrs={**I, "step": "0.01"}),
            "included_km": forms.NumberInput(attrs={**I, "step": "0.1"}),
            "price_per_km": forms.NumberInput(attrs={**I, "step": "0.01"}),
            "effective_from": forms.DateInput(attrs={**I, "type": "date"}),
            "is_active": forms.CheckboxInput(attrs={"class": "check"}),
        }


class PaymentCycleForm(forms.ModelForm):
    class Meta:
        model = PaymentCycle
        fields = ["name", "times_per_month", "pay_day_1", "pay_day_2", "is_active"]
        widgets = {
            "name": forms.TextInput(attrs={**I, "placeholder": "เช่น รอบปกติ"}),
            "times_per_month": forms.Select(attrs={"class": "select"}),
            "pay_day_1": forms.NumberInput(attrs={**I, "min": 1, "max": 31}),
            "pay_day_2": forms.NumberInput(attrs={**I, "min": 1, "max": 31}),
            "is_active": forms.CheckboxInput(attrs={"class": "check"}),
        }


class BillForm(forms.ModelForm):
    class Meta:
        model = Bill
        fields = ["po_no", "note"]
        widgets = {
            "po_no": forms.TextInput(attrs={**I, "placeholder": "กรอกเลข PO หลังชำระเงินแล้ว"}),
            "note": forms.TextInput(attrs=I),
        }
