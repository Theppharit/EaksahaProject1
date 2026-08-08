<?php
require '../conn/config.php';
require 'includes/auth.php';

$pageTitle  = 'รายงานคะแนน';
$activePage = 'report';

$THAI_MON = ['', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
$thaiDate = fn($d) => date('j', strtotime($d)) . ' ' . $THAI_MON[(int) date('n', strtotime($d))] . ' ' . date('Y', strtotime($d));

// ══════════════════════════════════════════════
//  รับค่าตัวกรอง
// ══════════════════════════════════════════════
$validDate = fn($d) => is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d);

$staffId     = isset($_GET['staff_id']) && $_GET['staff_id'] !== '' ? (int) $_GET['staff_id'] : null;
$brandId     = isset($_GET['brand_id']) && $_GET['brand_id'] !== '' ? (int) $_GET['brand_id'] : null;
$range       = $_GET['range'] ?? 'all';
$dateFrom    = $validDate($_GET['date_from'] ?? '') ? $_GET['date_from'] : '';
$dateTo      = $validDate($_GET['date_to']   ?? '') ? $_GET['date_to']   : '';

$today = date('Y-m-d');

// ----- แปลง preset เป็นช่วงวันที่ -----
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

// ----- สร้างเงื่อนไข SQL -----
$where  = [];
$params = [];
if ($staffId)         { $where[] = 'r.staff_id = ?'; $params[] = $staffId; }
if ($brandId)         { $where[] = 's.brand_id = ?'; $params[] = $brandId; }
if ($dateFrom !== '') { $where[] = 'r.created_at >= ?'; $params[] = $dateFrom . ' 00:00:00'; }
if ($dateTo   !== '') { $where[] = 'r.created_at <= ?'; $params[] = $dateTo   . ' 23:59:59'; }
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// ══════════════════════════════════════════════
//  ผลลัพธ์ตามตัวกรอง
// ══════════════════════════════════════════════
// สรุปจำนวน + คะแนนเฉลี่ย
$stStat = $pdo->prepare("SELECT COUNT(*) AS total, ROUND(AVG(r.score),2) AS avg_score
                         FROM ratings r JOIN staff s ON s.id = r.staff_id $whereSql");
$stStat->execute($params);
$fStat    = $stStat->fetch() ?: [];
$fCount   = (int) ($fStat['total'] ?? 0);
$fAvg     = $fStat['avg_score'] !== null ? (float) ($fStat['avg_score'] ?? 0) : null;

// การกระจายคะแนน 1–5
$stDist = $pdo->prepare("SELECT r.score, COUNT(*) AS cnt
                         FROM ratings r JOIN staff s ON s.id = r.staff_id $whereSql GROUP BY r.score");
$stDist->execute($params);
$dist = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
foreach ($stDist->fetchAll() as $d) {
    $sc = (int) $d['score'];
    if (isset($dist[$sc])) $dist[$sc] = (int) $d['cnt'];
}
$distTotal = max(1, array_sum($dist));

// จำนวนที่ยังรอ AI ให้ดาว (นับเฉพาะในชุดที่กรองอยู่)
$stPend = $pdo->prepare("SELECT COUNT(*) FROM ratings r JOIN staff s ON s.id = r.staff_id $whereSql"
                        . ($whereSql ? ' AND ' : ' WHERE ') . "r.score IS NULL");
$stPend->execute($params);
$fPending = (int) $stPend->fetchColumn();

// คอลัมน์ฝั่ง AI จะมีก็ต่อเมื่อรัน ai_migration.sql แล้ว
// ถ้ายังไม่ได้รัน ให้ดึงเท่าที่มี หน้าจะได้ไม่พัง
$aiCols = ai_columns_ready($pdo)
    ? 'r.ai_reason, r.ai_confidence, r.ai_status, r.ai_score,'
    : 'NULL AS ai_reason, NULL AS ai_confidence, NULL AS ai_status, NULL AS ai_score,';

// รายการรีวิว
$stList = $pdo->prepare("
    SELECT r.id, r.score, r.feedback, r.created_at, $aiCols
           s.name, s.position, b.name AS brand_name, b.color AS brand_color
    FROM ratings r
    JOIN staff s ON s.id = r.staff_id
    LEFT JOIN brands b ON b.id = s.brand_id
    $whereSql
    ORDER BY r.created_at DESC
    LIMIT 200
");
$stList->execute($params);
$ratings = $stList->fetchAll();

// ══════════════════════════════════════════════
//  ข้อมูลประกอบ + ภาพรวมทั้งหมด
// ══════════════════════════════════════════════
$staffOptions = $pdo->query('SELECT id, name FROM staff ORDER BY name ASC')->fetchAll();
$brandOptions = $pdo->query('SELECT id, name FROM brands ORDER BY sort_order ASC, id ASC')->fetchAll();

// สรุปรายพนักงานขาย
// เรียงคะแนนน้อยขึ้นก่อน เพราะหน้านี้มีไว้หาคนที่ต้องเข้าไปดูแล
// ส่วนคนที่ยังไม่มีรีวิวเลย ดันไปไว้ท้ายตาราง — ไม่ใช่คะแนนแย่ แค่ยังไม่มีข้อมูล
// นับเฉพาะรีวิวที่มีดาวแล้ว (r.score IS NOT NULL) รายการที่รอ AI ยังไม่นับ
$summary = $pdo->query("
    SELECT s.id, s.name, s.position, b.name AS brand_name, b.color AS brand_color,
           COUNT(r.score) AS total_ratings, AVG(r.score) AS avg_score
    FROM staff s
    LEFT JOIN ratings r ON r.staff_id = s.id AND r.score IS NOT NULL
    LEFT JOIN brands  b ON b.id = s.brand_id
    GROUP BY s.id, s.name, s.position, b.name, b.color
    ORDER BY (AVG(r.score) IS NULL) ASC, AVG(r.score) ASC, COUNT(r.score) DESC
")->fetchAll();

// ----- ป้ายอธิบายช่วงที่เลือก -----
$rangeNames = ['all' => 'ทั้งหมด', 'today' => 'วันนี้', '7d' => '7 วันล่าสุด', '30d' => '30 วันล่าสุด', 'month' => 'เดือนนี้', 'custom' => 'กำหนดเอง'];
$rangeText  = $rangeNames[$range] ?? 'ทั้งหมด';
if ($dateFrom !== '' && $dateTo !== '') {
    $rangeText .= $dateFrom === $dateTo ? ' (' . $thaiDate($dateFrom) . ')' : ' (' . $thaiDate($dateFrom) . ' – ' . $thaiDate($dateTo) . ')';
}
$distColors = [5 => '#16A34A', 4 => '#84CC16', 3 => '#F59E0B', 2 => '#F97316', 1 => '#E11D48'];
$distLabels = [5 => 'พึงพอใจมากที่สุด', 4 => 'พึงพอใจมาก', 3 => 'ปานกลาง', 2 => 'พึงพอใจน้อย', 1 => 'ควรปรับปรุง'];

// ══════════════════════════════════════════════
//  ชิ้นส่วนที่เปลี่ยนตามตัวกรอง
//  ประกอบไว้เป็นสตริง ใช้ได้ทั้งตอนเปิดหน้าปกติ และตอนกดปุ่มกรอง (JSON)
//  หน้าจึงไม่ต้องโหลดใหม่ ตำแหน่งที่เลื่อนอยู่ไม่ขยับ
// ══════════════════════════════════════════════
require __DIR__ . '/includes/report_parts.php';

$filterHtml  = reportFilterBar($range, $dateFrom, $dateTo, $brandId, $staffId, $brandOptions, $staffOptions);

$resultHtml  = reportResults($rangeText, $fCount, $fAvg, $dist, $distTotal, $distColors, $distLabels, $fPending);
$listHtml    = reportList($ratings);
$listCount   = number_format(min($fCount, 200)) . ($fCount > 200 ? ' จาก ' . number_format($fCount) : '');

// ----- ถ้ามาจากการกดปุ่มกรอง: ตอบเป็น JSON แล้วจบ -----
if (($_GET['ajax'] ?? '') === '1') {
    header('Content-Type: application/json; charset=utf-8');
    $q = $_GET; unset($q['ajax']);
    echo json_encode([
        'url'   => 'report.php' . ($q ? '?' . http_build_query($q) : ''),
        'patch' => [
            '#filterZone'  => $filterHtml,
            '#resultZone'  => $resultHtml,
            '#listCount'   => $listCount,
            '#listZone'    => $listHtml,
        ],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

require 'includes/head.php';
?>
<h1>รายงานคะแนน</h1>
<p class="page-sub">เลือกช่วงเวลา แบรนด์ หรือพนักงานขายด้านล่าง — ผลสรุปและรายการรีวิวจะเปลี่ยนตามตัวกรองทันที</p>

<!-- ═══ ตัวกรอง — เปลี่ยนค่าโดยไม่โหลดหน้าใหม่ ═══ -->
<div id="filterZone" data-ajax-zone><?= $filterHtml ?></div>

<!-- ═══ สรุปผลตามตัวกรอง ═══ -->
<div id="resultZone"><?= $resultHtml ?></div>

<h2 class="section-title">รายการรีวิว <span class="count-badge" id="listCount"><?= $listCount ?></span></h2>
<div class="table-card" id="listZone"><?= $listHtml ?></div>

<h2 class="section-title">สรุปคะแนนรายพนักงานขาย</h2>
<p class="page-sub" style="margin-top:-6px;">
    เรียงจากคะแนนน้อยไปมาก คนที่ควรเข้าไปดูแลก่อนจะอยู่บนสุด —
    ส่วนนี้เป็นยอดสะสมทั้งหมด ไม่เปลี่ยนตามตัวกรองด้านบน
</p>
<div class="table-card">
    <table>
        <thead>
            <tr><th>ชื่อ-นามสกุล</th><th>แบรนด์</th><th>ตำแหน่ง</th><th>คะแนนเฉลี่ย</th><th>จำนวนรีวิว</th></tr>
        </thead>
        <tbody>
            <?php if (empty($summary)): ?>
                <tr><td colspan="5" class="empty-cell">ยังไม่มีข้อมูลพนักงานขาย — เพิ่มได้ที่เมนู "พนักงานขาย"</td></tr>
            <?php else: ?>
                <?php foreach ($summary as $row):
                    $hasData = $row['avg_score'] !== null && (int) $row['total_ratings'] > 0;
                    $avgNum  = $hasData ? (float) $row['avg_score'] : null;
                ?>
                    <tr>
                        <td class="wrap-cell"><?= htmlspecialchars($row['name']) ?></td>
                        <td>
                            <?php if (!empty($row['brand_name'])): ?>
                                <span class="brand-tag" style="<?= brand_tag_style($row['brand_color'] ?? '#D81300') ?>"><?= htmlspecialchars($row['brand_name']) ?></span>
                            <?php else: ?>
                                <span class="brand-tag none">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="wrap-cell"><?= htmlspecialchars($row['position']) ?></td>
                        <td>
                            <?php if ($hasData): ?>
                                <!-- คะแนนต่ำกว่า 3 ทำสีให้สังเกตเห็นง่าย -->
                                <span class="stars-display"><span aria-hidden="true"><?= str_repeat('★', (int) round($avgNum)) . str_repeat('☆', 5 - (int) round($avgNum)) ?></span></span>
                                <span class="avg-num <?= $avgNum < 3 ? 'is-low' : '' ?>"><?= number_format($avgNum, 2) ?></span>
                            <?php else: ?>
                                <!-- ยังไม่มีรีวิว — ไม่ใช่คะแนน 0 ต้องไม่แสดงเป็นดาว -->
                                <span class="no-data">ยังไม่มีรีวิว</span>
                            <?php endif; ?>
                        </td>
                        <td><?= (int) $row['total_ratings'] ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script src="assets/filters.js?v=<?= @filemtime(__DIR__ . '/assets/filters.js') ?: time() ?>"></script>
<script>
// ══════════════════════════════════════════════
//  แก้ดาวที่ AI ให้ผิด — แก้ในตารางได้เลย ไม่ต้องโหลดหน้าใหม่
//  ผูกเหตุการณ์ไว้ที่ document เพราะตารางถูกสลับใหม่ทุกครั้งที่กรอง
// ══════════════════════════════════════════════
(function () {
    function cell(el) { return el.closest('.score-cell'); }

    document.addEventListener('click', function (e) {
        var t = e.target;

        // เปิดกล่องแก้
        if (t.classList.contains('score-edit-btn')) {
            var c = cell(t);
            c.querySelector('.score-view').hidden = true;
            c.querySelector('.score-edit').hidden = false;
            c.querySelector('.score-edit-sel').focus();
            return;
        }

        // ยกเลิก
        if (t.classList.contains('score-edit-cancel')) {
            var c2 = cell(t);
            c2.querySelector('.score-edit').hidden = true;
            c2.querySelector('.score-view').hidden = false;
            return;
        }

        // บันทึก
        if (t.classList.contains('score-edit-save')) {
            var c3    = cell(t);
            var rid   = c3.dataset.rid;
            var score = c3.querySelector('.score-edit-sel').value;

            t.disabled = true;
            t.textContent = 'กำลังบันทึก...';

            var body = new URLSearchParams();
            body.set('id', rid);
            body.set('score', score);

            fetch('ai_override.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString()
            })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    if (!d.ok) throw new Error(d.error || 'บันทึกไม่สำเร็จ');

                    var n = parseInt(d.score, 10);
                    var stars = '';
                    for (var i = 1; i <= 5; i++) stars += (i <= n ? '★' : '☆');

                    var view = c3.querySelector('.score-view');
                    view.innerHTML =
                        '<span class="stars-display" aria-label="' + n + ' จาก 5 คะแนน">' +
                        '<span aria-hidden="true">' + stars + '</span></span>' +
                        '<span class="ai-chip manual">ผู้ดูแลกรอกเอง</span>' +
                        '<button type="button" class="score-edit-btn" aria-label="แก้ดาวรายการนี้">แก้</button>' +
                        '<div class="ai-why">' + d.note + '</div>';

                    c3.querySelector('.score-edit').hidden = true;
                    view.hidden = false;
                    t.disabled = false;
                    t.textContent = 'บันทึก';
                })
                .catch(function (err) {
                    alert('แก้ดาวไม่สำเร็จ — ' + err.message);
                    t.disabled = false;
                    t.textContent = 'บันทึก';
                });
        }
    });
})();
</script>

<?php require 'includes/footer.php'; ?>
