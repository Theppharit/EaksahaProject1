<?php
require 'conn/config.php';

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

// ----- บันทึกคะแนน (กดปุ่มแล้วส่งทันที) -----
if ($staff && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $score    = (int) ($_POST['score'] ?? 0);
    $feedback = trim($_POST['feedback'] ?? '');

    // จำกัดความยาวข้อเสนอแนะไว้ที่ 1000 ตัวอักษร
    if (mb_strlen($feedback, 'UTF-8') > 1000) {
        $feedback = mb_substr($feedback, 0, 1000, 'UTF-8');
    }

    if ($score >= 1 && $score <= 5) {
        $stmt = $pdo->prepare('INSERT INTO ratings (staff_id, score, feedback) VALUES (?, ?, ?)');
        $stmt->execute([$staff['id'], $score, $feedback !== '' ? $feedback : null]);
        header('Location: thankyou.php?name=' . urlencode($staff['name']));
        exit;
    }
}

// อักษรย่อสำหรับกรณีไม่มีรูป
$initial = '';
if ($staff) {
    $parts   = preg_split('/\s+/', trim($staff['name']));
    $initial = mb_substr($parts[0] ?? '', 0, 1, 'UTF-8');
}
$accent = $staff['brand_color'] ?? '#0EA5E9';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ประเมินความพึงพอใจ | <?= htmlspecialchars(ORG_NAME) ?></title>
    <link rel="stylesheet" href="assets/rate.css">
    <style>:root { --accent: <?= htmlspecialchars($accent) ?>; }</style>
</head>
<body>
    <div class="rate-page">
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
                </div>
            <?php else: ?>

                <!-- รูป / อักษรย่อ -->
                <div class="staff-photo">
                    <?php if ($staff['photo']): ?>
                        <img src="admin/uploads/staff/<?= htmlspecialchars($staff['photo']) ?>" alt="<?= htmlspecialchars($staff['name']) ?>">
                    <?php else: ?>
                        <div class="staff-initial"><?= htmlspecialchars($initial) ?></div>
                    <?php endif; ?>
                </div>

                <?php if (!empty($staff['brand_name'])): ?>
                    <div class="brand-badge"><?= htmlspecialchars($staff['brand_name']) ?></div>
                <?php endif; ?>

                <h1><?= htmlspecialchars($staff['name']) ?></h1>
                <p class="position"><?= htmlspecialchars($staff['position']) ?></p>

                <p class="prompt">กรุณาประเมินความพึงพอใจต่อการให้บริการของพนักงานขาย</p>

                <!-- ปุ่มให้คะแนน 5 ระดับ กดแล้วส่งทันที -->
                <form method="POST" id="rateForm">
                    <input type="hidden" name="code" value="<?= htmlspecialchars($code) ?>">
                    <input type="hidden" name="score" id="scoreInput" value="">

                    <div class="feedback-field">
                        <label for="feedback">ข้อเสนอแนะเพิ่มเติม (ถ้ามี)</label>
                        <textarea name="feedback" id="feedback" rows="3" maxlength="1000"
                            placeholder="เช่น ให้ข้อมูลรถละเอียด แนะนำโปรโมชั่นดี บริการสุภาพเป็นกันเอง..."></textarea>
                    </div>

                    <div class="score-buttons">
                        <button type="button" class="score-btn score-5" onclick="submitScore(5)">
                            <span class="emo">😍</span><span class="lbl">5 · พึงพอใจมากที่สุด</span>
                        </button>
                        <button type="button" class="score-btn score-4" onclick="submitScore(4)">
                            <span class="emo">😊</span><span class="lbl">4 · พึงพอใจมาก</span>
                        </button>
                        <button type="button" class="score-btn score-3" onclick="submitScore(3)">
                            <span class="emo">🙂</span><span class="lbl">3 · ปานกลาง</span>
                        </button>
                        <button type="button" class="score-btn score-2" onclick="submitScore(2)">
                            <span class="emo">😐</span><span class="lbl">2 · พึงพอใจน้อย</span>
                        </button>
                        <button type="button" class="score-btn score-1" onclick="submitScore(1)">
                            <span class="emo">😞</span><span class="lbl">1 · ควรปรับปรุง</span>
                        </button>
                    </div>
                </form>

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
        function submitScore(score) {
            document.querySelectorAll('.score-btn').forEach(function (btn) {
                btn.disabled = true;
                btn.style.opacity = '0.55';
            });
            document.getElementById('scoreInput').value = score;
            document.getElementById('rateForm').submit();
        }
    </script>
</body>
</html>
