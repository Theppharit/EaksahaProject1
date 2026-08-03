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
    """
    รอบจ่ายเงิน — ตกลงกับลูกค้าแล้วว่าจ่ายเดือนละครั้ง
    จึงล็อก times_per_month = 1 ไว้ ไม่ต้องให้ผู้ใช้เลือก และซ่อนช่องวันจ่ายครั้งที่ 2
    (ยังเก็บฟิลด์ไว้ในโมเดล เผื่อวันหน้าลูกค้าขอเป็นเดือนละ 2 ครั้ง)
    """

    class Meta:
        model = PaymentCycle
        fields = ["name", "pay_day_1", "is_active"]
        labels = {"pay_day_1": "จ่ายเงินทุกวันที่"}
        help_texts = {"pay_day_1": "ใส่ 31 = สิ้นเดือน (เดือนที่ไม่มีวันที่ 31 ระบบจะใช้วันสุดท้ายของเดือนแทน)"}
        widgets = {
            "name": forms.TextInput(attrs={**I, "placeholder": "เช่น รอบจ่ายปกติ"}),
            "pay_day_1": forms.NumberInput(attrs={**I, "min": 1, "max": 31}),
            "is_active": forms.CheckboxInput(attrs={"class": "check"}),
        }

    def save(self, commit=True):
        obj = super().save(commit=False)
        obj.times_per_month = 1
        obj.pay_day_2 = None
        if commit:
            obj.save()
        return obj


class BillForm(forms.ModelForm):
    class Meta:
        model = Bill
        fields = ["po_no", "note"]
        widgets = {
            "po_no": forms.TextInput(attrs={**I, "placeholder": "กรอกเลข PO หลังชำระเงินแล้ว"}),
            "note": forms.TextInput(attrs=I),
        }
