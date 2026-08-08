-- ============================================================
--  ย้อนระบบสิทธิ์ผู้ใช้กลับก่อนมี 3 บทบาท
-- ------------------------------------------------------------
--  คำเตือน: โน้ตที่เคยฝากถึงพนักงานทั้งหมดจะถูกลบทิ้ง
--           บัญชี manager และ sales จะกลายเป็น admin ทั้งหมด
--           ถ้าไม่ต้องการแบบนั้น ให้ลบบัญชีเหล่านั้นทิ้งก่อนรันไฟล์นี้
-- ============================================================

DROP TABLE IF EXISTS `review_notes`;

ALTER TABLE `admin_users` DROP FOREIGN KEY `fk_admin_staff`;
DROP INDEX `idx_admin_role` ON `admin_users`;

ALTER TABLE `admin_users`
    DROP COLUMN `role`,
    DROP COLUMN `staff_id`,
    DROP COLUMN `display_name`,
    DROP COLUMN `is_active`;

SELECT `username` FROM `admin_users` ORDER BY `username`;
