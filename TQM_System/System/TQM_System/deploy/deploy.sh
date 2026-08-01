#!/usr/bin/env bash
# ============================================================
# สคริปต์อัปเดตโค้ดบนเซิร์ฟจริง
# ------------------------------------------------------------
# ใช้:  cd /srv/tqm && ./deploy/deploy.sh
#
# ทำ 6 อย่างตามลำดับ: ดึงโค้ด → ลง package → build CSS
#                    → รวบ static → อัปตาราง → รีสตาร์ต
# ============================================================
set -euo pipefail   # เจอ error ที่บรรทัดไหน หยุดทันที ไม่ทำต่อครึ่ง ๆ กลาง ๆ

APP_DIR="/srv/tqm"
cd "$APP_DIR"

echo "▶ 1/6 ดึงโค้ดใหม่จาก git"
git pull --ff-only

echo "▶ 2/6 ติดตั้ง package ที่เพิ่มใหม่"
.venv/bin/pip install -r requirements.txt --quiet

echo "▶ 3/6 build Tailwind"
npm ci --omit=dev --silent
npm run build

echo "▶ 4/6 รวบไฟล์ static"
.venv/bin/python manage.py collectstatic --noinput

echo "▶ 5/6 อัปโครงตารางฐานข้อมูล"
.venv/bin/python manage.py migrate --noinput

echo "▶ 6/6 รีสตาร์ตเซิร์ฟเวอร์"
sudo systemctl restart tqm

echo ""
echo "✅ เสร็จแล้ว — ตรวจสอบด้วย:"
echo "   sudo systemctl status tqm"
echo "   .venv/bin/python manage.py check --deploy"
