<?php
// ============================================================
//  ประกาศระบบ — แถบแจ้งเตือนที่พนักงานเห็นทุกหน้า
// ------------------------------------------------------------
//  ทำไมต้องมี:
//  เวลาระบบมีปัญหา (AI ไม่ให้ดาว / ฐานข้อมูลช้า / ปิดปรับปรุง)
//  พนักงานขายจะเห็นแค่ "หน้าจอว่างๆ" แล้วเดาเอาเองว่าตัวเองทำอะไรผิด
//  หรือคิดว่ารีวิวของลูกค้าหายไป ทั้งที่ข้อมูลอยู่ครบ
//  แถบนี้ทำหน้าที่ตอบคำถามเดียว: "ตอนนี้อะไรใช้ไม่ได้ และฉันต้องทำอะไร"
//
//  มี 2 แหล่ง
//   1) ประกาศที่ผู้ดูแลพิมพ์เอง — เช่น "วันเสาร์ปิดปรับปรุง 9 โมง"
//   2) ระบบตรวจเจอเอง — เช่น AI ค้างไม่ให้ดาวเกิน 30 นาที
//
//  เก็บประกาศไว้ในไฟล์ JSON ไม่ใช่ฐานข้อมูล — ตั้งใจ
//  เพราะแถบนี้ต้องยังทำงานได้ "ตอนที่ฐานข้อมูลล่ม" ซึ่งเป็นตอนที่ต้องใช้มากที่สุด
//  ถ้าเก็บใน DB พอ DB ล่มก็อ่านประกาศไม่ได้ กลายเป็นไร้ประโยชน์พอดี
// ============================================================

/** ที่เก็บประกาศ — อยู่นอก uploads เพื่อไม่ให้เปิดอ่านผ่านเบราว์เซอร์ตรงๆ */
function notice_file(): string
{
    return dirname(__DIR__, 2) . '/conn/system_notice.json';
}

/**
 * อ่านประกาศที่ตั้งไว้
 * คืนค่า: ['on'=>bool, 'level'=>'info|warn|down', 'title'=>string,
 *          'body'=>string, 'audience'=>'all|staff', 'show_customer'=>bool,
 *          'until'=>'' หรือ 'YYYY-MM-DDTHH:MM', 'updated_at'=>string]
 */
function notice_get(): array
{
    $blank = ['on' => false, 'level' => 'info', 'title' => '', 'body' => '',
              'audience' => 'all', 'show_customer' => false, 'until' => '', 'updated_at' => ''];

    $f = notice_file();
    if (!is_file($f)) { return $blank; }

    $raw = @file_get_contents($f);
    if ($raw === false) { return $blank; }

    $d = json_decode($raw, true);
    if (!is_array($d)) { return $blank; }

    return array_merge($blank, $d);
}

/** บันทึกประกาศ คืน true ถ้าเขียนไฟล์สำเร็จ */
function notice_save(array $d): bool
{
    $clean = [
        'on'            => !empty($d['on']),
        'level'         => in_array($d['level'] ?? '', ['info', 'warn', 'down'], true) ? $d['level'] : 'info',
        'title'         => mb_substr(trim((string) ($d['title'] ?? '')), 0, 120, 'UTF-8'),
        'audience'      => ($d['audience'] ?? '') === 'staff' ? 'staff' : 'all',
        'show_customer' => !empty($d['show_customer']),
        'until'         => preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', (string) ($d['until'] ?? ''))
                           ? $d['until'] : '',
        'body'          => mb_substr(trim((string) ($d['body'] ?? '')), 0, 600, 'UTF-8'),
        'updated_at'    => date('c'),
    ];

    // เขียนลงไฟล์ชั่วคราวก่อนแล้วค่อย rename — กันไฟล์พังครึ่งๆ กลางๆ
    // ถ้าไฟล์ JSON เสีย notice_get() จะคืนค่าว่าง = ประกาศหายเงียบโดยไม่มีใครรู้
    $f   = notice_file();
    $tmp = $f . '.tmp';
    $ok  = @file_put_contents($tmp, json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
    if ($ok === false) { return false; }

    return @rename($tmp, $f);
}

/** ประกาศยังมีผลอยู่ไหม (เปิดอยู่ และยังไม่เลยเวลาที่ตั้งให้หมดอายุ) */
function notice_active(array $n): bool
{
    if (empty($n['on']) || ($n['title'] === '' && $n['body'] === '')) { return false; }
    if (!empty($n['until'])) {
        $t = strtotime($n['until']);
        if ($t !== false && $t < time()) { return false; }   // หมดอายุแล้ว ปิดให้เอง
    }
    return true;
}

/**
 * แถบทั้งหมดที่ควรขึ้นให้ผู้ใช้คนนี้เห็น
 * @param string $role บทบาทของคนที่กำลังเปิดหน้าอยู่ (admin/manager/sales)
 * @return array<array{level:string,title:string,body:string,auto:bool}>
 */
function notice_banners(?PDO $pdo, string $role): array
{
    $out = [];

    // ---- 1) ประกาศที่ผู้ดูแลพิมพ์เอง ----
    $n = notice_get();
    if (notice_active($n)) {
        // 'staff' = อยากบอกเฉพาะหน้างาน ไม่ต้องรบกวนผู้บริหาร
        if ($n['audience'] !== 'staff' || $role === 'sales') {
            $out[] = ['level' => $n['level'], 'title' => $n['title'],
                      'body' => $n['body'], 'auto' => false];
        }
    }

    // ---- 2) ระบบตรวจเจอเอง: AI ค้างไม่ให้ดาว ----
    // เขียนคนละสำนวนตามบทบาท เพราะสิ่งที่แต่ละคน "ทำได้" ไม่เหมือนกัน
    // พนักงานขายทำอะไรไม่ได้ สิ่งที่เขาต้องรู้คือ "งานของฉันไม่ได้หาย"
    if ($pdo !== null && function_exists('ai_columns_ready') && ai_columns_ready($pdo)) {
        try {
            $st = $pdo->query(
                "SELECT COUNT(*) FROM ratings
                 WHERE ai_status = 'pending' AND created_at < (NOW() - INTERVAL 30 MINUTE)"
            );
            $stuck = (int) ($st ? $st->fetchColumn() : 0);

            if ($stuck > 0) {
                $out[] = $role === 'sales'
                    ? ['level' => 'warn', 'auto' => true,
                       'title' => 'ระบบให้ดาวอัตโนมัติกำลังมีปัญหา',
                       'body'  => 'รีวิวจากลูกค้าถูกบันทึกไว้ครบแล้ว ไม่มีอะไรหาย '
                                . 'แต่ดาวจะขึ้นช้ากว่าปกติ ตอนนี้มี ' . number_format($stuck)
                                . ' รายการรอคะแนนอยู่ — ทีมผู้ดูแลกำลังแก้ให้ ไม่ต้องให้ลูกค้าประเมินซ้ำนะครับ']
                    : ['level' => 'warn', 'auto' => true,
                       'title' => 'มีรีวิว ' . number_format($stuck) . ' รายการค้างรอ AI ให้ดาวเกิน 30 นาที',
                       'body'  => 'มักเกิดจาก API ล่มหรือ key หมดอายุ — ตรวจที่เมนู "ทดสอบ AI ให้ดาว" '
                                . 'แล้วกด "ลองให้ดาวใหม่" ระหว่างนี้คะแนนเฉลี่ยจะยังไม่รวมรายการเหล่านี้'];
            }
        } catch (PDOException $e) {
            // ตัวแจ้งเตือนต้องไม่มีวันทำให้หน้าที่มันไปเกาะอยู่ล้มเสียเอง
        }
    }

    return $out;
}

/** วาดแถบทั้งหมด — ไม่มีอะไรต้องแจ้งก็ไม่วาดอะไรเลย */
function notice_bar(?PDO $pdo, string $role): string
{
    $icons = [
        'info' => '<circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/>',
        'warn' => '<path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
        'down' => '<circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>',
    ];

    $html = '';
    foreach (notice_banners($pdo, $role) as $b) {
        $lv = $b['level'];
        $html .= '<div class="sys-note ' . htmlspecialchars($lv) . '">'
               . '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" '
               . 'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
               . ($icons[$lv] ?? $icons['info']) . '</svg><div>';

        if ($b['title'] !== '') {
            $html .= '<b>' . htmlspecialchars($b['title']) . '</b>';
        }
        if ($b['body'] !== '') {
            $html .= '<span>' . nl2br(htmlspecialchars($b['body'])) . '</span>';
        }
        $html .= '</div></div>';
    }
    return $html;
}
