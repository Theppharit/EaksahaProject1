"""
จุดเชื่อมสำหรับเว็บเซิร์ฟเวอร์ตอน deploy (gunicorn / uWSGI)
ตอนพัฒนาไม่ได้ใช้ไฟล์นี้ ไม่ต้องแก้
"""

import os

from django.core.wsgi import get_wsgi_application

os.environ.setdefault("DJANGO_SETTINGS_MODULE", "project.config.settings")

application = get_wsgi_application()
