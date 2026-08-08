<?php
// ============================================================
//  แก้ดาวที่ AI ให้ผิด
// ------------------------------------------------------------
//  ทำไมต้องมี: AI อ่านข้อความแล้วให้ดาวเอง ถ้าอ่านประชดไม่ออก
//  หรือตีความผิด พนักงานคนนั้นจะได้ดาวผิดไปจริงๆ
//  คนที่อ่านข้อความแล้วรู้บริบทต้องแก้ทับได้เสมอ
//
//  สิ่งที่เกิดขึ้นเมื่อแก้
//    • score          → ดาวใหม่ที่ผู้ดูแลกรอก (กราฟ/รายงานใช้ค่านี้)
//    • ai_score       → ไม่แตะ เก็บดาวเดิมของ AI ไว้เทียบย้อนหลัง
//    • ai_status      → 'manual' บอกว่าแถวนี้คนแก้แล้ว
//                       ตัวลองใหม่จะได้ไม่หยิบไปให้ AI อ่านซ้ำ
//
//  ความปลอดภัย: ต้องล็อกอินหลังบ้าน และต้องเป็น POST เท่านั้น
// ============================================================

require '../conn/config.php';
require 'includes/auth.php';

require_perm('edit_score', true);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function ovFail(int $code, string $msg): void
{
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ovFail(405, 'ต้องส่งแบบ POST เท่านั้น');
}
if (!ai_columns_ready($pdo)) {
    ovFail(400, 'ยังไม่ได้รัน ai_migration.sql');
}

$id    = (int) ($_POST['id'] ?? 0);
$score = (int) ($_POST['score'] ?? 0);

if ($id <= 0)                  ovFail(400, 'ไม่พบรายการที่ต้องการแก้');
if ($score < 1 || $score > 5)  ovFail(400, 'ดาวต้องอยู่ระหว่าง 1 ถึง 5');

$st = $pdo->prepare('SELECT id, score, ai_score FROM ratings WHERE id = ?');
$st->execute([$id]);
$row = $st->fetch();
if (!$row) ovFail(404, 'ไม่พบรีวิวรายการนี้');

// ai_score เก็บดาวเดิมของ AI ไว้ ไม่เขียนทับ
// ถ้าแถวนี้ AI ยังไม่เคยให้ดาว (รอคิวอยู่) ก็ปล่อยว่างไว้ตามเดิม
$pdo->prepare(
    "UPDATE ratings
     SET score = ?, ai_status = 'manual', scored_at = NOW()
     WHERE id = ?"
)->execute([$score, $id]);

$aiScore = $row['ai_score'] !== null ? (int) $row['ai_score'] : null;

echo json_encode([
    'ok'      => true,
    'score'   => $score,
    'aiScore' => $aiScore,
    'by'      => $_SESSION['admin_username'] ?? '',
    'note'    => $aiScore !== null && $aiScore !== $score
        ? "AI เคยให้ $aiScore ดาว · ผู้ดูแลแก้เป็น $score ดาว"
        : 'ผู้ดูแลกรอกเอง',
], JSON_UNESCAPED_UNICODE);
