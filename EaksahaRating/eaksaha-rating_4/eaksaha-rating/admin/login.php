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
    <script>
        // ใช้ธีมสีที่เลือกไว้ (กันจอกะพริบ)
        (function () {
            try {
                var t = localStorage.getItem('eaksaha_theme');
                if (t && ['fresh', 'soft', 'dark-red', 'midnight'].indexOf(t) !== -1 && t !== 'fresh') {
                    document.documentElement.setAttribute('data-theme', t);
                }
            } catch (e) { }
        })();
    </script>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        /* ── โทนสีเริ่มต้น: สดใส (ฟ้า-มิ้นต์) ── */
        :root {
            --accent:#0EA5E9; --accent-deep:#0284C7; --mint:#10B981; --mint-deep:#059669;
            --line:#DCE9F2; --text:#0F1F2E; --muted:#5C7186;
            --page:#F2F9FE; --card:#FFFFFF; --input-bg:#FBFDFF;
            --v1:#0369A1; --v2:#0EA5E9; --v3:#10B981;
            --v1r:3,105,161; --v2r:14,165,233; --v3r:16,185,129;
            --hi:#D2FBE8; --hi2:#C9F3D9;
            --glow:14,165,233;
            --scheme:light;
        }
        html[data-theme="soft"] {
            --accent:#46698C; --accent-deep:#37536F; --mint:#7FA184; --mint-deep:#5F8266;
            --line:#E2E3DE; --text:#2A333C; --muted:#68727C;
            --page:#F4F4F2; --card:#FFFFFF; --input-bg:#FAFAF8;
            --v1:#2F4759; --v2:#46698C; --v3:#7FA184;
            --v1r:47,71,89; --v2r:70,105,140; --v3r:127,161,132;
            --hi:#DDEBE0; --hi2:#D3E4D7;
            --glow:70,105,140;
            --scheme:light;
        }
        html[data-theme="dark-red"] {
            --accent:#FF3B3B; --accent-deep:#C10500; --mint:#FFB020; --mint-deep:#D98E0B;
            --line:#363646; --text:#F6F7FB; --muted:#B0B2C2;
            --page:#12121B; --card:#1E1E29; --input-bg:#14141C;
            --v1:#1A0A0C; --v2:#C10500; --v3:#FF6A2B;
            --v1r:26,10,12; --v2r:193,5,0; --v3r:255,106,43;
            --hi:#FFD98A; --hi2:#FFC9A8;
            --glow:225,6,0;
            --scheme:dark;
        }
        html[data-theme="midnight"] {
            --accent:#4C8DFF; --accent-deep:#2F6BDB; --mint:#22D3EE; --mint-deep:#0E9CB8;
            --line:#2A394E; --text:#EDF2F9; --muted:#96A5BA;
            --page:#0D1420; --card:#16202E; --input-bg:#0E1724;
            --v1:#0A1730; --v2:#2F6BDB; --v3:#22D3EE;
            --v1r:10,23,48; --v2r:47,107,219; --v3r:34,211,238;
            --hi:#B6F3FD; --hi2:#BFD8FF;
            --glow:47,107,219;
            --scheme:dark;
        }
        html { color-scheme: var(--scheme, light); }
        body { font-family:'Kanit',sans-serif; color:var(--text); background:var(--page); }
        .login-split { display:flex; min-height:100vh; }

        /* ── ฝั่งซ้าย: ภาพ/แบรนด์ (ไล่เฉดฟ้า→มิ้นต์) ── */
        .login-visual {
            flex:1; position:relative; overflow:hidden;
            background: linear-gradient(150deg, var(--v1) 0%, var(--v2) 52%, var(--v3) 100%);
            <?php if ($bgImage): ?>
            background-image:
                linear-gradient(150deg, rgba(var(--v1r),0.82), rgba(var(--v2r),0.72) 52%, rgba(var(--v3r),0.78)),
                url('<?= htmlspecialchars($bgImage) ?>');
            background-size: cover; background-position: center;
            <?php endif; ?>
            display:flex; flex-direction:column; justify-content:space-between; padding:48px 52px;
            color:#FFFFFF;
        }
        .login-visual::after {
            content:''; position:absolute; inset:0;
            background-image: radial-gradient(circle, rgba(255,255,255,0.10) 1px, transparent 1px);
            background-size: 30px 30px; pointer-events:none;
        }
        /* วงกลมเรืองแสงลอย */
        .login-visual::before {
            content:''; position:absolute;
            width:420px; height:420px; border-radius:50%;
            background: radial-gradient(circle, rgba(255,255,255,0.22), transparent 65%);
            top:-140px; right:-120px; pointer-events:none;
        }
        .lv-top { position:relative; z-index:1; }
        .lv-logo { font-size:26px; font-weight:800; letter-spacing:0.14em; }
        .lv-logo span { color:var(--hi); }
        .lv-logo img { max-height:56px; display:block; }
        .lv-center { position:relative; z-index:1; }
        .lv-eyebrow {
            display:inline-block; font-size:11px; font-weight:700; letter-spacing:0.18em; text-transform:uppercase;
            color:#FFFFFF; border:1px solid rgba(255,255,255,0.45); background:rgba(255,255,255,0.14);
            border-radius:30px; padding:5px 16px; margin-bottom:18px; backdrop-filter:blur(4px);
        }
        .lv-title { font-size:clamp(28px,3.4vw,42px); font-weight:800; line-height:1.15; margin-bottom:14px; color:#FFFFFF; }
        .lv-title .accent { color:var(--hi2); }
        .lv-sub { font-size:15px; color:rgba(255,255,255,0.85); line-height:1.8; max-width:460px; }
        .lv-bottom { position:relative; z-index:1; font-size:12px; color:rgba(255,255,255,0.65); }

        /* ── ฝั่งขวา: ฟอร์ม ── */
        .login-form-side {
            width:460px; flex-shrink:0;
            background:var(--card);
            border-left:1px solid var(--line);
            display:flex; align-items:center; justify-content:center; padding:40px 44px;
        }
        .login-box { width:100%; max-width:340px; }
        .login-box h1 { font-size:24px; font-weight:700; margin-bottom:4px; color:var(--text); }
        .login-box .sub { font-size:13.5px; color:var(--muted); margin-bottom:28px; }
        .field { margin-bottom:16px; }
        .field label { display:block; font-size:13px; font-weight:600; margin-bottom:7px; color:var(--text); }
        .field input {
            width:100%; padding:13px 14px; border:1px solid var(--line); border-radius:11px;
            background:var(--input-bg); color:var(--text); font-family:'Kanit',sans-serif; font-size:14px;
        }
        .field input:focus { outline:none; border-color:var(--accent); box-shadow:0 0 0 3px rgba(var(--glow),0.16); }
        .btn-login {
            width:100%; margin-top:8px; padding:14px; border:none; border-radius:11px;
            background:linear-gradient(135deg,var(--accent),var(--mint)); color:#FFF;
            font-family:'Kanit',sans-serif; font-size:15px; font-weight:700; cursor:pointer;
            box-shadow:0 10px 24px -10px rgba(var(--glow),0.6); transition:filter 0.15s, transform 0.1s;
        }
        .btn-login:hover { filter:brightness(1.05); }
        .btn-login:active { transform:translateY(1px); }
        .alert-error {
            background:rgba(225,29,72,0.07); color:#BE123C; border:1px solid rgba(225,29,72,0.25);
            padding:12px 15px; border-radius:10px; font-size:13.5px; margin-bottom:18px;
        }
        .login-links { margin-top:22px; text-align:center; font-size:12.5px; color:var(--muted); line-height:2; }
        .login-links a { color:var(--accent-deep); text-decoration:none; font-weight:600; }
        .login-links a:hover { text-decoration:underline; }
        .login-links .divider { height:1px; background:var(--line); margin:12px 0; }

        /* ── ปุ่มเลือกธีมสี ── */
        .login-theme {
            margin-top:22px; padding-top:18px; border-top:1px solid var(--line);
            display:flex; align-items:center; justify-content:center; gap:10px;
        }
        .login-theme .lt-label { font-size:11px; font-weight:700; letter-spacing:.08em; color:var(--muted); }
        .login-theme button {
            width:26px; height:26px; border-radius:50%; padding:0; cursor:pointer;
            border:2px solid var(--line); transition:transform .15s, box-shadow .15s;
        }
        .login-theme button:hover { transform:scale(1.15); }
        .login-theme button.active { border-color:var(--text); box-shadow:0 0 0 3px rgba(var(--glow),0.25); }
        .lt-fresh    { background:linear-gradient(135deg,#0EA5E9 50%,#10B981 50%); }
        .lt-soft     { background:linear-gradient(135deg,#46698C 50%,#7FA184 50%); }
        .lt-dark-red { background:linear-gradient(135deg,#1E1E29 50%,#FF3B3B 50%); }
        .lt-midnight { background:linear-gradient(135deg,#16202E 50%,#4C8DFF 50%); }

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
                <p class="lv-sub"><?= htmlspecialchars(ORG_TAGLINE) ?> · <?= htmlspecialchars(ORG_NAME_TH) ?><br>DEEPAL · GEELY · AION · OMODA &amp; JAECOO · WULING · CHERY · GWM · FORD · NISSAN</p>
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

                <div class="login-theme">
                    <span class="lt-label">ธีมสี</span>
                    <button type="button" class="lt-fresh"    data-theme-pick="fresh"    title="สดใส (ฟ้า-มิ้นต์)"></button>
                    <button type="button" class="lt-soft"     data-theme-pick="soft"     title="นุ่มสบายตา"></button>
                    <button type="button" class="lt-dark-red" data-theme-pick="dark-red" title="ดำ-แดง Eaksaha"></button>
                    <button type="button" class="lt-midnight" data-theme-pick="midnight" title="มืดน้ำเงิน"></button>
                </div>

                <!-- <div class="login-links">
                    <a href="developer.php">ทีมผู้พัฒนาระบบ</a>
                    <div class="divider"></div>
                    Copyright &copy; <?= date('Y') ?> <?= htmlspecialchars(ORG_DEPT) ?><br>
                    <?= htmlspecialchars(ORG_DEPT2) ?>
                </div> -->
            </div>
        </div>
    </div>

    <script>
        // สลับธีมสี — จำค่าไว้ใช้กับทุกหน้าในระบบ
        (function () {
            var current = document.documentElement.getAttribute('data-theme') || 'fresh';
            document.querySelectorAll('[data-theme-pick]').forEach(function (btn) {
                var t = btn.getAttribute('data-theme-pick');
                if (t === current) btn.classList.add('active');
                btn.addEventListener('click', function () {
                    try { localStorage.setItem('eaksaha_theme', t); } catch (e) {}
                    if (t === 'fresh') document.documentElement.removeAttribute('data-theme');
                    else document.documentElement.setAttribute('data-theme', t);
                    document.querySelectorAll('[data-theme-pick]').forEach(function (b) {
                        b.classList.toggle('active', b === btn);
                    });
                });
            });
        })();
    </script>
</body>
</html>
