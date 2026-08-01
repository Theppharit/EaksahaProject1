from django import forms

from .models import Driver, Truck


class DriverForm(forms.ModelForm):
    class Meta:
        model = Driver
        fields = ["name", "phone", "national_id", "license_no", "license_expiry",
                  "truck", "photo", "is_backup", "is_available"]
        widgets = {
            "name": forms.TextInput(attrs={"class": "input"}),
            "phone": forms.TextInput(attrs={"class": "input", "placeholder": "08x-xxx-xxxx"}),
            "national_id": forms.TextInput(attrs={"class": "input", "placeholder": "13 หลัก"}),
            "license_no": forms.TextInput(attrs={"class": "input"}),
            "license_expiry": forms.DateInput(attrs={"class": "input", "type": "date"}),
            "truck": forms.Select(attrs={"class": "select"}),
            "photo": forms.ClearableFileInput(attrs={"class": "input", "accept": "image/*"}),
            "is_backup": forms.CheckboxInput(attrs={"class": "check"}),
            "is_available": forms.CheckboxInput(attrs={"class": "check"}),
        }

    def __init__(self, *args, owner=None, **kwargs):
        super().__init__(*args, **kwargs)
        if owner:
            self.fields["truck"].queryset = Truck.objects.filter(owner=owner, is_active=True)
        self.fields["truck"].required = False
        self.fields["truck"].empty_label = "— ยังไม่กำหนดรถ —"
