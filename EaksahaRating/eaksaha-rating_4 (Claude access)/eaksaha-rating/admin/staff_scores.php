<?php
// ============================================================
//  คะแนนรายพนักงานขาย
//  ------------------------------------------------------------
//  แยกออกมาจากหน้ารายการรีวิว เพราะเดิมสองตารางอยู่หน้าเดียวกัน
//  แต่ทำงานคนละแบบ — ตารางบนเปลี่ยนตามตัวกรอง ตารางล่างไม่เปลี่ยน
//  พอเลื่อนลงมาแล้วตัวเลขไม่ตรงกัน คนใช้จึงสับสน
//
//  หน้านี้ตอบคำถามเดียว: "ใครควรได้รับการดูแลก่อน"
//  เรียงคะแนนน้อยขึ้นก่อนเสมอ
// ============================================================

require '../conn/config.php';
require 'includes/auth.php';

require_perm('view_all');
$pageTitle  = 'คะแนนรายพนักงาน';
$activePage = 'staff_scores';

// ----- ตัวกรองแบรนด์ (อันเดียวพอ ไม่ต้องมีอะไรมากกว่านี้) -----
$brandId = isset($_GET['brand_id']) && $_GET['brand_id'] !== '' ? (int) $_GET['brand_id'] : null;

$brandWhere  = $brandId ? 'WHERE s.brand_id = ?' : '';

// ไม่นับรีวิวที่ผู้ดูแลซ่อนไว้ — ว่างไว้ถ้ายังไม่ได้รัน hardening_migration.sql
$visAndR = hidden_columns_ready($pdo) ? ' AND r.hidden_at IS NULL' : '';
$brandParams = $brandId ? [$brandId] : [];

// นับเฉพาะรีวิวที่มีดาวแล้ว รายการที่ยังรอ AI ไม่ถูกนับเป็นคะแนน
$st = $pdo->prepare("
    SELECT s.id, s.name, s.position, s.code,
           b.name AS brand_name, b.color AS brand_color,
           COUNT(r.score) AS total_ratings,
           AVG(r.score)   AS avg_score,
           MAX(r.created_at) AS last_review
    FROM staff s
    LEFT JOIN ratings r ON r.staff_id = s.id AND r.score IS NOT NULL $visAndR
    LEFT JOIN brands  b ON b.id = s.brand_id
    $brandWhere
    GROUP BY s.id, s.name, s.position, s.code, b.name, b.color
    ORDER BY (AVG(r.score) IS NULL) ASC, AVG(r.score) ASC, COUNT(r.score) DESC
");
$st->execute($brandParams);
$rows = $st->fetchAll();

$brandOptions = $pdo->query('SELECT id, name FROM brands ORDER BY sort_order ASC, id ASC')->fetchAll();

// ----- ตัวเลขสรุปด้านบน -----
$rated    = array_filter($rows, fn($r) => (int) $r['total_ratings'] > 0);
$noReview = count($rows) - count($rated);
$lowCount = count(array_filter($rated, fn($r) => (float) $r['avg_score'] < 3));

$THAI_MON = ['', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
$shortDate = function (?string $ts) use ($THAI_MON): string {
    if (!$ts) return '-';
    $t = strtotime($ts);
    return date('j', $t) . ' ' . $THAI_MON[(int) date('n', $t)] . ' ' . date('Y', $t);
};

require 'includes/head.php';
?>
<style>
.ss-summary { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 16px; }
.ss-card {
  flex: 1 1 170px; padding: 14px 16px;
  background: var(--panel); border: 1px solid var(--line);
  border-radius: 10px; box-shadow: var(--sh-1);
}
.ss-card .n { font-size: 24px; font-weight: 700; color: var(--text); line-height: 1.2; }
.ss-card .l { font-size: 12.5px; color: var(--muted); margin-top: 2px; }
.ss-card.warn { border-left: 3px solid var(--warn); }
.ss-card.warn .n { color: var(--warn); }

.rank-cell { width: 44px; color: var(--muted-2); font-size: 13px; text-align: center; }
.ss-name { font-weight: 600; color: var(--text); }
.ss-meta { display: flex; align-items: center; gap: 7px; margin-top: 4px; flex-wrap: wrap; }
.ss-pos  { font-size: 12.5px; color: var(--muted-2); }
tr.is-low td { background: rgba(180,83,9,0.045); }
html[data-theme="dark"] tr.is-low td { background: rgba(251,191,36,0.06); }
</style>

<h1>คะแนนรายพนักงาน</h1>
<p class="page-sub">
    เรียงจากคะแนนน้อยไปมาก คนที่ควรเข้าไปดูแลก่อนอยู่บนสุด —
    ดูรีวิวทีละรายการได้ที่เมนู <a href="report.php">รายการรีวิว</a>
</p>

<div class="ss-summary">
    <div class="ss-card">
        <div class="n"><?= number_format(count($rows)) ?></div>
        <div class="l">พนักงานขายทั้งหมด</div>
    </div>
    <div class="ss-card <?= $lowCount > 0 ? 'warn' : '' ?>">
        <div class="n"><?= number_format($lowCount) ?></div>
        <div class="l">คะแนนเฉลี่ยต่ำกว่า 3</div>
    </div>
    <div class="ss-card">
        <div class="n"><?= number_format($noReview) ?></div>
        <div class="l">ยังไม่มีรีวิวเลย</div>
    </div>
</div>

<div class="filter-bar filter-bar-one">
    <form class="filter-group" method="GET" onchange="this.submit()">
        <span class="filter-label">แบรนด์</span>
        <select name="brand_id" class="filter-select" aria-label="กรองตามแบรนด์">
            <option value="">ทุกแบรนด์</option>
            <?php foreach ($brandOptions as $b): ?>
                <option value="<?= (int) $b['id'] ?>" <?= $brandId === (int) $b['id'] ? 'selected' : '' ?>><?= htmlspecialchars($b['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <noscript><button type="submit" class="pill mint">ใช้ตัวกรอง</button></noscript>
    </form>
    <?php if ($brandId): ?>
        <div class="filter-tail"><a href="staff_scores.php" class="pill">ล้างตัวกรอง</a></div>
    <?php endif; ?>
</div>

<div class="table-card">
    <table>
        <thead>
            <tr>
                <th class="rank-cell">#</th>
                <th>พนักงานขาย</th>
                <th>คะแนนเฉลี่ย</th>
                <th>จำนวนรีวิว</th>
                <th>รีวิวล่าสุด</th>
                <th>ดูรีวิว</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($rows)): ?>
                <tr><td colspan="6" class="empty-cell">ยังไม่มีข้อมูลพนักงานขาย — เพิ่มได้ที่เมนู "พนักงานขาย"</td></tr>
            <?php else: ?>
                <?php $i = 0; foreach ($rows as $row):
                    $i++;
                    $total   = (int) $row['total_ratings'];
                    $hasData = $total > 0 && $row['avg_score'] !== null;
                    $avg     = $hasData ? (float) $row['avg_score'] : null;
                    $isLow   = $hasData && $avg < 3;
                ?>
                    <tr class="<?= $isLow ? 'is-low' : '' ?>">
                        <td class="rank-cell"><?= $i ?></td>
                        <td class="wrap-cell">
                            <div class="ss-name"><?= htmlspecialchars($row['name']) ?></div>
                            <div class="ss-meta">
                                <?php if (!empty($row['brand_name'])): ?>
                                    <span class="brand-tag" style="<?= brand_tag_style($row['brand_color'] ?? '#D81300') ?>"><?= htmlspecialchars($row['brand_name']) ?></span>
                                <?php endif; ?>
                                <span class="ss-pos"><?= htmlspecialchars($row['position']) ?></span>
                            </div>
                        </td>
                        <td>
                            <?php if ($hasData): ?>
                                <span class="stars-display"><span aria-hidden="true"><?= str_repeat('★', (int) round($avg)) . str_repeat('☆', 5 - (int) round($avg)) ?></span></span>
                                <span class="avg-num <?= $isLow ? 'is-low' : '' ?>"><?= number_format($avg, 2) ?></span>
                            <?php else: ?>
                                <span class="no-data">ยังไม่มีรีวิว</span>
                            <?php endif; ?>
                        </td>
                        <td><?= number_format($total) ?></td>
                        <td class="date-cell"><?= htmlspecialchars($shortDate($row['last_review'])) ?></td>
                        <td>
                            <?php if ($total > 0): ?>
                                <a href="report.php?staff_id=<?= (int) $row['id'] ?>" class="btn btn-secondary btn-sm">ดูรีวิว</a>
                            <?php else: ?>
                                <span class="dash-cell">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require 'includes/footer.php'; ?>
