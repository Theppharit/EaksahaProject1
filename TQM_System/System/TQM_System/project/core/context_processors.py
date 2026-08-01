"""จำนวนแจ้งเตือนที่ยังไม่อ่าน — ใช้แสดงจุดแดงบนกระดิ่งทุกหน้า"""


def unread_count(request):
    user = getattr(request, "user", None)
    if not (user and user.is_authenticated):
        return {"unread_notifications": 0}
    return {"unread_notifications": user.notifications.filter(is_read=False).count()}
