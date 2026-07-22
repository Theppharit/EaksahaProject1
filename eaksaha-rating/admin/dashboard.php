<?php
require '../conn/config.php';
require 'includes/auth.php';

$pageTitle  = 'แดชบอร์ด';
$activePage = 'dashboard';

// ---- สถิติภาพรวม ----
$totalStaff   = (int) $pdo->query('SELECT COUNT(*) FROM staff')->fetchColumn();
$totalRatings = (int) $pdo->query('SELECT COUNT(*) FROM ratings')->fetchColumn();

$avgScoreRaw = $pdo->query('SELECT AVG(score) FROM ratings')->fetchColumn();
$avgScore    = $avgScoreRaw !== null ? round((float) $avgScoreRaw, 2) : 0;

$todayRatings = (int) $pdo->query(
    "SELECT COUNT(*) FROM ratings WHERE DATE(created_at) = CURDATE()"
)->fetchColumn();

// ---- รีวิวล่าสุด 10 รายการ ----
$recent = $pdo->query("
    SELECT r.score, r.created_at, s.name, s.position, r.feedback, b.name AS brand_name, b.color AS brand_color
    FROM ratings r
    JOIN staff s ON s.id = r.staff_id
    LEFT JOIN brands b ON b.id = s.brand_id
    ORDER BY r.created_at DESC
    LIMIT 10
")->fetchAll();

// ---- กราฟเส้น: จำนวนรีวิวย้อนหลัง 14 วัน ----
$dailyRaw = $pdo->query("
    SELECT DATE(created_at) AS day, COUNT(*) AS cnt
    FROM ratings
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
    GROUP BY DATE(created_at)
")->fetchAll(PDO::FETCH_KEY_PAIR);

$dailyLabels = [];
$dailyCounts = [];
for ($i = 13; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-{$i} days"));
    $dailyLabels[] = date('d/m', strtotime($d));
    $dailyCounts[] = (int) ($dailyRaw[$d] ?? 0);
}

// ---- กราฟแท่ง: คะแนนเฉลี่ยรายแบรนด์ ----
$brandAvg = $pdo->query("
    SELECT b.name, b.color, ROUND(AVG(r.score), 2) AS avg_score, COUNT(r.id) AS total
    FROM brands b
    LEFT JOIN staff s  ON s.brand_id = b.id
    LEFT JOIN ratings r ON r.staff_id = s.id
    GROUP BY b.id, b.name, b.color
    ORDER BY b.sort_order ASC
")->fetchAll();

$brandLabels = array_column($brandAvg, 'name');
$brandScores = array_map(fn($r) => $r['avg_score'] !== null ? (float) $r['avg_score'] : 0, $brandAvg);
$brandColors = array_column($brandAvg, 'color');
$brandTotals = array_column($brandAvg, 'total');

require 'includes/head.php';
?>
<h1>แดชบอร์ด</h1>

<div class="stat-grid">
    <div class="stat-card">
        <div class="label">คะแนนเฉลี่ยรวม</div>
        <div class="value"><?= htmlspecialchars((string) $avgScore) ?> <small>/ 5</small></div>
    </div>
    <div class="stat-card">
        <div class="label">จำนวนรีวิวทั้งหมด</div>
        <div class="value"><?= $totalRatings ?></div>
    </div>
    <div class="stat-card">
        <div class="label">รีวิววันนี้</div>
        <div class="value"><?= $todayRatings ?></div>
    </div>
    <div class="stat-card">
        <div class="label">จำนวนพนักงานขาย</div>
        <div class="value"><?= $totalStaff ?></div>
    </div>
</div>

<div class="chart-grid">
    <div class="chart-card">
        <div class="chart-title">จำนวนรีวิวรายวัน (14 วันล่าสุด)</div>
        <canvas id="lineChart" height="90"></canvas>
    </div>
    <div class="chart-card">
        <div class="chart-title">คะแนนเฉลี่ยรายแบรนด์</div>
        <canvas id="barChart" height="90"></canvas>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
(function () {
    const lineLabels = <?= json_encode($dailyLabels, JSON_UNESCAPED_UNICODE) ?>;
    const lineCounts = <?= json_encode($dailyCounts) ?>;
    const barLabels  = <?= json_encode($brandLabels, JSON_UNESCAPED_UNICODE) ?>;
    const barScores  = <?= json_encode($brandScores) ?>;
    const barColors  = <?= json_encode($brandColors, JSON_UNESCAPED_UNICODE) ?>;
    const barTotals  = <?= json_encode($brandTotals) ?>;

    Chart.defaults.font.family = "'Kanit', sans-serif";
    Chart.defaults.color = '#9A9AA2';
    const grid = 'rgba(255,255,255,0.06)';

    new Chart(document.getElementById('lineChart'), {
        type: 'line',
        data: {
            labels: lineLabels,
            datasets: [{
                label: 'จำนวนรีวิว',
                data: lineCounts,
                borderColor: '#E10600',
                backgroundColor: 'rgba(225,6,0,0.12)',
                borderWidth: 2.5,
                pointRadius: 3,
                pointBackgroundColor: '#FF3B30',
                tension: 0.35,
                fill: true,
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false }, tooltip: { callbacks: { label: ctx => ` ${ctx.parsed.y} รีวิว` } } },
            scales: {
                y: { beginAtZero: true, ticks: { stepSize: 1, precision: 0 }, grid: { color: grid } },
                x: { grid: { display: false } }
            }
        }
    });

    new Chart(document.getElementById('barChart'), {
        type: 'bar',
        data: {
            labels: barLabels,
            datasets: [{
                label: 'คะแนนเฉลี่ย',
                data: barScores,
                backgroundColor: barColors.map(c => c || '#E10600'),
                borderRadius: 8,
                borderSkipped: false,
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false },
                tooltip: { callbacks: { label: (ctx) => ` ${ctx.parsed.y.toFixed(2)} / 5 (${barTotals[ctx.dataIndex]} รีวิว)` } }
            },
            scales: {
                y: { min: 0, max: 5, ticks: { stepSize: 1 }, grid: { color: grid } },
                x: { grid: { display: false }, ticks: { maxRotation: 40, minRotation: 20, font: { size: 10 } } }
            }
        }
    });
})();
</script>

<h2 class="section-title">รีวิวล่าสุด</h2>
<div class="table-card">
    <table>
        <thead>
            <tr>
                <th>พนักงานขาย</th>
                <th>แบรนด์</th>
                <th>ตำแหน่ง</th>
                <th>คะแนน</th>
                <th>ข้อเสนอแนะ</th>
                <th>วันที่</th>
            </tr>
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
