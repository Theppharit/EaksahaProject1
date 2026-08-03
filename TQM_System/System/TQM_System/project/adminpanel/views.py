"""
หน้าของแอดมิน — ซัพพอร์ต 4 ระบบ · สิทธิ์และรหัสผ่าน · อัปโหลดเอกสาร
------------------------------------------------------------
"ซัพพอร์ตระบบ X" = แอดมินเข้าไป "ใช้งานระบบของผู้ใช้คนนั้นจริง ๆ"
(โหมดสวมสิทธิ์) ไม่ใช่แค่เปิดดูสถิติ — เพราะปัญหาที่ผู้ใช้เจอส่วนใหญ่
ต้องกดตามเองถึงจะเห็น กติกาความปลอดภัยอยู่ใน accounts/middleware.py
"""

from django.conf import settings
from django.contrib import messages
from django.contrib.auth.decorators import login_required
from django.core.exceptions import PermissionDenied
from django.db.models import Count, Q
from django.shortcuts import get_object_or_404, redirect, render
from django.utils import timezone

from project.accounts.middleware import LOG_KEY, SESSION_KEY
from project.accounts.models import Role, RoleChangeRequest, RoleChangeStatus, User
from project.core.models import ImpersonationLog, Notification, SystemDoc
from project.jobs.models import Booking, BookingStatus

from .forms import DocForm

SUPPORT_PAGES = {
    "shr": (Role.SHR, "ซัพพอร์ตระบบผู้จัดการ"),
    "requester": (Role.REQUESTER, "ซัพพอร์ตระบบผู้จองคิว"),
    "trailer": (Role.TRAILER, "ซัพพอร์ตระบบคนขับรถสไลด์"),
    "accounting": (Role.ACCOUNTING, "ซัพพอร์ตระบบงานบัญชี"),
}


def _admin_only(user):
    if not (user.is_superuser or user.role == Role.ADMIN):
        raise PermissionDenied("เฉพาะแอดมินเท่านั้น")


# ============================================================
# ซัพพอร์ตระบบของแต่ละตำแหน่ง
# ============================================================
def _support(request, key):
    _admin_only(request.user)
    role, title = SUPPORT_PAGES[key]

    users = (
        User.objects.filter(role=role)
        .annotate(jobs=Count("bookings_created", distinct=True))
        .order_by("-is_active", "username")
    )

    related = Booking.objects.with_related()
    if role == Role.REQUESTER:
        related = related.filter(created_by__role=role)
    elif role == Role.TRAILER:
        related = related.filter(truck_owner__isnull=False)
    elif role == Role.SHR:
        related = related.filter(status=BookingStatus.PENDING_APPROVAL)
    elif role == Role.ACCOUNTING:
        related = related.filter(
            status__in=[BookingStatus.SHR_APPROVED, BookingStatus.ACCOUNTING_REVIEW]
        )

    return render(request, "adminpanel/support.html", {
        "page_title": title,
        "role_key": key,
        "role_label": dict(Role.choices)[role],
        "users": users,
        "bookings": related[:20],
        "stuck": related.filter(
            Q(status=BookingStatus.PENDING_ACCEPT) | Q(status=BookingStatus.PENDING_APPROVAL)
        ).count(),
        "recent_logs": ImpersonationLog.objects.select_related("admin", "target").filter(
            target__role=role
        )[:5],
    })


@login_required
def support_shr(request):
    return _support(request, "shr")


@login_required
def support_requester(request):
    return _support(request, "requester")


@login_required
def support_trailer(request):
    return _support(request, "trailer")


@login_required
def support_accounting(request):
    return _support(request, "accounting")


# ============================================================
# โหมดสวมสิทธิ์ — เข้าใช้งานระบบแทนผู้ใช้
# ============================================================
@login_required
def impersonate_start(request, pk):
    """เริ่มโหมดสวมสิทธิ์ — ใช้ POST เท่านั้น กันโดนหลอกให้กดลิงก์"""
    _admin_only(request.user)
    if request.method != "POST":
        return redirect("accounts:dashboard")

    target = get_object_or_404(User, pk=pk, is_active=True)
    if target.is_superuser or target.role == Role.ADMIN:
        messages.error(request, "สวมสิทธิ์บัญชีแอดมินด้วยกันไม่ได้")
        return redirect(request.POST.get("next") or "accounts:dashboard")

    log = ImpersonationLog.objects.create(admin=request.user, target=target)
    request.session[SESSION_KEY] = target.pk
    request.session[LOG_KEY] = log.pk

    messages.warning(
        request,
        f"คุณกำลังใช้งานระบบในนามของ {target.get_full_name() or target.username} "
        f"({target.get_role_display()}) — ทุกการกดจะมีผลจริง",
    )
    return redirect(target.dashboard_url_name)


@login_required
def impersonate_stop(request):
    """
    ออกจากโหมดสวมสิทธิ์
    จงใจไม่เช็ค _admin_only เพราะตอนนี้ request.user คือผู้ใช้ปลายทางไปแล้ว
    ตัวจริงอยู่ที่ request.impersonator
    """
    admin = getattr(request, "impersonator", None)
    log_id = request.session.pop(LOG_KEY, None)
    request.session.pop(SESSION_KEY, None)

    if log_id:
        ImpersonationLog.objects.filter(pk=log_id, ended_at__isnull=True).update(
            ended_at=timezone.now()
        )

    if admin is None:
        return redirect("accounts:dashboard")

    messages.success(request, "ออกจากโหมดซัพพอร์ตแล้ว กลับมาเป็นบัญชีของคุณเรียบร้อย")
    return redirect("accounts:dashboard")


# ============================================================
# สิทธิ์และรหัสผ่าน
# ============================================================
@login_required
def permissions(request):
    """
    เปลี่ยนสิทธิ์ = ส่ง "คำขอ" ให้เจ้าของบัญชีกดยอมรับก่อน สิทธิ์ถึงจะเปลี่ยนจริง
    และรีเซตรหัสผ่านกลับค่าเริ่มต้นได้
    """
    _admin_only(request.user)

    if request.method == "POST":
        action = request.POST.get("action")
        target = get_object_or_404(User, pk=request.POST.get("user_id"))

        if action == "change_role":
            _propose_role_change(request, target)
        elif action == "reset_password":
            _reset_password(request, target)
        elif action == "toggle_active":
            _toggle_active(request, target)

        return redirect("adminpanel:permissions")

    return render(request, "adminpanel/permissions.html", {
        "users": User.objects.all().order_by("role", "username"),
        "roles": Role.choices,
        "pending": RoleChangeRequest.objects.filter(
            status=RoleChangeStatus.PENDING
        ).select_related("user", "requested_by"),
        "history": RoleChangeRequest.objects.exclude(
            status=RoleChangeStatus.PENDING
        ).select_related("user", "requested_by")[:20],
        "default_password": settings.DEFAULT_USER_PASSWORD,
    })


def _propose_role_change(request, target):
    new_role = request.POST.get("role", "")
    if new_role not in dict(Role.choices):
        messages.error(request, "ไม่รู้จักตำแหน่งนี้")
        return
    if new_role == target.role:
        messages.info(request, f"{target.username} อยู่ตำแหน่งนี้อยู่แล้ว")
        return
    if target.pk == request.user.pk:
        messages.error(request, "เปลี่ยนตำแหน่งของตัวเองไม่ได้ ให้แอดมินคนอื่นเป็นคนเปลี่ยนให้")
        return

    # มีคำขอค้างอยู่แล้วให้ยกเลิกของเดิมก่อน จะได้ไม่มี 2 ใบซ้อนกัน
    RoleChangeRequest.objects.filter(
        user=target, status=RoleChangeStatus.PENDING
    ).update(status=RoleChangeStatus.CANCELLED, decided_at=timezone.now())

    req = RoleChangeRequest.objects.create(
        user=target,
        from_role=target.role,
        to_role=new_role,
        reason=request.POST.get("reason", "")[:300],
        requested_by=request.user,
    )

    Notification.push(
        [target],
        title="มีการขอเปลี่ยนสิทธิ์การเข้าถึงของคุณ",
        body=f"จาก “{req.get_from_role_display()}” เป็น “{req.get_to_role_display()}” — "
             f"กรุณากดยอมรับหรือปฏิเสธ",
        link="/notifications/",
        kind=Notification.Kind.PERMISSION,
        need_ack=True,
    )
    messages.success(
        request,
        f"ส่งคำขอเปลี่ยนตำแหน่งให้ {target.username} แล้ว — "
        f"สิทธิ์จะเปลี่ยนจริงเมื่อเจ้าของบัญชีกดยอมรับ",
    )


def _reset_password(request, target):
    if target.is_superuser and not request.user.is_superuser:
        messages.error(request, "รีเซตรหัสผ่านของ superuser ไม่ได้")
        return

    target.set_password(settings.DEFAULT_USER_PASSWORD)
    target.save(update_fields=["password"])

    Notification.push(
        [target],
        title="รหัสผ่านของคุณถูกรีเซต",
        body="แอดมินรีเซตรหัสผ่านของคุณกลับเป็นค่าเริ่มต้นแล้ว "
             "กรุณาเข้าระบบแล้วเปลี่ยนรหัสผ่านใหม่ทันที",
        kind=Notification.Kind.PERMISSION,
    )
    messages.success(
        request,
        f"รีเซตรหัสผ่านของ {target.username} เป็น “{settings.DEFAULT_USER_PASSWORD}” แล้ว — "
        f"แจ้งให้เจ้าตัวเปลี่ยนทันทีที่เข้าระบบ",
    )


def _toggle_active(request, target):
    if target.pk == request.user.pk:
        messages.error(request, "ปิดการใช้งานบัญชีตัวเองไม่ได้")
        return
    target.is_active = not target.is_active
    target.save(update_fields=["is_active"])
    messages.success(
        request,
        f"{'เปิด' if target.is_active else 'ปิด'}การใช้งานบัญชี {target.username} แล้ว",
    )


# ============================================================
# เอกสารการใช้งานระบบ
# ============================================================
@login_required
def doc_upload(request):
    """อัปโหลดเอกสารการใช้งานระบบ + กำหนดว่าตำแหน่งไหนเห็น"""
    _admin_only(request.user)

    form = DocForm(request.POST or None, request.FILES or None)
    if request.method == "POST" and form.is_valid():
        obj = form.save(commit=False)
        obj.uploaded_by = request.user
        obj.visible_roles = form.cleaned_data["roles"]
        obj.save()
        messages.success(request, "อัปโหลดเอกสารเรียบร้อย")
        return redirect("adminpanel:doc_upload")

    return render(request, "adminpanel/doc_upload.html", {
        "form": form,
        "docs": SystemDoc.objects.select_related("uploaded_by"),
    })
