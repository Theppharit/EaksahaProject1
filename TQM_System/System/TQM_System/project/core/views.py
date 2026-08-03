"""4 หน้าที่ทุกตำแหน่งใช้ร่วมกัน — แจ้งเตือน · เอกสาร · โปรไฟล์"""

from django.contrib import messages
from django.contrib.auth.decorators import login_required
from django.shortcuts import get_object_or_404, redirect, render
from django.utils import timezone

from project.accounts.forms import ProfileForm, VerifyIdentityForm
from project.accounts.models import RoleChangeRequest, RoleChangeStatus

from .models import Notification, SystemDoc


@login_required
def notifications(request):
    """การแจ้งเตือน — แสดงเฉพาะของผู้ใช้คนนี้"""
    qs = request.user.notifications.all()

    if request.GET.get("filter") == "unread":
        qs = qs.filter(is_read=False)

    return render(
        request,
        "core/notifications.html",
        {
            "items": qs[:100],
            "only_unread": request.GET.get("filter") == "unread",
            # คำขอเปลี่ยนสิทธิ์ที่รอเจ้าตัวตัดสิน — ดันขึ้นบนสุดเพราะสำคัญกว่าแจ้งเตือนทั่วไป
            "role_changes": RoleChangeRequest.objects.filter(
                user=request.user, status=RoleChangeStatus.PENDING
            ).select_related("requested_by"),
        },
    )


@login_required
def role_change_decide(request, pk):
    """
    เจ้าของบัญชีกดยอมรับหรือปฏิเสธการเปลี่ยนสิทธิ์
    ⚠ สิทธิ์เปลี่ยนจริงตรงนี้เท่านั้น — ตอนแอดมินกดยังไม่มีผลใด ๆ
    """
    req = get_object_or_404(
        RoleChangeRequest, pk=pk, user=request.user, status=RoleChangeStatus.PENDING
    )
    if request.method != "POST":
        return redirect("core:notifications")

    accepted = request.POST.get("decision") == "accept"
    req.status = RoleChangeStatus.ACCEPTED if accepted else RoleChangeStatus.REJECTED
    req.decided_at = timezone.now()
    req.save(update_fields=["status", "decided_at"])

    # ปิดแจ้งเตือนใบที่ค้างอยู่ จะได้ไม่ค้างเป็นจุดแดงตลอด
    request.user.notifications.filter(
        kind=Notification.Kind.PERMISSION, acked_at__isnull=True, need_ack=True
    ).update(acked_at=timezone.now(), is_read=True)

    Notification.push(
        [req.requested_by],
        title=f"ผลคำขอเปลี่ยนสิทธิ์ของ {request.user.get_full_name() or request.user.username}",
        body=f"{'ยอมรับ' if accepted else 'ปฏิเสธ'}การเปลี่ยนเป็น “{req.get_to_role_display()}”",
        link="/manage/permissions/",
        kind=Notification.Kind.PERMISSION,
    )

    if accepted:
        request.user.role = req.to_role
        request.user.save(update_fields=["role"])
        messages.success(
            request,
            f"เปลี่ยนตำแหน่งเป็น “{req.get_to_role_display()}” เรียบร้อย "
            f"หน้าจอและเมนูจะเปลี่ยนตามตำแหน่งใหม่ทันที",
        )
        return redirect("accounts:dashboard")

    messages.success(request, "ปฏิเสธคำขอเปลี่ยนสิทธิ์แล้ว ตำแหน่งของคุณยังเหมือนเดิม")
    return redirect("core:notifications")


@login_required
def notification_open(request, pk):
    """กดอ่าน — ทำเครื่องหมายว่าอ่านแล้วและพาไปหน้าปลายทาง"""
    n = get_object_or_404(Notification, pk=pk, user=request.user)
    if not n.is_read:
        n.is_read = True
        n.save(update_fields=["is_read"])
    return redirect(n.link or "core:notifications")


@login_required
def notification_ack(request, pk):
    """กดยอมรับ — ใช้กับการแจ้งเตือนเปลี่ยนสิทธิ์"""
    n = get_object_or_404(Notification, pk=pk, user=request.user, need_ack=True)
    n.acked_at = timezone.now()
    n.is_read = True
    n.save(update_fields=["acked_at", "is_read"])
    messages.success(request, "ยืนยันเรียบร้อยแล้ว")
    return redirect("core:notifications")


@login_required
def notifications_read_all(request):
    request.user.notifications.filter(is_read=False).update(is_read=True)
    messages.success(request, "ทำเครื่องหมายว่าอ่านทั้งหมดแล้ว")
    return redirect("core:notifications")


@login_required
def docs(request):
    """การใช้งานระบบ — เห็นเฉพาะเอกสารที่แอดมินอนุญาตตำแหน่งนี้"""
    items = [d for d in SystemDoc.objects.select_related("uploaded_by") if d.visible_to(request.user)]
    return render(request, "core/docs.html", {"items": items})


@login_required
def profile(request):
    """โปรไฟล์ + ยืนยันตัวตน"""
    form = ProfileForm(request.POST or None, request.FILES or None, instance=request.user)
    verify = VerifyIdentityForm(user=request.user)

    if request.method == "POST" and form.is_valid():
        form.save()
        messages.success(request, "บันทึกข้อมูลส่วนตัวเรียบร้อย")
        return redirect("core:profile")

    return render(request, "core/profile.html", {"form": form, "verify_form": verify})


@login_required
def verify_identity(request):
    """ยืนยันตัวตนด้วยรหัสผ่าน — ใช้ก่อนทำรายการสำคัญ เช่น อนุมัติใบจอง"""
    form = VerifyIdentityForm(request.POST or None, user=request.user)
    nxt = request.POST.get("next") or request.GET.get("next") or "accounts:dashboard"

    if request.method == "POST" and form.is_valid():
        request.session["identity_verified_at"] = timezone.now().isoformat()
        messages.success(request, "ยืนยันตัวตนสำเร็จ")
        return redirect(nxt)

    return render(request, "core/verify_identity.html", {"form": form, "next": nxt})
