-- ============================================================
--  ระบบสิทธิ์ผู้ใช้ 3 ระดับ + โน้ตแจ้งเตือนพนักงาน
--  ระบบ eTDR — Eaksaha Test Drive Rating
-- ------------------------------------------------------------
--  วิธีใช้
--    1. phpMyAdmin → ฐานข้อมูล eaksaha_rating → แท็บ SQL → วางทั้งไฟล์ → Go
--    2. ย้อนกลับได้ที่ roles_migration_rollback.sql
--
--  สิทธิ์ 3 ระดับ
--    admin   — ทำได้ทุกอย่าง แก้คะแนน จัดการพนักงาน แบรนด์ และผู้ใช้
--    manager — เห็นได้ทุกอย่าง ส่งออกไฟล์ได้ ฝากโน้ตถึงพนักงานได้
--              แต่แก้ไขข้อมูลอะไรไม่ได้เลย
--    sales   — เห็นเฉพาะคะแนนและรีวิวของตัวเอง กับโน้ตที่หัวหน้าฝากไว้
-- ============================================================

-- ---------- 1) เพิ่มสิทธิ์ให้ตารางผู้ใช้ ----------
ALTER TABLE `admin_users`
    ADD COLUMN `role` ENUM('admin','manager','sales') NOT NULL DEFAULT 'admin' AFTER `password`,
    -- ผูกบัญชี sales เข้ากับพนักงานขายในตาราง staff
    -- ระบบใช้ค่านี้ตัดสินว่า "ของตัวเอง" คือรีวิวของใคร
    ADD COLUMN `staff_id` INT NULL AFTER `role`,
    ADD COLUMN `display_name` VARCHAR(120) NULL AFTER `staff_id`,
    -- ปิดการใช้งานชั่วคราวได้ โดยไม่ต้องลบบัญชีทิ้ง (ประวัติโน้ตจะได้ไม่หาย)
    ADD COLUMN `is_active` TINYINT(1) NOT NULL DEFAULT 1 AFTER `display_name`;

ALTER TABLE `admin_users`
    ADD CONSTRAINT `fk_admin_staff` FOREIGN KEY (`staff_id`)
        REFERENCES `staff`(`id`) ON DELETE SET NULL ON UPDATE CASCADE;

CREATE INDEX `idx_admin_role` ON `admin_users` (`role`);

-- บัญชีเดิมทั้งหมดถือเป็น admin ตามเดิม จะได้ไม่มีใครล็อกอินไม่ได้กะทันหัน
UPDATE `admin_users` SET `role` = 'admin' WHERE `role` IS NULL OR `role` = '';


-- ---------- 2) ตารางโน้ตที่ฝากถึงพนักงาน ----------
-- Manager และ Admin ฝากโน้ตติดรีวิวรายการใดรายการหนึ่งได้
-- พนักงานขายเจ้าของรีวิวจะเห็นเป็นแจ้งเตือนตอนล็อกอิน แล้วกดรับทราบ
CREATE TABLE IF NOT EXISTS `review_notes` (
    `id`          INT AUTO_INCREMENT PRIMARY KEY,
    `rating_id`   INT NOT NULL,
    `staff_id`    INT NOT NULL,              -- คนที่ต้องเห็นโน้ตนี้
    `author_id`   INT NULL,                  -- คนฝาก (NULL ถ้าบัญชีถูกลบ)
    `author_name` VARCHAR(120) NOT NULL,     -- เก็บชื่อไว้ด้วย เผื่อบัญชีถูกลบ
    `author_role` VARCHAR(20)  NOT NULL,
    `note`        TEXT NOT NULL,
    `is_read`     TINYINT(1) NOT NULL DEFAULT 0,
    `read_at`     TIMESTAMP NULL,
    `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT `fk_note_rating` FOREIGN KEY (`rating_id`)
        REFERENCES `ratings`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_note_staff` FOREIGN KEY (`staff_id`)
        REFERENCES `staff`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_note_author` FOREIGN KEY (`author_id`)
        REFERENCES `admin_users`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX `idx_note_staff_read` ON `review_notes` (`staff_id`, `is_read`);
CREATE INDEX `idx_note_rating`     ON `review_notes` (`rating_id`);


-- ---------- 3) ตรวจผล ----------
SELECT `username`, `role`, `staff_id`, `display_name`, `is_active`
FROM `admin_users`
ORDER BY `role`, `username`;


-- ============================================================
--  ขั้นตอนต่อไป (ทำในระบบ ไม่ต้องรัน SQL)
-- ------------------------------------------------------------
--  ล็อกอินด้วยบัญชี admin เดิม แล้วไปที่เมนู "ผู้ใช้งานระบบ"
--  เพื่อเพิ่มบัญชี manager และ sales
--
--  บัญชี sales ต้องเลือก "พนักงานขาย" ที่ผูกด้วยเสมอ
--  ไม่งั้นจะล็อกอินได้แต่ไม่เห็นรีวิวของตัวเอง เพราะระบบไม่รู้ว่าเป็นใคร
-- ============================================================
