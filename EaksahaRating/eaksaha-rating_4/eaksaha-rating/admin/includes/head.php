<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'หลังบ้าน') ?> · <?= htmlspecialchars(ORG_NAME) ?></title>
    <script>
        // โหลดธีมที่ผู้ใช้เลือกไว้ ก่อนหน้าเว็บวาดสี (กันจอกะพริบ)
        (function () {
            try {
                var t = localStorage.getItem('eaksaha_theme');
                if (t && ['fresh', 'soft', 'dark-red', 'midnight'].indexOf(t) !== -1 && t !== 'fresh') {
                    document.documentElement.setAttribute('data-theme', t);
                }
            } catch (e) { /* localStorage ถูกปิด — ใช้ธีมเริ่มต้น */ }
        })();
    </script>
    <link rel="stylesheet" href="assets/admin.css">
</head>
<body>
<div class="admin-layout">
    <input type="checkbox" id="navToggle" class="nav-toggle">
    <label for="navToggle" class="nav-burger" aria-label="เมนู">
        <span></span><span></span><span></span>
    </label>

    <aside class="sidebar">
        <div class="brand">
            <div class="brand-logo">EAKSAHA<span>GROUP</span></div>
            <small>ระบบประเมินความพึงพอใจเซลล์ EV</small>
        </div>
        <nav>
            <a href="dashboard.php" class="<?= ($activePage ?? '') === 'dashboard' ? 'active' : '' ?>">
                <span class="nav-ic">▚</span> แดชบอร์ด
            </a>
            <a href="report.php" class="<?= ($activePage ?? '') === 'report' ? 'active' : '' ?>">
                <span class="nav-ic">▤</span> รายงานคะแนน
            </a>
            <a href="staff.php" class="<?= ($activePage ?? '') === 'staff' ? 'active' : '' ?>">
                <span class="nav-ic">☰</span> พนักงานขาย
            </a>
            <a href="brands.php" class="<?= ($activePage ?? '') === 'brands' ? 'active' : '' ?>">
                <span class="nav-ic">◈</span> แบรนด์รถ
            </a>
            <a href="change_password.php" class="<?= ($activePage ?? '') === 'password' ? 'active' : '' ?>">
                <span class="nav-ic">⚿</span> เปลี่ยนรหัสผ่าน
            </a>
            <a href="manual.php" class="<?= ($activePage ?? '') === 'manual' ? 'active' : '' ?>">
                <span class="nav-ic">❓</span> คู่มือการใช้งาน
            </a>

            <!-- ปุ่มเลือกธีมสี -->
            <div class="theme-picker">
                <div class="tp-label">ธีมสี</div>
                <div class="tp-row">
                    <button type="button" class="tp-dot tp-fresh"    data-theme-pick="fresh"    title="สดใส (ฟ้า-มิ้นต์)"></button>
                    <button type="button" class="tp-dot tp-soft"     data-theme-pick="soft"     title="นุ่มสบายตา"></button>
                    <button type="button" class="tp-dot tp-dark-red" data-theme-pick="dark-red" title="ดำ-แดง Eaksaha"></button>
                    <button type="button" class="tp-dot tp-midnight" data-theme-pick="midnight" title="มืดน้ำเงิน"></button>
                </div>
            </div>

            <div class="logout">
                <a href="logout.php">⏻ ออกจากระบบ (<?= htmlspecialchars($_SESSION['admin_username'] ?? '') ?>)</a>
            </div>
        </nav>
    </aside>

    <script>
        // สลับธีม: บันทึกค่า แล้วโหลดหน้าใหม่เพื่อให้กราฟใช้สีธีมด้วย
        (function () {
            var current = document.documentElement.getAttribute('data-theme') || 'fresh';
            document.querySelectorAll('[data-theme-pick]').forEach(function (btn) {
                if (btn.getAttribute('data-theme-pick') === current) btn.classList.add('active');
                btn.addEventListener('click', function () {
                    var t = btn.getAttribute('data-theme-pick');
                    try { localStorage.setItem('eaksaha_theme', t); } catch (e) {}
                    if (t === 'fresh') document.documentElement.removeAttribute('data-theme');
                    else document.documentElement.setAttribute('data-theme', t);
                    location.reload();
                });
            });
        })();
    </script>

    <main class="main">
