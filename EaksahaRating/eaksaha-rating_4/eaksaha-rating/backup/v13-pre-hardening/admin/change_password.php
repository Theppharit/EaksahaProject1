<?php
require '../conn/config.php';
require 'includes/auth.php';
require 'includes/flash.php';

$pageTitle  = 'เปลี่ยนรหัสผ่าน';
$activePage = 'password';

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current = $_POST['current_password'] ?? '';
    $new     = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    $stmt = $pdo->prepare('SELECT * FROM admin_users WHERE id = ?');
    $stmt->execute([$_SESSION['admin_id']]);
    $admin = $stmt->fetch();

    if (!$admin || !password_verify($current, $admin['password'])) {
        $message = 'รหัสผ่านปัจจุบันไม่ถูกต้อง';
        $messageType = 'error';
    } elseif (strlen($new) < 6) {
        $message = 'รหัสผ่านใหม่ต้องมีอย่างน้อย 6 ตัวอักษร';
        $messageType = 'error';
    } elseif ($new !== $confirm) {
        $message = 'รหัสผ่านใหม่และคำยืนยันไม่ตรงกัน';
        $messageType = 'error';
    } else {
        $hash = password_hash($new, PASSWORD_DEFAULT);
        $pdo->prepare('UPDATE admin_users SET password = ? WHERE id = ?')->execute([$hash, $_SESSION['admin_id']]);
        flash('success', 'เปลี่ยนรหัสผ่านแล้ว', 'ครั้งหน้าที่ล็อกอิน ให้ใช้รหัสผ่านใหม่');
        flash_redirect('change_password.php');
        $message = 'เปลี่ยนรหัสผ่านสำเร็จ';
        $messageType = 'success';
    }
}

require 'includes/head.php';
?>
<h1>เปลี่ยนรหัสผ่าน</h1>
<p class="page-sub">เปลี่ยนรหัสผ่านของบัญชีผู้ดูแลที่กำลังใช้งานอยู่ — แนะนำให้เปลี่ยนทันทีหลังติดตั้งระบบครั้งแรก</p>

<?php if ($message): ?>
    <script>window.toast('error', 'เปลี่ยนรหัสผ่านไม่สำเร็จ', <?= json_encode($message, JSON_UNESCAPED_UNICODE) ?>);</script>
<?php endif; ?>

<div class="form-card">
    <form method="POST">
        <div class="field">
            <label for="current_password">รหัสผ่านปัจจุบัน</label>
            <input type="password" id="current_password" name="current_password" required>
        </div>
        <div class="field">
            <label for="new_password">รหัสผ่านใหม่</label>
            <input type="password" id="new_password" name="new_password" required>
            <div class="hint">อย่างน้อย 6 ตัวอักษร</div>
        </div>
        <div class="field">
            <label for="confirm_password">ยืนยันรหัสผ่านใหม่</label>
            <input type="password" id="confirm_password" name="confirm_password" required>
        </div>
        <button type="submit" class="btn btn-primary">บันทึก</button>
    </form>
</div>

<?php require 'includes/footer.php'; ?>
