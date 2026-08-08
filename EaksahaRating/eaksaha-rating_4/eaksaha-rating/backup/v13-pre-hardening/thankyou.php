<?php
require 'conn/config.php';
$name = trim($_GET['name'] ?? '');

// ----- ลิงก์สั่งให้ AI ให้ดาวเบื้องหลัง -----
// หน้านี้แสดงผลให้ลูกค้าเห็นก่อน แล้วค่อยยิงคำขอไปให้ ai_score.php ทำงานต่อ
// ลูกค้าจึงไม่ต้องรอ AI เลย ถ้ายิงไม่สำเร็จ รายการจะค้างเป็น "รอให้ดาว"
// และผู้ดูแลสั่งลองใหม่ได้จากหน้าแดชบอร์ด
$scoreUrl = '';
$rid = (int) ($_GET['id'] ?? 0);
$exp = (int) ($_GET['exp'] ?? 0);
$tok = (string) ($_GET['t'] ?? '');
if ($rid > 0 && $exp > 0 && $tok !== '') {
    $scoreUrl = 'ai_score.php?' . http_build_query(['id' => $rid, 'exp' => $exp, 't' => $tok]);
}

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
    <title>ขอบคุณสำหรับการประเมิน | <?= htmlspecialchars(ORG_NAME) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="assets/rate.css?v=<?= $cssVer ?>">
</head>
<body>
    <div class="bg-scene">
        <div class="bg-lines"></div>
        <div class="bg-grain"></div>
    </div>

    <div class="rate-page">

        <div class="top-logo">
            <?php if ($logoFile): ?>
                <img src="<?= htmlspecialchars($logoFile) ?>" alt="<?= htmlspecialchars(ORG_NAME) ?>">
            <?php else: ?>
                <span class="wordmark">EAKSAHA<span>GROUP</span></span>
            <?php endif; ?>
        </div>

        <div class="rate-card thankyou-card">
            <div class="brand-strip">
                <div class="brand-mark"><?= htmlspecialchars(ORG_NAME) ?></div>
                <div class="brand-tagline"><?= htmlspecialchars(ORG_TAGLINE) ?></div>
            </div>

            <div class="card-body">
                <div class="thankyou-icon" aria-hidden="true">✓</div>
                <h1>ขอบคุณสำหรับการประเมิน</h1>
                <?php if ($name !== ''): ?>
                    <p class="subtitle">ความคิดเห็นของคุณต่อ <strong><?= htmlspecialchars($name) ?></strong><br>ได้ถูกบันทึกเรียบร้อยแล้ว</p>
                <?php else: ?>
                    <p class="subtitle">ความคิดเห็นของคุณได้ถูกบันทึกเรียบร้อยแล้ว</p>
                <?php endif; ?>
                <p class="thankyou-note">ความคิดเห็นของคุณช่วยให้เราพัฒนาการบริการให้ดียิ่งขึ้น</p>
            </div>
        </div>

        <div class="footer-bar">
            <div class="footer-logo">EAKSAHA<span>GROUP</span></div>
            <div class="footer-dev">พัฒนาระบบโดย <?= htmlspecialchars(ORG_DEPT) ?></div>
            <div class="footer-college"><?= htmlspecialchars(ORG_NAME_TH) ?></div>
            <div class="footer-divider"></div>
            <div class="footer-copy">&copy; <?= date('Y') ?> <?= htmlspecialchars(ORG_NAME) ?> · All Rights Reserved</div>
        </div>
    </div>

<?php if ($scoreUrl): ?>
    <script>
    // ยิงคำขอทิ้งไว้เฉยๆ ไม่ต้องรอผลลัพธ์ ไม่มีอะไรเปลี่ยนบนหน้าจอนี้
    // keepalive ทำให้คำขอไปต่อจนจบ แม้ลูกค้าจะปิดหน้าทันที
    (function () {
        var u = <?= json_encode($scoreUrl, JSON_UNESCAPED_SLASHES) ?>;
        try {
            fetch(u, { keepalive: true, credentials: 'same-origin' }).catch(function () {});
        } catch (e) {
            // เบราว์เซอร์รุ่นเก่าที่ไม่มี fetch — ใช้รูปภาพล่องหนแทน
            new Image().src = u;
        }
    })();
    </script>
<?php endif; ?>
</body>
</html>
