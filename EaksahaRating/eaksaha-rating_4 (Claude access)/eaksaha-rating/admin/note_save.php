<?php
// ============================================================
//  ฝากข้อความถึงพนักงานขาย ติดกับรีวิวรายการหนึ่ง
// ------------------------------------------------------------
//  ใครใช้: Admin และ Manager
//  ผู้จัดการแก้คะแนนไม่ได้ แต่ต้อง "สั่งงาน" ได้ ช่องทางนี้คือช่องทางนั้น
//
//  พนักงานขายเจ้าของรีวิวจะเห็นเป็นแจ้งเตือนตอนล็อกอิน
//  แล้วกดรับทราบได้ที่หน้า "ข้อความจากหัวหน้า"
//
//  ข้อความที่ฝากแล้วลบไม่ได้โดยตั้งใจ — เป็นบันทึกการสั่งงาน
//  ถ้าลบได้ ก็เถียงกันได้ภายหลังว่าเคยบอกหรือไม่เคยบอก
// ============================================================

require '../conn/config.php';
require 'includes/auth.php';
// ทุกคำขอที่เปลี่ยนแปลงข้อมูลต้องมาจากหน้าจอของเราจริง
csrf_check(true);

require_perm('note', true);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function noteFail(int $code, string $msg): void
{
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    noteFail(405, 'ต้องส่งแบบ POST เท่านั้น');
}

$ratingId = (int) ($_POST['rating_id'] ?? 0);
$note     = trim($_POST['note'] ?? '');

if ($ratingId <= 0)  noteFail(400, 'ไม่พบรีวิวรายการนี้');
if ($note === '')    noteFail(400, 'กรุณาพิมพ์ข้อความที่ต้องการฝาก');

if (mb_strlen($note, 'UTF-8') > 500) {
    $note = mb_substr($note, 0, 500, 'UTF-8');
}

// หาว่ารีวิวนี้เป็นของพนักงานคนไหน — ข้อความจะได้ส่งถึงคนที่ถูกต้อง
$st = $pdo->prepare('SELECT r.id, r.staff_id, s.name FROM ratings r JOIN staff s ON s.id = r.staff_id WHERE r.id = ?');
$st->execute([$ratingId]);
$row = $st->fetch();
if (!$row) noteFail(404, 'ไม่พบรีวิวรายการนี้');

try {
    $ins = $pdo->prepare(
        'INSERT INTO review_notes (rating_id, staff_id, author_id, author_name, author_role, note)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $ins->execute([
        $ratingId,
        (int) $row['staff_id'],
        (int) ($_SESSION['admin_id'] ?? 0),
        user_display(),
        user_role(),
        $note,
    ]);
} catch (PDOException $e) {
    noteFail(500, 'บันทึกไม่สำเร็จ — อาจยังไม่ได้รัน roles_migration.sql');
}

// จำนวนข้อความทั้งหมดของรีวิวนี้ ไว้อัปเดตตัวเลขบนหน้าจอ
$cnt = $pdo->prepare('SELECT COUNT(*) FROM review_notes WHERE rating_id = ?');
$cnt->execute([$ratingId]);

echo json_encode([
    'ok'    => true,
    'count' => (int) $cnt->fetchColumn(),
    'to'    => $row['name'],
], JSON_UNESCAPED_UNICODE);
