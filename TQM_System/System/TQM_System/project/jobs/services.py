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
from django.utils import timezone

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


# ============================================================
# คำขอแก้ไข / คำขอยกเลิก
# ------------------------------------------------------------
# ผู้จองคิวและเจ้าของรถไม่มีสิทธิ์แก้หรือยกเลิกใบจองเอง
# ทั้งคู่ "ยื่นคำขอ" แล้ว ผจก.สาขา (หรือแอดมิน) เป็นคนตัดสิน
# ทำแบบนี้เพราะใบจอง 1 ใบมีคนเกี่ยวข้องหลายฝ่าย
# ถ้าปล่อยให้ฝ่ายใดฝ่ายหนึ่งแก้เองเงียบ ๆ อีกฝ่ายจะทำงานผิด
# ============================================================
DECIDER_ROLES = [Role.SHR, Role.ADMIN]

# สถานะที่ถือว่า "ผจก.อนุมัติแล้ว" — เจ้าของรถถึงจะเห็นปุ่มขอแก้ไข/ยกเลิก
AFTER_SHR_APPROVED = [
    BookingStatus.SHR_APPROVED,
    BookingStatus.ACCOUNTING_REVIEW,
    BookingStatus.APPROVED,
    BookingStatus.IN_PROGRESS,
]

# สถานะที่แก้ใบจองไม่ได้แล้ว
LOCKED_FOR_EDIT = [BookingStatus.IN_PROGRESS, BookingStatus.CLOSED, BookingStatus.CANCELLED]


def can_request(booking, user):
    """ผู้ใช้คนนี้ยื่นคำขอแก้ไข/ยกเลิกกับใบนี้ได้ไหม"""
    from .models import RequestStatus

    if booking.status in (BookingStatus.CLOSED, BookingStatus.CANCELLED):
        return False
    if pending_request(booking):
        return False  # มีคำขอค้างอยู่แล้ว ไม่ให้ยื่นซ้อน

    if user.role == Role.REQUESTER:
        return booking.created_by_id == user.id

    if user.role == Role.TRAILER:
        # สเปก: เจ้าของรถเห็นปุ่มนี้ "หลังจาก ผจก.อนุมัติ" เท่านั้น
        return (
            booking.truck_owner is not None
            and booking.truck_owner.user_id == user.id
            and booking.status in AFTER_SHR_APPROVED
        )

    return False


def pending_request(booking):
    """
    คำขอที่ยังรอพิจารณาของใบนี้ (ถ้ามี) — ใช้โชว์ป้ายในตาราง

    จงใจวนใน Python ไม่ใช่ .filter() เพราะหน้าตารางใช้ prefetch_related("requests")
    มาแล้ว ถ้าใช้ .filter() จะยิง query ใหม่ทุกแถว (N+1)
    """
    from .models import RequestStatus

    for req in booking.requests.all():
        if req.status == RequestStatus.PENDING:
            return req
    return None


@transaction.atomic
def create_request(booking, user, kind, reason):
    """ยื่นคำขอ — ตรวจสิทธิ์ฝั่งเซิร์ฟเวอร์เสมอ ไม่เชื่อค่าจากฟอร์ม"""
    from .models import BookingRequest, RequestKind

    if kind not in RequestKind.values:
        raise ValidationError("ไม่รู้จักประเภทคำขอนี้")
    if not reason.strip():
        raise ValidationError("กรุณาระบุเหตุผลของคำขอ")
    if not can_request(booking, user):
        raise PermissionDenied("คุณยื่นคำขอกับใบจองนี้ไม่ได้ หรือมีคำขอค้างอยู่แล้ว")

    return BookingRequest.objects.create(
        booking=booking, kind=kind, reason=reason.strip()[:1000], created_by=user
    )


@transaction.atomic
def decide_request(req, user, approve, note=""):
    """
    ผจก./แอดมิน ตัดสินคำขอ
      อนุมัติคำขอยกเลิก → ยกเลิกใบจองให้เลย (มี log ครบ)
      อนุมัติคำขอแก้ไข  → ปลดล็อกให้ผู้ยื่นเข้าไปแก้ได้ 1 ครั้ง
    """
    from .models import RequestKind, RequestStatus

    if not (user.is_superuser or user.role in DECIDER_ROLES):
        raise PermissionDenied("เฉพาะผู้จัดการสาขาหรือแอดมินเท่านั้น")
    if not req.is_pending:
        raise ValidationError("คำขอนี้ถูกพิจารณาไปแล้ว")
    if not approve and not note.strip():
        raise ValidationError("การไม่อนุมัติต้องระบุเหตุผลเสมอ")

    req.status = RequestStatus.APPROVED if approve else RequestStatus.REJECTED
    req.decided_by = user
    req.decided_at = timezone.now()
    req.decision_note = note.strip()[:300]
    req.save()

    if approve and req.kind == RequestKind.CANCEL:
        booking = req.booking
        if booking.can_transition_to(BookingStatus.CANCELLED):
            booking.transition_to(
                BookingStatus.CANCELLED,
                by=user,
                note=f"อนุมัติคำขอยกเลิกของ {req.created_by.get_full_name() or req.created_by.username}: {req.reason}"[:300],
            )
            req.status = RequestStatus.DONE
            req.save(update_fields=["status"])

    return req


def can_edit_booking(booking, user):
    """แก้ใบจองใบนี้ได้ไหม — ผจก./แอดมินแก้ได้เลย คนอื่นต้องมีคำขอที่อนุมัติแล้ว"""
    from .models import RequestKind, RequestStatus

    if booking.status in LOCKED_FOR_EDIT:
        return False
    if user.is_superuser or user.role in DECIDER_ROLES:
        return True

    is_owner_side = booking.created_by_id == user.id or (
        booking.truck_owner is not None and booking.truck_owner.user_id == user.id
    )
    return is_owner_side and booking.requests.filter(
        kind=RequestKind.EDIT, status=RequestStatus.APPROVED
    ).exists()


def consume_edit_request(booking):
    """แก้เสร็จแล้วปิดคำขอ — กันเอาสิทธิ์เดิมกลับมาแก้ซ้ำเรื่อย ๆ"""
    from .models import RequestKind, RequestStatus

    booking.requests.filter(kind=RequestKind.EDIT, status=RequestStatus.APPROVED).update(
        status=RequestStatus.DONE
    )


def open_requests_for(user):
    """คำขอที่รอผู้ใช้คนนี้พิจารณา — ใช้บนแดชบอร์ด ผจก./แอดมิน"""
    from .models import BookingRequest, RequestStatus

    if not (user.is_superuser or user.role in DECIDER_ROLES):
        return BookingRequest.objects.none()
    return (
        BookingRequest.objects.filter(status=RequestStatus.PENDING)
        .select_related("booking", "booking__customer", "created_by")
        .order_by("created_at")
    )


# ============================================================
# ตัวเลขเฉพาะแดชบอร์ดเจ้าของรถสไลด์
# ============================================================
def trailer_summary(user):
    """
    ยอดเงินที่ต้องได้รับของงวดเดือนนี้ + สถิติงาน
    นับจาก "ค่าบริการรวมของงานที่ปิดในเดือนนี้" ตามที่ตกลงกันไว้
    → ตัวเลขขยับทันทีที่กดปิดงานสำเร็จ ไม่ต้องรอบัญชีออกบิล
    """
    from datetime import datetime, time

    from .models import Booking

    owner = getattr(user, "truck_owner", None)
    today = timezone.localdate()
    month_start = today.replace(day=1)
    tz = timezone.get_current_timezone()
    lo = timezone.make_aware(datetime.combine(month_start, time.min), tz)

    blank = {
        "earning": "0",
        "earning_jobs": 0,
        "in_progress": 0,
        "closed": 0,
        "cancelled": 0,
        "month_label": f"{month_start:%m}/{month_start.year + 543}",
        "has_owner": False,
    }
    if owner is None:
        return blank

    mine = Booking.objects.filter(truck_owner=owner)
    closed = mine.filter(status=BookingStatus.CLOSED, closed_at__gte=lo)

    total = sum((b.price or 0) + (b.extra_charge or 0) for b in closed)
    return {
        "earning": f"{total:,.0f}",
        "earning_jobs": closed.count(),
        "in_progress": mine.filter(status=BookingStatus.IN_PROGRESS).count(),
        "closed": closed.count(),
        "cancelled": mine.filter(status=BookingStatus.CANCELLED, updated_at__gte=lo).count(),
        "month_label": blank["month_label"],
        "has_owner": True,
    }


def trailer_alerts(user):
    """
    2 กล่องแจ้งเตือนบนสุดของเจ้าของรถ (โชว์เมื่อตรงเงื่อนไขเท่านั้น)
      start_jobs → ถึงวันนัดแล้วและอนุมัติใบจองแล้ว = กดเริ่มดำเนินงานได้
      fill_jobs  → กดรับงานแล้วแต่ยังไม่ได้ส่งขออนุมัติ
    """
    from datetime import datetime, time

    from .models import Booking

    owner = getattr(user, "truck_owner", None)
    if owner is None:
        return {"start_jobs": [], "fill_jobs": []}

    tz = timezone.get_current_timezone()
    end_today = timezone.make_aware(datetime.combine(timezone.localdate(), time.max), tz)
    mine = Booking.objects.filter(truck_owner=owner).select_related("customer", "vehicle")

    return {
        "start_jobs": list(
            mine.filter(status=BookingStatus.APPROVED, appointment_at__lte=end_today).order_by(
                "appointment_at"
            )[:3]
        ),
        "fill_jobs": list(
            mine.filter(status=BookingStatus.ACCEPTED).order_by("appointment_at")[:3]
        ),
    }


def available_jobs():
    """คิวงานที่ยังไม่มีใครกดรับ — เจ้าของรถทุกเจ้าเห็นเหมือนกัน"""
    from .models import Booking

    return (
        Booking.objects.filter(
            status=BookingStatus.PENDING_ACCEPT, truck_owner__isnull=True
        )
        .select_related("customer", "vehicle", "created_by")
        .order_by("appointment_at")
    )


# ============================================================
# หน้ารออนุมัติ — ใช้ร่วมกันระหว่าง ผจก.สาขา และงานบัญชี
# ------------------------------------------------------------
# ทั้งสองตำแหน่งทำงานเหมือนกันมาก (ดูใบ → อนุมัติ หรือ ตีกลับพร้อมหมายเหตุ)
# ต่างกันแค่ "ดูสถานะไหน" กับ "อนุมัติแล้วไปสถานะอะไร"
# → เก็บความต่างไว้ใน dict เดียว แล้วใช้ view/template ชุดเดียวกัน
# ============================================================
APPROVAL_INBOX = {
    Role.SHR: {
        "title": "รายการรออนุมัติ",
        "subtitle": "ตรวจข้อมูลคนขับ รถ และใบจอง ก่อนกดอนุมัติ",
        "statuses": [BookingStatus.PENDING_APPROVAL],
        "show_requests": True,   # ผจก.เป็นคนตัดสินคำขอแก้ไข/ยกเลิกด้วย
    },
    Role.ACCOUNTING: {
        "title": "รออนุมัติ",
        "subtitle": "ตรวจสอบและอนุมัติใบจอง เพื่อให้การนัดหมายไปขึ้นในปฏิทินของผู้เกี่ยวข้อง",
        "statuses": [BookingStatus.SHR_APPROVED, BookingStatus.ACCOUNTING_REVIEW],
        "show_requests": False,
    },
    Role.ADMIN: {
        "title": "รายการรออนุมัติ",
        "subtitle": "ดูแลแทนผู้จัดการสาขาได้",
        "statuses": [BookingStatus.PENDING_APPROVAL],
        "show_requests": True,
    },
}


def approval_config(user):
    """ตั้งค่าหน้ารออนุมัติของตำแหน่งนี้ — ไม่มีสิทธิ์จะได้ None"""
    if user.is_superuser and user.role not in APPROVAL_INBOX:
        return APPROVAL_INBOX[Role.SHR]
    return APPROVAL_INBOX.get(user.role)


def approval_actions(booking, user):
    """
    แยกปุ่มบนใบจองออกเป็น 3 กลุ่ม เพื่อให้ template วางตำแหน่งได้ถูก
      approve   เดินหน้าไปขั้นถัดไป
      send_back ตีกลับให้แก้ (บังคับใส่หมายเหตุ)
      cancel    ยกเลิกงาน
    """
    result = {"approve": None, "send_back": None, "cancel": None}
    for action in available_actions(booking, user):
        if action["target"] == BookingStatus.CANCELLED:
            result["cancel"] = action
        elif (booking.status, action["target"]) in SEND_BACK:
            result["send_back"] = action
        else:
            result["approve"] = action
    return result


def verify_password(user, raw_password):
    """
    ยืนยันตัวตนก่อนอนุมัติ (สเปก: "เมื่อกดอนุมัติจะต้องยืนยันตัวตนเสมอ")

    ตรวจทุกครั้งที่กด ไม่เก็บสถานะ "ยืนยันแล้ว" ไว้ใน session
    เพราะใบจองแต่ละใบคือการตัดสินใจคนละครั้ง ไม่ควรยืนยันครั้งเดียวแล้วรูดยาว
    """
    if not raw_password:
        raise ValidationError("กรุณากรอกรหัสผ่านเพื่อยืนยันตัวตนก่อนอนุมัติ")
    if not user.check_password(raw_password):
        raise ValidationError("รหัสผ่านไม่ถูกต้อง — ยังไม่ได้บันทึกการอนุมัติ")
    return True
