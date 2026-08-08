<?php
require '../conn/config.php';
require 'includes/auth.php';
require 'includes/report_query.php';
require 'includes/report_parts.php';

$pageTitle  = 'รายการรีวิว';
$activePage = 'report';

// ══════════════════════════════════════════════
//  ตัวกรอง (อ่านและตรวจใน includes/report_query.php
//  เพื่อให้หน้าส่งออกไฟล์ใช้ตรรกะเดียวกันเป๊ะ)
// ══════════════════════════════════════════════
$f = rqReadFilters();
[$whereSql, $params] = rqBuildWhere($f);

$range    = $f['range'];
$dateFrom = $f['dateFrom'];
$dateTo   = $f['dateTo'];
$brandId  = $f['brandId'];
$staffId  = $f['staffId'];
$score    = $f['score'];

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
$distFilters['score'] = '';
[$distWhere, $distParams] = rqBuildWhere($distFilters);

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
                            $fPending, $score, $keepForLinks);
$listHtml   = reportList($ratings);
$listCount  = number_format(min($fCount, 200)) . ($fCount > 200 ? ' จาก ' . number_format($fCount) : '');

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

<h2 class="section-title">รายการรีวิว <span class="count-badge" id="listCount"><?= $listCount ?></span></h2>
<div class="table-card" id="listZone"><?= $listHtml ?></div>

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

        if (t.classList.contains('score-edit-btn')) {
            var c = cell(t);
            c.querySelector('.score-view').hidden = true;
            c.querySelector('.score-edit').hidden = false;
            c.querySelector('.score-edit-sel').focus();
            return;
        }

        if (t.classList.contains('score-edit-cancel')) {
            var c2 = cell(t);
            c2.querySelector('.score-edit').hidden = true;
            c2.querySelector('.score-view').hidden = false;
            return;
        }

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
