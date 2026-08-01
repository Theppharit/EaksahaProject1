"""
ตัวแปรที่ทุก template ใช้ได้โดยไม่ต้องส่งจาก view ทีละหน้า
------------------------------------------------------------
  {{ accent_color }}  สีประจำตำแหน่ง (แอดมินตั้งค่าได้)
  {{ nav_items }}     เมนูข้างของตำแหน่งนั้น
"""

from .menus import menu_for
from .models import BRAND_COLOR, RoleTheme


def role_theme(request):
    user = getattr(request, "user", None)

    if not (user and user.is_authenticated):
        # หน้า login และหน้าสาธารณะ ใช้สีแบรนด์เสมอ
        return {"accent_color": BRAND_COLOR, "brand_color": BRAND_COLOR, "nav_items": []}

    return {
        "accent_color": RoleTheme.color_for(user.role),
        "brand_color": BRAND_COLOR,
        "nav_items": menu_for(user),
    }
