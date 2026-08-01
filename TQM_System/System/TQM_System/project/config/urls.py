"""
สารบัญ URL ชั้นบนสุด
------------------------------------------------------------
หน้าที่เดียวคือ "URL ขึ้นต้นด้วยอะไร → ส่งต่อไป app ไหน"
"""

from django.conf import settings
from django.conf.urls.static import static
from django.contrib import admin
from django.urls import include, path

urlpatterns = [
    path("admin/", admin.site.urls),
    path("jobs/", include("project.jobs.urls")),  # ใบจองคิว · งาน
    path("", include("project.accounts.urls")),  # login · แดชบอร์ด 7 ตำแหน่ง
    # ── เพิ่มทีละบรรทัดตอนสร้าง app ────────────────────
    # path("fleet/", include("project.fleet.urls")),     รถ · คนขับ
    # path("billing/", include("project.billing.urls")), บิล · การจ่ายเงิน
]

# เสิร์ฟไฟล์ที่ผู้ใช้อัปโหลด (รูปถ่ายรถ) ตอนพัฒนาเท่านั้น
# บนเซิร์ฟจริง nginx เป็นคนเสิร์ฟให้
if settings.DEBUG:
    urlpatterns += static(settings.MEDIA_URL, document_root=settings.MEDIA_ROOT)
