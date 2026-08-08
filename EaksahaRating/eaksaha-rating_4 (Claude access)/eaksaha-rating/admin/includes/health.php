<?php
// ============================================================
//  ตรวจสุขภาพระบบ — เช็กลิสต์ "ก่อนใช้งานจริง" ที่โผล่ในระบบเอง
// ------------------------------------------------------------
//  ทำไมต้องมี:
//  งานตั้งค่าที่ยังค้าง (ยังไม่รัน migration / QR ชี้ localhost /
//  รหัสผ่าน admin ยังเป็นค่าเริ่มต้น) เป็นเรื่องที่ "ระบบยังเดินได้"
//  แต่พอเอาไปใช้หน้างานจริงจะพังทันที และไม่มีอะไรเตือนเลย
//  ที่ผ่านมาเรื่องพวกนี้จดไว้แค่ในไฟล์ TODO ซึ่งคนใช้งานไม่เคยเปิด
//
//  กติกาการแสดงผล:
//  • เห็นเฉพาะ admin — คนอื่นแก้ไม่ได้อยู่แล้ว โชว์ไปก็รกเปล่าๆ
//  • ถ้าผ่านครบ กล่องนี้จะหายไปเอง ไม่เหลืออะไรค้างบนหน้าจอ
//    (เจ้าของงานไม่ชอบ UI ที่รก จึงต้องไม่เป็นแถบถาวร)
// ============================================================

/**
 * ตรวจรายการตั้งค่าที่ค้างอยู่
 * คืน array ของ ['level' => 'high'|'mid', 'title' => ..., 'how' => ...]
 * เรียงจากเรื่องที่กระทบการใช้งานจริงมากที่สุดก่อน
 */
function system_health(PDO $pdo): array
{
    $issues = [];

    // ---- 1) migration ตัวสุดท้ายยังไม่ได้รัน ----
    // ผลกระทบ: ปุ่มซ่อนรีวิวและตัวกันรีวิวซ้ำจะเงียบไปทั้งคู่
    // ระบบไม่พัง แต่ตัวเลขเชื่อไม่ได้ เพราะใครส่งกี่รอบก็ได้
    if (!hidden_columns_ready($pdo)) {
        $issues[] = [
            'level' => 'high',
            'title' => 'ยังไม่ได้รัน hardening_migration.sql',
            'how'   => 'เปิด phpMyAdmin → ฐานข้อมูล eaksaha_rating → แท็บ SQL → '
                     . 'วางไฟล์ hardening_migration.sql ทั้งไฟล์ → Go '
                     . '(ตอนนี้ตัวกันรีวิวซ้ำและปุ่มซ่อนรีวิวยังไม่ทำงาน)',
        ];
    }

    // ---- 2) QR ยังชี้มาที่เครื่องนี้ ----
    // ผลกระทบ: มือถือลูกค้าสแกนแล้วเปิดไม่ขึ้นเลย = ระบบใช้งานจริงไม่ได้
    if (function_exists('rate_base_url')) {
        $qr = rate_base_url();
        if (!empty($qr['mismatch'])) {
            $issues[] = [
                'level' => 'high',
                'title' => 'PUBLIC_BASE_URL ชี้ไปคนละที่กับตำแหน่งจริงของระบบ — สแกน QR แล้วได้ 404',
                'how'   => 'แก้ค่าใน conn/config.php เป็น http://' . $qr['host']
                         . rawurldecode((string) ($qr['expected'] ?? '')),
            ];
        } elseif ($qr['source'] === 'local') {
            $issues[] = [
                'level' => 'high',
                'title' => 'ลิงก์ QR ยังชี้มาที่เครื่องนี้ (' . $qr['host'] . ')',
                'how'   => 'ตั้งค่า PUBLIC_BASE_URL ใน conn/config.php เป็นที่อยู่ที่เครื่องอื่นเข้าถึงได้ '
                         . 'เช่น http://192.168.1.50/eaksaha-rating',
            ];
        } elseif ($qr['source'] === 'guess') {
            $issues[] = [
                'level' => 'mid',
                'title' => 'QR ใช้ IP ที่ระบบเดาให้ (' . $qr['host'] . ') — ยังไม่ได้ยืนยัน',
                'how'   => 'ลองสแกน QR ด้วยมือถือที่ต่อ wifi วงเดียวกัน 1 ครั้ง ถ้าเปิดได้ก็ใช้งานได้ '
                         . 'แต่ IP อาจเปลี่ยนเมื่อรีสตาร์ตเครื่อง ถ้าจะพิมพ์ QR แจกถาวรควรตั้ง PUBLIC_BASE_URL เอง',
            ];
        }
    }

    // ---- 3) รหัสผ่าน admin ยังเป็นค่าเริ่มต้น ----
    // ผลกระทบ: ใครก็ตามที่เคยเห็นคู่มือติดตั้งเข้าระบบได้เลย
    // ตรวจแบบไม่เก็บอะไรไว้ ใช้ password_verify กับค่าเริ่มต้นตรงๆ
    try {
        $st = $pdo->query("SELECT password FROM admin_users WHERE username = 'admin' LIMIT 1");
        $hash = $st ? $st->fetchColumn() : false;
        if ($hash && password_verify('admin1234', $hash)) {
            $issues[] = [
                'level' => 'high',
                'title' => 'บัญชี admin ยังใช้รหัสผ่านเริ่มต้น',
                'how'   => 'ไปที่เมนู "เปลี่ยนรหัสผ่าน" แล้วตั้งรหัสใหม่',
            ];
        }
    } catch (PDOException $e) {
        // ตารางยังไม่พร้อม — ไม่ใช่เรื่องที่ต้องรายงานตรงนี้
    }

    // ---- 4) มีรีวิวค้างรอ AI นานผิดปกติ ----
    // ผลกระทบ: ดูเผินๆ เหมือนไม่มีใครรีวิว ทั้งที่ข้อความเข้ามาแล้วแต่ไม่มีดาว
    if (ai_columns_ready($pdo)) {
        try {
            $st = $pdo->query(
                "SELECT COUNT(*) FROM ratings
                 WHERE ai_status = 'pending' AND created_at < (NOW() - INTERVAL 1 HOUR)"
            );
            $stuck = (int) ($st ? $st->fetchColumn() : 0);
            if ($stuck > 0) {
                $issues[] = [
                    'level' => 'mid',
                    'title' => 'มีรีวิว ' . $stuck . ' รายการค้างรอ AI ให้ดาวเกิน 1 ชั่วโมง',
                    'how'   => 'มักเกิดจาก API ล่มหรือ key หมดอายุ — กดปุ่ม "ลองให้ดาวใหม่" '
                             . 'หรือตรวจที่เมนู "ทดสอบ AI ให้ดาว"',
                ];
            }
        } catch (PDOException $e) {
            // ข้ามไป ไม่ให้หน้าแดชบอร์ดล้มเพราะตัวตรวจสุขภาพ
        }
    }

    return $issues;
}

/** วาดกล่องเช็กลิสต์ — ไม่มีปัญหาก็ไม่วาดอะไรเลย */
function system_health_box(PDO $pdo): string
{
    $issues = system_health($pdo);
    if (!$issues) { return ''; }

    $high = 0;
    foreach ($issues as $i) { if ($i['level'] === 'high') { $high++; } }

    $rows = '';
    foreach ($issues as $i) {
        $rows .= '<li class="hc-item ' . $i['level'] . '">'
               . '<b>' . htmlspecialchars($i['title']) . '</b>'
               . '<span>' . htmlspecialchars($i['how']) . '</span>'
               . '</li>';
    }

    $sum = $high > 0
        ? 'ต้องแก้ก่อนใช้งานจริง ' . $high . ' เรื่อง'
        : 'มีเรื่องควรตรวจ ' . count($issues) . ' เรื่อง';

    return '<details class="health-card' . ($high ? ' urgent' : '') . '"' . ($high ? ' open' : '') . '>'
         . '<summary>'
         . '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
         . '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>'
         . '<span>ความพร้อมของระบบ — ' . htmlspecialchars($sum) . '</span>'
         . '</summary><ul class="hc-list">' . $rows . '</ul></details>';
}
