"""
โหมดสวมสิทธิ์ (impersonation)
------------------------------------------------------------
ใช้กับหน้า "ซัพพอร์ตระบบ X" ของแอดมิน — เข้าไปใช้งานระบบของผู้ใช้จริง ๆ
เพื่อดูว่าผู้ใช้เจอปัญหาอะไรอยู่ แทนที่จะเดาจากคำบอกเล่า

กติกาความปลอดภัยที่บังคับไว้ในนี้:
  1. คนที่สวมสิทธิ์ได้มีแค่ แอดมิน หรือ superuser เท่านั้น
  2. สวมสิทธิ์ "ใส่" แอดมิน/superuser คนอื่นไม่ได้ — กันยกระดับสิทธิ์ตัวเอง
  3. ระหว่างสวมสิทธิ์ request.user จะกลายเป็นผู้ใช้ปลายทางจริง ๆ
     → ด่านตรวจสิทธิ์ทุกจุดในระบบทำงานเหมือนผู้ใช้คนนั้นล็อกอินเอง
       แอดมินจึงเข้าหน้าแอดมินไม่ได้ระหว่างอยู่ในโหมดนี้ (ตั้งใจให้เป็นแบบนั้น)
  4. ตัวจริงเก็บไว้ที่ request.impersonator เพื่อโชว์แถบเตือนและปุ่มออกจากโหมด
"""

SESSION_KEY = "impersonate_id"
LOG_KEY = "impersonate_log_id"


class ImpersonationMiddleware:
    """ต้องวางไว้ "หลัง" AuthenticationMiddleware เสมอ ไม่งั้นยังไม่มี request.user"""

    def __init__(self, get_response):
        self.get_response = get_response

    def __call__(self, request):
        request.impersonator = None

        target_id = request.session.get(SESSION_KEY)
        user = getattr(request, "user", None)

        if target_id and user is not None and user.is_authenticated and _may_impersonate(user):
            from .models import Role, User

            target = User.objects.filter(pk=target_id, is_active=True).first()
            if target and not (target.is_superuser or target.role == Role.ADMIN):
                request.impersonator = user
                request.user = target
            else:
                # เป้าหมายถูกลบ/ถูกปิดใช้งาน/กลายเป็นแอดมินไปแล้ว → ออกจากโหมดให้เลย
                request.session.pop(SESSION_KEY, None)
                request.session.pop(LOG_KEY, None)

        return self.get_response(request)


def _may_impersonate(user):
    from .models import Role

    return user.is_superuser or user.role == Role.ADMIN
