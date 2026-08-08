<?php
// ============================================================
//  ส่งออกรายการรีวิวเป็นไฟล์ CSV (เปิดใน Excel ได้)
//  ------------------------------------------------------------
//  ใช้ตัวกรองชุดเดียวกับหน้า report.php เป๊ะๆ ผ่าน report_query.php
//  ไฟล์ที่ได้จึงตรงกับที่เห็นบนจอเสมอ
//
//  เรื่องภาษาไทยใน Excel: Excel บน Windows จะเดารหัสอักขระเอง
//  ถ้าไม่ใส่ BOM ไว้หน้าไฟล์ ภาษาไทยจะกลายเป็นตัวยึกยือทั้งไฟล์
//  บรรทัด BOM ด้านล่างจึงสำคัญมาก ห้ามลบ
//
//  ไม่มี LIMIT — ส่งออกทั้งหมดที่ตรงเงื่อนไข ต่างจากบนจอที่จำกัด 200 แถว
// ============================================================

require '../conn/config.php';
require 'includes/auth.php';
require 'includes/report_query.php';

$f = rqReadFilters();
[$whereSql, $params] = rqBuildWhere($f);

$aiCols = ai_columns_ready($pdo)
    ? 'r.ai_score, r.ai_reason, r.ai_confidence, r.ai_status,'
    : 'NULL AS ai_score, NULL AS ai_reason, NULL AS ai_confidence, NULL AS ai_status,';

$st = $pdo->prepare("
    SELECT r.id, r.score, r.feedback, r.created_at, $aiCols
           s.name, s.position, s.code,
           b.name AS brand_name
    FROM ratings r
    JOIN staff s ON s.id = r.staff_id
    LEFT JOIN brands b ON b.id = s.brand_id
    $whereSql
    ORDER BY r.created_at DESC
");
$st->execute($params);

$filename = 'eaksaha-reviews-' . date('Y-m-d-Hi') . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store');

$out = fopen('php://output', 'w');

// BOM — บอก Excel ว่าไฟล์นี้เป็น UTF-8 ถ้าไม่มี ภาษาไทยจะเพี้ยนทั้งไฟล์
fwrite($out, "\xEF\xBB\xBF");

// บรรทัดหัวเรื่อง บอกว่าไฟล์นี้กรองอะไรมา — คนรับไฟล์จะได้ไม่เข้าใจผิด
$scoreText = $f['score'] === ''    ? 'ทุกดาว'
           : ($f['score'] === 'low' ? 'เฉพาะ 1-2 ดาว' : 'เฉพาะ ' . (int) $f['score'] . ' ดาว');

fputcsv($out, ['รายงานรีวิวลูกค้า — ' . ORG_NAME]);
fputcsv($out, ['ช่วงเวลา', rqRangeText($f)]);
fputcsv($out, ['ตัวกรองดาว', $scoreText]);
fputcsv($out, ['ออกรายงานเมื่อ', date('d/m/Y H:i')]);
fputcsv($out, []);

fputcsv($out, [
    'วันที่', 'เวลา', 'พนักงานขาย', 'รหัสพนักงาน', 'ตำแหน่ง', 'แบรนด์',
    'คะแนน', 'ที่มาของคะแนน', 'ดาวที่ AI ให้', 'ความมั่นใจ AI (%)',
    'เหตุผลที่ AI ให้ดาว', 'ข้อความจากลูกค้า',
]);

$statusText = [
    'pending' => 'รอ AI ให้ดาว',
    'done'    => 'AI ให้ดาว',
    'manual'  => 'ผู้ดูแลกรอกเอง',
];

$n = 0;
while ($row = $st->fetch()) {
    $n++;
    $t = strtotime($row['created_at']);
    fputcsv($out, [
        date('d/m/Y', $t),
        date('H:i', $t),
        $row['name'],
        $row['code'],
        $row['position'],
        $row['brand_name'] ?? '-',
        $row['score'] !== null ? (int) $row['score'] : '',
        $statusText[$row['ai_status'] ?? ''] ?? '-',
        $row['ai_score'] !== null ? (int) $row['ai_score'] : '',
        $row['ai_confidence'] !== null ? round((float) $row['ai_confidence'] * 100) : '',
        $row['ai_reason'] ?? '',
        // ตัดขึ้นบรรทัดใหม่ในข้อความออก ไม่งั้นเซลล์ใน Excel จะสูงผิดปกติ
        trim(preg_replace('/\s*\R\s*/u', ' ', (string) $row['feedback'])),
    ]);
}

if ($n === 0) {
    fputcsv($out, ['ไม่พบรีวิวตามเงื่อนไขที่เลือก']);
}

fclose($out);
