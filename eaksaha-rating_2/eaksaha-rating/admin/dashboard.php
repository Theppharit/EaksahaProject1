<?php
require '../conn/config.php';
require 'includes/auth.php';

$pageTitle  = 'แดชบอร์ด';
$activePage = 'dashboard';

// ══════════════════════════════════════════════
//  ตัวช่วยเกี่ยวกับช่วงเวลา
// ══════════════════════════════════════════════
$THAI_MON = ['', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];

$validDate = fn($d) => is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d);

// ----- รับค่าจาก GET -----
$preset = $_GET['preset'] ?? '30d';
$group  = $_GET['group']  ?? '';               // day | month | year (เว้นว่าง = auto)
$from   = $validDate($_GET['from'] ?? '') ? $_GET['from'] : '';
$to     = $validDate($_GET['to']   ?? '') ? $_GET['to']   : '';

$today  = date('Y-m-d');

// วันที่เก่าสุดในระบบ (สำหรับ preset = all)
$minDateRaw = $pdo->query("SELECT MIN(DATE(created_at)) FROM ratings")->fetchColumn();
$minDate    = $minDateRaw ?: date('Y-m-d', strtotime('-29 days'));

// ----- คำนวณช่วง + group เริ่มต้นตาม preset -----
$autoGroup = 'day';
switch ($preset) {
    case '7d':   $from = date('Y-m-d', strtotime('-6 days'));   $to = $today; $autoGroup = 'day';   break;
    case '90d':  $from = date('Y-m-d', strtotime('-89 days'));  $to = $today; $autoGroup = 'day';   break;
    case 'ytd':  $from = date('Y-01-01');                        $to = $today; $autoGroup = 'month'; break;
    case '12m':  $from = date('Y-m-01', strtotime('-11 months'));$to = $today; $autoGroup = 'month'; break;
    case 'all':  $from = $minDate;                               $to = $today;
                 $autoGroup = (strtotime($to) - strtotime($from)) > 60*86400*13 ? 'year' : 'month'; break;
    case 'custom':
        if (!$from) $from = date('Y-m-d', strtotime('-29 days'));
        if (!$to)   $to   = $today;
        if (strtotime($from) > strtotime($to)) { [$from, $to] = [$to, $from]; }
        $autoGroup = 'day';
        break;
    case '30d':
    default:     $preset = '30d'; $from = date('Y-m-d', strtotime('-29 days')); $to = $today; $autoGroup = 'day';
}

// group ที่ผู้ใช้เลือกเองมาก่อน ถ้าไม่เลือกใช้ค่า auto
$group = in_array($group, ['day', 'month', 'year'], true) ? $group : $autoGroup;

// ----- จำกัดจำนวนจุดไม่ให้กราฟรก (ปรับ group ขึ้นอัตโนมัติถ้าจำเป็น) -----
$spanDays = (int) round((strtotime($to) - strtotime($from)) / 86400) + 1;
if ($group === 'day'   && $spanDays > 400)  $group = 'month';
if ($group === 'month' && ($spanDays/30) > 400) $group = 'year';

// ══════════════════════════════════════════════
//  สร้างชุดข้อมูลกราฟตามช่วง/กลุ่ม
// ══════════════════════════════════════════════
function buildSeries($pdo, string $from, string $to, string $group, array $THAI_MON): array
{
    $fromDT = $from . ' 00:00:00';
    $toDT   = $to   . ' 23:59:59';

    if ($group === 'year') {
        $sql = "SELECT YEAR(created_at) k, COUNT(*) c, ROUND(AVG(score),2) a
                FROM ratings WHERE created_at BETWEEN ? AND ? GROUP BY YEAR(created_at)";
    } elseif ($group === 'month') {
        $sql = "SELECT DATE_FORMAT(created_at,'%Y-%m') k, COUNT(*) c, ROUND(AVG(score),2) a
                FROM ratings WHERE created_at BETWEEN ? AND ? GROUP BY DATE_FORMAT(created_at,'%Y-%m')";
    } else {
        $sql = "SELECT DATE(created_at) k, COUNT(*) c, ROUND(AVG(score),2) a
                FROM ratings WHERE created_at BETWEEN ? AND ? GROUP BY DATE(created_at)";
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$fromDT, $toDT]);

    $map = [];
    foreach ($stmt->fetchAll() as $r) {
        $map[(string) $r['k']] = ['c' => (int) $r['c'], 'a' => $r['a'] !== null ? (float) $r['a'] : null];
    }

    $labels = $counts = $avgs = [];
    $cur = new DateTime($from);
    $end = new DateTime($to);

    if ($group === 'year') {
        $y0 = (int) $cur->format('Y'); $y1 = (int) $end->format('Y');
        for ($y = $y0; $y <= $y1; $y++) {
            $labels[] = (string) $y;
            $counts[] = $map[(string) $y]['c'] ?? 0;
            $avgs[]   = $map[(string) $y]['a'] ?? null;
        }
    } elseif ($group === 'month') {
        $cur->modify('first day of this month');
        while ($cur <= $end) {
            $key = $cur->format('Y-m');
            $labels[] = $THAI_MON[(int) $cur->format('n')] . " '" . $cur->format('y');
            $counts[] = $map[$key]['c'] ?? 0;
            $avgs[]   = $map[$key]['a'] ?? null;
            $cur->modify('+1 month');
        }
    } else {
        while ($cur <= $end) {
            $key = $cur->format('Y-m-d');
            $labels[] = $cur->format('j') . ' ' . $THAI_MON[(int) $cur->format('n')];
            $counts[] = $map[$key]['c'] ?? 0;
            $avgs[]   = $map[$key]['a'] ?? null;
            $cur->modify('+1 day');
        }
    }

    return [$labels, $counts, $avgs, array_sum($counts)];
}

[$serLabels, $serCounts, $serAvgs, $rangeTotal] = buildSeries($pdo, $from, $to, $group, $THAI_MON);

// คะแนนเฉลี่ยรวมในช่วงที่เลือก
$rangeAvgRaw = $pdo->prepare("SELECT ROUND(AVG(score),2) FROM ratings WHERE created_at BETWEEN ? AND ?");
$rangeAvgRaw->execute([$from . ' 00:00:00', $to . ' 23:59:59']);
$rangeAvg = $rangeAvgRaw->fetchColumn();
$rangeAvg = $rangeAvg !== null ? (float) $rangeAvg : 0;

// ══════════════════════════════════════════════
//  สถิติภาพรวม (ทั้งหมด)
// ══════════════════════════════════════════════
$totalStaff   = (int) $pdo->query('SELECT COUNT(*) FROM staff')->fetchColumn();
$totalRatings = (int) $pdo->query('SELECT COUNT(*) FROM ratings')->fetchColumn();
$avgScoreRaw  = $pdo->query('SELECT AVG(score) FROM ratings')->fetchColumn();
$avgScore     = $avgScoreRaw !== null ? round((float) $avgScoreRaw, 2) : 0;
$todayRatings = (int) $pdo->query("SELECT COUNT(*) FROM ratings WHERE DATE(created_at) = CURDATE()")->fetchColumn();

// รีวิวล่าสุด 10 รายการ
$recent = $pdo->query("
    SELECT r.score, r.created_at, s.name, s.position, r.feedback, b.name AS brand_name, b.color AS brand_color
    FROM ratings r
    JOIN staff s ON s.id = r.staff_id
    LEFT JOIN brands b ON b.id = s.brand_id
    ORDER BY r.created_at DESC LIMIT 10
")->fetchAll();

// กราฟแท่ง: คะแนนเฉลี่ยรายแบรนด์ (ภาพรวมทั้งหมด)
$brandAvg = $pdo->query("
    SELECT b.name, b.color, ROUND(AVG(r.score), 2) AS avg_score, COUNT(r.id) AS total
    FROM brands b
    LEFT JOIN staff s   ON s.brand_id = b.id
    LEFT JOIN ratings r ON r.staff_id = s.id
    GROUP BY b.id, b.name, b.color
    ORDER BY b.sort_order ASC
")->fetchAll();
$brandLabels = array_column($brandAvg, 'name');
$brandScores = array_map(fn($r) => $r['avg_score'] !== null ? (float) $r['avg_score'] : 0, $brandAvg);
$brandColors = array_column($brandAvg, 'color');
$brandTotals = array_column($brandAvg, 'total');

// ----- helper สร้างลิงก์ preset/group (คงค่า custom range ไว้) -----
function rangeLink(array $ov, string $preset, string $from, string $to): string {
    $q = array_merge(['preset' => $preset], $preset === 'custom' ? ['from' => $from, 'to' => $to] : [], $ov);
    return '?' . http_build_query($q);
}

$presets = ['7d' => '7 วัน', '30d' => '30 วัน', '90d' => '90 วัน', '12m' => '12 เดือน', 'ytd' => 'ปีนี้', 'all' => 'ทั้งหมด'];
$groups  = ['day' => 'รายวัน', 'month' => 'รายเดือน', 'year' => 'รายปี'];
$rangeThai = date('j', strtotime($from)) . ' ' . $THAI_MON[(int) date('n', strtotime($from))] . ' ' . date('Y', strtotime($from))
           . ' – ' . date('j', strtotime($to)) . ' ' . $THAI_MON[(int) date('n', strtotime($to))] . ' ' . date('Y', strtotime($to));

require 'includes/head.php';
?>
<h1>แดชบอร์ด</h1>
<p class="page-sub">ภาพรวมผลประเมินความพึงพอใจของลูกค้าทั้งระบบ — ตัวเลข 4 ช่องบนคือยอดสะสมทั้งหมด ส่วนกราฟด้านล่างเลือกช่วงเวลาที่ต้องการดูได้</p>

<div class="stat-grid">
    <div class="stat-card accent-gold">
        <div class="st-top"><span class="label">คะแนนเฉลี่ยรวม</span><span class="st-ic">★</span></div>
        <div class="value"><?= htmlspecialchars((string) $avgScore) ?> <small>/ 5</small></div>
    </div>
    <div class="stat-card">
        <div class="st-top"><span class="label">จำนวนรีวิวทั้งหมด</span><span class="st-ic">▤</span></div>
        <div class="value"><?= number_format($totalRatings) ?></div>
    </div>
    <div class="stat-card">
        <div class="st-top"><span class="label">รีวิววันนี้</span><span class="st-ic">☀</span></div>
        <div class="value"><?= number_format($todayRatings) ?></div>
    </div>
    <div class="stat-card">
        <div class="st-top"><span class="label">จำนวนพนักงานขาย</span><span class="st-ic">☰</span></div>
        <div class="value"><?= number_format($totalStaff) ?></div>
    </div>
</div>

<h2 class="section-title">แนวโน้มการประเมินตามช่วงเวลา</h2>

<!-- ═══ แถบเลือกช่วงเวลา ═══ -->
<div class="filter-bar">
    <div class="filter-group">
        <span class="filter-label">ช่วง</span>
        <?php foreach ($presets as $k => $label): ?>
            <a class="pill <?= $preset === $k ? 'active' : '' ?>" href="<?= htmlspecialchars(rangeLink([], $k, $from, $to)) ?>"><?= $label ?></a>
        <?php endforeach; ?>
    </div>
    <div class="filter-sep"></div>
    <div class="filter-group">
        <span class="filter-label">มุมมอง</span>
        <?php foreach ($groups as $k => $label): ?>
            <a class="pill <?= $group === $k ? 'active' : '' ?>" href="<?= htmlspecialchars(rangeLink(['group' => $k], $preset, $from, $to)) ?>"><?= $label ?></a>
        <?php endforeach; ?>
    </div>
    <form class="custom-range" method="GET">
        <input type="hidden" name="preset" value="custom">
        <input type="hidden" name="group" value="<?= htmlspecialchars($group) ?>">
        <span class="filter-label">เลือกวันที่เอง</span>
        <input type="date" name="from" value="<?= htmlspecialchars($from) ?>" title="วันที่เริ่มต้น">
        <span class="to">ถึง</span>
        <input type="date" name="to" value="<?= htmlspecialchars($to) ?>" title="วันที่สิ้นสุด">
        <button type="submit" class="pill gold <?= $preset === 'custom' ? 'active' : '' ?>">✓ ใช้ช่วงนี้</button>
    </form>
</div>

<div class="chart-grid two">
    <div class="chart-card">
        <div class="chart-head">
            <div class="chart-title"><span class="dot red"></span>จำนวนรีวิว</div>
            <div class="chart-caption"><?= htmlspecialchars($rangeThai) ?> · รวม <b><?= number_format($rangeTotal) ?></b> รีวิว</div>
        </div>
        <canvas id="lineChart" height="118"></canvas>
    </div>
    <div class="chart-card">
        <div class="chart-head">
            <div class="chart-title"><span class="dot gold"></span>คะแนนเฉลี่ย</div>
            <div class="chart-caption">เฉลี่ยในช่วง <b><?= $rangeAvg > 0 ? number_format($rangeAvg, 2) : '-' ?></b> / 5</div>
        </div>
        <canvas id="avgChart" height="118"></canvas>
    </div>
</div>

<h2 class="section-title">ภาพรวมคะแนนรายแบรนด์ (ทั้งหมด)</h2>
<div class="chart-grid">
    <div class="chart-card">
        <div class="chart-head"><div class="chart-title"><span class="dot red"></span>คะแนนเฉลี่ยรายแบรนด์</div></div>
        <canvas id="barChart" height="80"></canvas>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
(function () {
    const labels    = <?= json_encode($serLabels, JSON_UNESCAPED_UNICODE) ?>;
    const counts    = <?= json_encode($serCounts) ?>;
    const avgs      = <?= json_encode($serAvgs) ?>;
    const barLabels = <?= json_encode($brandLabels, JSON_UNESCAPED_UNICODE) ?>;
    const barScores = <?= json_encode($brandScores) ?>;
    const barColors = <?= json_encode($brandColors, JSON_UNESCAPED_UNICODE) ?>;
    const barTotals = <?= json_encode($brandTotals) ?>;

    Chart.defaults.font.family = "'Kanit', sans-serif";
    Chart.defaults.color = '#B0B2C2';
    const grid = 'rgba(255,255,255,0.09)';
    const tip = {
        backgroundColor: '#14141C', borderColor: 'rgba(255,255,255,0.12)', borderWidth: 1,
        titleColor: '#FFF', bodyColor: '#E9EAF0', padding: 10, cornerRadius: 8, displayColors: false
    };

    // จำนวนรีวิว (แดง)
    const g1 = document.getElementById('lineChart').getContext('2d');
    const grad1 = g1.createLinearGradient(0, 0, 0, 240);
    grad1.addColorStop(0, 'rgba(255,59,59,0.34)'); grad1.addColorStop(1, 'rgba(255,59,59,0.02)');
    new Chart(g1, {
        type: 'line',
        data: { labels, datasets: [{
            label: 'จำนวนรีวิว', data: counts,
            borderColor: '#FF3B3B', backgroundColor: grad1, borderWidth: 2.5,
            pointRadius: labels.length > 40 ? 0 : 3, pointHoverRadius: 5,
            pointBackgroundColor: '#FF6A5A', pointBorderColor: '#14141C', pointBorderWidth: 1.5,
            tension: 0.35, fill: true
        }]},
        options: {
            responsive: true, interaction: { mode: 'index', intersect: false },
            plugins: { legend: { display: false }, tooltip: { ...tip, callbacks: { label: c => ` ${c.parsed.y} รีวิว` } } },
            scales: { y: { beginAtZero: true, ticks: { stepSize: 1, precision: 0 }, grid: { color: grid } },
                      x: { grid: { display: false }, ticks: { maxRotation: 0, autoSkip: true, maxTicksLimit: 12 } } }
        }
    });

    // คะแนนเฉลี่ย (ทอง)
    const g2 = document.getElementById('avgChart').getContext('2d');
    const grad2 = g2.createLinearGradient(0, 0, 0, 240);
    grad2.addColorStop(0, 'rgba(255,176,32,0.32)'); grad2.addColorStop(1, 'rgba(255,176,32,0.02)');
    new Chart(g2, {
        type: 'line',
        data: { labels, datasets: [{
            label: 'คะแนนเฉลี่ย', data: avgs, spanGaps: true,
            borderColor: '#FFB020', backgroundColor: grad2, borderWidth: 2.5,
            pointRadius: labels.length > 40 ? 0 : 3, pointHoverRadius: 5,
            pointBackgroundColor: '#FFC24B', pointBorderColor: '#14141C', pointBorderWidth: 1.5,
            tension: 0.35, fill: true
        }]},
        options: {
            responsive: true, interaction: { mode: 'index', intersect: false },
            plugins: { legend: { display: false }, tooltip: { ...tip, callbacks: { label: c => c.parsed.y === null ? ' ไม่มีข้อมูล' : ` ${c.parsed.y.toFixed(2)} / 5` } } },
            scales: { y: { min: 0, max: 5, ticks: { stepSize: 1 }, grid: { color: grid } },
                      x: { grid: { display: false }, ticks: { maxRotation: 0, autoSkip: true, maxTicksLimit: 12 } } }
        }
    });

    // คะแนนเฉลี่ยรายแบรนด์
    new Chart(document.getElementById('barChart'), {
        type: 'bar',
        data: { labels: barLabels, datasets: [{
            label: 'คะแนนเฉลี่ย', data: barScores,
            backgroundColor: barColors.map(c => c || '#FF3B3B'),
            borderRadius: 8, borderSkipped: false, maxBarThickness: 64
        }]},
        options: {
            responsive: true,
            plugins: { legend: { display: false }, tooltip: { ...tip, callbacks: { label: c => ` ${c.parsed.y.toFixed(2)} / 5 (${barTotals[c.dataIndex]} รีวิว)` } } },
            scales: { y: { min: 0, max: 5, ticks: { stepSize: 1 }, grid: { color: grid } },
                      x: { grid: { display: false }, ticks: { maxRotation: 40, minRotation: 20, font: { size: 10 } } } }
        }
    });
})();
</script>

<h2 class="section-title">รีวิวล่าสุด</h2>
<div class="table-card">
    <table>
        <thead>
            <tr><th>พนักงานขาย</th><th>แบรนด์</th><th>ตำแหน่ง</th><th>คะแนน</th><th>ข้อเสนอแนะ</th><th>วันที่</th></tr>
        </thead>
        <tbody>
            <?php if (empty($recent)): ?>
                <tr><td colspan="6">ยังไม่มีข้อมูลรีวิว</td></tr>
            <?php else: ?>
                <?php foreach ($recent as $row): ?>
                    <tr>
                        <td class="wrap-cell"><?= htmlspecialchars($row['name']) ?></td>
                        <td>
                            <?php if (!empty($row['brand_name'])): ?>
                                <span class="brand-tag" style="background:<?= htmlspecialchars($row['brand_color'] ?? '#FF3B3B') ?>"><?= htmlspecialchars($row['brand_name']) ?></span>
                            <?php else: ?>
                                <span class="brand-tag none">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="wrap-cell"><?= htmlspecialchars($row['position']) ?></td>
                        <td class="stars-display"><?= str_repeat('★', (int) $row['score']) . str_repeat('☆', 5 - (int) $row['score']) ?></td>
                        <td class="wrap-cell" style="color:#CFD0DA;">
                            <?= $row['feedback'] !== null && $row['feedback'] !== ''
                                ? nl2br(htmlspecialchars($row['feedback']))
                                : '<span style="color:#7E8091;">-</span>' ?>
                        </td>
                        <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($row['created_at']))) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require 'includes/footer.php'; ?>
