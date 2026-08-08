<?php
// ── จดจำการเข้าสู่ระบบ: ตั้งอายุ session cookie ก่อน start ──
$remember = !empty($_COOKIE['eak_remember']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $remember = !empty($_POST['remember']);
}
$cookieLife = $remember ? 60 * 60 * 24 * 30 : 0; // 30 วัน
session_set_cookie_params($cookieLife);
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

        // จำชื่อผู้ใช้ไว้ล่วงหน้า / ล้างเมื่อไม่ติ๊ก
        if ($remember) {
            setcookie('eak_remember', '1', time() + $cookieLife, '/');
            setcookie('eak_user', $username, time() + $cookieLife, '/');
        } else {
            setcookie('eak_remember', '', time() - 3600, '/');
            setcookie('eak_user', '', time() - 3600, '/');
        }
        header('Location: dashboard.php');
        exit;
    }
    $error = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
}

// ค่าที่จำไว้สำหรับเติมช่องชื่อผู้ใช้
$savedUser = $_COOKIE['eak_user'] ?? '';

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
foreach (['logo.png', 'logo.svg', 'logo.jpg', 'logo.jpeg', 'logo.webp'] as $lf) {
    if (file_exists($bgDir . $lf)) { $logoFile = $bgDir . $lf; break; }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ · <?= htmlspecialchars(ORG_NAME) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --red:       #E11500;
            --red-2:     #FF3B2E;
            --red-deep:  #A80800;
            --ink:       #08080B;
            --card:      #FFFFFF;
            --card-line: #ECEDF1;
            --field:     #F3F4F7;
            --field-line:#E3E5EA;
            --txt:       #16171D;
            --txt-2:     #6B6E7B;
            --txt-3:     #9A9DA9;
            --glow:      225,21,0;
        }
        html { color-scheme: dark; }
        body {
            font-family: 'Kanit', sans-serif;
            color: var(--txt);
            background: var(--ink);
            min-height: 100vh;
            -webkit-font-smoothing: antialiased;
            overflow-x: hidden;
        }

        /* ══════════ ฉากพื้นหลังมืด-แดง ══════════ */
        .bg-scene {
            position: fixed; inset: 0; z-index: 0; overflow: hidden;
            background:
                radial-gradient(70% 55% at 88% 6%, rgba(255,59,46,0.28), transparent 60%),
                radial-gradient(60% 60% at 6% 96%, rgba(225,21,0,0.22), transparent 62%),
                radial-gradient(120% 90% at 50% 40%, #14141A 0%, #0B0B0F 55%, #060608 100%);
        }
        <?php if ($bgImage): ?>
        .bg-scene {
            background:
                linear-gradient(180deg, rgba(8,8,11,0.78), rgba(8,8,11,0.86)),
                radial-gradient(70% 55% at 88% 6%, rgba(255,59,46,0.30), transparent 60%),
                radial-gradient(60% 60% at 6% 96%, rgba(225,21,0,0.24), transparent 62%),
                url('<?= htmlspecialchars($bgImage) ?>') center/cover no-repeat;
        }
        <?php endif; ?>
        /* เส้นแสงแดงทแยง */
        .bg-lines { position: absolute; inset: -25%; pointer-events: none; mix-blend-mode: screen; opacity: 0.9;
            background:
                linear-gradient(122deg, transparent 61.4%, rgba(255,70,42,0.85) 62.2%, rgba(255,120,90,0.2) 62.8%, transparent 63.4%),
                linear-gradient(122deg, transparent 66%, rgba(225,21,0,0.55) 66.7%, transparent 67.4%),
                linear-gradient(122deg, transparent 17%, rgba(255,60,35,0.55) 18%, transparent 18.7%),
                linear-gradient(122deg, transparent 11%, rgba(225,21,0,0.35) 12%, transparent 12.7%);
            filter: blur(0.4px);
        }
        .bg-grain { position: absolute; inset: 0; pointer-events: none; opacity: 0.5;
            background-image: radial-gradient(circle, rgba(255,255,255,0.045) 1px, transparent 1px);
            background-size: 30px 30px; }
        .bg-vignette { position: absolute; inset: 0; pointer-events: none;
            background: radial-gradient(120% 100% at 50% 50%, transparent 55%, rgba(0,0,0,0.55) 100%); }

        /* ══════════ เลย์เอาต์หน้า ══════════ */
        .page {
            position: relative; z-index: 2;
            min-height: 100vh;
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            padding: 92px 20px 84px;
        }
        .brand-logo { margin-bottom: 26px; text-align: center; }
        .brand-logo img { max-height: 84px; max-width: 300px; display: block; margin: 0 auto;
            filter: drop-shadow(0 8px 26px rgba(0,0,0,0.55)); }
        .brand-logo .wordmark { font-size: 30px; font-weight: 800; letter-spacing: 0.16em; color: #fff; }
        .brand-logo .wordmark span { color: var(--red-2); }

        /* ══════════ การ์ดขาว ══════════ */
        .login-card {
            position: relative;
            width: 100%; max-width: 468px;
            background: var(--card);
            border-radius: 24px;
            padding: 44px 46px 40px;
            box-shadow: 0 40px 90px -30px rgba(0,0,0,0.75), 0 0 0 1px rgba(255,255,255,0.04);
            overflow: hidden;
        }
        .login-card::before {
            content: ''; position: absolute; top: 0; left: 0; right: 0; height: 5px;
            background: linear-gradient(90deg, var(--red-deep), var(--red), var(--red-2));
        }

        .lc-head { text-align: center; margin-bottom: 30px; }
        .lc-head h1 { font-size: 27px; font-weight: 700; color: var(--txt); letter-spacing: -0.01em; }
        .lc-head h1 .accent { color: var(--red); }
        .lc-head p { font-size: 14px; color: var(--txt-2); margin-top: 6px; }

        .field { margin-bottom: 18px; }
        .field label { display: block; font-size: 13px; font-weight: 600; color: var(--txt); margin-bottom: 8px; }
        .input-wrap { position: relative; }
        .input-wrap .lead-ic {
            position: absolute; left: 15px; top: 50%; transform: translateY(-50%);
            width: 18px; height: 18px; color: var(--txt-3); pointer-events: none;
        }
        .field input {
            width: 100%; padding: 14px 16px 14px 45px;
            border: 1.5px solid var(--field-line); border-radius: 13px;
            background: var(--field); color: var(--txt);
            font-family: 'Kanit', sans-serif; font-size: 14.5px;
            transition: border-color 0.16s, box-shadow 0.16s, background 0.16s;
        }
        .field input::placeholder { color: var(--txt-3); }
        .field input:focus {
            outline: none; border-color: var(--red); background: #fff;
            box-shadow: 0 0 0 4px rgba(var(--glow),0.12);
        }
        .input-wrap:focus-within .lead-ic { color: var(--red); }
        /* แก้พื้นหลังสีเทาจาก autofill ของเบราว์เซอร์ ให้คงสีดีไซน์เดิม */
        .field input:-webkit-autofill,
        .field input:-webkit-autofill:hover,
        .field input:-webkit-autofill:focus,
        .field input:-webkit-autofill:active {
            -webkit-text-fill-color: var(--txt);
            -webkit-box-shadow: 0 0 0 1000px var(--field) inset;
            box-shadow: 0 0 0 1000px var(--field) inset;
            caret-color: var(--txt);
            transition: background-color 9999s ease-in-out 0s;
        }
        .field input:-webkit-autofill:focus {
            -webkit-box-shadow: 0 0 0 1000px #fff inset, 0 0 0 4px rgba(var(--glow),0.12);
            box-shadow: 0 0 0 1000px #fff inset, 0 0 0 4px rgba(var(--glow),0.12);
        }
        .toggle-pw {
            position: absolute; right: 8px; top: 50%; transform: translateY(-50%);
            background: none; border: none; cursor: pointer; padding: 9px; color: var(--txt-3);
            display: flex; border-radius: 9px; transition: color 0.15s, background 0.15s;
        }
        .toggle-pw:hover { color: var(--txt); background: rgba(0,0,0,0.05); }
        .toggle-pw svg { width: 19px; height: 19px; }

        /* แถว remember / forgot */
        .row-between {
            display: flex; align-items: center; justify-content: space-between;
            gap: 12px; margin: 6px 0 22px;
        }
        .remember { display: inline-flex; align-items: center; gap: 9px; cursor: pointer; user-select: none; }
        .remember input {
            appearance: none; -webkit-appearance: none;
            width: 19px; height: 19px; border: 1.5px solid var(--field-line); border-radius: 6px;
            background: var(--field); cursor: pointer; position: relative; transition: all 0.15s; flex-shrink: 0;
        }
        .remember input:checked { background: var(--red); border-color: var(--red); }
        .remember input:checked::after {
            content: ''; position: absolute; left: 6px; top: 2px;
            width: 5px; height: 10px; border: solid #fff; border-width: 0 2px 2px 0; transform: rotate(45deg);
        }
        .remember span { font-size: 13.5px; color: var(--txt-2); }
        .forgot { font-size: 13.5px; font-weight: 600; color: var(--red); text-decoration: none; cursor: pointer; }
        .forgot:hover { text-decoration: underline; }
        .forgot-hint {
            font-size: 12.5px; color: var(--txt-2); background: var(--field);
            border: 1px solid var(--field-line); border-radius: 10px; padding: 10px 13px;
            margin: -12px 0 20px; line-height: 1.6;
        }

        .btn-login {
            width: 100%; padding: 15px; border: none; border-radius: 13px;
            background: linear-gradient(135deg, var(--red) 0%, var(--red-deep) 100%);
            color: #fff; font-family: 'Kanit', sans-serif; font-size: 15.5px; font-weight: 700;
            letter-spacing: 0.02em; cursor: pointer; position: relative; overflow: hidden;
            box-shadow: 0 14px 30px -12px rgba(var(--glow),0.7), inset 0 1px 0 rgba(255,255,255,0.22);
            transition: transform 0.12s, box-shadow 0.2s, filter 0.2s;
        }
        .btn-login::after {
            content: ''; position: absolute; top: 0; left: -120%; width: 60%; height: 100%;
            background: linear-gradient(105deg, transparent, rgba(255,255,255,0.35), transparent);
            transform: skewX(-18deg); transition: left 0.6s ease;
        }
        .btn-login:hover { filter: brightness(1.06); box-shadow: 0 18px 40px -12px rgba(var(--glow),0.85); }
        .btn-login:hover::after { left: 130%; }
        .btn-login:active { transform: translateY(1px); }
        .btn-login .arrow { margin-left: 7px; }

        .alert-error {
            display: flex; align-items: center; gap: 10px;
            background: rgba(225,21,0,0.06); color: #C11500;
            border: 1px solid rgba(225,21,0,0.22);
            padding: 13px 15px; border-radius: 12px; font-size: 13.5px; margin-bottom: 20px;
            animation: shake 0.4s ease;
        }
        .alert-error svg { width: 18px; height: 18px; flex-shrink: 0; }
        @keyframes shake { 0%,100%{transform:translateX(0)} 25%{transform:translateX(-5px)} 75%{transform:translateX(5px)} }

        /* ══════════ footer ══════════ */
        .page-foot {
            position: fixed; z-index: 2; bottom: 0; left: 0; right: 0;
            display: flex; align-items: center; justify-content: space-between;
            padding: 18px 34px; font-size: 12.5px;
        }
        .page-foot .dev-link {
            display: inline-flex; align-items: center; gap: 7px;
            color: rgba(255,255,255,0.72); text-decoration: none; font-weight: 500;
            padding: 7px 14px; border-radius: 10px; transition: background 0.15s, color 0.15s;
        }
        .page-foot .dev-link:hover { background: rgba(255,255,255,0.08); color: #fff; }
        .page-foot .dev-link svg { width: 15px; height: 15px; }
        .page-foot .copy { color: rgba(255,255,255,0.5); }
        .page-foot .copy b { color: rgba(255,255,255,0.75); font-weight: 600; }

        @media (max-width: 560px) {
            .page { padding: 60px 16px 96px; }
            .login-card { padding: 34px 26px 30px; }
            .brand-logo img { max-height: 64px; }
            .page-foot { flex-direction: column-reverse; gap: 6px; padding: 14px; text-align: center; }
        }
    </style>
</head>
<body>
    <!-- ฉากพื้นหลัง -->
    <div class="bg-scene">
        <div class="bg-lines"></div>
        <div class="bg-grain"></div>
        <div class="bg-vignette"></div>
    </div>

    <div class="page">
        <!-- โลโก้ -->
        <div class="brand-logo">
            <?php if ($logoFile): ?>
                <img src="<?= htmlspecialchars($logoFile) ?>" alt="<?= htmlspecialchars(ORG_NAME) ?>">
            <?php else: ?>
                <span class="wordmark">EAKSAHA<span>GROUP</span></span>
            <?php endif; ?>
        </div>

        <!-- การ์ดเข้าสู่ระบบ -->
        <div class="login-card">
            <div class="lc-head">
                <h1>เข้าสู่ระบบ <span class="accent">Eaksaha Rating</span></h1>
                <p>ระบบประเมินความพึงพอใจพนักงานขายรถ EV</p>
            </div>

            <?php if ($error): ?>
                <div class="alert-error">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="field">
                    <label for="username">ชื่อผู้ใช้</label>
                    <div class="input-wrap">
                        <input type="text" id="username" name="username" placeholder="กรอกชื่อผู้ใช้"
                               value="<?= htmlspecialchars($savedUser) ?>" required <?= $savedUser ? '' : 'autofocus' ?>>
                        <svg class="lead-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    </div>
                </div>

                <div class="field">
                    <label for="password">รหัสผ่าน</label>
                    <div class="input-wrap">
                        <input type="password" id="password" name="password" placeholder="กรอกรหัสผ่าน" required <?= $savedUser ? 'autofocus' : '' ?>>
                        <svg class="lead-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        <button type="button" class="toggle-pw" id="togglePw" aria-label="แสดง/ซ่อนรหัสผ่าน">
                            <svg id="eyeIcon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                </div>

                <div class="row-between">
                    <label class="remember">
                        <input type="checkbox" name="remember" value="1" <?= $remember ? 'checked' : '' ?>>
                        <span>จดจำการเข้าสู่ระบบ</span>
                    </label>
                    <a class="forgot" id="forgot">ลืมรหัสผ่าน?</a>
                </div>
                <div class="forgot-hint" id="forgotHint" style="display:none;">
                    หากลืมรหัสผ่าน กรุณาติดต่อผู้ดูแลระบบหลักเพื่อขอรีเซ็ตรหัสผ่านใหม่
                </div>

                <button type="submit" class="btn-login">เข้าสู่ระบบ<span class="arrow">→</span></button>
            </form>
        </div>
    </div>

    <!-- footer -->
    <footer class="page-foot">
        <a class="dev-link" href="developer.php">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>
            ทีมผู้พัฒนา (Developers)
        </a>
        <span class="copy">&copy; <?= date('Y') ?> <b><?= htmlspecialchars(ORG_NAME) ?></b> · All Rights Reserved.</span>
    </footer>

    <script>
        (function () {
            // toggle รหัสผ่าน
            var btn = document.getElementById('togglePw');
            var pw  = document.getElementById('password');
            var eye = document.getElementById('eyeIcon');
            var open = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
            var off  = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>';
            if (btn) btn.addEventListener('click', function () {
                var show = pw.type === 'password';
                pw.type = show ? 'text' : 'password';
                eye.innerHTML = show ? off : open;
            });
            // ลืมรหัสผ่าน
            var f = document.getElementById('forgot');
            var h = document.getElementById('forgotHint');
            if (f) f.addEventListener('click', function () {
                h.style.display = h.style.display === 'none' ? 'block' : 'none';
            });
        })();
    </script>
</body>
</html>
