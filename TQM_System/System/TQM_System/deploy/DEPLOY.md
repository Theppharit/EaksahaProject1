# คู่มือ Deploy — TQM System

สำหรับ VPS ที่ใช้ Ubuntu 22.04 / 24.04 · Django 5.2 + PostgreSQL + gunicorn + nginx

---

## ภาพรวมว่าอะไรทำหน้าที่อะไร

```
ผู้ใช้ ──HTTPS──▶ nginx ──socket──▶ gunicorn ──▶ Django ──▶ PostgreSQL
                    │                  │
              รับ HTTPS          รัน Python
              ส่งไฟล์ static      หลาย worker
```

- **nginx** — รับคำขอจากอินเทอร์เน็ต จัดการ HTTPS ส่งไฟล์ static เอง
- **gunicorn** — รันโค้ด Django หลาย process พร้อมกัน (แทน `runserver` ที่ห้ามใช้จริง)
- **systemd** — คอยดูให้ gunicorn รันตลอด ล่มแล้วเปิดใหม่เอง บูตเครื่องแล้วเปิดเอง

---

## ครั้งแรก — ติดตั้งบนเซิร์ฟใหม่

### 1. เตรียมเครื่อง

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y python3-venv python3-pip postgresql nginx git curl

# Node.js สำหรับ build Tailwind
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
sudo apt install -y nodejs
```

### 2. สร้างผู้ใช้เฉพาะของแอป

```bash
sudo adduser --system --group --home /srv/tqm tqm
sudo mkdir -p /var/log/tqm /var/www/tqm/media
sudo chown -R tqm:www-data /var/log/tqm /var/www/tqm
```

> ห้ามรันแอปด้วย root — ถ้าโดนเจาะจะเสียทั้งเครื่อง

### 3. สร้างฐานข้อมูล

```bash
sudo -u postgres psql
```
```sql
CREATE DATABASE tqm;
CREATE USER tqm_user WITH PASSWORD 'ใส่รหัสผ่านที่เดายาก';
ALTER ROLE tqm_user SET client_encoding TO 'utf8';
ALTER ROLE tqm_user SET timezone TO 'Asia/Bangkok';
GRANT ALL PRIVILEGES ON DATABASE tqm TO tqm_user;
\c tqm
GRANT ALL ON SCHEMA public TO tqm_user;
\q
```

### 4. ดึงโค้ดลงมา

```bash
sudo -u tqm git clone <URL ของ repo> /srv/tqm
cd /srv/tqm
sudo -u tqm python3 -m venv .venv
sudo -u tqm .venv/bin/pip install -r requirements.txt
sudo -u tqm npm ci --omit=dev
```

### 5. ตั้งค่า .env

```bash
sudo -u tqm cp .env.example .env
sudo -u tqm nano .env
```

ค่าที่ต้องใส่:

```ini
SECRET_KEY=<สร้างใหม่ ห้ามใช้ตัวเดียวกับเครื่องพัฒนา>
DEBUG=False
ALLOWED_HOSTS=tqm.eaksaha.co.th
CSRF_TRUSTED_ORIGINS=https://tqm.eaksaha.co.th
DATABASE_URL=postgres://tqm_user:รหัสผ่าน@localhost:5432/tqm
MEDIA_ROOT=/var/www/tqm/media
LOG_FILE=/var/log/tqm/django.log
```

สร้าง SECRET_KEY ใหม่:

```bash
.venv/bin/python -c "from django.core.management.utils import get_random_secret_key as k; print(k())"
```

```bash
sudo chmod 600 .env && sudo chown tqm:tqm .env
```

### 6. เตรียมไฟล์และฐานข้อมูล

```bash
sudo -u tqm npm run build
sudo -u tqm .venv/bin/python manage.py collectstatic --noinput
sudo -u tqm .venv/bin/python manage.py migrate
sudo -u tqm .venv/bin/python manage.py createsuperuser
```

> ⚠ **ห้ามรัน `seed_roles` บนเซิร์ฟจริง** — บัญชีทดสอบรหัส `tqm12345` จะกลายเป็นช่องโหว่ทันที

### 7. เปิด gunicorn

```bash
sudo cp deploy/tqm.service /etc/systemd/system/
sudo nano /etc/systemd/system/tqm.service   # แก้ path/ผู้ใช้ให้ตรง
sudo systemctl daemon-reload
sudo systemctl enable --now tqm
sudo systemctl status tqm                   # ต้องขึ้น active (running)
```

### 8. เปิด nginx + HTTPS

```bash
sudo cp deploy/nginx.conf /etc/nginx/sites-available/tqm
sudo nano /etc/nginx/sites-available/tqm    # แก้โดเมน
sudo ln -s /etc/nginx/sites-available/tqm /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t && sudo systemctl reload nginx

# ใบรับรอง HTTPS ฟรี
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d tqm.eaksaha.co.th
```

### 9. เปิดไฟร์วอลล์

```bash
sudo ufw allow OpenSSH
sudo ufw allow 'Nginx Full'
sudo ufw enable
```

### 10. ตรวจก่อนส่งมอบ

```bash
.venv/bin/python manage.py check --deploy
```

ต้องไม่มีคำเตือนเหลือ ถ้ามีให้แก้ก่อนเปิดใช้จริง

---

## ครั้งต่อไป — อัปเดตโค้ดใหม่

```bash
cd /srv/tqm && ./deploy/deploy.sh
```

หรือทำเองทีละขั้น:

```bash
git pull
.venv/bin/pip install -r requirements.txt
npm ci --omit=dev && npm run build
.venv/bin/python manage.py collectstatic --noinput
.venv/bin/python manage.py migrate
sudo systemctl restart tqm
```

---

## เช็กลิสต์ก่อนส่งมอบลูกค้า

- [ ] `DEBUG=False` ใน `.env` บนเซิร์ฟ
- [ ] `SECRET_KEY` เป็นค่าใหม่ ไม่ซ้ำกับเครื่องพัฒนา และไม่อยู่ใน git
- [ ] `ALLOWED_HOSTS` มีเฉพาะโดเมนจริง
- [ ] HTTPS ใช้งานได้ และ http เด้งไป https อัตโนมัติ
- [ ] **ลบบัญชีทดสอบทั้ง 7 (`req01` … `admin01`) ออกจากฐานข้อมูล**
- [ ] `python manage.py check --deploy` ผ่านหมด
- [ ] ตั้งสำรองฐานข้อมูลอัตโนมัติแล้ว (ดูด้านล่าง)
- [ ] ทดสอบล็อกอินครบทั้ง 7 ตำแหน่งบนเซิร์ฟจริง

---

## สำรองฐานข้อมูล

```bash
sudo crontab -e
```
```cron
# สำรองทุกวันตี 2 เก็บ 14 วันล่าสุด
0 2 * * * sudo -u postgres pg_dump tqm | gzip > /var/backups/tqm-$(date +\%F).sql.gz
0 3 * * * find /var/backups -name 'tqm-*.sql.gz' -mtime +14 -delete
```

> ระบบสำรองข้อมูลที่ไม่เคยลองกู้คืน = ไม่มีระบบสำรอง ทดสอบกู้คืนอย่างน้อยปีละครั้ง

กู้คืน:

```bash
gunzip -c /var/backups/tqm-2026-08-01.sql.gz | sudo -u postgres psql tqm
```

---

## เวลามีปัญหา

| อาการ | ดูตรงไหน |
|---|---|
| หน้า 502 Bad Gateway | `sudo systemctl status tqm` — gunicorn ไม่ได้รัน |
| หน้า 500 แต่ไม่บอกอะไร | `/var/log/tqm/django.log` (เพราะ `DEBUG=False` ซ่อนรายละเอียดไว้) |
| CSS ไม่ขึ้น | ลืม `npm run build` หรือ `collectstatic` |
| ฟอร์มขึ้น CSRF verification failed | `CSRF_TRUSTED_ORIGINS` ไม่มีโดเมน หรือลืมใส่ `https://` |
| แก้โค้ดแล้วไม่เปลี่ยน | ลืม `sudo systemctl restart tqm` |

ดู log สด ๆ:

```bash
sudo journalctl -u tqm -f
sudo tail -f /var/log/nginx/error.log
```
