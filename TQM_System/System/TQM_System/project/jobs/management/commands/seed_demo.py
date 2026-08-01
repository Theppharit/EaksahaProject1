"""
สร้างข้อมูลตัวอย่างสำหรับทดสอบระบบ
------------------------------------------------------------
รัน:  python manage.py seed_demo

จะได้: ลูกค้า 5 ราย · รถของลูกค้า · เจ้าของรถ 2 ราย · รถ 5 คัน
       คนขับ 5 คน · ใบจอง 8 ใบ กระจายทุกสถานะ

รันซ้ำได้ ไม่สร้างซ้ำ
⚠ ห้ามรันบนเซิร์ฟจริง — เป็นข้อมูลสมมติทั้งหมด
"""

from datetime import timedelta

from django.core.management.base import BaseCommand
from django.db import transaction
from django.utils import timezone

from project.accounts.models import Role, User
from project.customers.models import Customer, CustomerType, CustomerVehicle
from project.fleet.models import Driver, DriverApproval, Truck, TruckOwner, TruckType
from project.jobs.models import Booking, BookingStatus

CUSTOMERS = [
    ("C-001", "บจก. เอกสหะ ทรานสปอร์ต", CustomerType.COMPANY, "044-234-412", "โตโยต้า", "วีออส", "1กก-4471 นม"),
    ("C-002", "คุณสมหญิง รักดี", CustomerType.PERSON, "089-221-1187", "ฮอนด้า", "ซิตี้", "2ขข-1120 นม"),
    ("C-003", "หจก. โคราชยานยนต์", CustomerType.COMPANY, "044-220-330", "อีซูซุ", "ดีแมคซ์", "3คค-0092 นม"),
    ("C-004", "บจก. ไทยรุ่งเรือง", CustomerType.COMPANY, "044-771-900", "ฟอร์ด", "เรนเจอร์", "4งง-7734 นม"),
    ("C-005", "คุณประสิทธิ์ มั่งมี", CustomerType.PERSON, "081-990-2244", "ฮุนได", "H1", "5จจ-1188 นม"),
]

OWNERS = [
    ("trail01", "ประยุทธ์ ขนส่ง", "081-455-7788", [("82-4471 นม", TruckType.SLIDE), ("82-1120 นม", TruckType.SLIDE), ("83-0092 นม", TruckType.LIFT)]),
]

DRIVERS = [
    ("อนุชา กิตติศักดิ์", "086-111-2233", False),
    ("ประเสริฐ มั่นคง", "086-222-3344", False),
    ("สมพงษ์ ทองดี", "086-333-4455", False),
    ("วิรัตน์ พูนผล", "086-444-5566", True),
    ("ธนกร สุขใจ", "086-555-6677", True),
]

# (จำนวนวันจากวันนี้, สถานะปลายทาง, จุดรับ, จุดส่ง, ราคา)
JOBS = [
    (1, BookingStatus.PENDING_ACCEPT, "ถ.มิตรภาพ กม.12 อ.เมือง", "ศูนย์บริการโคราช", 2800),
    (2, BookingStatus.PENDING_ACCEPT, "บ้านเกาะ อ.เมือง", "อู่ช่างเอก ถ.สุรนารายณ์", 3200),
    (2, BookingStatus.ACCEPTED, "นิคมฯ สุรนารี", "คลังสินค้า A ปากช่อง", 8600),
    (3, BookingStatus.PENDING_APPROVAL, "ปากช่อง ถ.ธนะรัชต์", "ศูนย์บริการโคราช", 5400),
    (4, BookingStatus.SHR_APPROVED, "โชคชัย อ.โชคชัย", "อู่สมศักดิ์ อ.เมือง", 4200),
    (5, BookingStatus.ACCOUNTING_REVIEW, "พิมาย อ.พิมาย", "ศูนย์บริการโคราช", 6100),
    (6, BookingStatus.IN_PROGRESS, "บัวใหญ่ อ.บัวใหญ่", "โชว์รูม EV โคราช", 7300),
    (-8, BookingStatus.CLOSED, "สีคิ้ว อ.สีคิ้ว", "อู่ยนต์การช่าง", 3900),
]


class Command(BaseCommand):
    help = "สร้างข้อมูลตัวอย่างสำหรับทดสอบ (ห้ามใช้บนเซิร์ฟจริง)"

    @transaction.atomic
    def handle(self, *args, **options):
        requester = User.objects.filter(role=Role.REQUESTER).first()
        sales = User.objects.filter(role=Role.SALES).first()
        trailer_user = User.objects.filter(role=Role.TRAILER).first()
        shr = User.objects.filter(role=Role.SHR).first()
        acc = User.objects.filter(role=Role.ACCOUNTING).first()

        if not all([requester, trailer_user, shr, acc]):
            self.stderr.write(self.style.ERROR("ยังไม่มีบัญชีทดสอบ — รัน python manage.py seed_roles ก่อน"))
            return

        # ── ลูกค้า + รถของลูกค้า ─────────────────
        vehicles = []
        for code, name, ctype, phone, brand, model, plate in CUSTOMERS:
            cust, _ = Customer.objects.get_or_create(
                code=code, defaults={"name": name, "type": ctype, "phone": phone}
            )
            veh, _ = CustomerVehicle.objects.get_or_create(
                customer=cust, plate=plate, defaults={"brand": brand, "model": model}
            )
            vehicles.append((cust, veh))
        self.stdout.write(f"ลูกค้า: {Customer.objects.count()} ราย")

        # ── เจ้าของรถ + รถ + คนขับ ──────────────
        owner, _ = TruckOwner.objects.get_or_create(
            user=trailer_user,
            defaults={"name": "ประยุทธ์ ขนส่ง", "phone": "081-455-7788"},
        )
        trucks = []
        for plate, ttype in OWNERS[0][3]:
            t, _ = Truck.objects.get_or_create(plate=plate, defaults={"owner": owner, "type": ttype})
            trucks.append(t)

        drivers = []
        for i, (name, phone, is_backup) in enumerate(DRIVERS):
            d, _ = Driver.objects.get_or_create(
                owner=owner, name=name,
                defaults={
                    "phone": phone, "is_backup": is_backup,
                    "truck": trucks[i % len(trucks)],
                    "approval_status": DriverApproval.APPROVED,
                },
            )
            drivers.append(d)
        self.stdout.write(f"เจ้าของรถ 1 ราย · รถ {len(trucks)} คัน · คนขับ {len(drivers)} คน")

        # ── ใบจอง ───────────────────────────────
        # ลำดับสถานะที่ต้องเดินผ่านเพื่อไปถึงปลายทาง
        PATH = [
            BookingStatus.ACCEPTED, BookingStatus.PENDING_APPROVAL,
            BookingStatus.SHR_APPROVED, BookingStatus.ACCOUNTING_REVIEW,
            BookingStatus.APPROVED, BookingStatus.IN_PROGRESS, BookingStatus.CLOSED,
        ]
        ACTOR = {
            BookingStatus.ACCEPTED: trailer_user, BookingStatus.PENDING_APPROVAL: trailer_user,
            BookingStatus.SHR_APPROVED: shr, BookingStatus.ACCOUNTING_REVIEW: shr,
            BookingStatus.APPROVED: acc, BookingStatus.IN_PROGRESS: trailer_user,
            BookingStatus.CLOSED: trailer_user,
        }

        created = 0
        for i, (days, target, pickup, dropoff, price) in enumerate(JOBS):
            cust, veh = vehicles[i % len(vehicles)]
            when = timezone.now() + timedelta(days=days, hours=2)

            if Booking.objects.filter(customer=cust, appointment_at=when).exists():
                continue

            b = Booking.objects.create(
                customer=cust, vehicle=veh, created_by=requester,
                sales=sales if i % 2 == 0 else None,
                appointment_at=when, pickup_address=pickup, dropoff_address=dropoff,
                distance_km=20 + i * 7, price=price, note="ข้อมูลตัวอย่างสำหรับทดสอบระบบ",
            )
            # เดินสถานะทีละขั้นจนถึงปลายทาง
            if target != BookingStatus.PENDING_ACCEPT:
                b.truck_owner, b.truck, b.driver = owner, trucks[i % len(trucks)], drivers[i % len(drivers)]
                b.save(update_fields=["truck_owner", "truck", "driver"])
                for step in PATH:
                    b.transition_to(step, by=ACTOR[step], note="ระบบสร้างข้อมูลตัวอย่าง")
                    if step == target:
                        break
            created += 1

        self.stdout.write(self.style.SUCCESS(f"\nสร้างใบจองใหม่ {created} ใบ (ทั้งหมด {Booking.objects.count()} ใบ)"))
        self.stdout.write(self.style.WARNING("⚠ ข้อมูลชุดนี้สำหรับทดสอบเท่านั้น ห้ามใช้บนเซิร์ฟจริง"))
