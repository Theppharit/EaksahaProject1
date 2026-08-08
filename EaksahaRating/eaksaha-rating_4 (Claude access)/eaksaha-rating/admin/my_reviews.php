<?php
// ============================================================
//  คะแนนของฉัน — หน้าแรกของบัญชีพนักงานขาย
// ------------------------------------------------------------
//  เห็นเฉพาะรีวิวของตัวเองเท่านั้น ไม่เห็นของเพื่อนร่วมงาน
//  ไม่เห็นแดชบอร์ดรวม ไม่เห็นคะแนนคนอื่น
//
//  ข้อมูลที่ตั้งใจ "ไม่แสดง" ให้พนักงานขายเห็น
//    • เหตุผลที่ AI ให้ดาว — เป็นเครื่องมือตรวจสอบของฝ่ายบริหาร
//      ถ้าเอามาโชว์ จะกลายเป็นเรื่องเถียงกับ AI แทนที่จะสนใจสิ่งที่ลูกค้าเขียน
//    • คะแนนเฉลี่ยของคนอื่นหรือของทั้งทีม
// ============================================================

require '../conn/config.php';
require 'includes/auth.php';

$pageTitle  = 'คะแนนของฉัน';
$activePage = 'my_reviews';

// หน้านี้สำหรับพนักงานขายเท่านั้น — ผู้ดูแลกับผู้จัดการมีหน้าของตัวเอง
if (user_role() !== 'sales') {
    header('Location: ' . role_home());
    exit;
}

$myId = user_staff_id();

// บัญชี sales ที่ยังไม่ได้ผูกกับพนักงานขาย — บอกให้ชัดว่าต้องทำอะไร
if ($myId === null) {
    require 'includes/head.php'; ?>
    <h1>คะแนนของฉัน</h1>
    <div class="alert alert-error" style="margin-top:16px;">
        บัญชีนี้ยังไม่ได้ผูกกับข้อมูลพนักงานขาย จึงยังไม่ทราบว่าต้องแสดงรีวิวของใคร<br>
        กรุณาแจ้งผู้ดูแลระบบให้ผูกบัญชีที่เมนู "ผู้ใช้งานระบบ"
    </div>
    <?php require 'includes/footer.php';
    exit;
}

// ----- ข้อมูลของฉัน -----
$me = $pdo->prepare('SELECT s.*, b.name AS brand_name, b.color AS brand_color
                     FROM staff s LEFT JOIN brands b ON b.id = s.brand_id WHERE s.id = ?');
$me->execute([$myId]);
$staff = $me->fetch();

// ----- ช่วงเวลา -----
$range  = in_array($_GET['range'] ?? '', ['30d', '90d', 'all'], true) ? $_GET['range'] : '30d';
$where  = ['r.staff_id = ?', 'r.score IS NOT NULL'];
// ไม่แสดงรีวิวที่ผู้ดูแลซ่อนไว้ (ข้ามถ้ายังไม่ได้รัน hardening_migration.sql)
if (hidden_columns_ready($pdo)) { $where[] = 'r.hidden_at IS NULL'; }
$params = [$myId];
if ($range === '30d') { $where[] = 'r.created_at >= ?'; $params[] = date('Y-m-d', strtotime('-29 days')) . ' 00:00:00'; }
if ($range === '90d') { $where[] = 'r.created_at >= ?'; $params[] = date('Y-m-d', strtotime('-89 days')) . ' 00:00:00'; }
$whereSql = 'WHERE ' . implode(' AND ', $where);

// ----- สรุป -----
$st = $pdo->prepare("SELECT COUNT(*) AS total, ROUND(AVG(r.score),2) AS avg_score FROM ratings r $whereSql");
$st->execute($params);
$sum   = $st->fetch() ?: [];
$total = (int) ($sum['total'] ?? 0);
$avg   = isset($sum['avg_score']) && $sum['avg_score'] !== null ? (float) $sum['avg_score'] : null;

// ----- การกระจายคะแนน -----
$sd = $pdo->prepare("SELECT r.score, COUNT(*) c FROM ratings r $whereSql GROUP BY r.score");
$sd->execute($params);
$dist = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
foreach ($sd->fetchAll() as $d) { if (isset($dist[(int) $d['score']])) $dist[(int) $d['score']] = (int) $d['c']; }
$distTotal = max(1, array_sum($dist));

// ----- รายการรีวิว + ข้อความจากหัวหน้าที่ติดกับรีวิวนั้น -----
$noteCol = 'NULL AS note_count,';
try {
    $pdo->query('SELECT 1 FROM review_notes LIMIT 1');
    $noteCol = '(SELECT COUNT(*) FROM review_notes n WHERE n.rating_id = r.id) AS note_count,';
} catch (PDOException $e) { /* ยังไม่ได้รัน roles_migration.sql */ }

$sl = $pdo->prepare("SELECT r.id, r.score, r.feedback, r.created_at, $noteCol 1 AS x
                     FROM ratings r $whereSql ORDER BY r.created_at DESC LIMIT 100");
$sl->execute($params);
$rows = $sl->fetchAll();

$THAI_MON = ['', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
$fmt = fn($ts) => date('j', strtotime($ts)) . ' ' . $THAI_MON[(int) date('n', strtotime($ts))] . ' ' . date('H:i', strtotime($ts));

$distColors = [5 => '#16A34A', 4 => '#84CC16', 3 => '#F59E0B', 2 => '#F97316', 1 => '#E11D48'];
$ranges = ['30d' => '30 วันล่าสุด', '90d' => '90 วันล่าสุด', 'all' => 'ทั้งหมด'];

require 'includes/head.php';
?>
<style>
.me-head { display: flex; align-items: center; gap: 16px; margin-bottom: 20px; flex-wrap: wrap; }
.me-photo, .me-initial {
  width: 62px; height: 62px; border-radius: 50%; object-fit: cover; flex-shrink: 0;
  border: 1px solid var(--line-2);
}
.me-initial {
  display: flex; align-items: center; justify-content: center;
  background: var(--accent); color: #fff; font-size: 24px; font-weight: 600; border: none;
}
.me-name { font-size: 19px; font-weight: 600; color: var(--text); }
.me-meta { display: flex; align-items: center; gap: 8px; margin-top: 6px; flex-wrap: wrap; }
.me-pos { font-size: 13px; color: var(--muted-2); }

.me-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; margin-bottom: 18px; }
.me-card { background: var(--panel); border: 1px solid var(--line); border-radius: 12px; padding: 18px 20px; box-shadow: var(--sh-1); }
.me-card .l { font-size: 12.5px; color: var(--muted); }
.me-card .v { font-size: 32px; font-weight: 700; color: var(--text); line-height: 1.15; margin-top: 4px; }
.me-card .v small { font-size: 15px; font-weight: 500; color: var(--muted); }
.me-card.big .v { color: var(--mint-soft); }

.me-review {
  background: var(--panel); border: 1px solid var(--line); border-radius: 12px;
  padding: 15px 17px; margin-bottom: 10px; box-shadow: var(--sh-1);
}
.me-top { display: flex; align-items: center; gap: 10px; justify-content: space-between; flex-wrap: wrap; }
.me-when { font-size: 12.5px; color: var(--muted-2); }
.me-text { margin-top: 9px; color: var(--text); line-height: 1.75; }
.me-note-flag {
  display: inline-flex; align-items: center; gap: 6px; margin-top: 10px;
  font-size: 12.5px; color: var(--warn);
}
.me-empty { text-align: center; padding: 40px 20px; color: var(--muted); }
</style>

<h1>คะแนนของฉัน</h1>
<p class="page-sub">ความเห็นที่ลูกค้าให้ไว้หลังกิจกรรม Test Drive — เห็นเฉพาะของคุณเท่านั้น</p>

<?php if ($staff): ?>
<div class="me-head">
    <?php if (!empty($staff['photo'])): ?>
        <img class="me-photo" src="uploads/staff/<?= htmlspecialchars($staff['photo']) ?>" alt="">
    <?php else: ?>
        <div class="me-initial" aria-hidden="true"><?= htmlspecialchars(mb_substr($staff['name'], 0, 1, 'UTF-8')) ?></div>
    <?php endif; ?>
    <div>
        <div class="me-name"><?= htmlspecialchars($staff['name']) ?></div>
        <div class="me-meta">
            <?php if (!empty($staff['brand_name'])): ?>
                <span class="brand-tag" style="<?= brand_tag_style($staff['brand_color'] ?? '#D81300') ?>"><?= htmlspecialchars($staff['brand_name']) ?></span>
            <?php endif; ?>
            <span class="me-pos"><?= htmlspecialchars($staff['position']) ?></span>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="filter-bar filter-bar-one">
    <span class="filter-label">ช่วงเวลา</span>
    <?php foreach ($ranges as $k => $label): ?>
        <a class="pill <?= $range === $k ? 'active' : '' ?>" href="?range=<?= $k ?>"><?= $label ?></a>
    <?php endforeach; ?>
</div>

<div class="me-cards">
    <div class="me-card big">
        <div class="l">คะแนนเฉลี่ยของฉัน</div>
        <div class="v"><?= $avg !== null ? number_format($avg, 2) : '-' ?> <small>/ 5</small></div>
    </div>
    <div class="me-card">
        <div class="l">จำนวนรีวิว</div>
        <div class="v"><?= number_format($total) ?></div>
    </div>
    <div class="me-card">
        <div class="l">รีวิว 5 ดาว</div>
        <div class="v"><?= number_format($dist[5]) ?></div>
    </div>
</div>

<h2 class="section-title">การกระจายคะแนน</h2>
<div class="result-strip" style="grid-template-columns:1fr;">
    <div class="rs-dist">
        <?php foreach ($dist as $sc => $cnt): $pct = round($cnt / $distTotal * 100); ?>
            <div class="dist-row" style="cursor:default;">
                <span class="dist-score"><?= $sc ?> <span aria-hidden="true">★</span></span>
                <span class="dist-track"><span class="dist-bar" style="width:<?= max($cnt > 0 ? 2 : 0, $pct) ?>%; background:<?= $distColors[$sc] ?>;"></span></span>
                <span class="dist-count"><?= number_format($cnt) ?></span>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<h2 class="section-title">สิ่งที่ลูกค้าเขียนถึงคุณ <span class="count-badge"><?= number_format(count($rows)) ?></span></h2>

<?php if (empty($rows)): ?>
    <div class="me-review me-empty">
        ยังไม่มีรีวิวในช่วงเวลานี้<br>
        <span style="font-size:13px;">ลองเลือกช่วง "ทั้งหมด" ด้านบน</span>
    </div>
<?php else: ?>
    <?php foreach ($rows as $r): $sc = (int) $r['score']; ?>
        <div class="me-review">
            <div class="me-top">
                <span class="stars-display" aria-label="<?= $sc ?> จาก 5 คะแนน"><span aria-hidden="true"><?= str_repeat('★', $sc) . str_repeat('☆', 5 - $sc) ?></span></span>
                <span class="me-when"><?= htmlspecialchars($fmt($r['created_at'])) ?></span>
            </div>
            <?php if (!empty($r['feedback'])): ?>
                <div class="me-text"><?= nl2br(htmlspecialchars($r['feedback'])) ?></div>
            <?php endif; ?>
            <?php if ((int) ($r['note_count'] ?? 0) > 0): ?>
                <a class="me-note-flag" href="my_notes.php">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:14px;height:14px;"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/></svg>
                    หัวหน้าฝากข้อความไว้กับรีวิวนี้ <?= (int) $r['note_count'] ?> ข้อความ
                </a>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php require 'includes/footer.php'; ?>
