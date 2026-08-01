"""
หน้าของแอดมิน — ซัพพอร์ต 4 ระบบ · สิทธิ์การเข้าถึง · อัปโหลดเอกสาร
------------------------------------------------------------
"ซัพพอร์ตระบบ X" ตามสเปกคือหน้าที่แอดมินเข้าไปดูสถานะการใช้งาน
ของตำแหน่งนั้น เพื่อช่วยแก้ปัญหาให้ผู้ใช้ได้
"""

from django.contrib import messages
from django.contrib.auth.decorators import login_required
from django.core.exceptions import PermissionDenied
from django.db.models import Count, Q
from django.shortcuts import get_object_or_404, redirect, render

from project.accounts.models import Role, User
from project.core.models import Notification, SystemDoc
from project.jobs.models import Booking, BookingStatus

from .forms import DocForm, UserRoleForm

SUPPORT_PAGES = {
    "shr": (Role.SHR, "ซัพพอร์ตระบบผู้จัดการ"),
    "requester": (Role.REQUESTER, "ซัพพอร์ตระบบผู้จองคิว"),
    "trailer": (Role.TRAILER, "ซัพพอร์ตระบบคนขับรถสไลด์"),
    "accounting": (Role.ACCOUNTING, "ซัพพอร์ตระบบงานบัญชี"),
}


def _admin_only(user):
    if not (user.is_superuser or user.role == Role.ADMIN):
        raise PermissionDenied("เฉพาะแอดมินเท่านั้น")


def _support(request, key):
    _admin_only(request.user)
    role, title = SUPPORT_PAGES[key]

    users = User.objects.filter(role=role).annotate(
        jobs=Count("bookings_created", distinct=True)
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
        "page_title": title, "role_label": dict(Role.choices)[role],
        "users": users, "bookings": related[:20],
        "stuck": related.filter(
            Q(status=BookingStatus.PENDING_ACCEPT) | Q(status=BookingStatus.PENDING_APPROVAL)
        ).count(),
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


@login_required
def permissions(request):
    """สิทธิ์การเข้าถึง — เปลี่ยนตำแหน่งแล้วส่งแจ้งเตือนให้เจ้าของบัญชีกดยอมรับ"""
    _admin_only(request.user)

    if request.method == "POST":
        target = get_object_or_404(User, pk=request.POST.get("user_id"))
        form = UserRoleForm(request.POST, instance=target)
        if form.is_valid():
            old = target.get_role_display()
            obj = form.save()
            if old != obj.get_role_display():
                Notification.push(
                    [obj],
                    title="สิทธิ์การเข้าถึงของคุณถูกเปลี่ยน",
                    body=f"จาก “{old}” เป็น “{obj.get_role_display()}” — กรุณากดยืนยันเพื่อรับทราบ",
                    link="/notifications/",
                    kind=Notification.Kind.PERMISSION,
                    need_ack=True,
                )
                messages.success(request, f"เปลี่ยนตำแหน่ง {obj.username} แล้ว และส่งแจ้งเตือนให้เจ้าของบัญชียืนยัน")
            else:
                messages.success(request, "บันทึกแล้ว")
            return redirect("adminpanel:permissions")

    users = User.objects.all().order_by("role", "username")
    return render(request, "adminpanel/permissions.html", {
        "rows": [{"user": u, "form": UserRoleForm(instance=u)} for u in users],
    })


@login_required
def doc_upload(request):
    """อัปโหลดเอกสารการใช้งานระบบ + กำหนดว่าโรลไหนเห็น"""
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
        "form": form, "docs": SystemDoc.objects.select_related("uploaded_by"),
    })
