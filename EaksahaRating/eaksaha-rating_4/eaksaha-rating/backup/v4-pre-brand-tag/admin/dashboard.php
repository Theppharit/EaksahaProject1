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

// ══════════════════════════════════════════════
//  ข้อมูลเพิ่มเติมสำหรับการ์ดสรุป
// ══════════════════════════════════════════════

// (8) ช่วงก่อนหน้าที่ยาวเท่ากัน — ใช้เทียบ ▲▼
$spanSec  = strtotime($to) - strtotime($from);
$prevTo   = date('Y-m-d', strtotime($from . ' -1 day'));
$prevFrom = date('Y-m-d', strtotime($prevTo) - $spanSec);
$prevStmt = $pdo->prepare('SELECT COUNT(*) c, ROUND(AVG(score),2) a FROM ratings WHERE created_at BETWEEN ? AND ?');
$prevStmt->execute([$prevFrom . ' 00:00:00', $prevTo . ' 23:59:59']);
$prevRow   = $prevStmt->fetch() ?: ['c' => 0, 'a' => null];
$prevTotal = (int) $prevRow['c'];
$prevAvg   = $prevRow['a'] !== null ? (float) $prevRow['a'] : null;

$deltaTotal = $prevTotal > 0 ? $rangeTotal - $prevTotal : null;
$deltaAvg   = ($prevAvg !== null && $rangeAvg > 0) ? round($rangeAvg - $prevAvg, 2) : null;

// รีวิวเมื่อวาน (เทียบกับวันนี้)
$yesterdayRatings = (int) $pdo->query(
    'SELECT COUNT(*) FROM ratings WHERE DATE(created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)'
)->fetchColumn();
$deltaToday = $todayRatings - $yesterdayRatings;

// (11) สัดส่วนรีวิวที่มีข้อเสนอแนะ
$withFeedback = (int) $pdo->query(
    "SELECT COUNT(*) FROM ratings WHERE feedback IS NOT NULL AND TRIM(feedback) <> ''"
)->fetchColumn();
$feedbackPct = $totalRatings > 0 ? (int) round($withFeedback * 100 / $totalRatings) : 0;

// (5) พนักงานที่มีรีวิวเข้ามาแล้วจริง
$staffRated     = (int) $pdo->query('SELECT COUNT(DISTINCT staff_id) FROM ratings')->fetchColumn();
$staffNoRating  = max(0, $totalStaff - $staffRated);

// (9) ข้อมูลกราฟจิ๋ว 7 วันล่าสุด
$sparkStmt = $pdo->prepare(
    'SELECT DATE(created_at) d, COUNT(*) c, ROUND(AVG(score),2) a
     FROM ratings WHERE created_at >= ? GROUP BY DATE(created_at)'
);
$sparkStmt->execute([date('Y-m-d', strtotime('-6 days')) . ' 00:00:00']);
$sparkMap = [];
foreach ($sparkStmt->fetchAll() as $r) {
    $sparkMap[(string) $r['d']] = ['c' => (int) $r['c'], 'a' => $r['a'] !== null ? (float) $r['a'] : null];
}
$sparkCounts = $sparkAvgs = [];
for ($i = 6; $i >= 0; $i--) {
    $k = date('Y-m-d', strtotime("-$i days"));
    $sparkCounts[] = $sparkMap[$k]['c'] ?? 0;
    $sparkAvgs[]   = $sparkMap[$k]['a'] ?? null;
}

// (10) รายการที่ต้องดูด่วน — เซลล์ที่คะแนนเฉลี่ยต่ำกว่า 3
$watchStaff = $pdo->query('
    SELECT s.name, ROUND(AVG(r.score),2) AS avg_score, COUNT(r.id) AS total
    FROM staff s JOIN ratings r ON r.staff_id = s.id
    GROUP BY s.id, s.name
    HAVING AVG(r.score) < 3
    ORDER BY avg_score ASC, total DESC
    LIMIT 3
')->fetchAll();

// ----- helper: กราฟเส้นจิ๋ว (SVG) -----
function sparkSvg(array $vals, ?float $forceMax = null): string
{
    $clean = [];
    $last  = 0.0;
    foreach ($vals as $v) { $last = $v === null ? $last : (float) $v; $clean[] = $last; }
    $n = count($clean);
    if ($n < 2) return '';
    $max = $forceMax ?? max(1.0, max($clean));
    if ($max <= 0) $max = 1.0;
    $w = 100; $h = 26; $step = $w / ($n - 1);
    $pts = [];
    foreach ($clean as $i => $v) {
        $x = round($i * $step, 2);
        $y = round($h - 2 - ($v / $max) * ($h - 5), 2);
        $pts[] = $x . ',' . $y;
    }
    $line = implode(' ', $pts);
    return '<svg class="spark" viewBox="0 0 ' . $w . ' ' . $h . '" preserveAspectRatio="none" aria-hidden="true">'
         . '<polygon points="0,' . $h . ' ' . $line . ' ' . $w . ',' . $h . '" fill="currentColor" opacity="0.13"/>'
         . '<polyline points="' . $line . '" fill="none" stroke="currentColor" stroke-width="2"'
         . ' stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke"/>'
         . '</svg>';
}

// ----- helper: ป้ายเทียบช่วงก่อน -----
function deltaTag(?float $d, string $unit = '', int $dec = 0): string
{
    if ($d === null) return '<span class="delta flat">— ไม่มีข้อมูลช่วงก่อนหน้า</span>';
    if (abs($d) < ($dec > 0 ? 0.005 : 0.5)) return '<span class="delta flat">▬ เท่าเดิม</span>';
    $up  = $d > 0;
    $cls = $up ? 'up' : 'down';
    $ar  = $up ? '▲' : '▼';
    $val = number_format(abs($d), $dec);
    return '<span class="delta ' . $cls . '">' . $ar . ' ' . $val . $unit . '</span>';
}

// ----- เวลาที่ดึงข้อมูล (บอกความสดของข้อมูลบนแดชบอร์ด) -----
$todayThai   = (int) date('j') . ' ' . $THAI_MON[(int) date('n')] . ' ' . date('Y');
$dataAsOf    = $todayThai . ' เวลา ' . date('H:i') . ' น.';

require 'includes/head.php';
?>
<style>
/* บรรทัดบอกความสดของข้อมูล */
.as-of {
    display: inline-flex; align-items: center; gap: 6px;
    font-size: 12.5px; color: var(--muted-2); margin-top: 4px;
}
.as-of::before {
    content: ''; width: 6px; height: 6px; border-radius: 50%;
    background: var(--good); flex-shrink: 0;
}
/* ═══════════ DASHBOARD — สรุปเน้นอ่านเร็ว ═══════════ */


/* การ์ดตัวเลขสรุป */
.kpi-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; }
.kpi {
    position: relative; background: var(--panel); border: 1px solid var(--line);
    border-radius: 12px; padding: 18px 20px; box-shadow: var(--sh-1);
    transition: box-shadow 0.18s, border-color 0.18s;
    display: flex; flex-direction: column;
}
.kpi:hover { box-shadow: var(--sh-2); border-color: var(--line-2); }
.kpi::before {
    content: ''; position: absolute; top: 12px; bottom: 12px; left: 0;
    width: 3px; border-radius: 0 3px 3px 0; background: var(--accent);
}
.kpi.gold::before { background: var(--mint); }
/* (7) ตัวชี้วัดหลักเด่นกว่าใบอื่น — ใช้ขนาดตัวเลข + สีทอง ไม่กินความกว้างจนแถวเสียรูป */
.kpi.primary .kpi-value { font-size: 38px; }
.kpi.primary { background: linear-gradient(180deg, rgba(var(--glow2),0.05), transparent 60%), var(--panel); }

.kpi-top { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; }
.kpi-ic {
    width: 36px; height: 36px; border-radius: 9px; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    background: rgba(var(--glow),0.09); border: 1px solid rgba(var(--glow),0.2);
    color: var(--accent-soft);
}
.kpi.gold .kpi-ic { background: rgba(var(--glow2),0.10); border-color: rgba(var(--glow2),0.24); color: var(--mint-soft); }
.kpi-ic svg { width: 19px; height: 19px; }
.kpi-label { font-size: 13px; color: var(--muted); margin-bottom: 5px; }
.kpi-value { font-size: 30px; font-weight: 700; color: var(--text); line-height: 1.05; letter-spacing: -0.02em; }
.kpi-value small { font-size: 15px; font-weight: 500; color: var(--muted); }
.kpi-sub {
    font-size: 12.5px; color: var(--muted-2); margin-top: auto;
    padding-top: 10px; border-top: 1px solid var(--line);
    display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
}
.kpi-foot-gap { margin-top: 10px; }

/* (8) ป้ายเทียบช่วงก่อนหน้า */
.delta { font-size: 12.5px; font-weight: 600; white-space: nowrap; }
.delta.up   { color: var(--good); }
.delta.down { color: var(--bad); }
.delta.flat { color: var(--muted-2); font-weight: 500; }

/* (9) กราฟเส้นจิ๋ว */
.spark-wrap { margin-top: 12px; }
.spark-wrap.red  { color: var(--accent); }
.spark-wrap.gold { color: var(--mint); }
.spark { width: 100%; height: 26px; display: block; }
.spark-cap { font-size: 12px; color: var(--muted-2); margin-top: 4px; }

/* (10) แถบต้องดูด่วน */
.watch-strip {
    display: flex; align-items: flex-start; gap: 12px;
    background: var(--panel); border: 1px solid var(--line);
    border-left: 3px solid var(--warn);
    border-radius: 10px; padding: 14px 16px; margin-top: 14px;
}
.watch-ic { color: var(--warn); flex-shrink: 0; display: flex; }
.watch-ic svg { width: 20px; height: 20px; }
.watch-body { min-width: 0; }
.watch-title { font-size: 13.5px; font-weight: 600; color: var(--text); margin-bottom: 4px; }
.watch-list { font-size: 13px; color: var(--muted); line-height: 1.7; }
.watch-list .nm { color: var(--text); font-weight: 600; }
.watch-list .sc { color: var(--bad); font-weight: 600; }
.watch-more { font-size: 12.5px; margin-top: 6px; }
.watch-more a { color: var(--accent-soft); font-weight: 600; text-decoration: none; }
.watch-more a:hover { text-decoration: underline; }

@media (max-width: 1100px) { .kpi-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 560px) {
    .kpi-grid { grid-template-columns: 1fr; }
    .kpi.primary .kpi-value { font-size: 32px; }
}
</style>

<!-- หัวข้อรูปแบบเดียวกับทุกหน้าในระบบ + บอกความสดของข้อมูล -->
<h1>แดชบอร์ด</h1>
<p class="page-sub">
    ภาพรวมผลประเมินความพึงพอใจของลูกค้าทั้งระบบ — การ์ดด้านล่างคือยอดสะสมทั้งหมด ส่วนกราฟเลือกช่วงเวลาได้<br>
    <span class="as-of">ข้อมูล ณ <?= htmlspecialchars($dataAsOf) ?></span>
</p>

<div class="kpi-grid">
    <!-- (7) ตัวชี้วัดหลัก — กว้าง 2 ช่อง ตัวเลขใหญ่กว่าใบอื่น -->
    <div class="kpi gold primary">
        <div class="kpi-top">
            <div>
                <div class="kpi-label">คะแนนเฉลี่ยรวม (ทั้งหมด)</div>
                <div class="kpi-value"><?= htmlspecialchars((string) $avgScore) ?> <small>/ 5</small></div>
            </div>
            <span class="kpi-ic"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l2.9 6.26L21.6 9l-4.8 4.68 1.13 6.6L12 17.27 6.07 20.28l1.13-6.6L2.4 9l6.7-.74L12 2z"/></svg></span>
        </div>
        <!-- (9) แนวโน้มคะแนน 7 วันล่าสุด -->
        <div class="spark-wrap gold">
            <?= sparkSvg($sparkAvgs, 5.0) ?>
            <div class="spark-cap">คะแนนเฉลี่ยราย 7 วันล่าสุด</div>
        </div>
        <div class="kpi-sub">
            <span>ในช่วงที่เลือก <?= $rangeAvg > 0 ? number_format($rangeAvg, 2) : '-' ?> / 5</span>
            <?= deltaTag($deltaAvg, ' คะแนน', 2) ?>
        </div>
    </div>

    <div class="kpi">
        <div class="kpi-top">
            <div>
                <div class="kpi-label">จำนวนรีวิวทั้งหมด</div>
                <div class="kpi-value"><?= number_format($totalRatings) ?></div>
            </div>
            <span class="kpi-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="8" y1="13" x2="16" y2="13"/><line x1="8" y1="17" x2="13" y2="17"/></svg></span>
        </div>
        <!-- (11) คุณภาพ feedback ไม่ใช่แค่ปริมาณ -->
        <div class="kpi-foot-gap"></div>
        <div class="kpi-sub">
            <span>มีข้อเสนอแนะ <b><?= $feedbackPct ?>%</b> (<?= number_format($withFeedback) ?> รายการ)</span>
        </div>
    </div>

    <div class="kpi">
        <div class="kpi-top">
            <div>
                <div class="kpi-label">รีวิววันนี้</div>
                <div class="kpi-value"><?= number_format($todayRatings) ?></div>
            </div>
            <span class="kpi-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></span>
        </div>
        <!-- (9) จำนวนรีวิว 7 วันล่าสุด -->
        <div class="spark-wrap red">
            <?= sparkSvg($sparkCounts) ?>
        </div>
        <!-- (2)(3) ไม่มีวันที่ซ้ำ ไม่มีคำว่า "เรียลไทม์" — เทียบเมื่อวานแทน -->
        <div class="kpi-sub">
            <span>เมื่อวาน <?= number_format($yesterdayRatings) ?></span>
            <?= deltaTag($yesterdayRatings > 0 || $todayRatings > 0 ? (float) $deltaToday : null, ' รายการ') ?>
        </div>
    </div>

    <div class="kpi">
        <div class="kpi-top">
            <div>
                <!-- (5) ข้อความตรงกับข้อมูลจริง: นับพนักงานทั้งหมดในระบบ -->
                <div class="kpi-label">พนักงานขายในระบบ</div>
                <div class="kpi-value"><?= number_format($totalStaff) ?></div>
            </div>
            <span class="kpi-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></span>
        </div>
        <div class="kpi-foot-gap"></div>
        <div class="kpi-sub">
            <span>มีรีวิวแล้ว <b><?= number_format($staffRated) ?></b> คน<?= $staffNoRating > 0 ? ' · ยังไม่มี ' . number_format($staffNoRating) . ' คน' : '' ?></span>
        </div>
    </div>
</div>

<!-- (10) แจ้งเตือนสิ่งที่ต้องดูด่วน — แสดงเฉพาะเมื่อมีจริง -->
<?php if (!empty($watchStaff) || $staffNoRating > 0): ?>
<div class="watch-strip">
    <span class="watch-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></span>
    <div class="watch-body">
        <div class="watch-title">ต้องดูด่วน</div>
        <div class="watch-list">
            <?php if (!empty($watchStaff)): ?>
                <?php foreach ($watchStaff as $w): ?>
                    <div><span class="nm"><?= htmlspecialchars($w['name']) ?></span> คะแนนเฉลี่ย <span class="sc"><?= number_format((float) $w['avg_score'], 2) ?></span> / 5 · จาก <?= number_format((int) $w['total']) ?> รีวิว</div>
                <?php endforeach; ?>
            <?php endif; ?>
            <?php if ($staffNoRating > 0): ?>
                <div>มีพนักงานขาย <span class="nm"><?= number_format($staffNoRating) ?></span> คน ที่ยังไม่มีรีวิวเข้ามาเลย</div>
            <?php endif; ?>
        </div>
        <div class="watch-more"><a href="report.php">ดูรายละเอียดในหน้ารายงานคะแนน →</a></div>
    </div>
</div>
<?php endif; ?>

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
        <input type="date" name="from" aria-label="วันที่เริ่มต้น" value="<?= htmlspecialchars($from) ?>" title="วันที่เริ่มต้น">
        <span class="to">ถึง</span>
        <input type="date" name="to" aria-label="วันที่สิ้นสุด" value="<?= htmlspecialchars($to) ?>" title="วันที่สิ้นสุด">
        <button type="submit" class="pill mint <?= $preset === 'custom' ? 'active' : '' ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>ใช้ช่วงนี้</button>
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
            <div class="chart-title"><span class="dot mint"></span>คะแนนเฉลี่ย</div>
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

    // ── อ่านสีจากธีมที่ผู้ใช้เลือก (CSS variables) แทนการกำหนดสีตายตัว ──
    const CSS = getComputedStyle(document.documentElement);
    const C   = (n, fb) => (CSS.getPropertyValue(n).trim() || fb);
    // แปลง hex เป็น "r,g,b" เพื่อทำเงา/ไล่สีโปร่งแสง
    const RGB = (hex) => {
        let h = hex.replace('#', '');
        if (h.length === 3) h = h[0] + h[0] + h[1] + h[1] + h[2] + h[2];
        const n = parseInt(h, 16);
        return [(n >> 16) & 255, (n >> 8) & 255, n & 255].join(',');
    };

    const cAccent     = C('--accent', '#0EA5E9');
    const cAccentSoft = C('--accent-soft', '#38BDF8');
    const cMint       = C('--mint', '#10B981');
    const cMintSoft   = C('--mint-soft', '#34D399');
    const cPanel      = C('--panel', '#FFFFFF');

    Chart.defaults.font.family = "'Kanit', sans-serif";
    Chart.defaults.color = C('--muted', '#5C7186');
    const grid = C('--grid', 'rgba(15,31,46,0.08)');
    const tip = {
        backgroundColor: C('--tooltip-bg', '#0F1F2E'), borderColor: 'rgba(255,255,255,0.15)', borderWidth: 1,
        titleColor: '#FFF', bodyColor: '#E9EAF0', padding: 10, cornerRadius: 8, displayColors: false
    };

    // จำนวนรีวิว (แดง)
    const g1 = document.getElementById('lineChart').getContext('2d');
    const grad1 = g1.createLinearGradient(0, 0, 0, 240);
    grad1.addColorStop(0, 'rgba(' + RGB(cAccent) + ',0.30)'); grad1.addColorStop(1, 'rgba(' + RGB(cAccent) + ',0.02)');
    new Chart(g1, {
        type: 'line',
        data: { labels, datasets: [{
            label: 'จำนวนรีวิว', data: counts,
            borderColor: cAccent, backgroundColor: grad1, borderWidth: 2.5,
            pointRadius: labels.length > 40 ? 0 : 3, pointHoverRadius: 5,
            pointBackgroundColor: cAccentSoft, pointBorderColor: cPanel, pointBorderWidth: 1.5,
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
    grad2.addColorStop(0, 'rgba(' + RGB(cMint) + ',0.28)'); grad2.addColorStop(1, 'rgba(' + RGB(cMint) + ',0.02)');
    new Chart(g2, {
        type: 'line',
        data: { labels, datasets: [{
            label: 'คะแนนเฉลี่ย', data: avgs, spanGaps: true,
            borderColor: cMint, backgroundColor: grad2, borderWidth: 2.5,
            pointRadius: labels.length > 40 ? 0 : 3, pointHoverRadius: 5,
            pointBackgroundColor: cMintSoft, pointBorderColor: cPanel, pointBorderWidth: 1.5,
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
            backgroundColor: barColors.map(c => c || cAccent),
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
                                <span class="brand-tag" style="background:<?= htmlspecialchars($row['brand_color'] ?? '#D81300') ?>;color:<?= brand_ink($row['brand_color'] ?? '#D81300') ?>;"><?= htmlspecialchars($row['brand_name']) ?></span>
                            <?php else: ?>
                                <span class="brand-tag none">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="wrap-cell"><?= htmlspecialchars($row['position']) ?></td>
                        <td class="stars-display" aria-label="<?= (int) $row['score'] ?> จาก 5 คะแนน"><span aria-hidden="true"><?= str_repeat('★', (int) $row['score']) . str_repeat('☆', 5 - (int) $row['score']) ?></span></td>
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

<?php require 'includes/footer.php'; ?>
