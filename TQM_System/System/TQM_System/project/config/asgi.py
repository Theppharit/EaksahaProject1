"""
จุดเชื่อมแบบ async (ใช้ตอนทำ real-time เช่น แจ้งเตือนสถานะรถสด ๆ)
ตอนนี้ยังไม่ได้ใช้ ไม่ต้องแก้
"""

import os

from django.core.asgi import get_asgi_application

os.environ.setdefault("DJANGO_SETTINGS_MODULE", "project.config.settings")

application = get_asgi_application()
