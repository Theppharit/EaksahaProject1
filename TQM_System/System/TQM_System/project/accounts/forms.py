"""ฟอร์มของระบบผู้ใช้ — login · โปรไฟล์ · ยืนยันตัวตน"""

from django import forms
from django.contrib.auth.forms import AuthenticationForm

from .models import User


class EmployeeLoginForm(AuthenticationForm):
    """ใช้ AuthenticationForm ของ Django ตรง ๆ แค่เปลี่ยนหน้าตาช่องกรอก"""

    error_messages = {
        **AuthenticationForm.error_messages,
        "invalid_login": "รหัสพนักงานหรือรหัสผ่านไม่ถูกต้อง",
        "inactive": "บัญชีนี้ถูกระงับการใช้งาน กรุณาติดต่อแอดมิน",
    }

    def __init__(self, *args, **kwargs):
        super().__init__(*args, **kwargs)
        self.fields["username"].label = "รหัสพนักงาน"
        self.fields["username"].widget.attrs.update(
            {"class": "input", "placeholder": "เช่น EK-00123", "autocomplete": "username", "autofocus": True}
        )
        self.fields["password"].label = "รหัสผ่าน"
        self.fields["password"].widget.attrs.update(
            {"class": "input", "placeholder": "กรอกรหัสผ่าน", "autocomplete": "current-password", "id": "password"}
        )


class ProfileForm(forms.ModelForm):
    """ข้อมูลส่วนตัวที่ผู้ใช้แก้เองได้ — ตำแหน่งแก้ไม่ได้ ต้องให้แอดมินเปลี่ยนให้"""

    class Meta:
        model = User
        fields = ["first_name", "last_name", "email", "phone", "avatar"]
        labels = {
            "first_name": "ชื่อ",
            "last_name": "นามสกุล",
            "email": "อีเมล",
            "phone": "เบอร์โทร",
            "avatar": "รูปโปรไฟล์",
        }
        widgets = {
            "first_name": forms.TextInput(attrs={"class": "input"}),
            "last_name": forms.TextInput(attrs={"class": "input"}),
            "email": forms.EmailInput(attrs={"class": "input", "placeholder": "ไม่บังคับ"}),
            "phone": forms.TextInput(attrs={"class": "input", "placeholder": "08x-xxx-xxxx"}),
            "avatar": forms.ClearableFileInput(attrs={"class": "input", "accept": "image/*"}),
        }


class VerifyIdentityForm(forms.Form):
    """
    ยืนยันตัวตนด้วยรหัสผ่านเดิม
    ตามสเปก: ผจก.สาขา ต้องยืนยันตัวตนทุกครั้งก่อนกดอนุมัติ
    """

    password = forms.CharField(
        label="รหัสผ่านของคุณ",
        widget=forms.PasswordInput(attrs={"class": "input", "placeholder": "กรอกรหัสผ่านเพื่อยืนยัน"}),
    )

    def __init__(self, *args, user=None, **kwargs):
        self.user = user
        super().__init__(*args, **kwargs)

    def clean_password(self):
        pw = self.cleaned_data["password"]
        if not self.user or not self.user.check_password(pw):
            raise forms.ValidationError("รหัสผ่านไม่ถูกต้อง")
        return pw
