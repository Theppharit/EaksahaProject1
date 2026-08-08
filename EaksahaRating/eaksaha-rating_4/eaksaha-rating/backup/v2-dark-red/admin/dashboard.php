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

// ----- ข้อมูลสำหรับ Hero -----
$h         = (int) date('H');
$greet     = $h < 12 ? 'สวัสดีตอนเช้า' : ($h < 17 ? 'สวัสดีตอนบ่าย' : 'สวัสดีตอนค่ำ');
$adminName = $_SESSION['admin_username'] ?? 'ผู้ดูแลระบบ';
$todayThai = (int) date('j') . ' ' . $THAI_MON[(int) date('n')] . ' ' . date('Y');

require 'includes/head.php';
?>
<style>
/* ═══════════ DASHBOARD PRO-MAX (อ้างอิงหน้า Login) ═══════════ */
.dash-hero {
    position: relative; overflow: hidden; border-radius: 24px; margin-bottom: 22px;
    padding: 32px 36px; color: #fff;
    background:
        radial-gradient(80% 130% at 88% -12%, rgba(255,90,44,0.55), transparent 55%),
        radial-gradient(60% 100% at 4% 112%, rgba(245,179,1,0.22), transparent 55%),
        linear-gradient(135deg, #1A0405 0%, #7E0A00 44%, #C10500 74%, #FF5A2C 126%);
    box-shadow: 0 24px 54px -28px rgba(var(--glow),0.8);
    border: 1px solid rgba(255,255,255,0.08);
}
.dash-hero::after {
    content: ''; position: absolute; inset: 0; pointer-events: none; mix-blend-mode: overlay;
    background-image: radial-gradient(circle, rgba(255,255,255,0.06) 1px, transparent 1px);
    background-size: 24px 24px;
}
.dh-streak {
    position: absolute; inset: -30% -10%; pointer-events: none; mix-blend-mode: screen; opacity: 0.85;
    background:
        linear-gradient(122deg, transparent 61.6%, rgba(255,80,50,0.85) 62.3%, transparent 63.1%),
        linear-gradient(122deg, transparent 73%, rgba(255,120,90,0.5) 73.6%, transparent 74.2%);
}
.dh-orb { position: absolute; width: 360px; height: 360px; border-radius: 50%; top: -150px; right: -110px;
    background: radial-gradient(circle, rgba(255,214,107,0.28), transparent 62%); pointer-events: none; }
.dh-inner { position: relative; z-index: 2; display: flex; align-items: flex-end; justify-content: space-between; gap: 26px; flex-wrap: wrap; }
.dh-eyebrow { display: inline-flex; align-items: center; gap: 8px; font-size: 11px; font-weight: 700;
    letter-spacing: 0.18em; text-transform: uppercase; color: #fff; background: rgba(255,255,255,0.14);
    border: 1px solid rgba(255,255,255,0.3); padding: 6px 14px; border-radius: 30px; backdrop-filter: blur(6px); margin-bottom: 14px; }
.dh-eyebrow .dot { width: 7px; height: 7px; border-radius: 50%; background: #FFD46B; box-shadow: 0 0 10px 2px rgba(245,179,1,0.85); }
.dash-hero h1 { font-size: clamp(24px, 3vw, 33px); font-weight: 800; line-height: 1.15; margin-bottom: 9px; color: #fff; }
.dash-hero h1::after { display: none; }
.dash-hero h1 .nm { color: #FFD46B; }
.dh-sub { font-size: 14px; color: rgba(255,255,255,0.86); line-height: 1.7; max-width: 540px; font-weight: 300; }
.dh-chips { display: flex; gap: 12px; flex-wrap: wrap; }
.dh-chip { background: rgba(255,255,255,0.12); border: 1px solid rgba(255,255,255,0.24); border-radius: 16px;
    padding: 13px 20px; backdrop-filter: blur(6px); min-width: 118px; }
.dh-chip .k { font-size: 11px; letter-spacing: 0.04em; color: rgba(255,255,255,0.82); margin-bottom: 4px; }
.dh-chip .v { font-size: 23px; font-weight: 800; line-height: 1; display: flex; align-items: baseline; gap: 4px; }
.dh-chip .v small { font-size: 12px; font-weight: 500; color: rgba(255,255,255,0.8); }

.kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(212px, 1fr)); gap: 16px; }
.kpi { position: relative; overflow: hidden; background: var(--panel); border: 1px solid var(--line);
    border-radius: 18px; padding: 20px 22px; box-shadow: 0 14px 34px -22px rgba(var(--glow),0.4);
    transition: transform 0.18s, border-color 0.18s, box-shadow 0.18s; }
.kpi:hover { transform: translateY(-3px); border-color: var(--line-2); box-shadow: 0 24px 52px -22px rgba(var(--glow),0.58); }
.kpi::before { content: ''; position: absolute; top: 0; bottom: 0; left: 0; width: 4px;
    background: linear-gradient(180deg, var(--accent), var(--accent-deep)); }
.kpi.gold::before { background: linear-gradient(180deg, var(--mint), var(--mint-deep)); }
.kpi-top { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; }
.kpi-ic { width: 44px; height: 44px; border-radius: 13px; display: flex; align-items: center; justify-content: center;
    background: rgba(var(--glow),0.12); border: 1px solid rgba(var(--glow),0.28); color: var(--accent-soft); }
.kpi.gold .kpi-ic { background: rgba(var(--glow2),0.12); border-color: rgba(var(--glow2),0.32); color: var(--mint-soft); }
.kpi-ic svg { width: 22px; height: 22px; }
.kpi-label { font-size: 13px; color: var(--muted); }
.kpi-value { font-size: 33px; font-weight: 800; color: var(--text); line-height: 1; letter-spacing: -0.02em; }
.kpi-value small { font-size: 15px; font-weight: 500; color: var(--muted); }
.kpi-sub { font-size: 12px; color: var(--muted-2); margin-top: 9px; }
.dash-hero + .kpi-grid { margin-top: 0; }
@media (max-width: 640px) { .dash-hero { padding: 26px 22px; } .dh-inner { align-items: flex-start; } }
</style>

<div class="dash-hero">
    <span class="dh-streak"></span>
    <span class="dh-orb"></span>
    <div class="dh-inner">
        <div class="dh-left">
            <span class="dh-eyebrow"><span class="dot"></span>Dashboard · <?= htmlspecialchars($todayThai) ?></span>
            <h1><?= htmlspecialchars($greet) ?>, <span class="nm"><?= htmlspecialchars($adminName) ?></span></h1>
            <p class="dh-sub">ภาพรวมผลประเมินความพึงพอใจของลูกค้าทั้งระบบ — การ์ดด้านล่างคือยอดสะสมทั้งหมด ส่วนกราฟเลือกช่วงเวลาที่ต้องการดูได้</p>
        </div>
        <div class="dh-chips">
            <div class="dh-chip">
                <div class="k">คะแนนเฉลี่ยรวม</div>
                <div class="v"><?= htmlspecialchars((string) $avgScore) ?><small>/ 5</small></div>
            </div>
            <div class="dh-chip">
                <div class="k">รีวิววันนี้</div>
                <div class="v"><?= number_format($todayRatings) ?></div>
            </div>
        </div>
    </div>
</div>

<div class="kpi-grid">
    <div class="kpi gold">
        <div class="kpi-top">
            <div>
                <div class="kpi-label">คะแนนเฉลี่ยรวม</div>
                <div class="kpi-value"><?= htmlspecialchars((string) $avgScore) ?> <small>/ 5</small></div>
            </div>
            <span class="kpi-ic"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l2.9 6.26L21.6 9l-4.8 4.68 1.13 6.6L12 17.27 6.07 20.28l1.13-6.6L2.4 9l6.7-.74L12 2z"/></svg></span>
        </div>
        <div class="kpi-sub">จากรีวิวสะสม <?= number_format($totalRatings) ?> รายการ</div>
    </div>
    <div class="kpi">
        <div class="kpi-top">
            <div>
                <div class="kpi-label">จำนวนรีวิวทั้งหมด</div>
                <div class="kpi-value"><?= number_format($totalRatings) ?></div>
            </div>
            <span class="kpi-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="8" y1="13" x2="16" y2="13"/><line x1="8" y1="17" x2="13" y2="17"/></svg></span>
        </div>
        <div class="kpi-sub">สะสมจากทุกโชว์รูมในระบบ</div>
    </div>
    <div class="kpi">
        <div class="kpi-top">
            <div>
                <div class="kpi-label">รีวิววันนี้</div>
                <div class="kpi-value"><?= number_format($todayRatings) ?></div>
            </div>
            <span class="kpi-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></span>
        </div>
        <div class="kpi-sub">อัปเดตแบบเรียลไทม์ · <?= htmlspecialchars($todayThai) ?></div>
    </div>
    <div class="kpi">
        <div class="kpi-top">
            <div>
                <div class="kpi-label">จำนวนพนักงานขาย</div>
                <div class="kpi-value"><?= number_format($totalStaff) ?></div>
            </div>
            <span class="kpi-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></span>
        </div>
        <div class="kpi-sub">ที่กำลังรับการประเมินอยู่</div>
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
        <button type="submit" class="pill mint <?= $preset === 'custom' ? 'active' : '' ?>">✓ ใช้ช่วงนี้</button>
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

<?php require 'includes/footer.php'; ?>
