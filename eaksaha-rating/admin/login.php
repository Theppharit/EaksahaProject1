<?php
session_start();
require '../conn/config.php';

// ถ้า login ค้างไว้อยู่แล้ว ให้ไปหน้าแดชบอร์ดเลย
if (!empty($_SESSION['admin_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare('SELECT * FROM admin_users WHERE username = ?');
    $stmt->execute([$username]);
    $admin = $stmt->fetch();

    if ($admin && password_verify($password, $admin['password'])) {
        $_SESSION['admin_id']       = $admin['id'];
        $_SESSION['admin_username'] = $admin['username'];
        header('Location: dashboard.php');
        exit;
    }
    $error = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
}

// รูปพื้นหลัง (วางไฟล์รูปใน admin/uploads/login/)
$bgImage = '';
$bgDir   = 'uploads/login/';
$allowed = ['jpg', 'jpeg', 'png', 'webp'];
if (is_dir($bgDir)) {
    foreach (scandir($bgDir) as $file) {
        if (stripos($file, 'logo') === 0) continue; // ข้ามไฟล์โลโก้
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (in_array($ext, $allowed, true)) { $bgImage = $bgDir . $file; break; }
    }
}

// โลโก้
$logoFile = '';
foreach (['logo.png', 'logo.jpg', 'logo.jpeg', 'logo.webp'] as $lf) {
    if (file_exists($bgDir . $lf)) { $logoFile = $bgDir . $lf; break; }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ · <?= htmlspecialchars(ORG_NAME) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        :root { --accent:#E10600; --line:#2A2A2E; --muted:#9A9AA2; }
        body { font-family:'Kanit',sans-serif; color:#F4F4F5; }
        .login-split { display:flex; min-height:100vh; }

        /* ── ฝั่งซ้าย: ภาพ/แบรนด์ ── */
        .login-visual {
            flex:1; position:relative; overflow:hidden;
            background:
                radial-gradient(700px 320px at 30% 20%, rgba(225,6,0,0.28), transparent 65%),
                linear-gradient(160deg, #141416 0%, #050506 70%);
            <?php if ($bgImage): ?>
            background-image:
                linear-gradient(90deg, rgba(5,5,6,0.55), rgba(5,5,6,0.85)),
                url('<?= htmlspecialchars($bgImage) ?>');
            background-size: cover; background-position: center;
            <?php endif; ?>
            display:flex; flex-direction:column; justify-content:space-between; padding:48px 52px;
        }
        .login-visual::after {
            content:''; position:absolute; inset:0;
            background-image: radial-gradient(circle, rgba(255,255,255,0.04) 1px, transparent 1px);
            background-size: 30px 30px; pointer-events:none;
        }
        .lv-top { position:relative; z-index:1; }
        .lv-logo { font-size:26px; font-weight:800; letter-spacing:0.14em; }
        .lv-logo span { color:var(--accent); }
        .lv-logo img { max-height:56px; display:block; }
        .lv-center { position:relative; z-index:1; }
        .lv-eyebrow {
            display:inline-block; font-size:11px; font-weight:700; letter-spacing:0.18em; text-transform:uppercase;
            color:var(--accent); border:1px solid rgba(225,6,0,0.4); background:rgba(225,6,0,0.10);
            border-radius:30px; padding:5px 16px; margin-bottom:18px;
        }
        .lv-title { font-size:clamp(28px,3.4vw,42px); font-weight:800; line-height:1.15; margin-bottom:14px; }
        .lv-title .accent { color:var(--accent); }
        .lv-sub { font-size:15px; color:var(--muted); line-height:1.8; max-width:440px; }
        .lv-bottom { position:relative; z-index:1; font-size:12px; color:#6E6E76; }

        /* ── ฝั่งขวา: ฟอร์ม ── */
        .login-form-side {
            width:460px; flex-shrink:0;
            background:linear-gradient(180deg,#101012 0%,#0A0A0C 100%);
            border-left:1px solid var(--line);
            display:flex; align-items:center; justify-content:center; padding:40px 44px;
        }
        .login-box { width:100%; max-width:340px; }
        .login-box h1 { font-size:24px; font-weight:700; margin-bottom:4px; }
        .login-box .sub { font-size:13.5px; color:var(--muted); margin-bottom:28px; }
        .field { margin-bottom:16px; }
        .field label { display:block; font-size:13px; font-weight:600; margin-bottom:7px; color:#E4E4E7; }
        .field input {
            width:100%; padding:13px 14px; border:1px solid var(--line); border-radius:11px;
            background:#0D0D0F; color:#F4F4F5; font-family:'Kanit',sans-serif; font-size:14px;
        }
        .field input:focus { outline:none; border-color:var(--accent); box-shadow:0 0 0 3px rgba(225,6,0,0.18); }
        .btn-login {
            width:100%; margin-top:8px; padding:14px; border:none; border-radius:11px;
            background:linear-gradient(135deg,var(--accent),#B10500); color:#FFF;
            font-family:'Kanit',sans-serif; font-size:15px; font-weight:700; cursor:pointer;
            box-shadow:0 8px 22px -8px rgba(225,6,0,0.7); transition:filter 0.15s;
        }
        .btn-login:hover { filter:brightness(1.08); }
        .alert-error {
            background:rgba(239,68,68,0.10); color:#FCA5A1; border:1px solid rgba(239,68,68,0.3);
            padding:12px 15px; border-radius:10px; font-size:13.5px; margin-bottom:18px;
        }
        .login-links { margin-top:22px; text-align:center; font-size:12.5px; color:var(--muted); line-height:2; }
        .login-links a { color:#C7C7CE; text-decoration:none; }
        .login-links a:hover { color:#FFF; }
        .login-links .divider { height:1px; background:var(--line); margin:12px 0; }

        @media (max-width: 860px) {
            .login-split { flex-direction:column; }
            .login-visual { min-height:220px; padding:32px; }
            .lv-sub, .lv-bottom { display:none; }
            .login-form-side { width:100%; border-left:none; border-top:1px solid var(--line); }
        }
    </style>
</head>
<body>
    <div class="login-split">
        <div class="login-visual">
            <div class="lv-top">
                <div class="lv-logo">
                    <?php if ($logoFile): ?>
                        <img src="<?= htmlspecialchars($logoFile) ?>" alt="<?= htmlspecialchars(ORG_NAME) ?>">
                    <?php else: ?>
                        EAKSAHA<span>GROUP</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="lv-center">
                <span class="lv-eyebrow">Satisfaction System</span>
                <div class="lv-title">ระบบประเมิน<br><span class="accent">ความพึงพอใจ</span><br>พนักงานขายรถ EV</div>
                <p class="lv-sub"><?= htmlspecialchars(ORG_TAGLINE) ?> · <?= htmlspecialchars(ORG_NAME_TH) ?><br>DEEPAL · GEELY · AION · GAC · OMODA &amp; JAECOO · WULING · CHERY · GWM</p>
            </div>
            <div class="lv-bottom">&copy; <?= date('Y') ?> <?= htmlspecialchars(ORG_NAME) ?> · All Rights Reserved</div>
        </div>

        <div class="login-form-side">
            <div class="login-box">
                <h1>เข้าสู่ระบบ</h1>
                <p class="sub">สำหรับผู้ดูแลระบบ</p>

                <?php if ($error): ?>
                    <div class="alert-error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form method="POST">
                    <div class="field">
                        <label for="username">ชื่อผู้ใช้</label>
                        <input type="text" id="username" name="username" placeholder="กรอกชื่อผู้ใช้" required autofocus>
                    </div>
                    <div class="field">
                        <label for="password">รหัสผ่าน</label>
                        <input type="password" id="password" name="password" placeholder="กรอกรหัสผ่าน" required>
                    </div>
                    <button type="submit" class="btn-login">เข้าสู่ระบบ</button>
                </form>

                <div class="login-links">
                    <a href="developer.php">ทีมผู้พัฒนาระบบ</a>
                    <div class="divider"></div>
                    Copyright &copy; <?= date('Y') ?> <?= htmlspecialchars(ORG_DEPT) ?><br>
                    <?= htmlspecialchars(ORG_DEPT2) ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
