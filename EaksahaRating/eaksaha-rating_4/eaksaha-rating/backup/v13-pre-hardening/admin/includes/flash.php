<?php
// ============================================================
//  ข้อความแจ้งผลแบบ popup (toast)
//  ------------------------------------------------------------
//  ปัญหาเดิม: กดบันทึกแล้วหน้าโหลดใหม่ ข้อความ "บันทึกสำเร็จ"
//  ไปโผล่กลางหน้าปนกับเนื้อหาอื่น มองไม่เห็น ไม่รู้ว่าของที่เพิ่งเพิ่ม
//  ไปอยู่ตรงไหน
//
//  วิธีใหม่: เก็บข้อความไว้ใน session แล้ว redirect (แบบ PRG)
//    • popup เด้งมุมขวาบน เห็นแน่นอน
//    • บอกด้วยว่าของที่เพิ่งทำไปอยู่ที่ไหน พร้อมลิงก์กดไปดู
//    • กดรีเฟรชไม่ส่งฟอร์มซ้ำอีกต่อไป
// ============================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * ฝากข้อความไว้ให้แสดงหลัง redirect
 *
 * @param string $type  success | error | info
 * @param string $title หัวข้อสั้นๆ เช่น "เพิ่มพนักงานขายแล้ว"
 * @param string $msg   รายละเอียด เช่น ชื่อและโค้ดที่เพิ่งสร้าง
 * @param array  $action ปุ่มพาไปดู ['text' => 'ดูในรายชื่อ', 'href' => '#row-12']
 */
function flash(string $type, string $title, string $msg = '', array $action = []): void
{
    $_SESSION['flash'][] = [
        'type'   => $type,
        'title'  => $title,
        'msg'    => $msg,
        'action' => $action,
    ];
}

/** ไปหน้าใหม่หลังบันทึก — คู่กับ flash() เสมอ */
function flash_redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

/** ดึงข้อความออกมา (อ่านแล้วลบทิ้ง จะได้ไม่ค้างเด้งซ้ำ) */
function flash_take(): array
{
    $all = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $all;
}
