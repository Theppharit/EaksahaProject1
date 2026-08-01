from django import forms

from project.accounts.models import Role, User
from project.core.models import SystemDoc


class UserRoleForm(forms.ModelForm):
    class Meta:
        model = User
        fields = ["role", "is_active", "id_verified"]
        widgets = {
            "role": forms.Select(attrs={"class": "select"}),
            "is_active": forms.CheckboxInput(attrs={"class": "check"}),
            "id_verified": forms.CheckboxInput(attrs={"class": "check"}),
        }


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
