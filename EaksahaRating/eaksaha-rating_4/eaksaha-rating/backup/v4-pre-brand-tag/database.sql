-- ============================================================
--  EAKSAHA GROUP — ระบบประเมินความพึงพอใจเซลล์ขายรถ EV
--  โครงสร้างฐานข้อมูล + ข้อมูลตั้งต้น
--  รองรับ MySQL / MariaDB (utf8mb4)
-- ============================================================

SET NAMES utf8mb4;
SET time_zone = '+07:00';

CREATE DATABASE IF NOT EXISTS `eaksaha_rating`
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `eaksaha_rating`;

-- ---------- ผู้ดูแลระบบ ----------
CREATE TABLE IF NOT EXISTS `admin_users` (
    `id`       INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50)  NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- แบรนด์รถ EV ----------
CREATE TABLE IF NOT EXISTS `brands` (
    `id`         INT AUTO_INCREMENT PRIMARY KEY,
    `name`       VARCHAR(80)  NOT NULL,
    `code`       VARCHAR(40)  NOT NULL UNIQUE,
    `color`      VARCHAR(20)  DEFAULT '#E10600',
    `sort_order` INT DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- เซลล์ (พนักงานขาย) ----------
CREATE TABLE IF NOT EXISTS `staff` (
    `id`       INT AUTO_INCREMENT PRIMARY KEY,
    `code`     VARCHAR(20)  NOT NULL UNIQUE,
    `name`     VARCHAR(150) NOT NULL,
    `position` VARCHAR(150) NOT NULL,
    `brand_id` INT NULL,
    `photo`    VARCHAR(255) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_staff_brand` FOREIGN KEY (`brand_id`)
        REFERENCES `brands`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- คะแนนประเมิน ----------
CREATE TABLE IF NOT EXISTS `ratings` (
    `id`         INT AUTO_INCREMENT PRIMARY KEY,
    `staff_id`   INT NOT NULL,
    `score`      TINYINT NOT NULL,
    `feedback`   TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_rating_staff` FOREIGN KEY (`staff_id`)
        REFERENCES `staff`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX `idx_ratings_created` ON `ratings` (`created_at`);
CREATE INDEX `idx_ratings_staff`   ON `ratings` (`staff_id`);

-- ============================================================
--  ข้อมูลตั้งต้น
-- ============================================================

-- ผู้ดูแลระบบเริ่มต้น
--   username : admin
--   password : admin1234   (กรุณาเปลี่ยนหลังเข้าใช้งานครั้งแรก)
INSERT INTO `admin_users` (`username`, `password`) VALUES
    ('admin', '$2y$12$2ADzVzl/L9o3T0daEU6VpOvCmDd4pfrpZAg2xc.WuO9bjJ2z1FWIG');

-- แบรนด์รถทั้ง 9
INSERT INTO `brands` (`name`, `code`, `color`, `sort_order`) VALUES
    ('DEEPAL',          'deepal',        '#FF3B30', 1),
    ('GEELY',           'geely',         '#2E8FE6', 2),
    ('AION',            'aion',          '#12BFBF', 3),
    ('OMODA & JAECOO',  'omoda_jaecoo',  '#17B57E', 4),
    ('WULING',          'wuling',        '#FF5A2C', 5),
    ('CHERY',           'chery',         '#E24A62', 6),
    ('GWM',             'gwm',           '#FF8A2B', 7),
    ('FORD',            'ford',          '#3D74E6', 8),
    ('NISSAN',          'nissan',        '#F5455A', 9);

-- ตัวอย่างเซลล์ (ลบทิ้งได้ / จัดการผ่านหลังบ้าน)
INSERT INTO `staff` (`code`, `name`, `position`, `brand_id`, `photo`) VALUES
    ('emp001', 'สมชาย ใจดี',   'ที่ปรึกษาการขาย (Sales Consultant)', 1, NULL),
    ('emp002', 'พิมพ์ชนก แสงทอง', 'ที่ปรึกษาการขายอาวุโส',              2, NULL),
    ('emp003', 'ธนกร วัฒนชัย',  'ที่ปรึกษาการขาย (Sales Consultant)', 5, NULL);
