"""ฟอร์มเข้าสู่ระบบ — ปรับ label/placeholder ให้เป็นภาษาไทย และใส่ class ของเรา"""

from django.contrib.auth.forms import AuthenticationForm


class EmployeeLoginForm(AuthenticationForm):
    """
    ใช้ AuthenticationForm ของ Django ตรง ๆ
    (ตรวจรหัสผ่าน จำกัดจำนวนครั้ง กันบัญชีที่ถูกปิด ให้ครบแล้ว)
    เราแค่เปลี่ยนหน้าตาช่องกรอก
    """

    error_messages = {
        **AuthenticationForm.error_messages,
        "invalid_login": "รหัสพนักงานหรือรหัสผ่านไม่ถูกต้อง",
        "inactive": "บัญชีนี้ถูกระงับการใช้งาน กรุณาติดต่อแอดมิน",
    }

    def __init__(self, *args, **kwargs):
        super().__init__(*args, **kwargs)

        self.fields["username"].label = "รหัสพนักงาน"
        self.fields["username"].widget.attrs.update(
            {
                "class": "input",
                "placeholder": "เช่น EK-00123",
                "autocomplete": "username",
                "autofocus": True,
            }
        )

        self.fields["password"].label = "รหัสผ่าน"
        self.fields["password"].widget.attrs.update(
            {
                "class": "input",
                "placeholder": "กรอกรหัสผ่าน",
                "autocomplete": "current-password",
                "id": "password",
            }
        )
