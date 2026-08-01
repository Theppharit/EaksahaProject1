"""
ตรรกะธุรกิจของใบจอง
------------------------------------------------------------
กฎ "ใครกดอะไรได้ตอนไหน" อยู่ที่ไฟล์นี้ที่เดียว
view มีหน้าที่แค่รับ request แล้วเรียกฟังก์ชันในนี้

ทำแบบนี้เพราะกฎเดียวกันต้องใช้ทั้งจากหน้าเว็บ หน้า admin
และ API ในอนาคต — เขียนที่เดียวจะได้ไม่หลุดกัน
"""

from django.core.exceptions import PermissionDenied, ValidationError
from django.db import transaction

from project.accounts.models import Role

from .models import Booking, BookingStatus, JobStep, JobStepCode

# ============================================================
# ใครกดเปลี่ยนสถานะอะไรได้บ้าง
# ------------------------------------------------------------
# key   = สถานะปลายทาง
# value = ตำแหน่งที่มีสิทธิ์กด
# ============================================================
ACTION_PERMISSIONS = {
    BookingStatus.ACCEPTED: [Role.TRAILER],
    BookingStatus.PENDING_APPROVAL: [Role.TRAILER],
    BookingStatus.SHR_APPROVED: [Role.SHR],
    BookingStatus.ACCOUNTING_REVIEW: [Role.SHR, Role.ACCOUNTING],
    BookingStatus.APPROVED: [Role.ACCOUNTING],
    BookingStatus.IN_PROGRESS: [Role.TRAILER],
    BookingStatus.CLOSED: [Role.TRAILER],
    BookingStatus.CANCELLED: [Role.REQUESTER, Role.SHR, Role.ADMIN],
}

# ข้อความบนปุ่ม + สีปุ่ม สำหรับแต่ละสถานะปลายทาง
ACTION_LABELS = {
    BookingStatus.ACCEPTED: ("รับงาน", "primary"),
    BookingStatus.PENDING_APPROVAL: ("ส่งขออนุมัติ", "primary"),
    BookingStatus.SHR_APPROVED: ("อนุมัติ (ผจก.)", "primary"),
    BookingStatus.ACCOUNTING_REVIEW: ("ส่งให้บัญชีตรวจสอบ", "primary"),
    BookingStatus.APPROVED: ("อนุมัติใบจอง", "primary"),
    BookingStatus.IN_PROGRESS: ("เริ่มดำเนินงาน", "primary"),
    BookingStatus.CLOSED: ("ปิดงาน", "primary"),
    BookingStatus.CANCELLED: ("ยกเลิกงาน", "danger"),
}

# การกระทำที่ถือว่า "ตีกลับ" — ปุ่มจะเป็นสีอ่อนและบังคับใส่หมายเหตุ
SEND_BACK = {
    (BookingStatus.PENDING_APPROVAL, BookingStatus.ACCEPTED): "ตีกลับให้แก้ไข",
    (BookingStatus.ACCOUNTING_REVIEW, BookingStatus.SHR_APPROVED): "ตีกลับให้ ผจก.",
}


def available_actions(booking, user):
    """
    ปุ่มที่ผู้ใช้คนนี้กดได้กับใบจองใบนี้ ณ สถานะปัจจุบัน
    ใช้ทั้งตอนแสดงปุ่มบนหน้าจอ และตอนตรวจสิทธิ์ก่อนทำจริง
    """
    from .models import ALLOWED_TRANSITIONS

    actions = []
    for target in ALLOWED_TRANSITIONS.get(booking.status, []):
        allowed_roles = ACTION_PERMISSIONS.get(target, [])
        if not (user.is_superuser or user.role in allowed_roles):
            continue

        # เจ้าของรถกดได้เฉพาะงานของตัวเอง (superuser ข้ามได้)
        if (
            user.role == Role.TRAILER
            and not user.is_superuser
            and booking.truck_owner
            and booking.truck_owner.user_id != user.id
        ):
            continue

        send_back_label = SEND_BACK.get((booking.status, target))
        label, style = ACTION_LABELS.get(target, (str(target), "outline"))
        actions.append(
            {
                "target": target,
                "label": send_back_label or label,
                "style": "outline" if send_back_label else style,
                "need_note": bool(send_back_label) or target == BookingStatus.CANCELLED,
            }
        )
    return actions


@transaction.atomic
def perform_action(booking, user, target_status, note=""):
    """
    เปลี่ยนสถานะจริง — ตรวจสิทธิ์ซ้ำอีกชั้นเสมอ
    ⚠ ห้ามเชื่อค่าที่ส่งมาจากฟอร์ม ผู้ใช้แก้ HTML แล้วยิงเองได้
    """
    allowed = {a["target"] for a in available_actions(booking, user)}
    if target_status not in allowed:
        raise PermissionDenied("คุณไม่มีสิทธิ์ทำรายการนี้ หรือสถานะเปลี่ยนไปแล้ว")

    if target_status == BookingStatus.CANCELLED and not note.strip():
        raise ValidationError("กรุณาระบุเหตุผลที่ยกเลิก")

    if (booking.status, target_status) in SEND_BACK and not note.strip():
        raise ValidationError("การตีกลับต้องระบุหมายเหตุเสมอ")

    booking.transition_to(target_status, by=user, note=note.strip())

    # เริ่มดำเนินงาน → สร้าง 5 ขั้นตอนรอไว้ให้คนขับกดทีละขั้น
    if target_status == BookingStatus.IN_PROGRESS:
        _ensure_job_steps(booking)

    # ปิดงาน → ปล่อยคนขับกลับเป็นสถานะว่าง
    if target_status == BookingStatus.CLOSED and booking.driver_id:
        booking.driver.is_available = True
        booking.driver.save(update_fields=["is_available"])

    return booking


def _ensure_job_steps(booking):
    """สร้างขั้นตอนการทำงาน 5 ขั้นให้ครบ (รันซ้ำได้ ไม่สร้างซ้ำ)"""
    existing = set(booking.steps.values_list("code", flat=True))
    JobStep.objects.bulk_create(
        [JobStep(booking=booking, code=code) for code in JobStepCode.values if code not in existing]
    )


def accept_booking(booking, user, truck, driver, note=""):
    """
    เจ้าของรถกดรับงาน — ผูกรถและคนขับเข้ากับใบจอง
    แล้วล็อกคนขับเป็นไม่ว่างจนกว่าจะปิดงาน
    """
    if not driver.can_take_job:
        raise ValidationError("คนขับคนนี้ไม่ว่าง หรือยังไม่ผ่านการอนุมัติ")

    booking.truck_owner = truck.owner
    booking.truck = truck
    booking.driver = driver
    booking.save(update_fields=["truck_owner", "truck", "driver"])

    perform_action(booking, user, BookingStatus.ACCEPTED, note)

    driver.is_available = False
    driver.save(update_fields=["is_available"])
    return booking


def dashboard_stats(user):
    """ตัวเลขสรุป 4 ช่องบนหัวแดชบอร์ด — คำนวณจากใบจองที่ผู้ใช้เห็นได้เท่านั้น"""
    qs = Booking.objects.for_user(user)
    return {
        "open": qs.open().count(),
        "waiting": qs.filter(
            status__in=[
                BookingStatus.PENDING_ACCEPT,
                BookingStatus.PENDING_APPROVAL,
                BookingStatus.ACCOUNTING_REVIEW,
            ]
        ).count(),
        "in_progress": qs.filter(status=BookingStatus.IN_PROGRESS).count(),
        "closed": qs.filter(status=BookingStatus.CLOSED).count(),
    }

# ============================================================


# ============================================================
# 5 ขั้นตอนการดำเนินงานของคนขับ
# ============================================================
def complete_step(booking, user, code, note="", photos=None):
    """
    ทำขั้นตอนหนึ่งให้เสร็จ — ต้องทำเรียงลำดับ ข้ามไม่ได้
    (สเปกกำหนดว่าต้องถ่ายรูปครบก่อนถึงจะไปขั้นถัดไปได้)
    """
    from django.utils import timezone

    from .models import JobPhoto, JobStep, JobStepCode

    if booking.truck_owner and booking.truck_owner.user_id != user.id and not user.is_superuser:
        raise PermissionDenied("งานนี้ไม่ใช่ของคุณ")

    order = list(JobStepCode.values)
    if code not in order:
        raise ValidationError("ไม่รู้จักขั้นตอนนี้")

    steps = {s.code: s for s in booking.steps.all()}
    idx = order.index(code)

    for prev in order[:idx]:
        if not (prev in steps and steps[prev].is_done):
            raise ValidationError("ต้องทำขั้นตอนก่อนหน้าให้เสร็จก่อน")

    step = steps.get(code) or JobStep.objects.create(booking=booking, code=code)
    step.is_done = True
    step.done_at = timezone.now()
    step.done_by = user
    step.note = note[:300]
    step.save()

    for f in photos or []:
        JobPhoto.objects.create(step=step, image=f)

    return step
