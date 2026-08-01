# TQM System — โครงสร้าง CSS

Tailwind CSS v4 (npm + PostCSS) · ระบบจัดการรถสไลด์และจองคิวรถยก · EAKSAHA GROUP

## คำสั่งประจำวัน

```powershell
cd F:\CustomerCoding\01-EaksahaGroup\01-TQM_system\TQM_System
npm run dev     # ระหว่างทำงาน — เฝ้าดูไฟล์ แก้แล้วอัปเดตทันที
npm run build   # ก่อน deploy — บีบอัดไฟล์
```

ผลลัพธ์ → `assets/css/tailwind.css` (ไฟล์นี้เครื่องสร้างให้ **ห้ามแก้มือ**)

## โครงสร้างไฟล์

```
TQM_System/
├─ login.html                หน้าเข้าสู่ระบบ
├─ login.cdn-backup.html     ไฟล์เดิมที่ใช้ Play CDN (เก็บไว้อ้างอิง ลบได้)
├─ preview.html              ★ หน้ารวม component ทั้งหมด
├─ pages/                    หน้า dashboard 7 ตำแหน่ง
│  ├─ booker.html            ผู้จองคิว
│  ├─ manager.html           ผู้จัดการ
│  ├─ owner.html             เจ้าของรถ
│  ├─ accounting.html        งานบัญชี
│  ├─ executive.html         ผู้บริหาร
│  ├─ admin.html             แอดมิน
│  └─ sales.html             เซลล์
├─ src/                      ★ แก้ CSS ที่นี่เท่านั้น
│  ├─ input.css              ตัวรวมไฟล์ (ไม่ต้องเขียน CSS ตรงนี้)
│  ├─ theme.css              สี ฟอนต์ ความโค้ง — แก้ที่นี่เปลี่ยนทั้งระบบ
│  ├─ utilities.css          utility ที่สร้างเพิ่มเอง
│  ├─ base.css               สไตล์เริ่มต้นของ tag
│  ├─ components/            ของกลางที่ทุกตำแหน่งใช้
│  │  ├─ layout.css          sidebar / topbar / โครงหน้า
│  │  ├─ button.css
│  │  ├─ card.css            การ์ด + การ์ดตัวเลขสรุป
│  │  ├─ badge.css           ป้ายสถานะงาน
│  │  ├─ table.css
│  │  ├─ form.css
│  │  ├─ modal.css           modal + alert
│  │  └─ auth.css            เฉพาะหน้า login
│  └─ roles/                 ของเฉพาะแต่ละตำแหน่ง (ไฟล์ละ ~20 บรรทัด)
│     ├─ booker.css  manager.css  owner.css  accounting.css
│     └─ executive.css  admin.css  sales.css
└─ assets/
   ├─ css/tailwind.css       ← ไฟล์ผลลัพธ์ (สร้างอัตโนมัติ)
   ├─ js/app.js              สคริปต์กลาง (เมนูมือถือ / modal / switch)
   └─ img/
```

## หลักคิด: ทำไม 7 ตำแหน่งใช้ CSS ไฟล์เดียว

ทั้ง 7 ตำแหน่งใช้ปุ่ม ตาราง ฟอร์ม การ์ด **ชุดเดียวกัน** ต่างกันแค่สีประจำตำแหน่ง
กับเนื้อหาในหน้า ซึ่งเป็นเรื่องของ Django ไม่ใช่ CSS

สีประจำตำแหน่งคุมด้วย `data-role` ที่ `<body>` เพียงจุดเดียว:

```html
<body data-role="manager">   <!-- ทั้งหน้าเปลี่ยนเป็นสีม่วง -->
```

เพราะทุก component เรียกใช้ `var(--color-accent)` และไฟล์ใน `src/roles/`
แค่ override ตัวแปรตัวนั้น

| ตำแหน่ง | data-role | สี |
|---|---|---|
| ผู้จองคิว | `booker` | ฟ้า `#0284c7` |
| ผู้จัดการ | `manager` | ม่วง `#7c3aed` |
| เจ้าของรถ | `owner` | อำพัน `#d97706` |
| งานบัญชี | `accounting` | เขียว `#059669` |
| ผู้บริหาร | `executive` | คราม `#4f46e5` |
| แอดมิน | `admin` | แดงแบรนด์ `#e11d2a` |
| เซลล์ | `sales` | ส้ม `#ea580c` |

ลองเปิด `preview.html` แล้วกดสลับตำแหน่งดูได้เลย

## กฎการทำงาน

1. **แก้สี/ฟอนต์** → `src/theme.css` เท่านั้น อย่าใส่สีดิบ (`#e11d2a`) ในไฟล์อื่น
2. **ตั้งชื่อโทเคนตามหน้าที่** เช่น `--color-status-done` ไม่ใช่ `--color-green`
3. **เจอ pattern ซ้ำครั้งที่ 3** ค่อยยกขึ้นเป็น component — ซ้ำ 2 ครั้งปล่อยไว้ก่อน
4. **ของที่ตำแหน่งเดียวใช้** เท่านั้นถึงจะไปอยู่ `src/roles/` ถ้าสงสัยให้ใส่ `components/`
5. **ห้ามแก้ `assets/css/tailwind.css`** งานจะหายตอน save ครั้งถัดไป

## ข้อควรระวังเรื่องสิทธิ์

CSS ซ่อนของได้แค่จากสายตา — ผู้ใช้กด F12 ลบ class `hidden` ปุ่มก็โผล่ทันที
ต้องกันที่ฝั่ง server ด้วยเสมอ:

```django
{% if perms.jobs.delete_job %}<button class="btn btn-danger">ลบงาน</button>{% endif %}
```

```python
@permission_required("jobs.delete_job")
def delete_job(request, pk): ...
```

## เมื่อย้ายขึ้น Django

```python
# settings.py
STATICFILES_DIRS = [BASE_DIR / "assets"]
STATIC_URL = "/static/"
STATIC_ROOT = BASE_DIR / "staticfiles"
```

```django
{% load static %}
<link rel="stylesheet" href="{% static 'css/tailwind.css' %}">
<script src="{% static 'js/app.js' %}"></script>
```

`@source "../"` ใน `input.css` สแกน `templates/` ของทุกแอปให้อัตโนมัติอยู่แล้ว
ก่อน deploy: `npm run build` → แล้วค่อย `python manage.py collectstatic`

## ข้อควรระวังเวลาเขียน JS

Tailwind อ่านไฟล์แบบข้อความล้วน มันมองไม่เห็นชื่อคลาสที่ต่อจาก string:

```js
// ❌ class จะไม่ถูกสร้าง สีไม่ขึ้น
el.className = "text-" + color;

// ✅ เขียนเต็ม
el.className = isDone ? "badge badge-done" : "badge badge-wait";
```
