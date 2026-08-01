"""
สารบัญ URL ชั้นบนสุด
------------------------------------------------------------
หน้าที่เดียวคือ "URL ขึ้นต้นด้วยอะไร → ส่งต่อไป app ไหน"
รายละเอียดของแต่ละหน้าอยู่ใน urls.py ของ app นั้น ๆ
"""

from django.contrib import admin
from django.urls import include, path

urlpatterns = [
    path("admin/", admin.site.urls),
    # เข้า/ออกระบบ + แดชบอร์ด 7 ตำแหน่ง
    path("", include("project.accounts.urls")),
    # ── เพิ่มทีละบรรทัดตอนสร้าง app ────────────────────
    # path("jobs/", include("project.jobs.urls")),     งาน · คิว
    # path("fleet/", include("project.fleet.urls")),   รถ · คนขับ
    # path("billing/", include("project.billing.urls")),
]
