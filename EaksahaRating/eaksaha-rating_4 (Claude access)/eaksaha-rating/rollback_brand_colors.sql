-- ============================================================
--  ย้อนสีแบรนด์กลับเป็นค่าเดิม
--  ใช้คู่กับ update_brand_colors.sql
-- ------------------------------------------------------------
--  วิธีใช้: phpMyAdmin → ฐานข้อมูล eaksaha_rating → แท็บ SQL → วาง → Go
--  เงื่อนไข: ต้องเคยรัน update_brand_colors.sql มาก่อน
--           (เพราะค่าสีเดิมถูกเก็บไว้ในตาราง brands_color_backup)
-- ============================================================

UPDATE `brands` b
JOIN `brands_color_backup` k ON k.`brand_id` = b.`id`
SET b.`color` = k.`old_color`
WHERE k.`old_color` IS NOT NULL;

-- ตรวจผล
SELECT b.`sort_order`, b.`name`, b.`code`, b.`color` AS `สีปัจจุบัน`
FROM `brands` b
ORDER BY b.`sort_order`, b.`id`;

-- ถ้าต้องการล้างตารางสำรองทิ้งหลังย้อนกลับเรียบร้อยแล้ว ให้ลบ -- ข้างหน้าบรรทัดนี้
-- DROP TABLE `brands_color_backup`;
