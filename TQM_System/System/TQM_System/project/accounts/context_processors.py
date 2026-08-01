"""ตัวแปรที่ทุก template ใช้ได้ — สีประจำตำแหน่ง · เมนู"""

from .menus import MOBILE_FIRST, bottom_nav_for, common_menu_for, menu_for
from .models import BRAND_COLOR, RoleTheme


def role_theme(request):
    user = getattr(request, "user", None)

    if not (user and user.is_authenticated):
        return {"accent_color": BRAND_COLOR, "brand_color": BRAND_COLOR,
                "nav_items": [], "common_items": [], "bottom_nav": [], "is_mobile_first": False}

    return {
        "accent_color": RoleTheme.color_for(user.role),
        "brand_color": BRAND_COLOR,
        "nav_items": menu_for(user),
        "common_items": common_menu_for(user),
        "bottom_nav": bottom_nav_for(user),
        "is_mobile_first": user.role in MOBILE_FIRST,
    }
