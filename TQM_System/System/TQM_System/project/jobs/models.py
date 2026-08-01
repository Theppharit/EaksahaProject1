"""
ใบจองคิว — แกนกลางของทั้งระบบ
------------------------------------------------------------
ทุกโรลวนอยู่รอบตาราง Booking ตารางเดียว ต่างกันแค่
"เห็นใบไหนได้บ้าง" และ "เปลี่ยนสถานะอะไรได้บ้าง"

สายสถานะตามเอกสารที่ลูกค้าอนุมัติ:

  รอรับงาน ──▶ รับงานแล้ว ──▶ รออนุมัติ ──▶ ผจก.อนุมัติ
     │              │              │              │
     │              │              │              ▼
     │              │              │      งานบัญชีตรวจสอบ
     │              │              │              │
     │              │              │              ▼
     │              │              │        อนุมัติใบจอง
     │              │              │              │
     │              │              │              ▼
     │              │              │      กำลังดำเนินงาน ──▶ ปิดงาน
     ▼              ▼              ▼              ▼
   ยกเลิก ◀────────────────────────────────────────
"""

from django.conf import settings
from django.core.exceptions import ValidationError
from django.db import models
from django.utils import timezone

from project.customers.models import Customer, CustomerVehicle
from project.fleet.models import Driver, Truck, TruckOwner


class BookingStatus(models.TextChoices):
    PENDING_ACCEPT = "pending_accept", "รอรับงาน"
    ACCEPTED = "accepted", "รับงานแล้ว"
    PENDING_APPROVAL = "pending_approval", "รออนุมัติ"
    SHR_APPROVED = "shr_approved", "ผจก.อนุมัติ"
    ACCOUNTING_REVIEW = "accounting_review", "งานบัญชีตรวจสอบ"
    APPROVED = "approved", "อนุมัติใบจอง"
    IN_PROGRESS = "in_progress", "กำลังดำเนินงาน"
    CLOSED = "closed", "ปิดงาน"
    CANCELLED = "cancelled", "ยกเลิก"


# ============================================================
# กฎการเปลี่ยนสถานะ — แหล่งความจริงเดียวของทั้งระบบ
# ------------------------------------------------------------
# เก็บไว้ที่เดียวเพื่อให้ทุก view / admin / API ใช้กฎชุดเดียวกัน
# ถ้าลูกค้าขอเพิ่มขั้นตอน แก้ที่ dict นี้ที่เดียวจบ
# ============================================================
ALLOWED_TRANSITIONS = {
    BookingStatus.PENDING_ACCEPT: [BookingStatus.ACCEPTED, BookingStatus.CANCELLED],
    BookingStatus.ACCEPTED: [BookingStatus.PENDING_APPROVAL, BookingStatus.CANCELLED],
    BookingStatus.PENDING_APPROVAL: [
        BookingStatus.SHR_APPROVED,
        BookingStatus.ACCEPTED,  # ผจก.ตีกลับให้แก้
        BookingStatus.CANCELLED,
    ],
    BookingStatus.SHR_APPROVED: [BookingStatus.ACCOUNTING_REVIEW, BookingStatus.CANCELLED],
    BookingStatus.ACCOUNTING_REVIEW: [
        BookingStatus.APPROVED,
        BookingStatus.SHR_APPROVED,  # บัญชีตีกลับ
        BookingStatus.CANCELLED,
    ],
    BookingStatus.APPROVED: [BookingStatus.IN_PROGRESS, BookingStatus.CANCELLED],
    BookingStatus.IN_PROGRESS: [BookingStatus.CLOSED, BookingStatus.CANCELLED],
    BookingStatus.CLOSED: [],  # ปิดงานแล้วจบ
    BookingStatus.CANCELLED: [],
}

# สถานะที่ถือว่า "ยังไม่จบ" — ใช้กรองในแดชบอร์ดทุกโรล
OPEN_STATUSES = [
    s for s in BookingStatus.values if s not in (BookingStatus.CLOSED, BookingStatus.CANCELLED)
]


class BookingQuerySet(models.QuerySet):
    """
    query ที่ใช้ซ้ำทั้งระบบ ยกมาไว้ที่เดียว
    เรียกใช้: Booking.objects.open().for_user(request.user)
    """

    def open(self):
        """ใบที่ยังไม่ปิดงานและไม่ถูกยกเลิก — แดชบอร์ดทุกโรลใช้ตัวนี้"""
        return self.filter(status__in=OPEN_STATUSES)

    def with_related(self):
        """ดึงตารางที่เกี่ยวข้องมาพร้อมกัน — กันปัญหา N+1 query ในหน้าตาราง"""
        return self.select_related(
            "customer", "vehicle", "created_by", "sales", "truck", "driver", "truck_owner"
        )

    def for_user(self, user):
        """
        กรองตามสิทธิ์ของแต่ละตำแหน่ง (ตามเอกสารสเปก)
        ⚠ นี่คือด่านกันข้อมูลรั่วฝั่งเซิร์ฟเวอร์ ไม่ใช่แค่ซ่อนเมนู
        """
        from project.accounts.models import Role

        if user.is_superuser or user.role in (Role.ADMIN, Role.SHR, Role.EXECUTIVE):
            return self  # เห็นทุกใบ

        if user.role == Role.REQUESTER:
            return self.filter(created_by=user)  # เฉพาะใบที่ตัวเองสร้าง

        if user.role == Role.SALES:
            return self.filter(sales=user)  # เฉพาะใบที่ระบุชื่อตัวเอง

        if user.role == Role.TRAILER:
            return self.filter(truck_owner__user=user)  # เฉพาะงานของตัวเอง

        if user.role == Role.ACCOUNTING:
            # บัญชีเห็นตั้งแต่ ผจก.อนุมัติ เป็นต้นไป
            return self.filter(
                status__in=[
                    BookingStatus.SHR_APPROVED,
                    BookingStatus.ACCOUNTING_REVIEW,
                    BookingStatus.APPROVED,
                    BookingStatus.IN_PROGRESS,
                    BookingStatus.CLOSED,
                ]
            )

        return self.none()


class Booking(models.Model):
    """ใบจองคิว 1 ใบ = งานขนย้ายรถ 1 ครั้ง"""

    booking_no = models.CharField(
        "เลขที่ใบจอง", max_length=20, unique=True, db_index=True, blank=True
    )

    # ── ลูกค้าและรถที่จะขน ──────────────────────
    customer = models.ForeignKey(
        Customer, verbose_name="ลูกค้า", on_delete=models.PROTECT, related_name="bookings"
    )
    vehicle = models.ForeignKey(
        CustomerVehicle,
        verbose_name="รถที่ขนย้าย",
        on_delete=models.PROTECT,
        null=True,
        blank=True,
        related_name="bookings",
    )

    # ── ผู้เกี่ยวข้องฝั่งบริษัท ───────────────────
    created_by = models.ForeignKey(
        settings.AUTH_USER_MODEL,
        verbose_name="ผู้สร้างใบจอง",
        on_delete=models.PROTECT,
        related_name="bookings_created",
    )
    sales = models.ForeignKey(
        settings.AUTH_USER_MODEL,
        verbose_name="เซลล์ผู้ดูแล",
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name="bookings_as_sales",
        help_text="ระบุแล้วเซลล์คนนี้จะเห็นใบจองนี้ในระบบของตัวเอง",
    )

    # ── งานและการเดินทาง ───────────────────────
    appointment_at = models.DateTimeField("วันเวลานัดหมาย", db_index=True)
    pickup_address = models.TextField("จุดรับรถ")
    dropoff_address = models.TextField("จุดส่งรถ")
    distance_km = models.DecimalField(
        "ระยะทาง (กม.)", max_digits=7, decimal_places=1, null=True, blank=True
    )
    note = models.TextField("รายละเอียดเพิ่มเติม", blank=True)

    # ── ผู้รับงาน (ว่างจนกว่าเจ้าของรถจะกดรับ) ────
    truck_owner = models.ForeignKey(
        TruckOwner,
        verbose_name="เจ้าของรถผู้รับงาน",
        on_delete=models.PROTECT,
        null=True,
        blank=True,
        related_name="bookings",
    )
    truck = models.ForeignKey(
        Truck,
        verbose_name="รถที่ใช้",
        on_delete=models.PROTECT,
        null=True,
        blank=True,
        related_name="bookings",
    )
    driver = models.ForeignKey(
        Driver,
        verbose_name="คนขับ",
        on_delete=models.PROTECT,
        null=True,
        blank=True,
        related_name="bookings",
    )
    accepted_at = models.DateTimeField("รับงานเมื่อ", null=True, blank=True)

    # ── เงิน ───────────────────────────────────
    price = models.DecimalField("ค่าบริการ (บาท)", max_digits=10, decimal_places=2, default=0)
    extra_charge = models.DecimalField(
        "ค่าใช้จ่ายเพิ่มเติม", max_digits=10, decimal_places=2, default=0
    )

    # ── สถานะ ──────────────────────────────────
    status = models.CharField(
        "สถานะ",
        max_length=20,
        choices=BookingStatus.choices,
        default=BookingStatus.PENDING_ACCEPT,
        db_index=True,
    )
    closed_at = models.DateTimeField("ปิดงานเมื่อ", null=True, blank=True)
    cancelled_reason = models.CharField("เหตุผลที่ยกเลิก", max_length=300, blank=True)

    created_at = models.DateTimeField("สร้างเมื่อ", auto_now_add=True, db_index=True)
    updated_at = models.DateTimeField("แก้ไขล่าสุด", auto_now=True)

    objects = BookingQuerySet.as_manager()

    class Meta:
        verbose_name = "ใบจองคิว"
        verbose_name_plural = "ใบจองคิว"
        ordering = ["-created_at"]
        indexes = [
            models.Index(fields=["status", "-appointment_at"]),
            models.Index(fields=["truck_owner", "status"]),
        ]

    def __str__(self):
        return f"{self.booking_no} · {self.customer.name}"

    # ── ราคารวม ────────────────────────────────
    @property
    def total_price(self):
        return self.price + self.extra_charge

    # ── กฎการเปลี่ยนสถานะ ──────────────────────
    def can_transition_to(self, new_status):
        return new_status in ALLOWED_TRANSITIONS.get(self.status, [])

    def transition_to(self, new_status, by, note=""):
        """
        เปลี่ยนสถานะพร้อมบันทึกประวัติ — ห้ามแก้ฟิลด์ status ตรง ๆ ที่อื่น
        ให้เรียกผ่านเมธอดนี้เสมอ จะได้มี log ครบทุกครั้ง
        """
        if not self.can_transition_to(new_status):
            raise ValidationError(
                f"เปลี่ยนสถานะจาก “{self.get_status_display()}” "
                f"ไปเป็น “{BookingStatus(new_status).label}” ไม่ได้"
            )

        old = self.status
        self.status = new_status

        if new_status == BookingStatus.ACCEPTED and not self.accepted_at:
            self.accepted_at = timezone.now()
        if new_status == BookingStatus.CLOSED:
            self.closed_at = timezone.now()
        if new_status == BookingStatus.CANCELLED and note:
            self.cancelled_reason = note[:300]

        self.save()
        BookingStatusLog.objects.create(
            booking=self, from_status=old, to_status=new_status, by=by, note=note
        )
        return self

    # ── สร้างเลขที่ใบจองอัตโนมัติ TQM-YYMM-0001 ──
    def save(self, *args, **kwargs):
        if not self.booking_no:
            now = timezone.localtime()
            prefix = f"TQM-{now:%y%m}-"
            last = (
                Booking.objects.filter(booking_no__startswith=prefix)
                .order_by("-booking_no")
                .values_list("booking_no", flat=True)
                .first()
            )
            seq = int(last.rsplit("-", 1)[1]) + 1 if last else 1
            self.booking_no = f"{prefix}{seq:04d}"
        super().save(*args, **kwargs)


class BookingStatusLog(models.Model):
    """
    ประวัติการเปลี่ยนสถานะ — ใครกดอะไรตอนไหน เพราะอะไร
    จำเป็นเพราะสเปกกำหนดว่า "ตีกลับได้พร้อมระบุหมายเหตุ"
    และเป็นหลักฐานเวลามีข้อโต้แย้งกับลูกค้า
    """

    booking = models.ForeignKey(
        Booking, verbose_name="ใบจอง", on_delete=models.CASCADE, related_name="status_logs"
    )
    from_status = models.CharField("จากสถานะ", max_length=20, choices=BookingStatus.choices)
    to_status = models.CharField("เป็นสถานะ", max_length=20, choices=BookingStatus.choices)
    by = models.ForeignKey(
        settings.AUTH_USER_MODEL,
        verbose_name="ผู้ดำเนินการ",
        on_delete=models.PROTECT,
        related_name="booking_actions",
    )
    note = models.CharField("หมายเหตุ", max_length=300, blank=True)
    created_at = models.DateTimeField("เมื่อ", auto_now_add=True)

    class Meta:
        verbose_name = "ประวัติสถานะใบจอง"
        verbose_name_plural = "ประวัติสถานะใบจอง"
        ordering = ["-created_at"]

    def __str__(self):
        return f"{self.booking.booking_no}: {self.get_from_status_display()} → {self.get_to_status_display()}"


# ============================================================
# 5 ขั้นตอนการดำเนินงานของคนขับรถสไลด์ (ตามสเปกหน้า Trailer)
# ============================================================
class JobStepCode(models.TextChoices):
    PHOTO_BEFORE = "photo_before", "ถ่ายภาพรถก่อนขึ้นรถสไลด์"
    PHOTO_LOADED = "photo_loaded", "ถ่ายภาพหลังขึ้นรถสไลด์แล้ว"
    ON_THE_WAY = "on_the_way", "กำลังเดินทาง (แผนที่)"
    DELIVERED = "delivered", "ส่งมอบรถ + ถ่ายภาพหลังลงจากรถสไลด์"
    FINISHED = "finished", "ปิดงาน"


class JobStep(models.Model):
    booking = models.ForeignKey(
        Booking, verbose_name="ใบจอง", on_delete=models.CASCADE, related_name="steps"
    )
    code = models.CharField("ขั้นตอน", max_length=20, choices=JobStepCode.choices)
    is_done = models.BooleanField("ทำแล้ว", default=False)
    done_at = models.DateTimeField("ทำเมื่อ", null=True, blank=True)
    done_by = models.ForeignKey(
        settings.AUTH_USER_MODEL,
        verbose_name="ผู้ทำ",
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name="job_steps_done",
    )
    # ตำแหน่งตอนกดยืนยัน — ใช้ตรวจว่าอยู่หน้างานจริง
    latitude = models.DecimalField("ละติจูด", max_digits=9, decimal_places=6, null=True, blank=True)
    longitude = models.DecimalField(
        "ลองจิจูด", max_digits=9, decimal_places=6, null=True, blank=True
    )
    note = models.CharField("หมายเหตุ", max_length=300, blank=True)

    class Meta:
        verbose_name = "ขั้นตอนการดำเนินงาน"
        verbose_name_plural = "ขั้นตอนการดำเนินงาน"
        ordering = ["booking", "id"]
        constraints = [
            models.UniqueConstraint(fields=["booking", "code"], name="uniq_step_per_booking")
        ]

    def __str__(self):
        return f"{self.booking.booking_no} · {self.get_code_display()}"


class JobPhoto(models.Model):
    """รูปถ่ายประกอบแต่ละขั้นตอน — หลักฐานสภาพรถก่อน/หลัง"""

    step = models.ForeignKey(
        JobStep, verbose_name="ขั้นตอน", on_delete=models.CASCADE, related_name="photos"
    )
    image = models.ImageField("รูปภาพ", upload_to="jobs/%Y/%m/")
    angle = models.CharField(
        "มุมภาพ", max_length=50, blank=True, help_text="เช่น หน้า หลัง ซ้าย ขวา"
    )
    uploaded_at = models.DateTimeField("อัปโหลดเมื่อ", auto_now_add=True)

    class Meta:
        verbose_name = "รูปถ่ายงาน"
        verbose_name_plural = "รูปถ่ายงาน"
        ordering = ["step", "id"]

    def __str__(self):
        return f"{self.step} · {self.angle or 'รูป'}"
