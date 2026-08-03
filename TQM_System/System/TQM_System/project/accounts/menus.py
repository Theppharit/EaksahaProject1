"""
เมนูของแต่ละตำแหน่ง — ตรงตามเอกสารสเปกที่ลูกค้าอนุมัติ
------------------------------------------------------------
โครงแต่ละรายการ: (ชื่อเมนู, ชื่อ URL, ไอคอน)
  ชื่อ URL = None → แสดงจาง ๆ กดไม่ได้ (ยังไม่ได้ทำ)

MOBILE_FIRST = ตำแหน่งที่สเปกระบุว่า "ส่วนใหญ่ใช้มือถือ"
              → หน้าจอจะโชว์แถบเมนูล่างแบบแอปมือถือ
"""

from .models import Role

ICONS = {
    "home": '<path d="m3 10 9-7 9 7v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M9 22V12h6v10"/>',
    "plus": '<path d="M12 5v14M5 12h14"/>',
    "list": '<path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/>',
    "clock": '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
    "check": '<path d="M20 6 9 17l-5-5"/>',
    "truck": '<path d="M10 17h4V5H2v12h3"/><path d="M20 17h2v-3.34a4 4 0 0 0-1.17-2.83L19 9h-5v8h1"/><circle cx="7.5" cy="17.5" r="2.5"/><circle cx="17.5" cy="17.5" r="2.5"/>',
    "users": '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/>',
    "money": '<rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/>',
    "file": '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/>',
    "chart": '<path d="M3 3v18h18"/><path d="m19 9-5 5-4-4-3 3"/>',
    "shield": '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10"/>',
    "bell": '<path d="M18 8a6 6 0 1 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/>',
    "user": '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
    "book": '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>',
    "life": '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="4"/><path d="m4.9 4.9 4.2 4.2M14.9 14.9l4.2 4.2M19.1 4.9l-4.2 4.2M9.1 14.9l-4.2 4.2"/>',
    "upload": '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M17 8l-5-5-5 5M12 3v12"/>',
}

_D = "accounts:dashboard_{}"

# เมนูเฉพาะตำแหน่ง (ตามหัวข้อใหญ่ในเอกสาร = 1 หน้า)
ROLE_MENUS = {
    Role.REQUESTER: [
        ("หน้าหลัก", _D.format("requester"), "home"),
        ("สร้างใบจอง", "jobs:create", "plus"),
        ("ประวัติการจองคิว", "jobs:history", "clock"),
    ],
    Role.SALES: [
        ("หน้าหลัก", _D.format("sales"), "home"),
        ("ประวัติการจองคิว", "jobs:history", "clock"),
    ],
    Role.TRAILER: [
        ("หน้าหลัก", _D.format("trailer"), "home"),
        ("รับคิวงาน", "jobs:available", "truck"),
        ("คนขับรถ", "fleet:driver_list", "users"),
        ("ประวัติการจองคิว", "jobs:history", "clock"),
    ],
    Role.SHR: [
        ("หน้าหลัก", _D.format("shr"), "home"),
        ("สร้างใบจอง", "jobs:create", "plus"),
        ("รายการรออนุมัติ", "jobs:pending", "check"),
        ("ประวัติการจองคิว", "jobs:history", "clock"),
        ("เกณฑ์ราคา · รอบจ่ายเงิน", "billing:rates", "money"),
        ("คนขับรถ", "fleet:driver_list", "users"),
    ],
    Role.ACCOUNTING: [
        ("หน้าหลัก", _D.format("accounting"), "home"),
        ("รออนุมัติ", "jobs:pending", "check"),
        ("ออกเลขบิล", "billing:bill_list", "file"),
        ("ประวัติการจ่ายเงิน", "billing:payment_history", "money"),
    ],
    Role.EXECUTIVE: [
        ("ภาพรวม", _D.format("executive"), "home"),
        ("รายงานเชิงลึก", "reports:deep", "chart"),
    ],
    Role.ADMIN: [
        ("หน้าหลัก", _D.format("admin"), "home"),
        ("ซัพพอร์ตระบบผู้จัดการ", "adminpanel:support_shr", "life"),
        ("ซัพพอร์ตระบบผู้จองคิว", "adminpanel:support_requester", "life"),
        ("ซัพพอร์ตระบบคนขับรถสไลด์", "adminpanel:support_trailer", "life"),
        ("ซัพพอร์ตระบบงานบัญชี", "adminpanel:support_accounting", "life"),
        ("สิทธิ์และรหัสผ่าน", "adminpanel:permissions", "shield"),
        ("อัปโหลดเอกสารการใช้งาน", "adminpanel:doc_upload", "upload"),
    ],
}

# เมนูที่ทุกตำแหน่งมีเหมือนกัน (ข้อ 3 ในเอกสารหน้าแรก)
COMMON_MENU = [
    ("การแจ้งเตือน", "core:notifications", "bell"),
    ("โปรไฟล์ · ยืนยันตัวตน", "core:profile", "user"),
    ("การใช้งานระบบ", "core:docs", "book"),
]

# ตำแหน่งที่สเปกระบุว่าใช้มือถือเป็นหลัก → เปิดแถบเมนูล่าง
MOBILE_FIRST = {Role.TRAILER, Role.SALES}


def _build(items):
    return [
        {"label": label, "url_name": url, "icon": ICONS.get(icon, "")}
        for label, url, icon in items
    ]


def menu_for(user):
    return _build(ROLE_MENUS.get(user.role, []))


def common_menu_for(user):
    return _build(COMMON_MENU)


def bottom_nav_for(user):
    """แถบล่างบนมือถือ — เอา 4 เมนูแรกของตำแหน่งนั้น + แจ้งเตือน"""
    items = ROLE_MENUS.get(user.role, [])[:3] + [COMMON_MENU[0]]
    return _build(items)
