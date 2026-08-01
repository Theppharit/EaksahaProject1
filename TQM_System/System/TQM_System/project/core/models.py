"""
ของกลางที่ทุกตำแหน่งใช้ร่วมกัน
------------------------------------------------------------
  Notification  การแจ้งเตือน (แสดงตามโรลของผู้ใช้)
  SystemDoc     เอกสารการใช้งานระบบ (แอดมินกำหนดว่าโรลไหนเห็น)
"""

from django.conf import settings
from django.db import models

from project.accounts.models import Role


class Notification(models.Model):
    """
    แจ้งเตือนรายบุคคล
    สร้างจาก services เวลาเกิดเหตุการณ์ เช่น มีงานใหม่รอรับ / ใบจองถูกตีกลับ
    """

    class Kind(models.TextChoices):
        JOB = "job", "งาน"
        APPROVAL = "approval", "การอนุมัติ"
        PERMISSION = "permission", "สิทธิ์การเข้าถึง"
        SYSTEM = "system", "ระบบ"

    user = models.ForeignKey(
        settings.AUTH_USER_MODEL,
        verbose_name="ผู้รับ",
        on_delete=models.CASCADE,
        related_name="notifications",
    )
    kind = models.CharField("ประเภท", max_length=20, choices=Kind.choices, default=Kind.SYSTEM)
    title = models.CharField("หัวข้อ", max_length=200)
    body = models.CharField("รายละเอียด", max_length=500, blank=True)
    link = models.CharField("ลิงก์ปลายทาง", max_length=200, blank=True)

    is_read = models.BooleanField("อ่านแล้ว", default=False, db_index=True)
    # ใช้กับการแจ้งเตือนที่ต้องกดยอมรับ เช่น เปลี่ยนสิทธิ์การเข้าถึง
    need_ack = models.BooleanField("ต้องกดยืนยัน", default=False)
    acked_at = models.DateTimeField("ยืนยันเมื่อ", null=True, blank=True)

    created_at = models.DateTimeField("เมื่อ", auto_now_add=True, db_index=True)

    class Meta:
        verbose_name = "การแจ้งเตือน"
        verbose_name_plural = "การแจ้งเตือน"
        ordering = ["-created_at"]
        indexes = [models.Index(fields=["user", "is_read"])]

    def __str__(self):
        return f"{self.user} — {self.title}"

    @classmethod
    def push(cls, users, title, body="", link="", kind=Kind.SYSTEM, need_ack=False):
        """ส่งแจ้งเตือนให้ผู้ใช้หลายคนพร้อมกันด้วย query เดียว"""
        users = [u for u in users if u]
        return cls.objects.bulk_create(
            [
                cls(user=u, title=title, body=body, link=link, kind=kind, need_ack=need_ack)
                for u in users
            ]
        )


class SystemDoc(models.Model):
    """
    คู่มือ/เอกสารการใช้งานระบบ
    แอดมินอัปโหลดแล้วติ๊กว่าตำแหน่งไหนเห็นได้บ้าง
    """

    title = models.CharField("ชื่อเอกสาร", max_length=200)
    description = models.CharField("คำอธิบาย", max_length=300, blank=True)
    file = models.FileField("ไฟล์", upload_to="docs/%Y/%m/")

    # เก็บเป็นรายการรหัสตำแหน่ง เช่น ["requester", "sales"]
    visible_roles = models.JSONField(
        "ตำแหน่งที่มองเห็น", default=list, help_text="ว่างไว้ = ทุกตำแหน่งเห็น"
    )

    uploaded_by = models.ForeignKey(
        settings.AUTH_USER_MODEL,
        verbose_name="ผู้อัปโหลด",
        on_delete=models.PROTECT,
        related_name="docs_uploaded",
    )
    created_at = models.DateTimeField("อัปโหลดเมื่อ", auto_now_add=True)

    class Meta:
        verbose_name = "เอกสารการใช้งานระบบ"
        verbose_name_plural = "เอกสารการใช้งานระบบ"
        ordering = ["-created_at"]

    def __str__(self):
        return self.title

    def visible_to(self, user):
        return (not self.visible_roles) or user.role in self.visible_roles or user.is_superuser

    @property
    def role_labels(self):
        if not self.visible_roles:
            return "ทุกตำแหน่ง"
        names = dict(Role.choices)
        return " · ".join(names.get(r, r) for r in self.visible_roles)
