"""ฟอร์มของระบบใบจอง"""

from django import forms
from django.utils import timezone

from project.accounts.models import Role, User
from project.fleet.models import Driver, Truck

from .models import Booking

TEXT = {"class": "input"}
AREA = {"class": "textarea", "rows": 3}


class BookingForm(forms.ModelForm):
    """สร้าง / แก้ไขใบจองคิว"""

    class Meta:
        model = Booking
        fields = [
            "customer", "vehicle", "sales", "appointment_at",
            "pickup_address", "dropoff_address", "distance_km", "price", "note",
        ]
        widgets = {
            "customer": forms.Select(attrs={"class": "select"}),
            "vehicle": forms.Select(attrs={"class": "select"}),
            "sales": forms.Select(attrs={"class": "select"}),
            "appointment_at": forms.DateTimeInput(
                attrs={"class": "input", "type": "datetime-local"}, format="%Y-%m-%dT%H:%M"
            ),
            "pickup_address": forms.Textarea(attrs=AREA),
            "dropoff_address": forms.Textarea(attrs=AREA),
            "distance_km": forms.NumberInput(attrs={**TEXT, "step": "0.1", "min": "0"}),
            "price": forms.NumberInput(attrs={**TEXT, "step": "0.01", "min": "0"}),
            "note": forms.Textarea(attrs=AREA),
        }

    def __init__(self, *args, **kwargs):
        super().__init__(*args, **kwargs)
        # เลือกได้เฉพาะผู้ใช้ที่เป็นเซลล์
        self.fields["sales"].queryset = User.objects.filter(role=Role.SALES, is_active=True)
        self.fields["sales"].required = False
        self.fields["sales"].empty_label = "— ไม่ระบุเซลล์ —"
        self.fields["vehicle"].required = False
        self.fields["vehicle"].empty_label = "— ไม่ระบุรถ —"
        self.fields["appointment_at"].input_formats = ["%Y-%m-%dT%H:%M"]

    def clean_appointment_at(self):
        value = self.cleaned_data["appointment_at"]
        if value and value < timezone.now():
            raise forms.ValidationError("วันเวลานัดหมายต้องเป็นอนาคต")
        return value

    def clean(self):
        data = super().clean()
        customer, vehicle = data.get("customer"), data.get("vehicle")
        # กันเลือกรถของลูกค้าคนอื่นมาใส่
        if customer and vehicle and vehicle.customer_id != customer.id:
            self.add_error("vehicle", "รถคันนี้ไม่ใช่ของลูกค้าที่เลือก")
        return data


class AcceptJobForm(forms.Form):
    """เจ้าของรถกดรับงาน — เลือกรถและคนขับ"""

    truck = forms.ModelChoiceField(
        label="รถที่ใช้", queryset=Truck.objects.none(),
        widget=forms.Select(attrs={"class": "select"}),
    )
    driver = forms.ModelChoiceField(
        label="คนขับ", queryset=Driver.objects.none(),
        widget=forms.Select(attrs={"class": "select"}),
    )
    note = forms.CharField(
        label="หมายเหตุ", required=False,
        widget=forms.Textarea(attrs={**AREA, "placeholder": "ถ้ามี"}),
    )

    def __init__(self, *args, owner=None, **kwargs):
        super().__init__(*args, **kwargs)
        if owner:
            # เห็นเฉพาะรถและคนขับของตัวเอง
            self.fields["truck"].queryset = Truck.objects.filter(owner=owner, is_active=True)
            self.fields["driver"].queryset = Driver.objects.filter(
                owner=owner, is_available=True, approval_status="approved"
            )


class ActionForm(forms.Form):
    """ฟอร์มเล็ก ๆ ท้ายปุ่มดำเนินการ — ส่งสถานะปลายทาง + หมายเหตุ"""

    target = forms.CharField(widget=forms.HiddenInput)
    note = forms.CharField(
        label="หมายเหตุ", required=False,
        widget=forms.Textarea(attrs={**AREA, "placeholder": "ระบุเหตุผล (บังคับกรณีตีกลับหรือยกเลิก)"}),
    )
