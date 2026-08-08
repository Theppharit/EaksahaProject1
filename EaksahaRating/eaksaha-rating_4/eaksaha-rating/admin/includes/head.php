<?php
// ── โลโก้ (ใช้ไฟล์เดียวกับหน้า login) ──
$logoDir   = __DIR__ . '/../uploads/login/';
$adminLogo = '';
foreach (['logo.png', 'logo.svg', 'logo.jpg', 'logo.jpeg', 'logo.webp'] as $lf) {
    if (file_exists($logoDir . $lf)) { $adminLogo = 'uploads/login/' . $lf; break; }
}
// โลโก้ตัวอักษรเข้ม สำหรับธีมสว่าง (ถ้าไม่มีจะใช้ตัวเดิม)
$adminLogoLight = file_exists($logoDir . 'logo-dark.png') ? 'uploads/login/logo-dark.png' : $adminLogo;

$cssVer = @filemtime(__DIR__ . '/../assets/admin.css') ?: time();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'หลังบ้าน') ?> · <?= htmlspecialchars(ORG_NAME) ?></title>
    <script>
        // ใส่ธีมก่อนวาดหน้า เพื่อไม่ให้เกิดอาการกระพริบ
        (function () {
            try {
                var t = localStorage.getItem('eaksaha_admin_theme') || 'light';
                document.documentElement.setAttribute('data-theme', t);
            } catch (e) {}
        })();
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="assets/admin.css?v=<?= $cssVer ?>">
</head>
<body>
<div class="admin-layout">
    <input type="checkbox" id="navToggle" class="nav-toggle">
    <label for="navToggle" class="nav-burger" aria-label="เมนู">
        <span></span><span></span><span></span>
    </label>

    <aside class="sidebar">
        <div class="brand">
            <a href="dashboard.php" class="brand-logo-wrap">
                <?php if ($adminLogo): ?>
                    <img src="<?= htmlspecialchars($adminLogoLight) ?>" alt="<?= htmlspecialchars(ORG_NAME) ?>" class="brand-logo-img for-light">
                    <img src="<?= htmlspecialchars($adminLogo) ?>" alt="<?= htmlspecialchars(ORG_NAME) ?>" class="brand-logo-img for-dark">
                <?php else: ?>
                    <div class="brand-logo">EAKSAHA<span>GROUP</span></div>
                <?php endif; ?>
            </a>
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

            <div class="theme-switch" role="group" aria-label="เลือกธีมสี">
                <button type="button" data-theme-set="light">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="4.5"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
                    สว่าง
                </button>
                <button type="button" data-theme-set="dark">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg>
                    มืด
                </button>
            </div>

            <div class="logout">
                <a href="logout.php">⏻ ออกจากระบบ (<?= htmlspecialchars($_SESSION['admin_username'] ?? '') ?>)</a>
            </div>
        </nav>
    </aside>

    <script>
    (function () {
        var KEY = 'eaksaha_admin_theme';
        var btns = document.querySelectorAll('[data-theme-set]');
        function mark(t) {
            btns.forEach(function (b) { b.classList.toggle('on', b.dataset.themeSet === t); });
        }
        mark(document.documentElement.getAttribute('data-theme') || 'light');
        btns.forEach(function (b) {
            b.addEventListener('click', function () {
                var t = b.dataset.themeSet;
                if (t === document.documentElement.getAttribute('data-theme')) return;
                document.documentElement.setAttribute('data-theme', t);
                try { localStorage.setItem(KEY, t); } catch (e) {}
                mark(t);
                // กราฟอ่านค่าสีตอนวาดครั้งเดียว — ถ้าหน้านี้มีกราฟ ให้โหลดใหม่เพื่อให้สีตรงธีม
                if (document.querySelector('canvas')) { location.reload(); }
            });
        });
    })();

    // ── (3) คำใบ้ + เงาบอกขอบ สำหรับตารางที่กว้างเกินจอ ──
    (function () {
        var ARROWS = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"'
            + ' stroke-linecap="round" stroke-linejoin="round">'
            + '<line x1="3" y1="12" x2="21" y2="12"/>'
            + '<polyline points="7 8 3 12 7 16"/><polyline points="17 8 21 12 17 16"/></svg>';
        function sync() {
            document.querySelectorAll('.table-card').forEach(function (card) {
                var over = card.scrollWidth - card.clientWidth > 8;
                var hint = card.previousElementSibling;
                var has  = hint && hint.classList.contains('table-scroll-hint');
                if (over && !has) {
                    var d = document.createElement('div');
                    d.className = 'table-scroll-hint';
                    d.innerHTML = ARROWS + '<span>เลื่อนตารางแนวนอนเพื่อดูคอลัมน์ที่เหลือ</span>';
                    card.parentNode.insertBefore(d, card);
                    card.addEventListener('scroll', function () { d.classList.add('is-done'); }, { once: true });
                } else if (!over && has) {
                    hint.remove();
                }
            });
        }
        window.addEventListener('load', sync);
        window.addEventListener('resize', sync);
        document.addEventListener('DOMContentLoaded', sync);
    })();

    // ── (5) แสดงชื่อไฟล์ที่เลือกในช่องเลือกไฟล์แบบจัดสไตล์เอง ──
    document.addEventListener('change', function (e) {
        var inp = e.target;
        if (!inp.matches || !inp.matches('.file-field input[type="file"]')) return;
        var box = inp.closest('.file-field').querySelector('.file-name');
        if (!box) return;
        if (inp.files && inp.files.length) {
            box.textContent = inp.files[0].name;
            box.classList.add('has-file');
        } else {
            box.textContent = box.dataset.empty || 'ยังไม่ได้เลือกไฟล์';
            box.classList.remove('has-file');
        }
    });
    </script>

    <main class="main">
