#!/usr/bin/env python
"""
ตัวสั่งงาน Django — ไฟล์นี้ไม่ต้องแก้อีกเลย
ใช้สำหรับ: runserver, makemigrations, migrate, startapp, createsuperuser
"""
import os
import sys


def main():
    # ชี้ไปที่ settings ที่อยู่ใน project/config/
    os.environ.setdefault("DJANGO_SETTINGS_MODULE", "project.config.settings")
    try:
        from django.core.management import execute_from_command_line
    except ImportError as exc:
        raise ImportError(
            "หา Django ไม่เจอ — น่าจะลืม activate virtual environment\n"
            "รันคำสั่งนี้ก่อน:  .venv\\Scripts\\Activate.ps1"
        ) from exc
    execute_from_command_line(sys.argv)


if __name__ == "__main__":
    main()
