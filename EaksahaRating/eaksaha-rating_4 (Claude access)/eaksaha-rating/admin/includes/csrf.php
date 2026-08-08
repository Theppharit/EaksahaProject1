<?php
// ============================================================
//  CSRF token — กันเว็บอื่นสั่งงานแทนเรา
//  ------------------------------------------------------------
//  ปัญหาที่กัน: ถ้าผู้ดูแลล็อกอินค้างไว้แล้วเผลอเปิดเว็บที่ถูกวางกับดัก
//  เว็บนั้นสั่งเบราว์เซอร์ยิงฟอร์มมาที่ระบบเราได้ โดยเบราว์เซอร์
//  จะแนบคุกกี้ล็อกอินไปให้เองด้วย ระบบจึงนึกว่าเป็นผู้ดูแลตัวจริง
//  อันตรายที่สุดคือหน้า "ผู้ใช้งานระบบ" — สร้างบัญชี admin ใหม่ให้ตัวเองได้
//
//  วิธีกัน: ทุกฟอร์มแนบรหัสลับที่มีเฉพาะใน session ของเรา
//  เว็บอื่นอ่านค่านี้ไม่ได้ จึงปลอมฟอร์มไม่ได้
//
//  วิธีใช้
//    ในฟอร์ม  :  echo csrf_field();     (แปะช่องซ่อนไว้ในฟอร์ม)
//    ตอนรับค่า:  csrf_check();          (ก่อนแตะฐานข้อมูล)
//    เรียกด้วย fetch: ส่ง header X-CSRF-Token หรือใส่ฟิลด์ _token ใน body
//
//  ห้ามเขียนเครื่องหมายปิดแท็ก PHP ในคอมเมนต์บรรทัดเดียวเด็ดขาด
//  เพราะ PHP จะถือว่าเป็นการปิดแท็กจริง แล้วโค้ดที่เหลือทั้งไฟล์
//  จะกลายเป็นข้อความธรรมดาที่พ่นออกหน้าจอ (เคยพลาดมาแล้วตรงนี้)
// ============================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/** รหัสลับประจำ session (สร้างครั้งเดียวแล้วใช้ตลอด) */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** ช่องซ่อนสำหรับแปะในฟอร์ม */
function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . htmlspecialchars(csrf_token()) . '">';
}

/**
 * ตรวจว่าคำขอนี้มาจากหน้าจอของเราจริง
 * ถ้าไม่ผ่านจะหยุดทันที ไม่ให้แตะฐานข้อมูลเลย
 */
function csrf_check(bool $asJson = false): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;

    $sent = $_POST['_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');

    // hash_equals เทียบแบบใช้เวลาคงที่ กันการเดาทีละตัวอักษร
    if (is_string($sent) && $sent !== '' && hash_equals(csrf_token(), $sent)) {
        return;
    }

    http_response_code(419);   // 419 = token หมดอายุ (นิยมใช้กับกรณีนี้)

    if ($asJson) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok'    => false,
            'error' => 'เซสชันหมดอายุ กรุณารีเฟรชหน้าแล้วลองใหม่',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="th"><head><meta charset="UTF-8">'
       . '<title>เซสชันหมดอายุ</title>'
       . '<style>body{font-family:sans-serif;max-width:460px;margin:80px auto;padding:0 20px;'
       . 'line-height:1.8;color:#16181D}a{color:#D81300}</style></head><body>'
       . '<h1>เซสชันหมดอายุ</h1>'
       . '<p>คำขอนี้ไม่ผ่านการตรวจสอบความปลอดภัย ซึ่งมักเกิดจากเปิดหน้าค้างไว้นานเกินไป</p>'
       . '<p>กรุณากลับไปที่หน้าเดิม กด F5 เพื่อรีเฟรช แล้วทำรายการใหม่อีกครั้ง</p>'
       . '<p><a href="javascript:history.back()">← กลับหน้าก่อนหน้า</a></p>'
       . '</body></html>';
    exit;
}
