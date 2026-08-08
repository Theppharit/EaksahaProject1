<?php
// ============================================================
//  ตัวรับค่าตัวกรอง + สร้างเงื่อนไข SQL ของหน้ารายงาน
//  ------------------------------------------------------------
//  แยกมาไว้ที่เดียว เพราะมีคนใช้ 2 หน้า:
//    • report.php        — แสดงบนจอ
//    • report_export.php — ส่งออกเป็นไฟล์
//  ถ้าแยกกันเขียน วันหนึ่งไฟล์ที่โหลดได้จะไม่ตรงกับที่เห็นบนจอ
//  ซึ่งเป็นบั๊กที่หาเจอยากมาก
// ============================================================

/**
 * อ่านค่าจาก $_GET แล้วคืนตัวกรองที่ผ่านการตรวจแล้ว
 * @return array{range:string,dateFrom:string,dateTo:string,brandId:?int,staffId:?int,score:string}
 */
function rqReadFilters(): array
{
    $validDate = fn($d) => is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d);

    $staffId  = isset($_GET['staff_id']) && $_GET['staff_id'] !== '' ? (int) $_GET['staff_id'] : null;
    $brandId  = isset($_GET['brand_id']) && $_GET['brand_id'] !== '' ? (int) $_GET['brand_id'] : null;
    $range    = $_GET['range'] ?? 'all';
    $dateFrom = $validDate($_GET['date_from'] ?? '') ? $_GET['date_from'] : '';
    $dateTo   = $validDate($_GET['date_to']   ?? '') ? $_GET['date_to']   : '';

    // ----- ตัวกรองดาว: เลือกได้หลายค่าพร้อมกัน -----
    // รูปแบบใน URL: ?score=1,2,5  (ไม่ใส่ = ดูทุกดาว)
    // เก็บเป็น array เสมอ เพื่อให้เงื่อนไข SQL เขียนแบบเดียวจบ
    $raw = (string) ($_GET['score'] ?? '');
    if ($raw === 'low') {
        $raw = '1,2';          // รองรับลิงก์เก่าที่บุ๊กมาร์กไว้ตอนยังมีปุ่ม "ต้องดูด่วน"
    }
    $scores = [];
    foreach (explode(',', $raw) as $s) {
        $s = (int) trim($s);
        if ($s >= 1 && $s <= 5) $scores[] = $s;
    }
    $scores = array_values(array_unique($scores));
    sort($scores);
    // ติ๊กครบ 5 ดาว = ไม่ได้กรองอะไรเลย ล้างทิ้งเพื่อให้ URL สะอาด
    if (count($scores) === 5) $scores = [];
    $score = implode(',', $scores);

    $today = date('Y-m-d');
    switch ($range) {
        case 'today': $dateFrom = $today;                              $dateTo = $today; break;
        case '7d':    $dateFrom = date('Y-m-d', strtotime('-6 days')); $dateTo = $today; break;
        case '30d':   $dateFrom = date('Y-m-d', strtotime('-29 days'));$dateTo = $today; break;
        case 'month': $dateFrom = date('Y-m-01');                      $dateTo = $today; break;
        case 'custom':
            if ($dateFrom === '' && $dateTo === '') { $range = 'all'; break; }
            if ($dateFrom === '') $dateFrom = $dateTo;
            if ($dateTo   === '') $dateTo   = $today;
            if (strtotime($dateFrom) > strtotime($dateTo)) { [$dateFrom, $dateTo] = [$dateTo, $dateFrom]; }
            break;
        case 'all':
        default:      $range = 'all'; $dateFrom = ''; $dateTo = '';
    }

    return compact('range', 'dateFrom', 'dateTo', 'brandId', 'staffId', 'score', 'scores');
}

/**
 * แปลงตัวกรองเป็นท่อน WHERE + พารามิเตอร์
 * @return array{0:string, 1:array}
 */
function rqBuildWhere(array $f, ?PDO $pdo = null): array
{
    $where  = [];
    $params = [];

    // รีวิวที่ผู้ดูแลซ่อนไว้ ไม่นับในสถิติและไม่ขึ้นในรายการ
    if ($pdo !== null && hidden_columns_ready($pdo)) {
        $where[] = 'r.hidden_at IS NULL';
    }

    if ($f['brandId'])         { $where[] = 's.brand_id = ?';    $params[] = $f['brandId']; }
    if ($f['staffId'])         { $where[] = 'r.staff_id = ?';    $params[] = $f['staffId']; }
    if ($f['dateFrom'] !== '') { $where[] = 'r.created_at >= ?'; $params[] = $f['dateFrom'] . ' 00:00:00'; }
    if ($f['dateTo']   !== '') { $where[] = 'r.created_at <= ?'; $params[] = $f['dateTo']   . ' 23:59:59'; }

    // ดาวที่ติ๊กไว้ — กี่ค่าก็ได้
    $scores = $f['scores'] ?? [];
    if (!empty($scores)) {
        $where[]  = 'r.score IN (' . implode(',', array_fill(0, count($scores), '?')) . ')';
        $params   = array_merge($params, array_map('intval', $scores));
    }

    return [$where ? ('WHERE ' . implode(' AND ', $where)) : '', $params];
}

/** ข้อความอธิบายช่วงเวลาที่เลือก ไว้โชว์บนแถบสรุปและหัวไฟล์ที่ส่งออก */
function rqRangeText(array $f): string
{
    $mon = ['', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
    $thai = fn($d) => date('j', strtotime($d)) . ' ' . $mon[(int) date('n', strtotime($d))] . ' ' . date('Y', strtotime($d));

    $names = ['all' => 'ทั้งหมด', 'today' => 'วันนี้', '7d' => '7 วันล่าสุด',
              '30d' => '30 วันล่าสุด', 'month' => 'เดือนนี้', 'custom' => 'กำหนดเอง'];
    $text = $names[$f['range']] ?? 'ทั้งหมด';

    if ($f['dateFrom'] !== '' && $f['dateTo'] !== '') {
        $text .= $f['dateFrom'] === $f['dateTo']
            ? ' (' . $thai($f['dateFrom']) . ')'
            : ' (' . $thai($f['dateFrom']) . ' – ' . $thai($f['dateTo']) . ')';
    }
    return $text;
}
