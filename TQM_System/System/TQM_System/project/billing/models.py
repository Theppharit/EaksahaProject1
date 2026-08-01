"""
การเงิน — เกณฑ์ราคา · รอบจ่ายเงิน · ใบวางบิล
------------------------------------------------------------
ตามสเปก:
  ผจก.สาขา  ตั้งเรทค่าเดินทาง และตั้งรอบวันจ่ายเงิน
  งานบัญชี   ออกเลขบิลรายคนขับ/รอบเดือน → ได้เลข PO เมื่อจ่ายแล้ว
"""

from django.conf import settings
from django.db import models

from project.fleet.models import TruckOwner
from project.jobs.models import Booking


class PriceRate(models.Model):
    """
    เกณฑ์ค่าเดินทาง — ผจก.สาขาเป็นคนตั้ง
    เก็บเป็นหลายแถวโดยมีวันเริ่มใช้ เพื่อให้ย้อนดูได้ว่างานเดือนก่อน
    คิดราคาด้วยเรทไหน (สำคัญเวลาลูกค้าทักท้วงย้อนหลัง)
    """

    name = models.CharField("ชื่อเกณฑ์", max_length=100)
    base_price = models.DecimalField("ค่าบริการพื้นฐาน (บาท)", max_digits=10, decimal_places=2)
    included_km = models.DecimalField(
        "ระยะทางที่รวมในราคาพื้นฐาน (กม.)", max_digits=6, decimal_places=1, default=0
    )
    price_per_km = models.DecimalField(
        "ค่าเดินทางส่วนเกิน (บาท/กม.)", max_digits=8, decimal_places=2, default=0
    )
    effective_from = models.DateField("เริ่มใช้วันที่", db_index=True)
    is_active = models.BooleanField("ใช้งานอยู่", default=True)

    created_by = models.ForeignKey(
        settings.AUTH_USER_MODEL,
        verbose_name="ผู้ตั้งค่า",
        on_delete=models.PROTECT,
        related_name="price_rates",
    )
    created_at = models.DateTimeField("สร้างเมื่อ", auto_now_add=True)

    class Meta:
        verbose_name = "เกณฑ์ราคา"
        verbose_name_plural = "เกณฑ์ราคา"
        ordering = ["-effective_from"]

    def __str__(self):
        return f"{self.name} (เริ่ม {self.effective_from})"

    def calculate(self, distance_km):
        """คำนวณค่าบริการจากระยะทาง"""
        if not distance_km:
            return self.base_price
        extra = max(distance_km - self.included_km, 0)
        return self.base_price + (extra * self.price_per_km)


class PaymentCycle(models.Model):
    """
    รอบจ่ายเงิน — ผจก.สาขาตั้งว่าจ่ายเดือนละกี่ครั้ง วันไหน
    เช่น เดือนละ 2 ครั้ง วันที่ 15 และวันสิ้นเดือน
    """

    name = models.CharField("ชื่อรอบ", max_length=100)
    times_per_month = models.PositiveSmallIntegerField(
        "จ่ายกี่ครั้งต่อเดือน", default=1, choices=[(1, "เดือนละ 1 ครั้ง"), (2, "เดือนละ 2 ครั้ง")]
    )
    pay_day_1 = models.PositiveSmallIntegerField("วันจ่ายครั้งที่ 1", default=30)
    pay_day_2 = models.PositiveSmallIntegerField(
        "วันจ่ายครั้งที่ 2", null=True, blank=True, help_text="กรอกเฉพาะเมื่อจ่ายเดือนละ 2 ครั้ง"
    )
    is_active = models.BooleanField("ใช้งานอยู่", default=True)

    class Meta:
        verbose_name = "รอบจ่ายเงิน"
        verbose_name_plural = "รอบจ่ายเงิน"

    def __str__(self):
        return self.name


class BillStatus(models.TextChoices):
    DRAFT = "draft", "ร่าง"
    ISSUED = "issued", "ออกบิลแล้ว"
    PAID = "paid", "จ่ายแล้ว"
    CANCELLED = "cancelled", "ยกเลิก"


class Bill(models.Model):
    """
    ใบวางบิลของเจ้าของรถ 1 ราย ต่อ 1 รอบ
    งานบัญชีเป็นคนออก — เมื่อจ่ายเงินแล้วจะได้เลข PO กลับมาบันทึก
    """

    bill_no = models.CharField("เลขที่บิล", max_length=20, unique=True, db_index=True)
    po_no = models.CharField(
        "เลข PO", max_length=30, blank=True, db_index=True, help_text="ได้มาหลังชำระเงินผ่านระบบบริษัท"
    )

    truck_owner = models.ForeignKey(
        TruckOwner, verbose_name="เจ้าของรถ", on_delete=models.PROTECT, related_name="bills"
    )
    period_start = models.DateField("รอบวันที่")
    period_end = models.DateField("ถึงวันที่")

    total_amount = models.DecimalField("ยอดรวม (บาท)", max_digits=12, decimal_places=2, default=0)

    status = models.CharField(
        "สถานะ", max_length=10, choices=BillStatus.choices, default=BillStatus.DRAFT, db_index=True
    )
    issued_by = models.ForeignKey(
        settings.AUTH_USER_MODEL,
        verbose_name="ผู้ออกบิล",
        on_delete=models.PROTECT,
        related_name="bills_issued",
    )
    issued_at = models.DateTimeField("ออกบิลเมื่อ", null=True, blank=True)
    paid_at = models.DateTimeField("จ่ายเมื่อ", null=True, blank=True)
    note = models.CharField("หมายเหตุ", max_length=300, blank=True)

    created_at = models.DateTimeField("สร้างเมื่อ", auto_now_add=True)

    class Meta:
        verbose_name = "ใบวางบิล"
        verbose_name_plural = "ใบวางบิล"
        ordering = ["-period_end", "-id"]

    def __str__(self):
        return f"{self.bill_no} · {self.truck_owner.name}"

    def recalculate_total(self, save=True):
        """รวมยอดจากรายการย่อยใหม่ทั้งหมด"""
        total = sum(item.amount for item in self.items.all())
        self.total_amount = total
        if save:
            self.save(update_fields=["total_amount"])
        return total


class BillItem(models.Model):
    """
    1 บรรทัดในใบวางบิล = 1 งานที่ปิดแล้ว
    ผูกกับ Booking แบบ OneToOne กันงานเดียวถูกวางบิลซ้ำสองใบ
    """

    bill = models.ForeignKey(
        Bill, verbose_name="ใบวางบิล", on_delete=models.CASCADE, related_name="items"
    )
    booking = models.OneToOneField(
        Booking, verbose_name="งาน", on_delete=models.PROTECT, related_name="bill_item"
    )
    amount = models.DecimalField("จำนวนเงิน (บาท)", max_digits=10, decimal_places=2)
    note = models.CharField("หมายเหตุ", max_length=200, blank=True)

    class Meta:
        verbose_name = "รายการในบิล"
        verbose_name_plural = "รายการในบิล"
        ordering = ["booking__appointment_at"]

    def __str__(self):
        return f"{self.booking.booking_no} — {self.amount:,.2f}"
