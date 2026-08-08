<?php
// ============================================================
//  กันการส่งรีวิวซ้ำ — eTDR
//  ------------------------------------------------------------
//  ทำไมต้องมี: ระบบนี้ใช้ประเมินคนจริง ถ้าใครถือลิงก์ QR แล้วส่งรีวิว
//  ได้ไม่จำกัด พนักงานก็ปั่นคะแนนตัวเองได้ หรือคนที่ไม่ชอบใครก็ถล่ม
//  1 ดาวได้ ตัวเลขทั้งระบบจะไม่มีความหมายเลย
//
//  ป้องกัน 2 ชั้น
//    ชั้นที่ 1 — คุกกี้ประจำเครื่อง
//        ฝังรหัสสุ่มไว้ในเครื่องลูกค้า 1 เครื่องส่งได้ 1 ครั้งต่อพนักงาน 1 คน
//        ภายในเวลาที่กำหนด (ค่าเริ่มต้น 12 ชั่วโมง)
//
//    ชั้นที่ 2 — นับตาม IP
//        คุกกี้ลบได้ ชั้นนี้จึงคุมว่า IP เดียวกันส่งให้พนักงานคนเดิม
//        ได้ไม่เกินกี่ครั้งต่อชั่วโมง กันคนนั่งล้างคุกกี้แล้วส่งรัว
//
//  ทำไมไม่บล็อก IP เดี่ยวๆ ไปเลย: โชว์รูมมี wifi เส้นเดียว ลูกค้าหลายคน
//  ที่มาพร้อมกันจะออกเน็ตด้วย IP เดียวกัน ถ้าบล็อกแรงเกินจะกันลูกค้าจริง
//  ชั้นที่ 2 จึงตั้งเพดานไว้หลวมๆ แค่กันการยิงรัวผิดปกติ
// ============================================================

// ----- ปรับค่าได้ตรงนี้ -----
define('RG_COOKIE',      'etdr_device');
define('RG_COOKIE_DAYS', 730);   // อายุคุกกี้ประจำเครื่อง (2 ปี)
define('RG_DEVICE_HOURS', 12);   // 1 เครื่อง ส่งให้พนักงานคนเดิมได้ 1 ครั้งใน x ชั่วโมง
define('RG_IP_PER_HOUR',   6);   // 1 IP ส่งให้พนักงานคนเดิมได้ไม่เกิน x ครั้งต่อชั่วโมง

/**
 * อ่านหรือสร้างรหัสประจำเครื่อง
 * ต้องเรียกก่อนที่หน้าจะเริ่มส่ง output (เพราะต้องตั้งคุกกี้)
 */
function rg_device_id(): string
{
    if (!empty($_COOKIE[RG_COOKIE]) && preg_match('/^[a-f0-9]{32}$/', $_COOKIE[RG_COOKIE])) {
        return $_COOKIE[RG_COOKIE];
    }

    $id = bin2hex(random_bytes(16));
    setcookie(RG_COOKIE, $id, [
        'expires'  => time() + RG_COOKIE_DAYS * 86400,
        'path'     => '/',
        'httponly' => true,          // JavaScript อ่านไม่ได้
        'samesite' => 'Lax',
    ]);
    $_COOKIE[RG_COOKIE] = $id;       // ให้ใช้ได้ทันทีในรอบนี้
    return $id;
}

/** IP ของผู้ส่ง (เผื่อกรณีอยู่หลัง proxy ในวง LAN) */
function rg_client_ip(): string
{
    foreach (['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'] as $k) {
        if (!empty($_SERVER[$k])) {
            $ip = trim(explode(',', $_SERVER[$k])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) return substr($ip, 0, 45);
        }
    }
    return '0.0.0.0';
}

/**
 * ตรวจว่าส่งรีวิวให้พนักงานคนนี้ได้ไหม
 *
 * @return array{ok:bool, reason:string}  reason = ข้อความบอกลูกค้าเมื่อส่งไม่ได้
 */
function rg_can_submit(PDO $pdo, int $staffId, string $deviceId, string $ip): array
{
    try {
        // ชั้นที่ 1 — เครื่องนี้เพิ่งส่งให้พนักงานคนนี้ไปหรือยัง
        $st = $pdo->prepare(
            'SELECT COUNT(*) FROM rate_limits
             WHERE staff_id = ? AND device_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL ? HOUR)'
        );
        $st->execute([$staffId, $deviceId, RG_DEVICE_HOURS]);
        if ((int) $st->fetchColumn() > 0) {
            return ['ok' => false, 'reason' => 'device'];
        }

        // ชั้นที่ 2 — IP นี้ยิงรัวผิดปกติหรือเปล่า
        $st = $pdo->prepare(
            'SELECT COUNT(*) FROM rate_limits
             WHERE staff_id = ? AND ip = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)'
        );
        $st->execute([$staffId, $ip]);
        if ((int) $st->fetchColumn() >= RG_IP_PER_HOUR) {
            return ['ok' => false, 'reason' => 'ip'];
        }
    } catch (PDOException $e) {
        // ยังไม่ได้รัน hardening_migration.sql — ปล่อยผ่านไปก่อน
        // ดีกว่าปิดกั้นลูกค้าจริงเพราะตารางยังไม่มี
        return ['ok' => true, 'reason' => ''];
    }

    return ['ok' => true, 'reason' => ''];
}

/** บันทึกว่าส่งไปแล้ว — เรียกทันทีหลังบันทึกรีวิวสำเร็จ */
function rg_record(PDO $pdo, int $staffId, string $deviceId, string $ip): void
{
    try {
        $pdo->prepare('INSERT INTO rate_limits (staff_id, device_id, ip) VALUES (?, ?, ?)')
            ->execute([$staffId, $deviceId, $ip]);

        // ล้างของเก่าทิ้งเป็นครั้งคราว (ราว 2% ของการส่ง) ตารางจะได้ไม่บวมไปเรื่อยๆ
        if (random_int(1, 50) === 1) {
            $pdo->query('DELETE FROM rate_limits WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)');
        }
    } catch (PDOException $e) {
        // ตารางยังไม่มี — ไม่ให้ล้มทั้งการบันทึกรีวิว
    }
}

/** ข้อความที่จะบอกลูกค้าเมื่อส่งไม่ได้ */
function rg_message(string $reason): string
{
    if ($reason === 'device') {
        return 'คุณได้ประเมินพนักงานท่านนี้ไปแล้ว ขอบคุณสำหรับความคิดเห็นครับ '
             . 'หากต้องการแก้ไขหรือเพิ่มเติม กรุณาแจ้งพนักงานที่ให้บริการ';
    }
    return 'ระบบได้รับการประเมินจำนวนมากจากเครือข่ายนี้ในช่วงเวลาสั้นๆ '
         . 'กรุณาลองใหม่อีกครั้งในภายหลัง';
}
