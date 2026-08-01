"""
ลูกค้า — เจ้าของรถที่เราไปรับ-ส่ง
------------------------------------------------------------
เก็บเป็นตารางถาวร เพื่อให้จองซ้ำได้โดยไม่ต้องกรอกใหม่
และทำรายงาน "ลูกค้ารายไหนใช้บริการบ่อย" ได้
"""

from django.db import models


class CustomerType(models.TextChoices):
    PERSON = "person", "บุคคลธรรมดา"
    COMPANY = "company", "นิติบุคคล"


class Customer(models.Model):
    code = models.CharField("รหัสลูกค้า", max_length=20, unique=True, db_index=True)
    type = models.CharField(
        "ประเภท", max_length=10, choices=CustomerType.choices, default=CustomerType.PERSON
    )
    name = models.CharField("ชื่อลูกค้า / ชื่อบริษัท", max_length=200, db_index=True)
    phone = models.CharField("เบอร์โทร", max_length=20, db_index=True)
    phone_alt = models.CharField("เบอร์สำรอง", max_length=20, blank=True)
    email = models.EmailField("อีเมล", blank=True)

    tax_id = models.CharField("เลขประจำตัวผู้เสียภาษี", max_length=13, blank=True)
    address = models.TextField("ที่อยู่", blank=True)

    note = models.TextField("หมายเหตุ", blank=True)
    is_active = models.BooleanField("ใช้งานอยู่", default=True)

    created_at = models.DateTimeField("สร้างเมื่อ", auto_now_add=True)
    updated_at = models.DateTimeField("แก้ไขล่าสุด", auto_now=True)

    class Meta:
        verbose_name = "ลูกค้า"
        verbose_name_plural = "ลูกค้า"
        ordering = ["name"]

    def __str__(self):
        return f"{self.name} ({self.phone})"


class CustomerVehicle(models.Model):
    """
    รถของลูกค้า — คันที่เราต้องขนย้าย
    แยกตารางเพราะลูกค้า 1 รายอาจมีหลายคัน (โดยเฉพาะนิติบุคคล)
    และช่วยให้จองซ้ำคันเดิมได้เร็ว
    """

    customer = models.ForeignKey(
        Customer, verbose_name="ลูกค้า", on_delete=models.CASCADE, related_name="vehicles"
    )
    brand = models.CharField("ยี่ห้อ", max_length=50)
    model = models.CharField("รุ่น", max_length=50, blank=True)
    plate = models.CharField("ทะเบียน", max_length=20, db_index=True)
    color = models.CharField("สี", max_length=30, blank=True)
    note = models.CharField("หมายเหตุ", max_length=200, blank=True)

    class Meta:
        verbose_name = "รถของลูกค้า"
        verbose_name_plural = "รถของลูกค้า"
        ordering = ["plate"]

    def __str__(self):
        return f"{self.brand} {self.model} · {self.plate}".strip()
