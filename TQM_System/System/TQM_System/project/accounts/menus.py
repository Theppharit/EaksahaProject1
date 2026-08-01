"""
เมนูข้างของแต่ละตำแหน่ง
------------------------------------------------------------
เก็บไว้ที่เดียว เพราะ sidebar ใช้ร่วมกันทั้ง 7 ตำแหน่ง
ต่างกันแค่รายการเมนู — ตามเอกสารสเปกที่ลูกค้าอนุมัติ

โครงแต่ละรายการ: (ชื่อเมนู, ชื่อ URL, ไอคอน)
  ชื่อ URL = None  → ยังไม่ได้ทำ แสดงเป็นเมนูจาง ๆ กดไม่ได้
"""

from .models import Role

# ── ไอคอน (เส้น SVG ล้วน ไม่ต้องโหลดไฟล์ภายนอก) ──
ICONS = {
    "home": '<path d="m3 10 9-7 9 7v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M9 22V12h6v10"/>',
    "plus": '<path d="M12 5v14M5 12h14"/>',
    "list": '<path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/>',
    "clock": '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
    "calendar": '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
    "truck": '<path d="M10 17h4V5H2v12h3"/><path d="M20 17h2v-3.34a4 4 0 0 0-1.17-2.83L19 9h-5v8h1"/><circle cx="7.5" cy="17.5" r="2.5"/><circle cx="17.5" cy="17.5" r="2.5"/>',
    "users": '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/>',
    "money": '<rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/>',
    "file": '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/>',
    "chart": '<path d="M3 3v18h18"/><path d="m19 9-5 5-4-4-3 3"/>',
    "settings": '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06A1.65 1.65 0 0 0 15 19.4a1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.6 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.6a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09A1.65 1.65 0 0 0 15 4.6a1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9"/>',
    "shield": '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10"/>',
    "check": '<path d="M20 6 9 17l-5-5"/>',
}

_DASH = "accounts:dashboard_{}"

MENUS = {
    Role.REQUESTER: [
        ("หน้าหลัก", _DASH.format("requester"), "home"),
        ("สร้างใบจอง", "jobs:create", "plus"),
        ("คิวของฉัน", "jobs:list", "list"),
        ("ประวัติการจองคิว", "jobs:history", "clock"),
        ("ปฏิทินนัดหมาย", None, "calendar"),
    ],
    Role.SALES: [
        ("หน้าหลัก", _DASH.format("sales"), "home"),
        ("คิวที่ดูแล", "jobs:list", "list"),
        ("ประวัติการจองคิว", "jobs:history", "clock"),
        ("ปฏิทินนัดหมาย", None, "calendar"),
    ],
    Role.TRAILER: [
        ("หน้าหลัก", _DASH.format("trailer"), "home"),
        ("งานที่ต้องรับ", "jobs:list", "list"),
        ("ประวัติการจองคิว", "jobs:history", "clock"),
        ("คนขับรถ", None, "users"),
        ("ยอดเงินรอบนี้", None, "money"),
    ],
    Role.SHR: [
        ("หน้าหลัก", _DASH.format("shr"), "home"),
        ("สร้างใบจอง", "jobs:create", "plus"),
        ("ใบจองทั้งหมด", "jobs:list", "list"),
        ("รายการรออนุมัติ", "jobs:pending", "check"),
        ("ประวัติการจองคิว", "jobs:history", "clock"),
        ("เกณฑ์ราคา · รอบจ่ายเงิน", None, "money"),
        ("คนขับรถ", None, "users"),
    ],
    Role.ACCOUNTING: [
        ("หน้าหลัก", _DASH.format("accounting"), "home"),
        ("ใบจองที่ผ่านอนุมัติ", "jobs:list", "list"),
        ("รออนุมัติ", "jobs:pending", "check"),
        ("ออกเลขบิล", None, "file"),
        ("ประวัติการจ่ายเงิน", None, "money"),
    ],
    Role.EXECUTIVE: [
        ("ภาพรวม", _DASH.format("executive"), "home"),
        ("ใบจองทั้งหมด", "jobs:list", "list"),
        ("ประวัติการจองคิว", "jobs:history", "clock"),
        ("รายงานเชิงลึก", None, "chart"),
    ],
    Role.ADMIN: [
        ("หน้าหลัก", _DASH.format("admin"), "home"),
        ("ใบจองทั้งหมด", "jobs:list", "list"),
        ("ประวัติการจองคิว", "jobs:history", "clock"),
        ("ผู้ใช้งาน · สิทธิ์", None, "shield"),
        ("เอกสารการใช้งานระบบ", None, "file"),
        ("ตั้งค่าระบบ", None, "settings"),
    ],
}


def menu_for(user):
    """คืนรายการเมนูของผู้ใช้คนนั้น พร้อมเส้น SVG ของไอคอน"""
    items = MENUS.get(user.role, [])
    return [
        {"label": label, "url_name": url_name, "icon": ICONS.get(icon, "")}
        for label, url_name, icon in items
    ]
