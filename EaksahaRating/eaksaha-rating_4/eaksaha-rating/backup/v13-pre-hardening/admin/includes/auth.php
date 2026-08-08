<?php
// ============================================================
//  ตรวจการเข้าสู่ระบบ + ระบบสิทธิ์ 3 ระดับ — eTDR
//  ------------------------------------------------------------
//  admin   ทำได้ทุกอย่าง
//  manager เห็นได้ทุกอย่าง ฝากโน้ตถึงพนักงานได้ แต่แก้ไขอะไรไม่ได้เลย
//  sales   เห็นเฉพาะคะแนนและรีวิวของตัวเอง
//
//  หลักสำคัญ: การซ่อนปุ่มบนหน้าจอ "ไม่ใช่" การกันสิทธิ์
//  ใครก็พิมพ์ URL ตรงเข้ามาได้ ทุกหน้าและทุก endpoint จึงต้องเรียก
//  require_perm() ที่ฝั่งเซิร์ฟเวอร์เสมอ ไม่ใช่แค่ไม่แสดงปุ่ม
// ============================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

// ------------------------------------------------------------
//  ตารางสิทธิ์ — จุดเดียวที่ตัดสินว่าใครทำอะไรได้
// ------------------------------------------------------------
function perm_map(): array
{
    return [
        // ดูข้อมูลของทุกคน (แดชบอร์ด รายการรีวิว คะแนนรายพนักงาน)
        'view_all'      => ['admin', 'manager'],
        // ส่งออกไฟล์
        'export'        => ['admin', 'manager'],
        // ฝากโน้ต/แจ้งเตือนถึงพนักงานขาย
        'note'          => ['admin', 'manager'],
        // หมายเหตุ: ระบบไม่มีการแก้คะแนนแล้ว คะแนนมาจาก AI อ่านสิ่งที่ลูกค้าเขียนล้วนๆ
        //           ถ้าเห็นว่าเคสไหนควรคุย ให้ใช้ 'note' ส่งข้อความถึงพนักงานแทน
        // สั่งให้ AI ให้ดาวใหม่ในรายการที่ค้าง
        'run_ai'        => ['admin'],
        // เพิ่ม/แก้/ลบ พนักงานขาย
        'manage_staff'  => ['admin'],
        // เพิ่ม/แก้/ลบ แบรนด์
        'manage_brands' => ['admin'],
        // จัดการผู้ใช้งานระบบ
        'manage_users'  => ['admin'],
        // หน้าทดสอบ AI (ยิง API จริง มีค่าใช้จ่าย)
        'ai_test'       => ['admin'],
    ];
}

/** บทบาทของคนที่ล็อกอินอยู่ */
function user_role(): string
{
    return $_SESSION['admin_role'] ?? 'admin';
}

/** รหัสพนักงานขายที่ผูกกับบัญชีนี้ (มีเฉพาะบัญชี sales) */
function user_staff_id(): ?int
{
    return isset($_SESSION['admin_staff_id']) && $_SESSION['admin_staff_id'] !== null
        ? (int) $_SESSION['admin_staff_id']
        : null;
}

/** ชื่อที่ใช้แสดงบนหน้าจอ */
function user_display(): string
{
    return $_SESSION['admin_display'] ?? ($_SESSION['admin_username'] ?? '');
}

/** ชื่อบทบาทภาษาไทย ไว้โชว์ในเมนู */
function role_label(?string $role = null): string
{
    $labels = ['admin' => 'ผู้ดูแลระบบ', 'manager' => 'ผู้จัดการ', 'sales' => 'พนักงานขาย'];
    return $labels[$role ?? user_role()] ?? 'ผู้ใช้งาน';
}

/** ตรวจว่าทำสิ่งนี้ได้ไหม — ใช้ทั้งซ่อนปุ่มและกันฝั่งเซิร์ฟเวอร์ */
function can(string $what): bool
{
    $map = perm_map();
    return isset($map[$what]) && in_array(user_role(), $map[$what], true);
}

/**
 * กันหน้าหรือ endpoint ที่ไม่มีสิทธิ์
 * $asJson = true สำหรับไฟล์ที่ตอบเป็น JSON (จะได้ไม่ส่ง HTML กลับไปให้ JS งง)
 */
function require_perm(string $what, bool $asJson = false): void
{
    if (can($what)) return;

    if ($asJson) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(403);
        echo json_encode([
            'ok'    => false,
            'error' => 'บัญชีของคุณ (' . role_label() . ') ไม่มีสิทธิ์ทำรายการนี้',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    http_response_code(403);
    $home = user_role() === 'sales' ? 'my_reviews.php' : 'dashboard.php';
    require __DIR__ . '/no_permission.php';
    exit;
}

/** หน้าแรกของแต่ละบทบาท */
function role_home(?string $role = null): string
{
    return ($role ?? user_role()) === 'sales' ? 'my_reviews.php' : 'dashboard.php';
}
