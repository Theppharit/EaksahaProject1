<?php
require '../conn/config.php';
require 'includes/auth.php';

$pageTitle  = 'รายงานคะแนน';
$activePage = 'report';

// ----- ตัวกรอง -----
$staffId     = isset($_GET['staff_id']) && $_GET['staff_id'] !== '' ? (int) $_GET['staff_id'] : null;
$brandId     = isset($_GET['brand_id']) && $_GET['brand_id'] !== '' ? (int) $_GET['brand_id'] : null;
$dateFrom    = $_GET['date_from'] ?? '';
$dateTo      = $_GET['date_to'] ?? '';
$hasFeedback = isset($_GET['has_feedback']) && $_GET['has_feedback'] === '1';

$validDate = fn($d) => $d !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d);

$where  = [];
$params = [];
if ($staffId)            { $where[] = 'r.staff_id = ?';           $params[] = $staffId; }
if ($brandId)            { $where[] = 's.brand_id = ?';           $params[] = $brandId; }
if ($validDate($dateFrom)) { $where[] = 'DATE(r.created_at) >= ?'; $params[] = $dateFrom; }
if ($validDate($dateTo))   { $where[] = 'DATE(r.created_at) <= ?'; $params[] = $dateTo; }
if ($hasFeedback)        { $where[] = "r.feedback IS NOT NULL AND r.feedback <> ''"; }

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// ----- สรุปคะแนนรายพนักงานขาย (ภาพรวมทั้งหมด) -----
$summary = $pdo->query("
    SELECT s.id, s.name, s.position, b.name AS brand_name, b.color AS brand_color,
           COUNT(r.id) AS total_ratings, AVG(r.score) AS avg_score
    FROM staff s
    LEFT JOIN ratings r ON r.staff_id = s.id
    LEFT JOIN brands  b ON b.id = s.brand_id
    GROUP BY s.id, s.name, s.position, b.name, b.color
    ORDER BY s.id ASC
")->fetchAll();

// ----- รายการรีวิวตามตัวกรอง -----
$listSql = "
    SELECT r.score, r.feedback, r.created_at, s.name, s.position, b.name AS brand_name, b.color AS brand_color
    FROM ratings r
    JOIN staff s ON s.id = r.staff_id
    LEFT JOIN brands b ON b.id = s.brand_id
    $whereSql
    ORDER BY r.created_at DESC
    LIMIT 200
";
$stmt = $pdo->prepare($listSql);
$stmt->execute($params);
$ratings = $stmt->fetchAll();

// dropdown
$staffOptions = $pdo->query('SELECT id, name FROM staff ORDER BY name ASC')->fetchAll();
$brandOptions = $pdo->query('SELECT id, name FROM brands ORDER BY sort_order ASC, id ASC')->fetchAll();

// ---- กราฟแท่ง: คะแนนเฉลี่ยรายแบรนด์ ----
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

require 'includes/head.php';
?>
<h1>รายงานคะแนน</h1>

<div class="chart-grid">
    <div class="chart-card">
        <div class="chart-head"><div class="chart-title"><span class="dot red"></span>คะแนนเฉลี่ยรายแบรนด์ (ภาพรวมทั้งหมด)</div></div>
        <canvas id="barChart" height="80"></canvas>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
(function () {
    const barLabels = <?= json_encode($barLabels, JSON_UNESCAPED_UNICODE) ?>;
    const barScores = <?= json_encode($barScores) ?>;
    const barColors = <?= json_encode($barColors, JSON_UNESCAPED_UNICODE) ?>;
    const barTotals = <?= json_encode($barTotals) ?>;

    Chart.defaults.font.family = "'Kanit', sans-serif";
    Chart.defaults.color = '#B0B2C2';
    const grid = 'rgba(255,255,255,0.09)';
    const tip = {
        backgroundColor: '#14141C', borderColor: 'rgba(255,255,255,0.12)', borderWidth: 1,
        titleColor: '#FFF', bodyColor: '#E9EAF0', padding: 10, cornerRadius: 8, displayColors: false
    };

    new Chart(document.getElementById('barChart'), {
        type: 'bar',
        data: { labels: barLabels, datasets: [{ label: 'คะแนนเฉลี่ย', data: barScores, backgroundColor: barColors.map(c => c || '#FF3B3B'), borderRadius: 8, borderSkipped: false, maxBarThickness: 64 }] },
        options: {
            responsive: true,
            plugins: { legend: { display: false }, tooltip: { ...tip, callbacks: { label: (ctx) => ` ${ctx.parsed.y.toFixed(2)} / 5 (${barTotals[ctx.dataIndex]} รีวิว)` } } },
            scales: {
                y: { min: 0, max: 5, ticks: { stepSize: 1 }, grid: { color: grid } },
                x: { grid: { display: false }, ticks: { maxRotation: 40, minRotation: 20, font: { size: 10 } } }
            }
        }
    });
})();
</script>

<h2 class="section-title">สรุปคะแนนรายพนักงานขาย</h2>
<div class="table-card">
    <table>
        <thead>
            <tr><th>ชื่อ-นามสกุล</th><th>แบรนด์</th><th>ตำแหน่ง</th><th>คะแนนเฉลี่ย</th><th>จำนวนรีวิว</th></tr>
        </thead>
        <tbody>
            <?php if (empty($summary)): ?>
                <tr><td colspan="5">ยังไม่มีข้อมูลพนักงานขาย</td></tr>
            <?php else: ?>
                <?php foreach ($summary as $row): ?>
                    <tr>
                        <td class="wrap-cell"><?= htmlspecialchars($row['name']) ?></td>
                        <td>
                            <?php if (!empty($row['brand_name'])): ?>
                                <span class="brand-tag" style="background:<?= htmlspecialchars($row['brand_color'] ?? '#E10600') ?>"><?= htmlspecialchars($row['brand_name']) ?></span>
                            <?php else: ?>
                                <span class="brand-tag none">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="wrap-cell"><?= htmlspecialchars($row['position']) ?></td>
                        <td class="stars-display">
                            <?php $avg = $row['avg_score'] !== null ? round((float) $row['avg_score']) : 0;
                                  echo str_repeat('★', (int) $avg) . str_repeat('☆', 5 - (int) $avg); ?>
                            <span style="color:#9A9AA2; letter-spacing:normal;">(<?= $row['avg_score'] !== null ? round((float) $row['avg_score'], 2) : '-' ?>)</span>
                        </td>
                        <td><?= (int) $row['total_ratings'] ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<h2 class="section-title">ค้นหารีวิว</h2>
<div class="form-card" style="max-width: none;">
    <form method="GET" style="display:flex; gap:16px; flex-wrap:wrap; align-items:flex-end;">
        <div class="field" style="margin-bottom:0; min-width:170px;">
            <label for="brand_id">แบรนด์</label>
            <select id="brand_id" name="brand_id">
                <option value="">ทั้งหมด</option>
                <?php foreach ($brandOptions as $b): ?>
                    <option value="<?= (int) $b['id'] ?>" <?= $brandId === (int) $b['id'] ? 'selected' : '' ?>><?= htmlspecialchars($b['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field" style="margin-bottom:0; min-width:190px;">
            <label for="staff_id">พนักงานขาย</label>
            <select id="staff_id" name="staff_id">
                <option value="">ทั้งหมด</option>
                <?php foreach ($staffOptions as $s): ?>
                    <option value="<?= (int) $s['id'] ?>" <?= $staffId === (int) $s['id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field" style="margin-bottom:0;">
            <label for="date_from">ตั้งแต่วันที่</label>
            <input type="date" id="date_from" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>">
        </div>
        <div class="field" style="margin-bottom:0;">
            <label for="date_to">ถึงวันที่</label>
            <input type="date" id="date_to" name="date_to" value="<?= htmlspecialchars($dateTo) ?>">
        </div>
        <div class="field" style="margin-bottom:0; display:flex; align-items:center; gap:8px;">
            <input type="checkbox" id="has_feedback" name="has_feedback" value="1" style="width:auto;" <?= $hasFeedback ? 'checked' : '' ?>>
            <label for="has_feedback" style="margin-bottom:0;">เฉพาะที่มีข้อเสนอแนะ</label>
        </div>
        <div class="field" style="margin-bottom:0;">
            <button type="submit" class="btn btn-primary">ค้นหา</button>
            <a href="report.php" class="btn btn-secondary">ล้างตัวกรอง</a>
        </div>
    </form>
</div>

<h2 class="section-title">รายการรีวิว (สูงสุด 200 รายการล่าสุด)</h2>
<div class="table-card">
    <table>
        <thead>
            <tr><th>พนักงานขาย</th><th>แบรนด์</th><th>ตำแหน่ง</th><th>คะแนน</th><th>ข้อเสนอแนะ</th><th>วันที่</th></tr>
        </thead>
        <tbody>
            <?php if (empty($ratings)): ?>
                <tr><td colspan="6">ไม่พบข้อมูลตามเงื่อนไขที่เลือก</td></tr>
            <?php else: ?>
                <?php foreach ($ratings as $row): ?>
                    <tr>
                        <td class="wrap-cell"><?= htmlspecialchars($row['name']) ?></td>
                        <td>
                            <?php if (!empty($row['brand_name'])): ?>
                                <span class="brand-tag" style="background:<?= htmlspecialchars($row['brand_color'] ?? '#E10600') ?>"><?= htmlspecialchars($row['brand_name']) ?></span>
                            <?php else: ?>
                                <span class="brand-tag none">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="wrap-cell"><?= htmlspecialchars($row['position']) ?></td>
                        <td class="stars-display"><?= str_repeat('★', (int) $row['score']) . str_repeat('☆', 5 - (int) $row['score']) ?></td>
                        <td class="wrap-cell" style="color:#C7C7CE;">
                            <?= $row['feedback'] !== null && $row['feedback'] !== ''
                                ? nl2br(htmlspecialchars($row['feedback']))
                                : '<span style="color:#6E6E76;">-</span>' ?>
                        </td>
                        <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($row['created_at']))) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require 'includes/footer.php'; ?>
