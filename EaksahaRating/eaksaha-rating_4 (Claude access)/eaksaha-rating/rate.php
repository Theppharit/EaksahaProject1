<?php
require 'conn/config.php';
require 'conn/ai.php';
require 'conn/rate_guard.php';

$code  = trim($_GET['code'] ?? '');
$staff = null;
$error = '';

if ($code === '') {
    $error = 'ไม่พบโค้ดพนักงานขาย';
} else {
    // ดึงข้อมูลเซลล์ + แบรนด์ที่สังกัด
    $stmt = $pdo->prepare('
        SELECT s.*, b.name AS brand_name, b.color AS brand_color
        FROM staff s
        LEFT JOIN brands b ON b.id = s.brand_id
        WHERE s.code = ?
    ');
    $stmt->execute([$code]);
    $staff = $stmt->fetch();
    if (!$staff) {
        $error = 'ไม่พบข้อมูลพนักงานขายที่ต้องการประเมิน';
    }
}

// ══════════════════════════════════════════════════════════
//  บันทึกผลประเมิน
//  ------------------------------------------------------------
//  ลูกค้าไม่ต้องกดดาวเองแล้ว — เขียนความรู้สึกมาอย่างเดียว
//  แล้ว AI จะเป็นคนอ่านและให้ดาว 1-5 ทีหลัง
//
//  จุดสำคัญ: ตรงนี้ "บันทึกอย่างเดียว ไม่เรียก AI"
//  เพราะถ้าเรียก AI ตรงนี้ ลูกค้าจะต้องนั่งจ้องหน้าจอรอ
//  วันไหนเน็ตช้าก็รอเป็นสิบวินาทีแล้วปิดหนีไปเลย
//  เราจึงเก็บข้อความไว้ก่อน เด้งหน้าขอบคุณทันที
//  แล้วให้หน้าขอบคุณเป็นคนสั่งให้ดาวเบื้องหลังอีกที
// ══════════════════════════════════════════════════════════
$formError = '';
$alreadyRated = false;

// รหัสประจำเครื่อง — ต้องอ่าน/สร้างก่อนหน้าจะเริ่มส่ง output
$deviceId = $staff ? rg_device_id() : '';
$clientIp = rg_client_ip();

// ตรวจตั้งแต่ตอนเปิดหน้า เพื่อไม่ให้ลูกค้าพิมพ์ยาวแล้วมาโดนปฏิเสธทีหลัง
if ($staff) {
    $guard = rg_can_submit($pdo, (int) $staff['id'], $deviceId, $clientIp);
    if (!$guard['ok']) {
        $alreadyRated = true;
        $formError    = rg_message($guard['reason']);
    }
}

if ($staff && !$alreadyRated && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $feedback = trim($_POST['feedback'] ?? '');

    // จำกัดความยาวข้อเสนอแนะไว้ที่ 1000 ตัวอักษร
    if (mb_strlen($feedback, 'UTF-8') > 1000) {
        $feedback = mb_substr($feedback, 0, 1000, 'UTF-8');
    }

    // ข้อความคือสิ่งเดียวที่ใช้ตัดสินดาว ถ้าไม่มีก็ให้ดาวไม่ได้
    if (mb_strlen($feedback, 'UTF-8') < 5) {
        $formError = 'กรุณาเล่าความรู้สึกสัก 1 ประโยค เพื่อให้เราประเมินได้';
    } elseif (!ai_columns_ready($pdo)) {
        // ยังไม่ได้รัน ai_migration.sql — บอกลูกค้าแบบสุภาพ ไม่โชว์ error ดิบ
        $formError = 'ขออภัย ระบบกำลังปรับปรุงชั่วคราว กรุณาลองใหม่อีกครั้งภายหลัง';
    } else {
        $stmt = $pdo->prepare(
            "INSERT INTO ratings (staff_id, score, feedback, ai_status) VALUES (?, NULL, ?, 'pending')"
        );
        $stmt->execute([$staff['id'], $feedback]);

        // จดไว้ว่าเครื่องนี้ส่งให้พนักงานคนนี้แล้ว
        rg_record($pdo, (int) $staff['id'], $deviceId, $clientIp);

        // ลิงก์เซ็นชื่อ อายุ 10 นาที — ใช้สั่งให้ดาวจากหน้าขอบคุณ
        $ratingId = (int) $pdo->lastInsertId();
        $expires  = time() + 600;

        header('Location: thankyou.php?' . http_build_query([
            'name' => $staff['name'],
            'id'   => $ratingId,
            'exp'  => $expires,
            't'    => ai_make_token($ratingId, $expires),
        ]));
        exit;
    }
}

// อักษรย่อสำหรับกรณีไม่มีรูป
$initial = '';
if ($staff) {
    $parts   = preg_split('/\s+/', trim($staff['name']));
    $initial = mb_substr($parts[0] ?? '', 0, 1, 'UTF-8');
}
$accent    = $staff['brand_color'] ?? '#D81300';
$accentInk = brand_ink($accent);   // สีตัวอักษรที่อ่านออกบนสีแบรนด์

// โลโก้องค์กร (ตัวอักษรขาว ใช้บนพื้นมืด)
$logoFile = '';
foreach (['logo.png', 'logo.svg', 'logo.jpg', 'logo.webp'] as $lf) {
    if (file_exists(__DIR__ . '/admin/uploads/login/' . $lf)) {
        $logoFile = 'admin/uploads/login/' . $lf;
        break;
    }
}

$cssVer = @filemtime(__DIR__ . '/assets/rate.css') ?: time();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#08080B">
    <title>ประเมินความพึงพอใจ | <?= htmlspecialchars(ORG_NAME) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="assets/rate.css?v=<?= $cssVer ?>">
    <style>:root { --accent: <?= htmlspecialchars($accent) ?>; --accent-ink: <?= $accentInk ?>; }</style>
</head>
<body>
    <div class="bg-scene">
        <div class="bg-lines"></div>
        <div class="bg-grain"></div>
    </div>

    <div class="rate-page">

        <!-- โลโก้องค์กร -->
        <div class="top-logo">
            <?php if ($logoFile): ?>
                <img src="<?= htmlspecialchars($logoFile) ?>" alt="<?= htmlspecialchars(ORG_NAME) ?>">
            <?php else: ?>
                <span class="wordmark">EAKSAHA<span>GROUP</span></span>
            <?php endif; ?>
        </div>

        <div class="rate-card">

            <!-- แถบหัวแบรนด์ -->
            <div class="brand-strip">
                <div class="brand-mark"><?= htmlspecialchars(ORG_NAME) ?></div>
                <div class="brand-tagline"><?= htmlspecialchars(ORG_TAGLINE) ?></div>
            </div>

            <?php if ($error): ?>
                <div class="error-state">
                    <div class="error-icon">!</div>
                    <h2>ไม่สามารถดำเนินการได้</h2>
                    <p><?= htmlspecialchars($error) ?></p>
                    <p style="margin-top:14px;">กรุณาสแกน QR ใหม่อีกครั้ง หรือติดต่อพนักงานขายที่ให้บริการคุณ</p>
                </div>
            <?php else: ?>

                <div class="card-body">
                    <!-- รูป / อักษรย่อ -->
                    <div class="staff-photo">
                        <?php if ($staff['photo']): ?>
                            <img src="admin/uploads/staff/<?= htmlspecialchars($staff['photo']) ?>" alt="<?= htmlspecialchars($staff['name']) ?>">
                        <?php else: ?>
                            <div class="staff-initial" aria-hidden="true"><?= htmlspecialchars($initial) ?></div>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($staff['brand_name'])): ?>
                        <span class="brand-badge"><?= htmlspecialchars($staff['brand_name']) ?></span>
                    <?php endif; ?>

                    <h1><?= htmlspecialchars($staff['name']) ?></h1>
                    <p class="position"><?= htmlspecialchars($staff['position']) ?></p>

                    <div class="divider"></div>

                    <?php if ($alreadyRated): ?>
                        <!-- เครื่องนี้ประเมินพนักงานคนนี้ไปแล้ว — ไม่ต้องโชว์ฟอร์มให้พิมพ์เสียเที่ยว -->
                        <div class="done-state">
                            <div class="done-ic" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                            </div>
                            <h2>ประเมินเรียบร้อยแล้ว</h2>
                            <p><?= htmlspecialchars($formError) ?></p>
                        </div>
                    <?php else: ?>

                    <p class="prompt">เล่าให้เราฟังหน่อยว่าวันนี้พนักงานขายดูแลคุณเป็นอย่างไรบ้าง</p>

                    <?php if ($formError): ?>
                        <div class="form-error" role="alert"><?= htmlspecialchars($formError) ?></div>
                    <?php endif; ?>

                    <form method="POST" id="rateForm">
                        <input type="hidden" name="code" value="<?= htmlspecialchars($code) ?>">

                        <div class="feedback-field">
                            <label for="feedback">ความรู้สึกของคุณ</label>
                            <textarea name="feedback" id="feedback" rows="5" maxlength="1000" required
                                placeholder="เช่น อธิบายรายละเอียดรถได้ดีมาก ใจเย็น ตอบทุกคำถาม ไม่เร่งให้ตัดสินใจ..."><?= htmlspecialchars($_POST['feedback'] ?? '') ?></textarea>
                            <div class="char-row">
                                <span class="char-hint">เขียนสั้นๆ ก็ได้ ไม่ต้องกดดาว</span>
                                <span class="char-count" id="charCount" aria-live="polite">0 / 1000</span>
                            </div>
                        </div>

                        <button type="submit" class="submit-btn" id="submitBtn">
                            <span class="btn-label">ส่งแบบประเมิน</span>
                            <span class="btn-sending">กำลังบันทึก...</span>
                        </button>

                        <p class="privacy-note">
                            ความเห็นของคุณจะถูกส่งให้ผู้ดูแลโดยไม่ระบุตัวตน
                            และใช้เพื่อพัฒนาคุณภาพการให้บริการเท่านั้น
                        </p>
                    </form>
                    <?php endif; ?>
                </div>

            <?php endif; ?>
        </div>

        <div class="footer-bar">
            <div class="footer-logo">EAKSAHA<span>GROUP</span></div>
            <div class="footer-dev">พัฒนาระบบโดย <?= htmlspecialchars(ORG_DEPT) ?></div>
            <div class="footer-college"><?= htmlspecialchars(ORG_NAME_TH) ?></div>
            <div class="footer-divider"></div>
            <div class="footer-copy">&copy; <?= date('Y') ?> <?= htmlspecialchars(ORG_NAME) ?> · All Rights Reserved</div>
        </div>
    </div>

    <script>
    (function () {
        // ตัวนับตัวอักษรแบบเรียลไทม์
        var ta = document.getElementById('feedback');
        var cc = document.getElementById('charCount');
        if (ta && cc) {
            var MAX = 1000;
            var update = function () {
                var n = ta.value.length;
                cc.textContent = n + ' / ' + MAX;
                cc.classList.toggle('near', n >= MAX * 0.8 && n < MAX);
                cc.classList.toggle('full', n >= MAX);
            };
            ta.addEventListener('input', update);
            update();
        }

        // ── กันกดปุ่มส่งซ้ำ ──
        // ถ้าเน็ตช้าแล้วลูกค้ากดรัวๆ จะได้ไม่บันทึกซ้ำหลายรายการ
        var form = document.getElementById('rateForm');
        var btn  = document.getElementById('submitBtn');
        if (form && btn) {
            form.addEventListener('submit', function (e) {
                if (form.dataset.sent === '1') { e.preventDefault(); return; }
                form.dataset.sent = '1';
                btn.classList.add('is-sending');
            });
        }
    })();
    </script>
</body>
</html>
