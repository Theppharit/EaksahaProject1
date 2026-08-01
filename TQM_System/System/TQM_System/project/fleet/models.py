"""
กองรถ — เจ้าของรถสไลด์ · รถ · คนขับ
------------------------------------------------------------
โครงความสัมพันธ์:
    TruckOwner (เจ้าของรถ)  1 ──< N  Truck (รถ)
                            1 ──< N  Driver (คนขับ)
    Driver  N ──> 1  Truck            (คนขับรับผิดชอบรถคันไหน)

เจ้าของรถ 1 คน = ผู้ใช้ 1 บัญชี (role = trailer)
"""

from django.conf import settings
from django.db import models


class TruckOwner(models.Model):
    """เจ้าของรถสไลด์ — ผูกกับบัญชีผู้ใช้ role=trailer หนึ่งบัญชี"""

    user = models.OneToOneField(
        settings.AUTH_USER_MODEL,
        verbose_name="บัญชีผู้ใช้",
        on_delete=models.PROTECT,
        related_name="truck_owner",
    )
    name = models.CharField("ชื่อผู้ประกอบการ", max_length=200)
    phone = models.CharField("เบอร์โทร", max_length=20)
    tax_id = models.CharField("เลขประจำตัวผู้เสียภาษี", max_length=13, blank=True)
    bank_account = models.CharField("เลขบัญชีธนาคาร", max_length=30, blank=True)
    bank_name = models.CharField("ธนาคาร", max_length=50, blank=True)
    is_active = models.BooleanField("ใช้งานอยู่", default=True)

    class Meta:
        verbose_name = "เจ้าของรถสไลด์"
        verbose_name_plural = "เจ้าของรถสไลด์"
        ordering = ["name"]

    def __str__(self):
        return self.name


class TruckType(models.TextChoices):
    SLIDE = "slide", "รถสไลด์"
    LIFT = "lift", "รถยก"


class Truck(models.Model):
    owner = models.ForeignKey(
        TruckOwner, verbose_name="เจ้าของ", on_delete=models.PROTECT, related_name="trucks"
    )
    plate = models.CharField("ทะเบียนรถ", max_length=20, unique=True, db_index=True)
    type = models.CharField(
        "ประเภท", max_length=10, choices=TruckType.choices, default=TruckType.SLIDE
    )
    brand = models.CharField("ยี่ห้อ", max_length=50, blank=True)
    capacity_kg = models.PositiveIntegerField("น้ำหนักบรรทุก (กก.)", null=True, blank=True)

    insurance_expiry = models.DateField("ประกันหมดอายุ", null=True, blank=True)
    tax_expiry = models.DateField("ภาษีหมดอายุ", null=True, blank=True)

    is_active = models.BooleanField("พร้อมใช้งาน", default=True)
    note = models.CharField("หมายเหตุ", max_length=200, blank=True)

    class Meta:
        verbose_name = "รถ"
        verbose_name_plural = "รถ"
        ordering = ["plate"]

    def __str__(self):
        return f"{self.plate} ({self.get_type_display()})"


class DriverApproval(models.TextChoices):
    PENDING = "pending", "รออนุมัติ"
    APPROVED = "approved", "อนุมัติ"
    REJECTED = "rejected", "ไม่อนุมัติ"


class Driver(models.Model):
    """
    คนขับ — มีทั้งคนขับหลักและคนขับเสริม
    สถานะว่าง/ไม่ว่าง เจ้าของรถกดเปลี่ยนเอง
    แต่พอรับงานแล้วจะถูกล็อกเป็น "ไม่ว่าง" จนกว่าจะปิดงาน
    """

    owner = models.ForeignKey(
        TruckOwner, verbose_name="สังกัดเจ้าของรถ", on_delete=models.CASCADE, related_name="drivers"
    )
    truck = models.ForeignKey(
        Truck,
        verbose_name="รถที่รับผิดชอบ",
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name="drivers",
    )

    name = models.CharField("ชื่อ-นามสกุล", max_length=150, db_index=True)
    phone = models.CharField("เบอร์โทร", max_length=20)
    national_id = models.CharField("เลขบัตรประชาชน", max_length=13, blank=True)
    license_no = models.CharField("เลขใบขับขี่", max_length=30, blank=True)
    license_expiry = models.DateField("ใบขับขี่หมดอายุ", null=True, blank=True)
    photo = models.ImageField("รูปโปรไฟล์", upload_to="drivers/", blank=True)

    is_backup = models.BooleanField(
        "เป็นคนขับเสริม", default=False, help_text="คนขับหลักไม่ต้องติ๊ก"
    )
    is_available = models.BooleanField("สถานะว่าง", default=True)
    approval_status = models.CharField(
        "สถานะอนุมัติ",
        max_length=10,
        choices=DriverApproval.choices,
        default=DriverApproval.PENDING,
    )

    created_at = models.DateTimeField("เพิ่มเมื่อ", auto_now_add=True)

    class Meta:
        verbose_name = "คนขับ"
        verbose_name_plural = "คนขับ"
        ordering = ["owner", "-is_available", "name"]
        indexes = [models.Index(fields=["owner", "is_available"])]

    def __str__(self):
        tag = " (เสริม)" if self.is_backup else ""
        return f"{self.name}{tag}"

    @property
    def can_take_job(self):
        """รับงานได้ก็ต่อเมื่อว่าง และผ่านการอนุมัติแล้ว"""
        return self.is_available and self.approval_status == DriverApproval.APPROVED
