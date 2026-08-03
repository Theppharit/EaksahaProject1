from django import forms

from project.accounts.models import Role
from project.core.models import SystemDoc

# หมายเหตุ: การเปลี่ยนตำแหน่งไม่ได้ใช้ ModelForm แล้ว
# เพราะสิทธิ์ต้องผ่านการยอมรับของเจ้าของบัญชีก่อน (ดู adminpanel/views.py)


class DocForm(forms.ModelForm):
    roles = forms.MultipleChoiceField(
        label="ตำแหน่งที่มองเห็นเอกสารนี้",
        choices=Role.choices,
        required=False,
        widget=forms.CheckboxSelectMultiple,
        help_text="ไม่ติ๊กเลย = ทุกตำแหน่งเห็น",
    )

    class Meta:
        model = SystemDoc
        fields = ["title", "description", "file"]
        widgets = {
            "title": forms.TextInput(attrs={"class": "input", "placeholder": "เช่น คู่มือการจองคิว"}),
            "description": forms.TextInput(attrs={"class": "input"}),
            "file": forms.ClearableFileInput(attrs={"class": "input"}),
        }
