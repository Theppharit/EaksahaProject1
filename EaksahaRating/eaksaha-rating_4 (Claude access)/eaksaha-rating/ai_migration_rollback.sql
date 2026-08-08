-- ============================================================
--  ย้อนโครงสร้างตาราง ratings กลับก่อนใส่ AI
-- ------------------------------------------------------------
--  คำเตือน: รีวิวที่ยังไม่มีดาว (ai_status = 'pending') จะถูกลบทิ้ง
--           เพราะคอลัมน์ score กลับไปเป็น NOT NULL เหมือนเดิม
--           ถ้าอยากเก็บไว้ ให้ export ตาราง ratings ออกไปก่อนรันไฟล์นี้
-- ============================================================

DELETE FROM `ratings` WHERE `score` IS NULL;

DROP INDEX `idx_ratings_ai_status` ON `ratings`;

ALTER TABLE `ratings`
    DROP COLUMN `ai_score`,
    DROP COLUMN `ai_confidence`,
    DROP COLUMN `ai_reason`,
    DROP COLUMN `ai_model`,
    DROP COLUMN `ai_status`,
    DROP COLUMN `ai_attempts`,
    DROP COLUMN `scored_at`;

ALTER TABLE `ratings` MODIFY COLUMN `score` TINYINT NOT NULL;

SELECT COUNT(*) AS `จำนวนรีวิวที่เหลือ` FROM `ratings`;
