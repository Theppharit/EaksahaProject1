<?php
// ============================================================
//  ซ่อน / เอากลับ รีวิวหนึ่งรายการ — เฉพาะผู้ดูแลระบบ
// ------------------------------------------------------------
//  ทำไมต้องมี: ระบบไม่มีการแก้คะแนนแล้ว ถ้ามีรีวิวปลอมหรือข้อความ
//  หยาบคายหลุดเข้ามา จะไม่มีทางแก้ข้อมูลเลยสักทาง
//
//  "ซ่อน" ไม่ใช่ "ลบ"
//    • ข้อความต้นฉบับยังอยู่ครบ ตรวจย้อนหลังได้เสมอ
//    • บันทึกไว้ด้วยว่าใครซ่อน เมื่อไหร่ เพราะอะไร
//    • ทุกสถิติ กราฟ และไฟล์ที่ส่งออก จะไม่นับรายการที่ซ่อน
//
//  ตั้งใจให้เป็นสิทธิ์ของ admin เท่านั้น เพราะการตัดข้อมูลออกจากสถิติ
//  มีผลต่อคะแนนของพนักงานโดยตรง ผู้จัดการดูได้แต่ทำเองไม่ได้
// ============================================================

require '../conn/config.php';
require 'includes/auth.php';
csrf_check(true);
require_perm('manage_users', true);   // ระดับเดียวกับผู้ดูแลระบบ

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function rhFail(int $code, string $msg): void
{
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST')  rhFail(405, 'ต้องส่งแบบ POST เท่านั้น');
if (!hidden_columns_ready($pdo))            rhFail(400, 'ยังไม่ได้รัน hardening_migration.sql');

$id     = (int) ($_POST['id'] ?? 0);
$action = $_POST['action'] ?? 'hide';
$reason = trim($_POST['reason'] ?? '');

if ($id <= 0) rhFail(400, 'ไม่พบรีวิวรายการนี้');

$st = $pdo->prepare('SELECT id, hidden_at FROM ratings WHERE id = ?');
$st->execute([$id]);
$row = $st->fetch();
if (!$row) rhFail(404, 'ไม่พบรีวิวรายการนี้');

if ($action === 'unhide') {
    $pdo->prepare('UPDATE ratings SET hidden_at = NULL, hidden_by = NULL, hidden_reason = NULL WHERE id = ?')
        ->execute([$id]);

    echo json_encode([
        'ok'     => true,
        'hidden' => false,
        'note'   => 'เอากลับมานับในสถิติแล้ว',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ต้องบอกเหตุผลเสมอ — กันการซ่อนตามอำเภอใจโดยไม่มีร่องรอย
if ($reason === '') rhFail(400, 'กรุณาระบุเหตุผลที่ซ่อนรีวิวนี้');
if (mb_strlen($reason, 'UTF-8') > 200) {
    $reason = mb_substr($reason, 0, 200, 'UTF-8');
}

$pdo->prepare('UPDATE ratings SET hidden_at = NOW(), hidden_by = ?, hidden_reason = ? WHERE id = ?')
    ->execute([user_display(), $reason, $id]);

echo json_encode([
    'ok'     => true,
    'hidden' => true,
    'by'     => user_display(),
    'reason' => $reason,
    'note'   => 'ตัดออกจากสถิติแล้ว — ข้อความต้นฉบับยังเก็บไว้',
], JSON_UNESCAPED_UNICODE);
