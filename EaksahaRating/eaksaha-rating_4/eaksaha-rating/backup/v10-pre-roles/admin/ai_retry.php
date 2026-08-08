<?php
// ============================================================
//  ลองให้ดาวใหม่ สำหรับรีวิวที่ยังค้างอยู่
// ------------------------------------------------------------
//  รีวิวจะค้างเป็น pending เมื่อ:
//    • ตอนลูกค้ากดส่ง เน็ตของเซิร์ฟเวอร์มีปัญหา
//    • Claude API ล่มหรือตอบช้าเกินไป
//    • โควตา API เต็ม
//    • ลูกค้าปิดหน้าจอเร็วมากจนคำขอเบื้องหลังไม่ทันได้ยิงออกไป
//
//  หน้านี้ต้องล็อกอินหลังบ้านก่อนถึงเรียกได้
//  ทำทีละไม่กี่รายการต่อรอบ กันไม่ให้ค้างนานหรือยิง API รัวเกินไป
// ============================================================

require '../conn/config.php';
require '../conn/ai.php';
require 'includes/auth.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

ignore_user_abort(true);
// 5 รายการ × สูงสุด 30 วินาทีต่อรายการ = 150 วินาที เผื่อไว้ 300
set_time_limit(300);

$LIMIT = 5;         // จำนวนรายการต่อการกด 1 ครั้ง
$MAX_ATTEMPTS = 5;  // ลองเกินนี้แล้วไม่ลองอีก กันวนไม่จบถ้ากุญแจผิด

$rows = $pdo->prepare(
    "SELECT id FROM ratings
     WHERE ai_status = 'pending' AND ai_attempts < ?
     ORDER BY created_at ASC
     LIMIT $LIMIT"
);
$rows->execute([$MAX_ATTEMPTS]);
$ids = $rows->fetchAll(PDO::FETCH_COLUMN);

$done = 0;
$failed = 0;
$lastError = '';

foreach ($ids as $id) {
    $res = ai_score_rating($pdo, (int) $id);
    if (isset($res['error'])) {
        $failed++;
        $lastError = $res['error'];
        // กุญแจผิดหรือหมดโควตา — ลองต่อไปก็เสียเวลาเปล่า หยุดทั้งรอบ
        if (strpos($res['error'], '401') !== false || strpos($res['error'], '429') !== false) {
            break;
        }
    } elseif (!isset($res['skipped'])) {
        $done++;
    }
}

// เหลือค้างอีกกี่รายการ
$left = (int) $pdo->query("SELECT COUNT(*) FROM ratings WHERE ai_status = 'pending'")->fetchColumn();

echo json_encode([
    'ok'      => true,
    'done'    => $done,
    'failed'  => $failed,
    'left'    => $left,
    'message' => $done > 0
        ? "ให้ดาวสำเร็จ $done รายการ" . ($left > 0 ? " · ยังเหลือ $left รายการ" : '')
        : ($failed > 0 ? 'ยังให้ดาวไม่สำเร็จ: ' . $lastError : 'ไม่มีรายการค้างแล้ว'),
], JSON_UNESCAPED_UNICODE);
