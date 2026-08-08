-- ============================================================
--  อัปเดตสีประจำแบรนด์ให้ตรงกับสีโลโก้จริง
--  ระบบประเมินความพึงพอใจ EAKSAHA GROUP
--  สร้างเมื่อ: 2026-08-06
-- ------------------------------------------------------------
--  วิธีใช้
--    1. เปิด phpMyAdmin → เลือกฐานข้อมูล eaksaha_rating
--    2. แท็บ SQL → วางไฟล์นี้ทั้งไฟล์ → กด Go
--    3. ถ้าอยากย้อนกลับ ให้เปิดไฟล์ rollback_brand_colors.sql
--
--  หมายเหตุ: สคริปต์นี้จะสำรองค่าสีเดิมไว้ในตาราง brands_color_backup
--            ให้อัตโนมัติก่อนแก้ ทำซ้ำได้ปลอดภัย (ไม่เขียนทับตัวสำรองแรก)
-- ============================================================

-- ---------- 1) สำรองค่าสีเดิม ----------
CREATE TABLE IF NOT EXISTS `brands_color_backup` (
    `brand_id`   INT PRIMARY KEY,
    `code`       VARCHAR(40)  NOT NULL,
    `name`       VARCHAR(80)  NOT NULL,
    `old_color`  VARCHAR(20)  NULL,
    `backed_up_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- INSERT IGNORE = ถ้าเคยสำรองไว้แล้วจะไม่ทับของเดิม (กันสำรองซ้ำหลังแก้ไปแล้ว)
INSERT IGNORE INTO `brands_color_backup` (`brand_id`, `code`, `name`, `old_color`)
SELECT `id`, `code`, `name`, `color` FROM `brands`;


-- ---------- 2) อัปเดตสีตามโลโก้จริง ----------
--
--  ตารางอ้างอิง (ที่มาของแต่ละค่า)
--  ┌──────────────────┬───────────┬──────────────────────────────────────────┐
--  │ แบรนด์            │ สีใหม่     │ ที่มา                                      │
--  ├──────────────────┼───────────┼──────────────────────────────────────────┤
--  │ FORD             │ #00095B   │ Ford Blue (Pantone 294) — Brand Standards │
--  │ NISSAN           │ #C3002F   │ Nissan Red — brand guidelines             │
--  │ DEEPAL           │ #0057FF   │ สีอ้างอิงแบรนด์ (ชื่อจีน 深蓝 = น้ำเงินลึก)   │
--  │ GEELY            │ #0160E6   │ Blue Ribbon — geely.com                   │
--  │ CHERY            │ #E90019   │ Cadmium Red — Chery color codes           │
--  │ GWM              │ #E60012   │ GWM Red — สีองค์กรหลัก                     │
--  │ WULING           │ #E2231A   │ Wuling Classic Red (โลโก้ W แดง) *ประมาณ  │
--  │ AION             │ #00A0A0   │ ไม่มีค่าทางการเผยแพร่ — คงค่าเดิม (เขียวน้ำทะเล)│
--  │ OMODA & JAECOO   │ #0B7A5B   │ ไม่มีค่าทางการเผยแพร่ — คงค่าเดิม (เขียวเข้ม) │
--  └──────────────────┴───────────┴──────────────────────────────────────────┘
--
--  * ค่าที่ทำเครื่องหมายไว้คือค่าประมาณจากงานสื่อสารแบรนด์
--    เพราะบริษัทไม่ได้เผยแพร่ brand guideline สาธารณะ

UPDATE `brands` SET `color` = '#00095B' WHERE `code` = 'ford';
UPDATE `brands` SET `color` = '#C3002F' WHERE `code` = 'nissan';
UPDATE `brands` SET `color` = '#0057FF' WHERE `code` = 'deepal';
UPDATE `brands` SET `color` = '#0160E6' WHERE `code` = 'geely';
UPDATE `brands` SET `color` = '#00A0A0' WHERE `code` = 'aion';
UPDATE `brands` SET `color` = '#0B7A5B' WHERE `code` = 'omoda_jaecoo';
UPDATE `brands` SET `color` = '#E2231A' WHERE `code` = 'wuling';
UPDATE `brands` SET `color` = '#E90019' WHERE `code` = 'chery';
UPDATE `brands` SET `color` = '#E60012' WHERE `code` = 'gwm';


-- ---------- 3) ตรวจผลลัพธ์ ----------
SELECT b.`sort_order`, b.`name`, b.`code`,
       k.`old_color` AS `สีเดิม`, b.`color` AS `สีใหม่`
FROM `brands` b
LEFT JOIN `brands_color_backup` k ON k.`brand_id` = b.`id`
ORDER BY b.`sort_order`, b.`id`;


-- ============================================================
--  ทางเลือก: ถ้าอยากให้ "แยกแบรนด์ด้วยสายตาได้ง่ายขึ้น"
-- ------------------------------------------------------------
--  ปัญหา: NISSAN / CHERY / GWM / WULING ใช้สีแดงใกล้เคียงกันมาก
--         (#C3002F / #E90019 / #E60012 / #E2231A)
--         ในตารางรายงานจะดูแทบไม่ออกว่าจุดสีไหนเป็นแบรนด์ไหน
--
--  ถ้าอยากคงเอกลักษณ์แบรนด์ไว้แต่ให้แยกออกจากกันได้ ให้ลบ -- ข้างหน้า
--  4 บรรทัดล่างนี้แล้วรันซ้ำ (เป็นการเลื่อนโทนสีในตระกูลเดิมของแต่ละแบรนด์)
-- ============================================================
-- UPDATE `brands` SET `color` = '#C3002F' WHERE `code` = 'nissan';   -- แดงเลือดหมู (คงเดิม)
-- UPDATE `brands` SET `color` = '#A6192E' WHERE `code` = 'chery';    -- แดงเข้มอมน้ำตาล
-- UPDATE `brands` SET `color` = '#E60012' WHERE `code` = 'gwm';      -- แดงสด (คงเดิม)
-- UPDATE `brands` SET `color` = '#F04E23' WHERE `code` = 'wuling';   -- แดงอมส้ม
