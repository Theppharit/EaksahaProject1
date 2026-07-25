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
$hasFeedback = isset($_GET['has_feedback']) && $_GET['has_feedback'] === '1';
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
if ($hasFeedback)     { $where[] = "r.feedback IS NOT NULL AND r.feedback <> ''"; }
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

// รายการรีวิว
$stList = $pdo->prepare("
    SELECT r.score, r.feedback, r.created_at, s.name, s.position, b.name AS brand_name, b.color AS brand_color
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

$summary = $pdo->query("
    SELECT s.id, s.name, s.position, b.name AS brand_name, b.color AS brand_color,
           COUNT(r.id) AS total_ratings, AVG(r.score) AS avg_score
    FROM staff s
    LEFT JOIN ratings r ON r.staff_id = s.id
    LEFT JOIN brands  b ON b.id = s.brand_id
    GROUP BY s.id, s.name, s.position, b.name, b.color
    ORDER BY s.id ASC
")->fetchAll();

$brandAvg = $pdo->query("
    SELECT b.name, b.color, ROUND(AVG(r.score), 2) AS avg_score, COUNT(r.id) AS total
    FROM brands b
    LEFT JOIN staff s   ON s.brand_id = b.id
    LEFT JOIN ratings r ON r.staff_id = s.id
    GROUP BY b.id, b.name, b.color
    ORDER BY b.sort_order ASC
")->fetchAll();
$barLabels = array_column($brandAvg, 'name');
$barScores = array_map(fn($r) => $r['avg_score'] !== null ? (float) $r['avg_score'] : 0, $brandAvg);
$barColors = array_column($brandAvg, 'color');
$barTotals = array_column($brandAvg, 'total');

// ----- ป้ายอธิบายช่วงที่เลือก -----
$rangeNames = ['all' => 'ทั้งหมด', 'today' => 'วันนี้', '7d' => '7 วันล่าสุด', '30d' => '30 วันล่าสุด', 'month' => 'เดือนนี้', 'custom' => 'กำหนดเอง'];
$rangeText  = $rangeNames[$range] ?? 'ทั้งหมด';
if ($dateFrom !== '' && $dateTo !== '') {
    $rangeText .= $dateFrom === $dateTo ? ' (' . $thaiDate($dateFrom) . ')' : ' (' . $thaiDate($dateFrom) . ' – ' . $thaiDate($dateTo) . ')';
}
$distColors = [5 => '#16A34A', 4 => '#84CC16', 3 => '#F59E0B', 2 => '#F97316', 1 => '#E11D48'];
$distLabels = [5 => 'พึงพอใจมากที่สุด', 4 => 'พึงพอใจมาก', 3 => 'ปานกลาง', 2 => 'พึงพอใจน้อย', 1 => 'ควรปรับปรุง'];

require 'includes/head.php';
?>
<h1>รายงานคะแนน</h1>
<p class="page-sub">เลือกช่วงเวลา แบรนด์ หรือพนักงานขายด้านล่าง — ผลสรุปและรายการรีวิวจะเปลี่ยนตามตัวกรองทันที</p>

<!-- ═══ ตัวกรอง ═══ -->
<form method="GET" id="filterForm">
    <input type="hidden" name="range" value="<?= htmlspecialchars($range) ?>">
    <div class="filter-bar" style="flex-direction:column; align-items:stretch; gap:14px;">

        <div class="filter-group">
            <span class="filter-label">ช่วงเวลา</span>
            <button type="submit" name="range" value="all"   class="pill <?= $range === 'all'   ? 'active' : '' ?>">ทั้งหมด</button>
            <button type="submit" name="range" value="today" class="pill <?= $range === 'today' ? 'active' : '' ?>">วันนี้</button>
            <button type="submit" name="range" value="7d"    class="pill <?= $range === '7d'    ? 'active' : '' ?>">7 วัน</button>
            <button type="submit" name="range" value="30d"   class="pill <?= $range === '30d'   ? 'active' : '' ?>">30 วัน</button>
            <button type="submit" name="range" value="month" class="pill <?= $range === 'month' ? 'active' : '' ?>">เดือนนี้</button>
            <span class="filter-sep" style="margin:0 4px;"></span>
            <span class="filter-label">เลือกวันที่เอง</span>
            <input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>" class="date-input">
            <span class="to">ถึง</span>
            <input type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>" class="date-input">
            <button type="submit" name="range" value="custom" class="pill mint <?= $range === 'custom' ? 'active' : '' ?>">✓ ใช้ช่วงนี้</button>
        </div>

        <div class="filter-group">
            <span class="filter-label">เจาะจง</span>
            <select name="brand_id" class="filter-select" onchange="this.form.submit()">
                <option value="">ทุกแบรนด์</option>
                <?php foreach ($brandOptions as $b): ?>
                    <option value="<?= (int) $b['id'] ?>" <?= $brandId === (int) $b['id'] ? 'selected' : '' ?>><?= htmlspecialchars($b['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="staff_id" class="filter-select" onchange="this.form.submit()">
                <option value="">พนักงานขายทุกคน</option>
                <?php foreach ($staffOptions as $s): ?>
                    <option value="<?= (int) $s['id'] ?>" <?= $staffId === (int) $s['id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <label class="check-pill <?= $hasFeedback ? 'active' : '' ?>">
                <input type="checkbox" name="has_feedback" value="1" <?= $hasFeedback ? 'checked' : '' ?> onchange="this.form.submit()">
                💬 เฉพาะที่มีข้อเสนอแนะ
            </label>
            <a href="report.php" class="pill" style="margin-left:auto;">↺ ล้างตัวกรอง</a>
        </div>
    </div>
</form>

<!-- ═══ สรุปผลตามตัวกรอง ═══ -->
<div class="result-strip">
    <div class="rs-stats">
        <div class="rs-stat">
            <div class="rs-label">ช่วงที่ดู</div>
            <div class="rs-value rs-small"><?= htmlspecialchars($rangeText) ?></div>
        </div>
        <div class="rs-stat">
            <div class="rs-label">จำนวนรีวิวที่พบ</div>
            <div class="rs-value"><?= number_format($fCount) ?> <small>รีวิว</small></div>
        </div>
        <div class="rs-stat">
            <div class="rs-label">คะแนนเฉลี่ย</div>
            <div class="rs-value rs-mint"><?= $fAvg !== null && $fCount > 0 ? number_format($fAvg, 2) : '-' ?> <small>/ 5</small></div>
        </div>
    </div>
    <div class="rs-dist">
        <?php foreach ($dist as $score => $cnt): $pct = round($cnt / $distTotal * 100); ?>
            <div class="dist-row" title="<?= $distLabels[$score] ?>">
                <span class="dist-score"><?= $score ?> ★</span>
                <div class="dist-track">
                    <div class="dist-bar" style="width:<?= max($cnt > 0 ? 2 : 0, $pct) ?>%; background:<?= $distColors[$score] ?>;"></div>
                </div>
                <span class="dist-count"><?= number_format($cnt) ?></span>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<h2 class="section-title">รายการรีวิว <span class="count-badge"><?= number_format(min($fCount, 200)) ?><?= $fCount > 200 ? ' จาก ' . number_format($fCount) : '' ?></span></h2>
<div class="table-card">
    <table>
        <thead>
            <tr><th>พนักงานขาย</th><th>แบรนด์</th><th>ตำแหน่ง</th><th>คะแนน</th><th>ข้อเสนอแนะ</th><th>วันที่</th></tr>
        </thead>
        <tbody>
            <?php if (empty($ratings)): ?>
                <tr><td colspan="6" class="empty-cell">ไม่พบรีวิวตามเงื่อนไขที่เลือก — ลองขยายช่วงเวลา หรือกด "ล้างตัวกรอง"</td></tr>
            <?php else: ?>
                <?php foreach ($ratings as $row): ?>
                    <tr>
                        <td class="wrap-cell"><?= htmlspecialchars($row['name']) ?></td>
                        <td>
                            <?php if (!empty($row['brand_name'])): ?>
                                <span class="brand-tag" style="background:<?= htmlspecialchars($row['brand_color'] ?? '#0EA5E9') ?>"><?= htmlspecialchars($row['brand_name']) ?></span>
                            <?php else: ?>
                                <span class="brand-tag none">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="wrap-cell"><?= htmlspecialchars($row['position']) ?></td>
                        <td class="stars-display"><?= str_repeat('★', (int) $row['score']) . str_repeat('☆', 5 - (int) $row['score']) ?></td>
                        <td class="wrap-cell" style="color:#46586B;">
                            <?= $row['feedback'] !== null && $row['feedback'] !== ''
                                ? nl2br(htmlspecialchars($row['feedback']))
                                : '<span style="color:#9AACBD;">-</span>' ?>
                        </td>
                        <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($row['created_at']))) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- ═══ ภาพรวมทั้งหมด (ไม่ขึ้นกับตัวกรอง) ═══ -->
<h2 class="section-title" style="margin-top:44px;">ภาพรวมทั้งระบบ</h2>
<p class="page-sub" style="margin-top:-6px;">ส่วนนี้แสดงข้อมูลสะสมทั้งหมด ไม่เปลี่ยนตามตัวกรองด้านบน</p>

<div class="chart-grid">
    <div class="chart-card">
        <div class="chart-head"><div class="chart-title"><span class="dot red"></span>คะแนนเฉลี่ยรายแบรนด์</div></div>
        <canvas id="barChart" height="80"></canvas>
    </div>
</div>

<h2 class="section-title">สรุปคะแนนรายพนักงานขาย</h2>
<div class="table-card">
    <table>
        <thead>
            <tr><th>ชื่อ-นามสกุล</th><th>แบรนด์</th><th>ตำแหน่ง</th><th>คะแนนเฉลี่ย</th><th>จำนวนรีวิว</th></tr>
        </thead>
        <tbody>
            <?php if (empty($summary)): ?>
                <tr><td colspan="5" class="empty-cell">ยังไม่มีข้อมูลพนักงานขาย — เพิ่มได้ที่เมนู "พนักงานขาย"</td></tr>
            <?php else: ?>
                <?php foreach ($summary as $row): ?>
                    <tr>
                        <td class="wrap-cell"><?= htmlspecialchars($row['name']) ?></td>
                        <td>
                            <?php if (!empty($row['brand_name'])): ?>
                                <span class="brand-tag" style="background:<?= htmlspecialchars($row['brand_color'] ?? '#0EA5E9') ?>"><?= htmlspecialchars($row['brand_name']) ?></span>
                            <?php else: ?>
                                <span class="brand-tag none">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="wrap-cell"><?= htmlspecialchars($row['position']) ?></td>
                        <td class="stars-display">
                            <?php $avg = $row['avg_score'] !== null ? round((float) $row['avg_score']) : 0;
                                  echo str_repeat('★', (int) $avg) . str_repeat('☆', 5 - (int) $avg); ?>
                            <span style="color:#5C7186; letter-spacing:normal;">(<?= $row['avg_score'] !== null ? round((float) $row['avg_score'], 2) : '-' ?>)</span>
                        </td>
                        <td><?= (int) $row['total_ratings'] ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
(function () {
    const barLabels = <?= json_encode($barLabels, JSON_UNESCAPED_UNICODE) ?>;
    const barScores = <?= json_encode($barScores) ?>;
    const barColors = <?= json_encode($barColors, JSON_UNESCAPED_UNICODE) ?>;
    const barTotals = <?= json_encode($barTotals) ?>;

    Chart.defaults.font.family = "'Kanit', sans-serif";
    Chart.defaults.color = '#5C7186';
    new Chart(document.getElementById('barChart'), {
        type: 'bar',
        data: { labels: barLabels, datasets: [{ label: 'คะแนนเฉลี่ย', data: barScores, backgroundColor: barColors.map(c => c || '#0EA5E9'), borderRadius: 8, borderSkipped: false, maxBarThickness: 64 }] },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#0F1F2E', borderColor: 'rgba(255,255,255,0.15)', borderWidth: 1,
                    titleColor: '#FFF', bodyColor: '#E9EAF0', padding: 10, cornerRadius: 8, displayColors: false,
                    callbacks: { label: (ctx) => ` ${ctx.parsed.y.toFixed(2)} / 5 (${barTotals[ctx.dataIndex]} รีวิว)` }
                }
            },
            scales: {
                y: { min: 0, max: 5, ticks: { stepSize: 1 }, grid: { color: 'rgba(15,31,46,0.08)' } },
                x: { grid: { display: false }, ticks: { maxRotation: 40, minRotation: 20, font: { size: 10 } } }
            }
        }
    });
})();
</script>

<?php require 'includes/footer.php'; ?>
