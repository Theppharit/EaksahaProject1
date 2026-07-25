<?php
require '../conn/config.php';
require 'includes/auth.php';

$pageTitle  = 'คู่มือการใช้งาน';
$activePage = 'manual';

$message = '';
$messageType = '';

$manualDir  = 'uploads/manual/';
$manualFile = $manualDir . 'manual.pdf';

// ----- ลบไฟล์คู่มือ -----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    if (file_exists($manualFile)) {
        unlink($manualFile);
        $message = 'ลบไฟล์คู่มือสำเร็จ';
        $messageType = 'success';
    }
}

// ----- อัปโหลด / แทนที่ไฟล์คู่มือ -----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'upload') {
    if (!empty($_FILES['manual']['name']) && $_FILES['manual']['error'] === UPLOAD_ERR_OK) {
        $ext   = strtolower(pathinfo($_FILES['manual']['name'], PATHINFO_EXTENSION));
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $_FILES['manual']['tmp_name']);
        finfo_close($finfo);

        if ($ext !== 'pdf' || $mime !== 'application/pdf') {
            $message = 'กรุณาอัปโหลดไฟล์ PDF เท่านั้น';
            $messageType = 'error';
        } elseif ($_FILES['manual']['size'] > 20 * 1024 * 1024) {
            $message = 'ขนาดไฟล์ต้องไม่เกิน 20MB';
            $messageType = 'error';
        } else {
            if (!is_dir($manualDir)) @mkdir($manualDir, 0755, true);

            if (!is_dir($manualDir) || !is_writable($manualDir)) {
                $message = 'อัปโหลดไม่สำเร็จ: โฟลเดอร์ ' . $manualDir . ' ไม่มีสิทธิ์เขียนไฟล์ (ตั้งเป็น 755 หรือ 775)';
                $messageType = 'error';
            } elseif (move_uploaded_file($_FILES['manual']['tmp_name'], $manualFile)) {
                $message = 'อัปโหลดคู่มือสำเร็จ';
                $messageType = 'success';
            } else {
                $message = 'อัปโหลดคู่มือไม่สำเร็จ';
                $messageType = 'error';
            }
        }
    } else {
        $message = 'กรุณาเลือกไฟล์ PDF ก่อนอัปโหลด';
        $messageType = 'error';
    }
}

$hasManual  = file_exists($manualFile);
$manualSize = $hasManual ? round(filesize($manualFile) / 1024 / 1024, 2) : 0;
$manualDate = $hasManual ? date('d/m/Y H:i', filemtime($manualFile)) : '';

require 'includes/head.php';
?>
<h1>คู่มือการใช้งาน</h1>
<p class="page-sub">อัปโหลดไฟล์คู่มือ (PDF) เก็บไว้ในระบบ เพื่อให้ผู้ดูแลคนอื่นดาวน์โหลดไปอ่านได้</p>

<?php if ($message): ?>
    <div class="alert alert-<?= htmlspecialchars($messageType) ?>"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<div class="form-card" style="max-width: 560px;">
    <?php if ($hasManual): ?>
        <div class="field">
            <label>ไฟล์คู่มือปัจจุบัน</label>
            <div class="hint" style="margin-bottom: 12px;">manual.pdf · <?= $manualSize ?> MB · อัปเดตล่าสุด <?= htmlspecialchars($manualDate) ?></div>
            <a href="<?= htmlspecialchars($manualFile) ?>" class="btn btn-primary" download>ดาวน์โหลดคู่มือ (PDF)</a>
        </div>
        <form method="POST" style="margin-top: 20px;" onsubmit="return confirm('ยืนยันการลบไฟล์คู่มือปัจจุบัน?');">
            <input type="hidden" name="action" value="delete">
            <button type="submit" class="btn btn-danger btn-sm">ลบไฟล์คู่มือ</button>
        </form>
        <div style="height:1px; background:#2A2A2E; margin: 24px 0;"></div>
    <?php else: ?>
        <p class="hint" style="margin-bottom: 16px;">ยังไม่มีไฟล์คู่มือในระบบ กรุณาอัปโหลดไฟล์ PDF เพื่อให้ผู้ดูแลระบบดาวน์โหลดได้</p>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="action" value="upload">
        <div class="field">
            <label for="manual">เลือกไฟล์คู่มือ (PDF เท่านั้น ขนาดไม่เกิน 20MB)</label>
            <input type="file" id="manual" name="manual" accept="application/pdf,.pdf" required>
        </div>
        <button type="submit" class="btn btn-primary"><?= $hasManual ? 'แทนที่ไฟล์คู่มือ' : 'อัปโหลดคู่มือ' ?></button>
    </form>
</div>

<?php require 'includes/footer.php'; ?>
