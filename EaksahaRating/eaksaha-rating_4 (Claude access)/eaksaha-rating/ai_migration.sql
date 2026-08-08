-- ============================================================
--  เพิ่มความสามารถ "ให้ดาวด้วย AI" ให้ตาราง ratings
--  ระบบประเมินความพึงพอใจ EAKSAHA GROUP
-- ------------------------------------------------------------
--  วิธีใช้
--    1. phpMyAdmin → เลือกฐานข้อมูล eaksaha_rating
--    2. แท็บ SQL → วางไฟล์นี้ทั้งไฟล์ → Go
--    3. ย้อนกลับได้ที่ไฟล์ ai_migration_rollback.sql
--
--  แนวคิดของโครงสร้าง
--    score      = ดาวที่ระบบใช้จริง (กราฟ/รายงานอ่านคอลัมน์นี้)
--                 ตอนบันทึกครั้งแรกจะยังว่าง (NULL) เพราะ AI ยังไม่ได้อ่าน
--    ai_score   = ดาวที่ AI ให้ เก็บแยกไว้เพื่อตรวจย้อนหลัง
--                 ถ้าผู้ดูแลแก้ score ทีหลัง ค่านี้จะยังเป็นของเดิมให้เทียบได้
--    ai_reason  = เหตุผลที่ AI ให้ดาวนั้น — สำคัญมาก เพราะนี่คือคะแนน
--                 ที่เอาไปประเมินคนจริงๆ ต้องอธิบายได้ว่าทำไม
-- ============================================================

-- ---------- 1) ให้ score ว่างได้ ----------
-- เดิมบังคับ NOT NULL แต่ตอนนี้ตอนลูกค้ากดส่ง เรายังไม่รู้ดาว
-- ต้องบันทึกข้อความไว้ก่อนแล้วค่อยให้ AI อ่านทีหลัง ลูกค้าจะได้ไม่ต้องรอ
ALTER TABLE `ratings` MODIFY COLUMN `score` TINYINT NULL;

-- ---------- 2) คอลัมน์ฝั่ง AI ----------
ALTER TABLE `ratings`
    ADD COLUMN `ai_score`      TINYINT       NULL AFTER `score`,
    ADD COLUMN `ai_confidence` DECIMAL(3,2)  NULL AFTER `ai_score`,
    ADD COLUMN `ai_reason`     VARCHAR(255)  NULL AFTER `ai_confidence`,
    ADD COLUMN `ai_model`      VARCHAR(60)   NULL AFTER `ai_reason`,
    ADD COLUMN `ai_status`     ENUM('pending','done','manual') NOT NULL DEFAULT 'pending' AFTER `ai_model`,
    ADD COLUMN `ai_attempts`   TINYINT       NOT NULL DEFAULT 0 AFTER `ai_status`,
    ADD COLUMN `scored_at`     TIMESTAMP     NULL AFTER `ai_attempts`;

-- ค้นหารายการที่ยังรอให้ดาวได้เร็ว
CREATE INDEX `idx_ratings_ai_status` ON `ratings` (`ai_status`);

-- ---------- 3) รีวิวเก่าที่มีดาวอยู่แล้ว ----------
-- ข้อมูลชุดเดิมคนกดดาวเอง ไม่ได้มาจาก AI จึงทำเครื่องหมายเป็น manual
-- เพื่อไม่ให้ตัวลองใหม่ไปหยิบมาให้ AI อ่านซ้ำ
UPDATE `ratings`
SET `ai_status` = 'manual'
WHERE `score` IS NOT NULL;

-- ---------- 4) ตรวจผล ----------
SELECT `ai_status`, COUNT(*) AS `จำนวน`
FROM `ratings`
GROUP BY `ai_status`;
