<?php
// ============================================================
//  ตั้งค่าองค์กร (แก้ไขตรงนี้)
// ============================================================
define('ORG_NAME',  'EAKSAHA GROUP');                       // ชื่อกลุ่มบริษัท
define('ORG_NAME_TH', 'เอกสหกรุ๊ป');                          // ชื่อภาษาไทย
define('ORG_TAGLINE', 'ตัวแทนจำหน่ายรถยนต์พลังงานไฟฟ้า');      // คำโปรย
define('ORG_DEPT',  'ฝ่ายพัฒนาธุรกิจและประสบการณ์ลูกค้า');     // ฝ่ายที่ดูแลระบบ
define('ORG_DEPT2', 'ศูนย์ดิจิทัลและเทคโนโลยีสารสนเทศ');       // ฝ่าย/งานที่พัฒนาระบบ

// ============================================================
//  ตั้งค่าการเชื่อมต่อฐานข้อมูล  (แก้ไขให้ตรงกับ server จริง)
// ============================================================
$db_host = 'localhost';
$db_name = 'eaksaha_rating';
$db_user = 'root';
$db_pass = '';

try {
    $pdo = new PDO(
        "mysql:host={$db_host};dbname={$db_name};charset=utf8mb4",
        $db_user,
        $db_pass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    die('เชื่อมต่อฐานข้อมูลไม่สำเร็จ: ' . $e->getMessage());
}
