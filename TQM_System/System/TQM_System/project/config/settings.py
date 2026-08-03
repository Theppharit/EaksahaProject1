"""
ตั้งค่าโปรเจกต์ TQM System
------------------------------------------------------------
ไฟล์เดียวใช้ได้ทั้งเครื่องพัฒนาและเซิร์ฟจริง
ค่าที่ต่างกันอ่านจากไฟล์ .env (ซึ่งไม่ขึ้น git)

เครื่องพัฒนา : .env มี DEBUG=True  → SQLite, ไม่บังคับ HTTPS
เซิร์ฟจริง   : .env มี DEBUG=False → PostgreSQL, บังคับ HTTPS, ปิดข้อมูล error

เอกสารอ้างอิง: https://docs.djangoproject.com/en/5.2/ref/settings/
"""

from pathlib import Path

import environ

# ============================================================
# เส้นทางโฟลเดอร์
# ------------------------------------------------------------
#   PROJECT_DIR = TQM_System/project   (โค้ดระบบทั้งหมด)
#   BASE_DIR    = TQM_System           (รากโปรเจกต์ ที่อยู่ของ manage.py)
# ============================================================
PROJECT_DIR = Path(__file__).resolve().parent.parent
BASE_DIR = PROJECT_DIR.parent

# ── อ่านค่าจากไฟล์ .env ─────────────────────────
env = environ.Env(
    DEBUG=(bool, False),  # ถ้าไม่ระบุใน .env ให้ถือว่าเป็นเซิร์ฟจริงไว้ก่อน (ปลอดภัยกว่า)
    ALLOWED_HOSTS=(list, []),
    CSRF_TRUSTED_ORIGINS=(list, []),
)
environ.Env.read_env(BASE_DIR / ".env")


# ============================================================
# ความปลอดภัย
# ============================================================
SECRET_KEY = env("SECRET_KEY")
DEBUG = env("DEBUG")
ALLOWED_HOSTS = env("ALLOWED_HOSTS")

# โดเมนที่เชื่อถือสำหรับฟอร์ม (ต้องใส่ https://... ตอนขึ้นเซิร์ฟจริง)
CSRF_TRUSTED_ORIGINS = env("CSRF_TRUSTED_ORIGINS")


# ============================================================
# แอปพลิเคชัน
# ============================================================
INSTALLED_APPS = [
    "django.contrib.admin",
    "django.contrib.auth",
    "django.contrib.contenttypes",
    "django.contrib.sessions",
    "django.contrib.messages",
    "django.contrib.staticfiles",
    # ── app ของเรา ────────────────────────────────────
    "project.accounts",   # ผู้ใช้ · 7 ตำแหน่ง · สิทธิ์ · สีประจำตำแหน่ง
    "project.customers",  # ลูกค้า · รถของลูกค้า
    "project.fleet",      # เจ้าของรถ · รถสไลด์ · คนขับ
    "project.jobs",       # ใบจองคิว · สถานะงาน · 5 ขั้นตอน · รูปถ่าย
    "project.billing",    # เกณฑ์ราคา · รอบจ่ายเงิน · ใบวางบิล
    "project.core",       # แจ้งเตือน · เอกสารการใช้งานระบบ
    "project.reports",    # รายงานเชิงลึกผู้บริหาร
    "project.adminpanel", # ซัพพอร์ต · สิทธิ์ · อัปโหลดเอกสาร
]

MIDDLEWARE = [
    "django.middleware.security.SecurityMiddleware",
    # WhiteNoise — ให้ Django เสิร์ฟไฟล์ static เองได้อย่างมีประสิทธิภาพ
    # ต้องอยู่ถัดจาก SecurityMiddleware เท่านั้น
    "whitenoise.middleware.WhiteNoiseMiddleware",
    "django.contrib.sessions.middleware.SessionMiddleware",
    "django.middleware.common.CommonMiddleware",
    "django.middleware.csrf.CsrfViewMiddleware",
    "django.contrib.auth.middleware.AuthenticationMiddleware",
    # โหมดสวมสิทธิ์ของแอดมิน (หน้าซัพพอร์ต) — ต้องอยู่หลัง AuthenticationMiddleware
    # เพราะต้องมี request.user ก่อนถึงจะสลับตัวผู้ใช้ได้
    "project.accounts.middleware.ImpersonationMiddleware",
    "django.contrib.messages.middleware.MessageMiddleware",
    "django.middleware.clickjacking.XFrameOptionsMiddleware",
]

ROOT_URLCONF = "project.config.urls"

TEMPLATES = [
    {
        "BACKEND": "django.template.backends.django.DjangoTemplates",
        "DIRS": [PROJECT_DIR / "templates"],
        "APP_DIRS": True,
        "OPTIONS": {
            "context_processors": [
                "django.template.context_processors.request",
                "django.contrib.auth.context_processors.auth",
                "django.contrib.messages.context_processors.messages",
                # ฉีดสีประจำตำแหน่งเข้าไปในทุกหน้า → ตัวแปร {{ accent_color }}
                "project.accounts.context_processors.role_theme",
                # จำนวนแจ้งเตือนที่ยังไม่อ่าน → {{ unread_notifications }}
                "project.core.context_processors.unread_count",
            ],
        },
    },
]

WSGI_APPLICATION = "project.config.wsgi.application"


# ============================================================
# ฐานข้อมูล
# ------------------------------------------------------------
# อ่านจาก DATABASE_URL ใน .env — สลับฐานข้อมูลได้โดยไม่แตะโค้ด
#   เครื่องพัฒนา : sqlite:///db.sqlite3
#   เซิร์ฟจริง   : postgres://user:pass@localhost:5432/tqm
# ============================================================
DATABASES = {
    "default": env.db_url("DATABASE_URL", default=f"sqlite:///{BASE_DIR / 'db.sqlite3'}")
}
# เปิด connection ค้างไว้ 60 วินาที ลดเวลาต่อฐานข้อมูลใหม่ทุก request
DATABASES["default"]["CONN_MAX_AGE"] = env.int("CONN_MAX_AGE", default=60)


# ============================================================
# ผู้ใช้และรหัสผ่าน
# ============================================================
AUTH_USER_MODEL = "accounts.User"

AUTH_PASSWORD_VALIDATORS = [
    {"NAME": "django.contrib.auth.password_validation.UserAttributeSimilarityValidator"},
    {"NAME": "django.contrib.auth.password_validation.MinimumLengthValidator"},
    {"NAME": "django.contrib.auth.password_validation.CommonPasswordValidator"},
    {"NAME": "django.contrib.auth.password_validation.NumericPasswordValidator"},
]

LOGIN_URL = "accounts:login"
LOGIN_REDIRECT_URL = "accounts:dashboard"
LOGOUT_REDIRECT_URL = "accounts:login"


# ============================================================
# ภาษาและเวลา
# ============================================================
LANGUAGE_CODE = "th"
TIME_ZONE = "Asia/Bangkok"
USE_I18N = True
USE_TZ = True


# ============================================================
# ไฟล์ static และไฟล์อัปโหลด
# ============================================================
STATIC_URL = "/static/"
STATICFILES_DIRS = [PROJECT_DIR / "assets"]
STATIC_ROOT = BASE_DIR / "staticfiles"

# WhiteNoise: บีบอัดไฟล์ + ใส่ hash ท้ายชื่อไฟล์
# ทำให้เบราว์เซอร์ cache ได้ยาว แต่พอ deploy เวอร์ชันใหม่ชื่อไฟล์เปลี่ยน
# ผู้ใช้จึงได้ CSS ใหม่ทันทีโดยไม่ต้องบอกให้กด Ctrl+F5
STORAGES = {
    "default": {"BACKEND": "django.core.files.storage.FileSystemStorage"},
    "staticfiles": {
        "BACKEND": "whitenoise.storage.CompressedManifestStaticFilesStorage"
        if not DEBUG
        else "django.contrib.staticfiles.storage.StaticFilesStorage"
    },
}

MEDIA_URL = "/media/"
MEDIA_ROOT = env.path("MEDIA_ROOT", default=BASE_DIR / "media")

# ขนาดไฟล์อัปโหลดสูงสุด 10 MB (รูปถ่ายก่อน/หลังขึ้นรถสไลด์)
DATA_UPLOAD_MAX_MEMORY_SIZE = 10 * 1024 * 1024
FILE_UPLOAD_MAX_MEMORY_SIZE = 10 * 1024 * 1024

DEFAULT_AUTO_FIELD = "django.db.models.BigAutoField"


# ============================================================
# ค่าที่เปิดเฉพาะบนเซิร์ฟจริง (DEBUG = False)
# ------------------------------------------------------------
# ตรวจว่าตั้งครบมั้ยด้วย:  python manage.py check --deploy
# ============================================================
if not DEBUG:
    # บังคับ HTTPS
    SECURE_SSL_REDIRECT = True
    SESSION_COOKIE_SECURE = True
    CSRF_COOKIE_SECURE = True

    # บอกเบราว์เซอร์ให้จำว่าเว็บนี้ใช้ HTTPS เท่านั้น (1 ปี)
    SECURE_HSTS_SECONDS = 31536000
    SECURE_HSTS_INCLUDE_SUBDOMAINS = True
    SECURE_HSTS_PRELOAD = True

    # nginx เป็นคนรับ HTTPS แล้วส่งต่อมาให้ Django ผ่าน http
    # header นี้บอก Django ว่าคำขอเดิมมาแบบ https
    SECURE_PROXY_SSL_HEADER = ("HTTP_X_FORWARDED_PROTO", "https")

    # กันเบราว์เซอร์เดาชนิดไฟล์เอง และกันเว็บอื่นเอาไปฝัง iframe
    SECURE_CONTENT_TYPE_NOSNIFF = True
    X_FRAME_OPTIONS = "DENY"

    # หมดอายุ session หลังไม่ใช้งาน 8 ชั่วโมง (1 กะทำงาน)
    SESSION_COOKIE_AGE = 8 * 60 * 60
    SESSION_SAVE_EVERY_REQUEST = True

    # เขียน log ระดับ error ลงไฟล์ เพื่อตามหาปัญหาบนเซิร์ฟจริง
    LOGGING = {
        "version": 1,
        "disable_existing_loggers": False,
        "formatters": {
            "verbose": {"format": "[{asctime}] {levelname} {name} — {message}", "style": "{"}
        },
        "handlers": {
            "file": {
                "level": "ERROR",
                "class": "logging.handlers.RotatingFileHandler",
                "filename": env("LOG_FILE", default=str(BASE_DIR / "logs" / "django.log")),
                "maxBytes": 5 * 1024 * 1024,
                "backupCount": 5,
                "formatter": "verbose",
            },
        },
        "root": {"handlers": ["file"], "level": "ERROR"},
    }


# ============================================================
# รหัสผ่านเริ่มต้น — ใช้ตอนแอดมินกด "รีเซตรหัสผ่าน"
# ------------------------------------------------------------
# ตั้งค่าจริงผ่านไฟล์ .env ได้ (DEFAULT_USER_PASSWORD=...)
# ผู้ใช้ควรเปลี่ยนรหัสผ่านทันทีหลังเข้าระบบครั้งแรก
# ============================================================
DEFAULT_USER_PASSWORD = env.str("DEFAULT_USER_PASSWORD", default="Tqm@2569")
