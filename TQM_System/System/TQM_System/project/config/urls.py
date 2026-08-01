"""
สารบัญ URL ชั้นบนสุด
------------------------------------------------------------
"URL ขึ้นต้นด้วยอะไร → ส่งต่อไป app ไหน"
"""

from django.conf import settings
from django.conf.urls.static import static
from django.contrib import admin
from django.urls import include, path

urlpatterns = [
    path("admin/", admin.site.urls),
    path("jobs/", include("project.jobs.urls")),  # ใบจองคิว · งาน · 5 ขั้นตอน
    path("fleet/", include("project.fleet.urls")),  # คนขับรถ
    path("billing/", include("project.billing.urls")),  # เกณฑ์ราคา · บิล
    path("reports/", include("project.reports.urls")),  # รายงานผู้บริหาร
    path("manage/", include("project.adminpanel.urls")),  # หน้าของแอดมิน
    path("", include("project.core.urls")),  # แจ้งเตือน · โปรไฟล์ · เอกสาร
    path("", include("project.accounts.urls")),  # login · แดชบอร์ด 7 ตำแหน่ง
]

if settings.DEBUG:
    urlpatterns += static(settings.MEDIA_URL, document_root=settings.MEDIA_ROOT)
