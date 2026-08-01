"""
สร้างข้อมูลตั้งต้นสำหรับทดสอบ
------------------------------------------------------------
รัน:  python manage.py seed_roles

จะได้:
  1. แถวสีประจำตำแหน่งครบ 7 ตำแหน่ง (ค่าเริ่มต้น = สีแบรนด์ #ED1D26)
  2. บัญชีทดสอบ 7 บัญชี รหัสผ่าน tqm12345 เหมือนกันหมด

รันซ้ำได้ ไม่สร้างซ้ำ และไม่ทับรหัสผ่านที่เปลี่ยนไปแล้ว
"""

from django.contrib.auth import get_user_model
from django.core.management.base import BaseCommand
from django.db import transaction

from project.accounts.models import BRAND_COLOR, Role, RoleTheme

User = get_user_model()

TEST_PASSWORD = "tqm12345"

DEMO_USERS = [
    ("req01", Role.REQUESTER, "สมชาย", "ใจดี"),
    ("sale01", Role.SALES, "กมล", "ศรีสุข"),
    ("trail01", Role.TRAILER, "ประยุทธ์", "ทรัพย์เจริญ"),
    ("shr01", Role.SHR, "วีระ", "พงษ์ศักดิ์"),
    ("acc01", Role.ACCOUNTING, "นภาพร", "บัญชีดี"),
    ("ceo01", Role.EXECUTIVE, "เอกสหะ", "วงศ์ไพศาล"),
    ("admin01", Role.ADMIN, "ธีรพงศ์", "ระบบดี"),
]


class Command(BaseCommand):
    help = "สร้างสีประจำตำแหน่ง และบัญชีทดสอบครบ 7 ตำแหน่ง"

    @transaction.atomic
    def handle(self, *args, **options):
        # ── 1. สีประจำตำแหน่ง ──────────────────────
        created_themes = 0
        for role in Role:
            _, created = RoleTheme.objects.get_or_create(
                role=role, defaults={"accent_color": BRAND_COLOR}
            )
            created_themes += int(created)
        self.stdout.write(f"สีประจำตำแหน่ง: เพิ่มใหม่ {created_themes} / ทั้งหมด {len(Role)}")

        # ── 2. บัญชีทดสอบ ─────────────────────────
        self.stdout.write("")
        self.stdout.write(f"{'รหัสพนักงาน':<12} {'ตำแหน่ง':<16} สถานะ")
        self.stdout.write("-" * 46)

        for username, role, first, last in DEMO_USERS:
            user, created = User.objects.get_or_create(
                username=username,
                defaults={
                    "role": role,
                    "first_name": first,
                    "last_name": last,
                    "is_staff": role == Role.ADMIN,
                },
            )
            if created:
                user.set_password(TEST_PASSWORD)
                user.save(update_fields=["password"])
                status = self.style.SUCCESS("สร้างใหม่")
            else:
                status = "มีอยู่แล้ว"
            self.stdout.write(f"{username:<12} {Role(role).label:<16} {status}")

        self.stdout.write("")
        self.stdout.write(self.style.WARNING(f"รหัสผ่านทุกบัญชี: {TEST_PASSWORD}"))
        self.stdout.write(
            self.style.WARNING("⚠ บัญชีชุดนี้สำหรับทดสอบเท่านั้น ห้ามใช้บนเซิร์ฟจริง")
        )
