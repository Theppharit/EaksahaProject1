<?php
require '../conn/config.php';
require 'includes/auth.php';
require_perm('view_all');
require 'includes/report_query.php';
require 'includes/report_parts.php';

$pageTitle  = 'รายการรีวิว';
$activePage = 'report';

// ══════════════════════════════════════════════
//  ตัวกรอง (อ่านและตรวจใน includes/report_query.php
//  เพื่อให้หน้าส่งออกไฟล์ใช้ตรรกะเดียวกันเป๊ะ)
// ══════════════════════════════════════════════
$f = rqReadFilters();
[$whereSql, $params] = rqBuildWhere($f, $pdo);

$range    = $f['range'];
$dateFrom = $f['dateFrom'];
$dateTo   = $f['dateTo'];
$brandId  = $f['brandId'];
$staffId  = $f['staffId'];
$score    = $f['score'];
$scores   = $f['scores'];

// ----- แบ่งหน้า -----
// เดิม LIMIT 200 ตายตัว พอรีวิวเกิน 200 รายการเก่าก็ดูไม่ได้อีกเลย
$perPage = 50;
$page    = max(1, (int) ($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;

// ----- โหมดดูรายการที่ถูกซ่อน (ผู้ดูแลเท่านั้น) -----
$showHidden = can('manage_users') && ($_GET['hidden'] ?? '') === '1' && hidden_columns_ready($pdo);

// ══════════════════════════════════════════════
//  ผลลัพธ์ตามตัวกรอง
// ══════════════════════════════════════════════
$stStat = $pdo->prepare("SELECT COUNT(*) AS total, ROUND(AVG(r.score),2) AS avg_score
                         FROM ratings r JOIN staff s ON s.id = r.staff_id $whereSql");
$stStat->execute($params);
$fStat  = $stStat->fetch() ?: [];
$fCount = (int) ($fStat['total'] ?? 0);
$fAvg   = isset($fStat['avg_score']) && $fStat['avg_score'] !== null ? (float) $fStat['avg_score'] : null;

// ══════════════════════════════════════════════
//  การกระจายคะแนน 1–5
//  สำคัญ: ไม่เอาตัวกรองดาวมาคิดด้วย มิฉะนั้นพอกด "1 ดาว"
//  แถบอื่นจะกลายเป็น 0 หมด แล้วกดกลับไปดาวอื่นไม่ได้อีกเลย
// ══════════════════════════════════════════════
$distFilters = $f;
$distFilters['score']  = '';
$distFilters['scores'] = [];
[$distWhere, $distParams] = rqBuildWhere($distFilters, $pdo);

$stDist = $pdo->prepare("SELECT r.score, COUNT(*) AS cnt
                         FROM ratings r JOIN staff s ON s.id = r.staff_id $distWhere GROUP BY r.score");
$stDist->execute($distParams);
$dist = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
foreach ($stDist->fetchAll() as $d) {
    $sc = (int) $d['score'];
    if (isset($dist[$sc])) $dist[$sc] = (int) $d['cnt'];
}
$distTotal = max(1, array_sum($dist));

// จำนวนที่ยังรอ AI ให้ดาว (ในชุดที่กรองอยู่ ไม่นับตัวกรองดาว)
$stPend = $pdo->prepare("SELECT COUNT(*) FROM ratings r JOIN staff s ON s.id = r.staff_id $distWhere"
                        . ($distWhere ? ' AND ' : ' WHERE ') . "r.score IS NULL");
$stPend->execute($distParams);
$fPending = (int) $stPend->fetchColumn();

// คอลัมน์ฝั่ง AI จะมีก็ต่อเมื่อรัน ai_migration.sql แล้ว
$aiCols = ai_columns_ready($pdo)
    ? 'r.ai_reason, r.ai_confidence, r.ai_status, r.ai_score,'
    : 'NULL AS ai_reason, NULL AS ai_confidence, NULL AS ai_status, NULL AS ai_score,';

// จำนวนข้อความที่หัวหน้าฝากไว้กับรีวิวแต่ละรายการ
$noteCol = 'NULL AS note_count,';
try {
    $pdo->query('SELECT 1 FROM review_notes LIMIT 1');
    $noteCol = '(SELECT COUNT(*) FROM review_notes n WHERE n.rating_id = r.id) AS note_count,';
} catch (PDOException $e) { /* ยังไม่ได้รัน roles_migration.sql */ }

// โหมดดูรายการที่ซ่อน — สลับเงื่อนไขจาก "ไม่ซ่อน" เป็น "ซ่อนเท่านั้น"
$listWhere  = $whereSql;
$listParams = $params;
if ($showHidden) {
    $listWhere = str_replace('r.hidden_at IS NULL', 'r.hidden_at IS NOT NULL', $listWhere);
}

$hiddenCols = hidden_columns_ready($pdo)
    ? 'r.hidden_at, r.hidden_by, r.hidden_reason,'
    : 'NULL AS hidden_at, NULL AS hidden_by, NULL AS hidden_reason,';

$stList = $pdo->prepare("
    SELECT r.id, r.score, r.feedback, r.created_at, $aiCols $noteCol $hiddenCols
           r.staff_id, s.name, s.position, b.name AS brand_name, b.color AS brand_color
    FROM ratings r
    JOIN staff s ON s.id = r.staff_id
    LEFT JOIN brands b ON b.id = s.brand_id
    $listWhere
    ORDER BY r.created_at DESC
    LIMIT $perPage OFFSET $offset
");
$stList->execute($listParams);
$ratings = $stList->fetchAll();

// จำนวนทั้งหมดในโหมดที่กำลังดู (ใช้คำนวณจำนวนหน้า)
$stTotal = $pdo->prepare("SELECT COUNT(*) FROM ratings r JOIN staff s ON s.id = r.staff_id $listWhere");
$stTotal->execute($listParams);
$listTotal  = (int) $stTotal->fetchColumn();
$totalPages = max(1, (int) ceil($listTotal / $perPage));
if ($page > $totalPages) { $page = $totalPages; }

// จำนวนรายการที่ถูกซ่อนทั้งหมด (ไว้โชว์ปุ่มสลับโหมด)
$hiddenTotal = 0;
if (hidden_columns_ready($pdo)) {
    $hiddenTotal = (int) $pdo->query('SELECT COUNT(*) FROM ratings WHERE hidden_at IS NOT NULL')->fetchColumn();
}

// ══════════════════════════════════════════════
//  ข้อมูลประกอบ
// ══════════════════════════════════════════════
$staffOptions = $pdo->query('SELECT id, name FROM staff ORDER BY name ASC')->fetchAll();
$brandOptions = $pdo->query('SELECT id, name FROM brands ORDER BY sort_order ASC, id ASC')->fetchAll();

$rangeText  = rqRangeText($f);
$distColors = [5 => '#16A34A', 4 => '#84CC16', 3 => '#F59E0B', 2 => '#F97316', 1 => '#E11D48'];
$distLabels = [5 => 'พึงพอใจมากที่สุด', 4 => 'พึงพอใจมาก', 3 => 'ปานกลาง', 2 => 'พึงพอใจน้อย', 1 => 'ควรปรับปรุง'];

// ค่าที่ต้องพกไปกับลิงก์ในแถบกระจายคะแนน
$keepForLinks = array_filter([
    'range'    => $range,
    'brand_id' => $brandId !== null ? (string) $brandId : '',
    'staff_id' => $staffId !== null ? (string) $staffId : '',
    'score'    => $score,
] + ($range === 'custom' ? ['date_from' => $dateFrom, 'date_to' => $dateTo] : []),
    fn($v) => $v !== '');

// ══════════════════════════════════════════════
//  ชิ้นส่วนที่เปลี่ยนตามตัวกรอง
//  ประกอบไว้เป็นสตริง ใช้ได้ทั้งตอนเปิดหน้าปกติ และตอนกดปุ่มกรอง (JSON)
// ══════════════════════════════════════════════
$filterHtml = reportFilterBar($range, $dateFrom, $dateTo, $brandId, $staffId, $score, $brandOptions, $staffOptions);
$resultHtml = reportResults($rangeText, $fCount, $fAvg, $dist, $distTotal, $distColors, $distLabels,
                            $fPending, $scores, $keepForLinks);
$listHtml   = reportList($ratings, $showHidden);
$listCount  = number_format($listTotal) . ($totalPages > 1 ? ' · หน้า ' . $page . '/' . $totalPages : '');
$pagerHtml  = reportPager($page, $totalPages, $_GET);

// ----- ถ้ามาจากการกดปุ่มกรอง: ตอบเป็น JSON แล้วจบ -----
if (($_GET['ajax'] ?? '') === '1') {
    header('Content-Type: application/json; charset=utf-8');
    $q = $_GET; unset($q['ajax']);
    echo json_encode([
        'url'   => 'report.php' . ($q ? '?' . http_build_query($q) : ''),
        'patch' => [
            '#filterZone' => $filterHtml,
            '#resultZone' => $resultHtml,
            '#listCount'  => $listCount,
            '#listZone'   => $listHtml,
            '#pagerZone'  => $pagerHtml,
        ],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

require 'includes/head.php';
?>
<h1>รายการรีวิว</h1>
<p class="page-sub">
    ความเห็นของลูกค้าทีละรายการ พร้อมดาวที่ AI ให้และเหตุผล —
    ถ้าอยากดูคะแนนรวมของแต่ละคน ไปที่เมนู <a href="staff_scores.php">คะแนนรายพนักงาน</a>
</p>

<!-- ═══ ตัวกรอง — เปลี่ยนค่าโดยไม่โหลดหน้าใหม่ ═══ -->
<div id="filterZone" data-ajax-zone><?= $filterHtml ?></div>

<!-- ═══ สรุปผล + แถบกระจายคะแนนที่กดกรองได้ ═══ -->
<div id="resultZone"><?= $resultHtml ?></div>

<h2 class="section-title">
    <?= $showHidden ? 'รายการที่ซ่อนไว้' : 'รายการรีวิว' ?>
    <span class="count-badge" id="listCount"><?= $listCount ?></span>
    <?php if (can('manage_users') && $hiddenTotal > 0): ?>
        <?php $q = $_GET; unset($q['page']); ?>
        <?php if ($showHidden): ?>
            <?php unset($q['hidden']); ?>
            <a class="hidden-toggle" href="?<?= htmlspecialchars(http_build_query($q)) ?>">← กลับไปดูรายการปกติ</a>
        <?php else: ?>
            <?php $q['hidden'] = '1'; ?>
            <a class="hidden-toggle" href="?<?= htmlspecialchars(http_build_query($q)) ?>">ดูรายการที่ซ่อนไว้ (<?= $hiddenTotal ?>)</a>
        <?php endif; ?>
    <?php endif; ?>
</h2>
<div class="table-card" id="listZone"><?= $listHtml ?></div>
<div id="pagerZone"><?= $pagerHtml ?></div>

<script src="assets/filters.js?v=<?= @filemtime(__DIR__ . '/assets/filters.js') ?: time() ?>"></script>
<script>
// ══════════════════════════════════════════════
//  ส่งข้อความถึงพนักงาน — ทำในตารางได้เลย ไม่ต้องโหลดหน้าใหม่
//  ผูกเหตุการณ์ไว้ที่ document เพราะตารางถูกสลับใหม่ทุกครั้งที่กรอง
//
//  ไม่มีปุ่มยกเลิก — กดปุ่ม "ส่งข้อความ" ซ้ำเพื่อปิดกล่อง
//  ปุ่มเดียวทำหน้าที่เปิดและปิด เข้าใจง่ายกว่ามีสองปุ่ม
// ══════════════════════════════════════════════
(function () {
    function cell(el) { return el.closest('.score-cell'); }

    document.addEventListener('click', function (e) {
        var btn = e.target.closest ? e.target.closest('.msg-btn') : null;

        // เปิด/ปิดกล่องพิมพ์ข้อความ
        if (btn) {
            var c   = cell(btn);
            var box = c.querySelector('.note-box');
            var open = box.hidden;
            box.hidden = !open;
            btn.setAttribute('aria-expanded', open ? 'true' : 'false');
            btn.classList.toggle('is-open', open);
            if (open) c.querySelector('.note-text').focus();
            return;
        }

        // ── ซ่อน / เอากลับ ──
        var hb = e.target.closest ? e.target.closest('.hide-btn') : null;
        if (hb) {
            var ch   = cell(hb);
            var mode = hb.dataset.mode;
            var body = { id: ch.dataset.rid, action: mode };

            if (mode === 'hide') {
                var why = prompt('ซ่อนรีวิวนี้ออกจากสถิติเพราะอะไร?\n\n(ข้อความต้นฉบับยังเก็บไว้ ไม่ได้ลบทิ้ง)');
                if (why === null) return;              // กดยกเลิก
                if (!why.trim()) {
                    window.toast('error', 'ต้องระบุเหตุผล', 'เพื่อให้ตรวจย้อนหลังได้ว่าซ่อนเพราะอะไร');
                    return;
                }
                body.reason = why.trim();
            } else if (!confirm('เอารีวิวนี้กลับมานับในสถิติ?')) {
                return;
            }

            hb.disabled = true;
            window.postJSON('review_hide.php', body)
                .then(function (d) {
                    if (!d.ok) throw new Error(d.error || 'ทำรายการไม่สำเร็จ');
                    window.toast('success', d.hidden ? 'ซ่อนรีวิวแล้ว' : 'เอากลับมานับแล้ว', d.note);
                    setTimeout(function () { location.reload(); }, 900);
                })
                .catch(function (err) {
                    window.toast('error', 'ทำรายการไม่สำเร็จ', err.message);
                    hb.disabled = false;
                });
            return;
        }

        // ส่ง
        var save = e.target.closest ? e.target.closest('.note-save') : null;
        if (!save) return;

        var c3  = cell(save);
        var box = c3.querySelector('.note-text');
        var msg = box.value.trim();
        if (!msg) { box.focus(); return; }

        var label = save.textContent;
        save.disabled = true;
        save.textContent = 'กำลังส่ง...';

        window.postJSON('note_save.php', { rating_id: c3.dataset.rid, note: msg })
            .then(function (d) {
                if (!d.ok) throw new Error(d.error || 'ส่งไม่สำเร็จ');

                var view = c3.querySelector('.score-view');
                var flag = view.querySelector('.note-flag');
                if (!flag) {
                    flag = document.createElement('div');
                    flag.className = 'note-flag';
                    view.appendChild(flag);
                }
                flag.textContent = d.count + ' ข้อความที่ส่งไปแล้ว';

                box.value = '';
                c3.querySelector('.note-box').hidden = true;
                var mb = c3.querySelector('.msg-btn');
                if (mb) { mb.classList.remove('is-open'); mb.setAttribute('aria-expanded', 'false'); }

                save.disabled = false;
                save.textContent = label;
                window.toast('success', 'ส่งถึง ' + d.to + ' แล้ว',
                             'จะเห็นแจ้งเตือนในเมนู "ข้อความจากหัวหน้า" ตอนล็อกอินครั้งถัดไป');
            })
            .catch(function (err) {
                window.toast('error', 'ส่งข้อความไม่สำเร็จ', err.message);
                save.disabled = false;
                save.textContent = label;
            });
    });
})();
</script>

<?php require 'includes/footer.php'; ?>
