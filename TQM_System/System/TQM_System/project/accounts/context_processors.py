"""
ฉีดสีประจำตำแหน่งเข้าไปในทุก template
------------------------------------------------------------
ทำให้ base.html เขียนแค่
    <body style="--color-accent: {{ accent_color }}">
แล้วทุก component ที่ใช้ var(--color-accent) เปลี่ยนสีตามตำแหน่งทันที
"""

from .models import BRAND_COLOR, RoleTheme


def role_theme(request):
    user = getattr(request, "user", None)

    if not (user and user.is_authenticated):
        # หน้า login และหน้าสาธารณะ ใช้สีแบรนด์เสมอ
        return {"accent_color": BRAND_COLOR, "brand_color": BRAND_COLOR}

    return {
        "accent_color": RoleTheme.color_for(user.role),
        "brand_color": BRAND_COLOR,
    }
