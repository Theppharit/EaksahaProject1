# โครงสร้างโปรเจกต์ TQM System

เปิดไฟล์นี้เวลาลืมว่าอะไรอยู่ตรงไหน

## ภาพรวม

```
TQM_System/
├─ manage.py              ตัวสั่งงาน Django (ไม่ต้องแก้)
├─ package.json           คำสั่ง npm + รายการ package
├─ postcss.config.mjs     ตัวเชื่อม Tailwind
├─ requirements.txt       รายการ package Python
├─ .gitignore
├─ db.sqlite3             ฐานข้อมูล (ไม่ขึ้น git)
├─ .venv/                 Python env (ไม่ขึ้น git)
├─ node_modules/          Node package (ไม่ขึ้น git)
│
└─ project/               ★ โค้ดระบบทั้งหมดอยู่ในนี้
   ├─ config/             ตั้งค่า Django
   ├─ templates/          HTML
   ├─ src/                Tailwind ต้นทาง
   ├─ assets/             CSS/JS/รูป ที่เสิร์ฟจริง
   ├─ docs/               คู่มือ
   └─ (app ต่าง ๆ มาทีหลัง)
```

**3 ไฟล์ที่ราก ย้ายไม่ได้** — `manage.py`, `package.json`, `postcss.config.mjs`
เพราะ Django กับ npm ใช้ตำแหน่งไฟล์พวกนี้ระบุว่ารากโปรเจกต์อยู่ไหน

## อยากแก้อะไร ไปไฟล์ไหน

| อยากทำ | ไปที่ |
|---|---|
| เปลี่ยนสี / ฟอนต์ทั้งระบบ | `project/src/theme.css` |
| แก้หน้าตาหน้า login | `project/templates/login.html` + `src/components/auth.css` |
| เพิ่ม app ใหม่ | `project/config/settings.py` → `INSTALLED_APPS` |
| เพิ่มหน้าใหม่ (URL) | `project/config/urls.py` → แล้วไป `urls.py` ของ app |
| เปลี่ยนภาษา / เขตเวลา | `project/config/settings.py` |
| เพิ่มรูป / ไอคอน | `project/assets/img/` |

## วิธีตามหาโค้ดของหน้าใดหน้าหนึ่ง

เริ่มจาก URL เสมอ — ใช้ได้ทุกครั้ง

```
เห็นหน้า /jobs/21/assign/
 1. project/config/urls.py        → "jobs/" ส่งต่อไปไหน
 2. project/jobs/urls.py          → "<pk>/assign/" เรียก view ตัวไหน
 3. project/jobs/views.py         → โค้ดอยู่ตรงนี้
 4. project/jobs/templates/...    → HTML อยู่ตรงนี้
```

## คำสั่งประจำวัน

เปิด 2 หน้าต่าง PowerShell:

```powershell
# หน้าต่างที่ 1 — Tailwind (ปล่อยค้างไว้)
npm run dev

# หน้าต่างที่ 2 — Django
.venv\Scripts\Activate.ps1
python manage.py runserver
```

ก่อน deploy:

```powershell
npm run build
python manage.py collectstatic
```

## กฎที่ทำให้โค้ดไม่เน่า

1. **ตรรกะธุรกิจอยู่ `services.py`** ไม่ใช่ `views.py` — view ยาวเกิน 20 บรรทัดคือสัญญาณว่าต้องแตก
2. **แบ่ง app ตามโดเมนธุรกิจ** (งาน, รถ, บัญชี) ไม่ใช่ตามตำแหน่งผู้ใช้ — เพราะ "งาน 1 ใบ" ถูกใช้โดยหลายตำแหน่ง
3. **ตั้งชื่อโทเคนสีตามหน้าที่** `--color-status-done` ไม่ใช่ `--color-green`
4. **สิทธิ์ต้องเช็คฝั่ง server เสมอ** — ซ่อนปุ่มด้วย CSS ไม่ใช่การป้องกัน ผู้ใช้กด F12 ลบ class ได้
5. **query ที่ใช้ซ้ำยกขึ้นเป็น Manager** — `Job.objects.pending()` แทนการพิมพ์ `filter(...)` กระจาย

## ข้อควรระวังที่เจอบ่อย

- **แก้ CSS แล้วไม่เปลี่ยน** → ลืมรัน `npm run dev` หรือยังไม่ได้กด Ctrl+F5
- **ไฟล์ใน `src/` ที่ไม่ได้ `@import` ใน `input.css` จะไม่ถูกรวม** — สร้างไฟล์ใหม่แล้วอย่าลืมไปเปิด import
- **class ที่ต่อจาก string ใน JS จะไม่ถูกสร้าง** — เขียน `"badge badge-done"` เต็ม ๆ อย่าเขียน `"badge-" + status`
- **`python manage.py` ใช้ไม่ได้** → ลืม activate `.venv`
