<?php
require_once __DIR__ . '/flash.php';

// ── โลโก้ (ใช้ไฟล์เดียวกับหน้า login) ──
$logoDir   = __DIR__ . '/../uploads/login/';
$adminLogo = '';
foreach (['logo.png', 'logo.svg', 'logo.jpg', 'logo.jpeg', 'logo.webp'] as $lf) {
    if (file_exists($logoDir . $lf)) { $adminLogo = 'uploads/login/' . $lf; break; }
}
// โลโก้ตัวอักษรเข้ม สำหรับธีมสว่าง (ถ้าไม่มีจะใช้ตัวเดิม)
$adminLogoLight = file_exists($logoDir . 'logo-dark.png') ? 'uploads/login/logo-dark.png' : $adminLogo;

$cssVer = @filemtime(__DIR__ . '/../assets/admin.css') ?: time();

// ── จำนวนโน้ตที่ยังไม่ได้อ่าน (เฉพาะบัญชีพนักงานขาย) ──
// ใช้ขึ้นตัวเลขแดงข้างเมนู เพื่อให้รู้ว่ามีเรื่องที่หัวหน้าฝากไว้
$unreadNotes = 0;
if (user_role() === 'sales' && user_staff_id() !== null && isset($pdo)) {
    try {
        $q = $pdo->prepare('SELECT COUNT(*) FROM review_notes WHERE staff_id = ? AND is_read = 0');
        $q->execute([user_staff_id()]);
        $unreadNotes = (int) $q->fetchColumn();
    } catch (PDOException $e) {
        $unreadNotes = 0;   // ยังไม่ได้รัน roles_migration.sql
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars(csrf_token()) ?>">
    <title><?= htmlspecialchars($pageTitle ?? 'หลังบ้าน') ?> · <?= htmlspecialchars(SYS_CODE) ?></title>
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
            <a href="dashboard.php" class="brand-logo-wrap" aria-label="กลับไปหน้าแดชบอร์ด · <?= htmlspecialchars(ORG_NAME) ?>">
                <?php if ($adminLogo): ?>
                    <img src="<?= htmlspecialchars($adminLogoLight) ?>" alt="<?= htmlspecialchars(ORG_NAME) ?>" class="brand-logo-img for-light">
                    <img src="<?= htmlspecialchars($adminLogo) ?>" alt="<?= htmlspecialchars(ORG_NAME) ?>" class="brand-logo-img for-dark">
                <?php else: ?>
                    <div class="brand-logo">EAKSAHA<span>GROUP</span></div>
                <?php endif; ?>
            </a>
            <small><?= htmlspecialchars(SYS_CODE) ?> · <?= htmlspecialchars(SYS_DESC) ?></small>
        </div>

        <?php
        // ── ป้ายบอกว่ากำลังใช้งานในนามใคร ──
        // ย้ายมาไว้บนสุด เพราะเป็นสิ่งแรกที่ต้องรู้ก่อนตัดสินใจอะไร
        // โดยเฉพาะเครื่องที่ใช้ร่วมกันหลายคน จะได้ไม่เผลอทำงานผิดบัญชี
        $ucName    = user_display();
        $ucInitial = mb_strtoupper(mb_substr(trim($ucName), 0, 1, 'UTF-8'), 'UTF-8');
        ?>
        <div class="user-card role-<?= htmlspecialchars(user_role()) ?>">
            <div class="uc-avatar" aria-hidden="true"><?= htmlspecialchars($ucInitial) ?></div>
            <div class="uc-body">
                <div class="uc-name" title="<?= htmlspecialchars($ucName) ?>"><?= htmlspecialchars($ucName) ?></div>
                <div class="uc-role"><span class="uc-dot" aria-hidden="true"></span><?= htmlspecialchars(role_label()) ?></div>
            </div>
        </div>
        <nav>
            <?php
            // ── เมนูขึ้นตามสิทธิ์ ──
            // พนักงานขายเห็นแค่ 2 เมนูแรก ที่เหลือไม่ต้องรู้ว่ามีอยู่ด้วยซ้ำ
            $ap = $activePage ?? '';
            ?>

            <?php if (user_role() === 'sales'): ?>
                <a href="my_reviews.php" class="<?= $ap === 'my_reviews' ? 'active' : '' ?>">
                    <span class="nav-ic" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2l2.9 6.26L21.6 9l-4.8 4.68 1.13 6.6L12 17.27 6.07 20.28l1.13-6.6L2.4 9l6.7-.74L12 2z"/></svg></span> คะแนนของฉัน
                </a>
                <a href="my_notes.php" class="<?= $ap === 'my_notes' ? 'active' : '' ?>">
                    <span class="nav-ic" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/></svg></span> ข้อความจากหัวหน้า
                    <?php if ($unreadNotes > 0): ?><span class="nav-badge"><?= $unreadNotes ?></span><?php endif; ?>
                </a>
            <?php endif; ?>

            <?php if (can('view_all')): ?>
                <a href="dashboard.php" class="<?= $ap === 'dashboard' ? 'active' : '' ?>">
                    <span class="nav-ic" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/></svg></span> แดชบอร์ด
                </a>
                <a href="report.php" class="<?= $ap === 'report' ? 'active' : '' ?>">
                    <span class="nav-ic" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg></span> รายการรีวิว
                </a>
                <a href="staff_scores.php" class="<?= $ap === 'staff_scores' ? 'active' : '' ?>">
                    <span class="nav-ic" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><line x1="4" y1="20" x2="4" y2="12"/><line x1="10" y1="20" x2="10" y2="4"/><line x1="16" y1="20" x2="16" y2="9"/><line x1="21" y1="20" x2="21" y2="15"/></svg></span> คะแนนรายพนักงาน
                </a>
            <?php endif; ?>

            <?php if (can('manage_staff')): ?>
                <a href="staff.php" class="<?= $ap === 'staff' ? 'active' : '' ?>">
                    <span class="nav-ic" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></span> พนักงานขาย
                </a>
            <?php endif; ?>

            <?php if (can('manage_brands')): ?>
                <a href="brands.php" class="<?= $ap === 'brands' ? 'active' : '' ?>">
                    <span class="nav-ic" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M5 17h14M5 17a2 2 0 0 1-2-2v-3l2-5a2 2 0 0 1 1.9-1.4h10.2A2 2 0 0 1 19 7l2 5v3a2 2 0 0 1-2 2"/><circle cx="7.5" cy="17" r="1.8"/><circle cx="16.5" cy="17" r="1.8"/></svg></span> แบรนด์รถ
                </a>
            <?php endif; ?>

            <?php if (can('manage_users')): ?>
                <a href="notice.php" class="<?= $ap === 'notice' ? 'active' : '' ?>">
                    <span class="nav-ic" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/></svg></span> ประกาศระบบ
                </a>
                <a href="users.php" class="<?= $ap === 'users' ? 'active' : '' ?>">
                    <span class="nav-ic" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9.5" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg></span> ผู้ใช้งานระบบ
                </a>
            <?php endif; ?>

            <a href="change_password.php" class="<?= $ap === 'password' ? 'active' : '' ?>">
                <span class="nav-ic" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="15" r="4"/><path d="M10.8 12.2 20 3"/><path d="m17 6 2.5 2.5"/><path d="m14.5 8.5 2.5 2.5"/></svg></span> เปลี่ยนรหัสผ่าน
            </a>

            <?php if (can('ai_test')): ?>
                <a href="ai_test.php" class="<?= $ap === 'aitest' ? 'active' : '' ?>">
                    <span class="nav-ic" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v3M12 18v3M3 12h3M18 12h3M5.6 5.6l2.1 2.1M16.3 16.3l2.1 2.1M5.6 18.4l2.1-2.1M16.3 7.7l2.1-2.1"/><circle cx="12" cy="12" r="3.5"/></svg></span> ทดสอบ AI ให้ดาว
                </a>
            <?php endif; ?>

            <a href="manual.php" class="<?= $ap === 'manual' ? 'active' : '' ?>">
                <span class="nav-ic" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M2 4.5A2.5 2.5 0 0 1 4.5 2H10a2 2 0 0 1 2 2v16a1.5 1.5 0 0 0-1.5-1.5H4.5A2.5 2.5 0 0 1 2 16z"/><path d="M22 4.5A2.5 2.5 0 0 0 19.5 2H14a2 2 0 0 0-2 2v16a1.5 1.5 0 0 1 1.5-1.5h6A2.5 2.5 0 0 0 22 16z"/></svg></span> คู่มือการใช้งาน
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
                <a href="logout.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg><span>ออกจากระบบ</span></a>
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

    <!-- ═══ popup แจ้งผล — เด้งมุมขวาบน ═══ -->
    <div class="toast-wrap" id="toastWrap" role="status" aria-live="polite"></div>
    <script>
    (function () {
        var wrap = document.getElementById('toastWrap');
        var ICONS = {
            success: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>',
            error:   '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>',
            info:    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><line x1="12" y1="11" x2="12" y2="16"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>'
        };

        // ── ตัวช่วยเรียก fetch ที่แนบ CSRF token ให้เอง ──
        // ใช้แทน fetch ปกติทุกที่ที่ส่งข้อมูลกลับเซิร์ฟเวอร์
        var CSRF = document.querySelector('meta[name="csrf-token"]');
        window.postJSON = function (url, params) {
            var body = new URLSearchParams(params || {});
            return fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-CSRF-Token': CSRF ? CSRF.content : ''
                },
                body: body.toString()
            }).then(function (r) { return r.json(); });
        };

        // เรียกได้จากทุกที่: toast('success', 'บันทึกแล้ว', 'รายละเอียด', {text:'ดู', href:'#x'})
        window.toast = function (type, title, msg, action) {
            if (!wrap) return;
            type = ICONS[type] ? type : 'info';

            var el = document.createElement('div');
            el.className = 'toast toast-' + type;

            var html = '<span class="t-ic" aria-hidden="true">' + ICONS[type] + '</span><div class="t-body">'
                     + '<div class="t-title"></div>';
            if (msg)    html += '<div class="t-msg"></div>';
            if (action && action.text) html += '<a class="t-act" href="#"></a>';
            html += '</div><button type="button" class="t-x" aria-label="ปิด">&times;</button>';
            el.innerHTML = html;

            // ใส่ข้อความด้วย textContent เสมอ กัน HTML แปลกปลอมจากชื่อที่ผู้ใช้พิมพ์
            el.querySelector('.t-title').textContent = title || '';
            if (msg) el.querySelector('.t-msg').textContent = msg;
            if (action && action.text) {
                var a = el.querySelector('.t-act');
                a.textContent = action.text;
                a.setAttribute('href', action.href || '#');
            }

            function close() {
                el.classList.add('is-out');
                setTimeout(function () { el.remove(); }, 220);
            }
            el.querySelector('.t-x').addEventListener('click', close);

            // ข้อความผิดพลาดค้างนานกว่า เพราะต้องอ่านและแก้ตาม
            var life = type === 'error' ? 9000 : 6000;
            var timer = setTimeout(close, life);
            // เอาเมาส์ชี้ค้างไว้ = หยุดนับถอยหลัง จะได้อ่านทัน
            el.addEventListener('mouseenter', function () { clearTimeout(timer); });
            el.addEventListener('mouseleave', function () { timer = setTimeout(close, 2500); });

            wrap.appendChild(el);
        };

        // ข้อความที่ฝั่งเซิร์ฟเวอร์ฝากไว้ (หลังกดบันทึกแล้ว redirect มา)
        var queued = <?= json_encode(flash_take(), JSON_UNESCAPED_UNICODE) ?>;
        queued.forEach(function (f, i) {
            setTimeout(function () {
                window.toast(f.type, f.title, f.msg, f.action && f.action.text ? f.action : null);
            }, i * 180);
        });

        // ไฮไลต์แถวที่เพิ่งเพิ่ม/แก้ไข เพื่อให้เห็นทันทีว่าไปอยู่ตรงไหน
        var hl = new URLSearchParams(location.search).get('hl');
        if (hl) {
            window.addEventListener('load', function () {
                var row = document.getElementById('row-' + hl);
                if (!row) return;
                row.classList.add('row-new');
                row.scrollIntoView({ behavior: 'smooth', block: 'center' });
                setTimeout(function () { row.classList.remove('row-new'); }, 3200);
            });
        }
    })();
    </script>

    <main class="main">
    <?php
    // แถบประกาศ/แจ้งเตือน — อยู่บนสุดของทุกหน้า ก่อนหัวข้อหน้า
    // วางไว้ที่นี่จุดเดียว หน้าใหม่ที่เพิ่มทีหลังจะได้ตามไปเองโดยไม่ต้องแก้อะไร
    require_once __DIR__ . '/notice.php';
    echo notice_bar($pdo ?? null, $_SESSION['admin_role'] ?? 'sales');
    ?>
