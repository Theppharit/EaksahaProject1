<?php
// ============================================================
//  สั่งให้ AI อ่านรีวิวหนึ่งรายการแล้วให้ดาว
// ------------------------------------------------------------
//  หน้านี้ถูกเรียกเบื้องหลังจากหน้าขอบคุณ ตอนที่ลูกค้าเห็นหน้าจอแล้ว
//  ลูกค้าจึงไม่ต้องรอ AI เลยแม้แต่วินาทีเดียว
//
//  การป้องกัน
//    • ต้องมาพร้อมลายเซ็น (t) ที่ระบบสร้างตอนบันทึกรีวิวเท่านั้น
//      คนภายนอกเดา id แล้วยิงเข้ามาเองไม่ได้
//    • ลายเซ็นหมดอายุใน 10 นาที
//    • ให้ดาวได้เฉพาะรายการที่ยังเป็น pending — ยิงซ้ำก็ไม่เกิดอะไรขึ้น
//    • ไม่คืนข้อมูลรีวิวหรือคะแนนกลับไป บอกแค่ว่าทำงานเสร็จหรือไม่
// ============================================================

require 'conn/config.php';
require 'conn/ai.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// ลูกค้าอาจปิดหน้าจอทันทีหลังเห็นคำขอบคุณ
// บรรทัดนี้บอก PHP ว่า "ถึงเบราว์เซอร์จะตัดการเชื่อมต่อ ก็ทำงานให้จบ"
ignore_user_abort(true);
set_time_limit(60);

$id    = (int) ($_GET['id'] ?? 0);
$exp   = (int) ($_GET['exp'] ?? 0);
$token = (string) ($_GET['t'] ?? '');

if ($id <= 0 || !ai_check_token($id, $exp, $token)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'ลิงก์ไม่ถูกต้องหรือหมดอายุ'], JSON_UNESCAPED_UNICODE);
    exit;
}

$res = ai_score_rating($pdo, $id);

if (isset($res['skipped'])) {
    echo json_encode(['ok' => true, 'skipped' => true], JSON_UNESCAPED_UNICODE);
    exit;
}
if (isset($res['error'])) {
    // ไม่ส่งรายละเอียดข้อผิดพลาดกลับไปหน้าลูกค้า
    // รายการจะยังค้างเป็น pending และผู้ดูแลสั่งลองใหม่ได้จากหลังบ้าน
    http_response_code(502);
    echo json_encode(['ok' => false], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
