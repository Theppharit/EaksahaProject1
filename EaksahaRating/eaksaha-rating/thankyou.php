<?php
require 'conn/config.php';
$name = trim($_GET['name'] ?? '');
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ขอบคุณสำหรับการประเมิน | <?= htmlspecialchars(ORG_NAME) ?></title>
    <link rel="stylesheet" href="assets/rate.css">
</head>
<body>
    <div class="rate-page">
        <div class="rate-card thankyou-card">
            <div class="brand-strip">
                <div class="brand-mark"><?= htmlspecialchars(ORG_NAME) ?></div>
                <div class="brand-tagline"><?= htmlspecialchars(ORG_TAGLINE) ?></div>
            </div>

            <div class="thankyou-icon">✓</div>
            <h1>ขอบคุณสำหรับการประเมิน</h1>
            <?php if ($name !== ''): ?>
                <p class="subtitle">ความคิดเห็นของคุณต่อ <strong><?= htmlspecialchars($name) ?></strong><br>ได้ถูกบันทึกเรียบร้อยแล้ว</p>
            <?php else: ?>
                <p class="subtitle">ความคิดเห็นของคุณได้ถูกบันทึกเรียบร้อยแล้ว</p>
            <?php endif; ?>
            <p class="thankyou-note">ความคิดเห็นของคุณช่วยให้เราพัฒนาการบริการให้ดียิ่งขึ้น</p>
        </div>

        <div class="footer-bar">
            <div class="footer-logo">EAKSAHA<span>GROUP</span></div>
            <div class="footer-dev">พัฒนาระบบโดย <?= htmlspecialchars(ORG_DEPT) ?></div>
            <div class="footer-college"><?= htmlspecialchars(ORG_NAME_TH) ?></div>
            <div class="footer-divider"></div>
            <div class="footer-copy">&copy; <?= date('Y') ?> <?= htmlspecialchars(ORG_NAME) ?> · All Rights Reserved</div>
        </div>
    </div>
</body>
</html>
