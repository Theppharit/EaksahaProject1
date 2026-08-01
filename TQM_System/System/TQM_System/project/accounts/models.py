"""
ผู้ใช้งานระบบ และ 7 ตำแหน่ง
------------------------------------------------------------
Django อนุญาตให้เปลี่ยน User model ได้ก่อน migrate ครั้งแรกเท่านั้น
ที่เพิ่มรอบนี้: avatar (รูปโปรไฟล์) · id_verified (ยืนยันตัวตนแล้ว)
"""

from django.contrib.auth.models import AbstractUser
from django.core.validators import RegexValidator
from django.db import models

BRAND_COLOR = "#ED1D26"


class Role(models.TextChoices):
    REQUESTER = "requester", "ผู้จองคิว"
    SALES = "sales", "เซลล์"
    TRAILER = "trailer", "เจ้าของรถสไลด์"
    SHR = "shr", "ผู้จัดการสาขา"
    ACCOUNTING = "accounting", "งานบัญชี"
    EXECUTIVE = "executive", "ผู้บริหาร"
    ADMIN = "admin", "แอดมิน"


class User(AbstractUser):
    """ใช้ช่อง username เก็บ 'รหัสพนักงาน' (เช่น EK-00123)"""

    role = models.CharField(
        "ตำแหน่ง", max_length=20, choices=Role.choices, default=Role.REQUESTER, db_index=True
    )
    phone = models.CharField("เบอร์โทร", max_length=20, blank=True)
    avatar = models.ImageField("รูปโปรไฟล์", upload_to="avatars/", blank=True)
    id_verified = models.BooleanField(
        "ยืนยันตัวตนแล้ว", default=False, help_text="แอดมินติ๊กหลังตรวจเอกสารยืนยันตัวตน"
    )

    class Meta:
        verbose_name = "ผู้ใช้งาน"
        verbose_name_plural = "ผู้ใช้งาน"
        ordering = ["username"]

    def __str__(self):
        return f"{self.get_full_name() or self.username} ({self.get_role_display()})"

    @property
    def dashboard_url_name(self):
        return f"accounts:dashboard_{self.role}"

    @property
    def initials(self):
        name = self.get_full_name() or self.username
        return name.strip()[:2].upper()


HEX_COLOR = RegexValidator(r"^#([0-9A-Fa-f]{6})$", "ต้องเป็นรหัสสีแบบ #RRGGBB เช่น #ED1D26")


class RoleTheme(models.Model):
    """สีหลักประจำตำแหน่ง — แอดมินแก้เองได้จาก /admin/ เห็นผลทันทีไม่ต้อง build ใหม่"""

    role = models.CharField("ตำแหน่ง", max_length=20, choices=Role.choices, unique=True)
    accent_color = models.CharField(
        "สีหลักประจำตำแหน่ง", max_length=7, default=BRAND_COLOR, validators=[HEX_COLOR],
        help_text=f"รหัสสีแบบ #RRGGBB — ค่าเริ่มต้นคือสีแบรนด์ {BRAND_COLOR}",
    )

    class Meta:
        verbose_name = "สีประจำตำแหน่ง"
        verbose_name_plural = "สีประจำตำแหน่ง"
        ordering = ["role"]

    def __str__(self):
        return f"{self.get_role_display()} — {self.accent_color}"

    @classmethod
    def color_for(cls, role):
        return (
            cls.objects.filter(role=role).values_list("accent_color", flat=True).first()
            or BRAND_COLOR
        )
