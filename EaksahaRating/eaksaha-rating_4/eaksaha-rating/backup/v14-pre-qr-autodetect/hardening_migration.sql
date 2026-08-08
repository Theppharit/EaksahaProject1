-- ============================================================
--  กันรีวิวปลอม + ซ่อนรีวิวที่ไม่ควรนับ
--  ระบบ eTDR — Eaksaha Test Drive Rating
-- ------------------------------------------------------------
--  วิธีใช้: phpMyAdmin → ฐานข้อมูล eaksaha_rating → แท็บ SQL → วางทั้งไฟล์ → Go
--  ย้อนกลับได้ที่ hardening_migration_rollback.sql
-- ============================================================

-- ---------- 1) บันทึกว่าใครส่งรีวิวไปแล้วบ้าง ----------
-- ปัญหาเดิม: ใครถือลิงก์ QR ก็ส่งรีวิวได้ไม่จำกัด
-- พนักงานเปิด QR ตัวเองแล้วส่ง 5 ดาว 30 รอบได้ ตัวเลขทั้งระบบก็ไม่มีความหมาย
--
-- device_id = รหัสสุ่มที่ฝังไว้ในคุกกี้ของเครื่องลูกค้า
--             ล้างคุกกี้แล้วส่งใหม่ได้ จึงมีการนับต่อ IP เป็นชั้นที่สองด้วย
CREATE TABLE IF NOT EXISTS `rate_limits` (
    `id`         INT AUTO_INCREMENT PRIMARY KEY,
    `staff_id`   INT NOT NULL,
    `device_id`  VARCHAR(64) NOT NULL,
    `ip`         VARCHAR(45) NOT NULL,        -- 45 ตัวอักษร รองรับ IPv6
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX `idx_rl_device` (`device_id`, `staff_id`, `created_at`),
    INDEX `idx_rl_ip`     (`ip`, `staff_id`, `created_at`),
    INDEX `idx_rl_time`   (`created_at`),
    CONSTRAINT `fk_rl_staff` FOREIGN KEY (`staff_id`)
        REFERENCES `staff`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------- 2) ซ่อนรีวิวออกจากสถิติ ----------
-- ระบบไม่มีการแก้คะแนนแล้ว ถ้ามีรีวิวปลอมหรือข้อความหยาบคายเข้ามา
-- จะไม่มีทางแก้ข้อมูลเลย คอลัมน์ชุดนี้ให้ผู้ดูแล "ตัดออกจากสถิติ" ได้
-- โดยไม่ลบทิ้ง — ข้อความต้นฉบับยังอยู่ครบ ตรวจย้อนหลังได้เสมอ
ALTER TABLE `ratings`
    ADD COLUMN `hidden_at`     TIMESTAMP    NULL AFTER `scored_at`,
    ADD COLUMN `hidden_by`     VARCHAR(120) NULL AFTER `hidden_at`,
    ADD COLUMN `hidden_reason` VARCHAR(255) NULL AFTER `hidden_by`;

-- คิวรีสถิติทุกตัวจะมีเงื่อนไข hidden_at IS NULL จึงต้องมี index ช่วย
CREATE INDEX `idx_ratings_hidden` ON `ratings` (`hidden_at`);


-- ---------- 3) ตรวจผล ----------
SELECT
    (SELECT COUNT(*) FROM `ratings`)                          AS `รีวิวทั้งหมด`,
    (SELECT COUNT(*) FROM `ratings` WHERE `hidden_at` IS NULL) AS `ที่นับในสถิติ`,
    (SELECT COUNT(*) FROM `rate_limits`)                       AS `บันทึกการส่ง`;
