<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'หลังบ้าน') ?> · <?= htmlspecialchars(ORG_NAME) ?></title>
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

            <div class="logout">
                <a href="logout.php">⏻ ออกจากระบบ (<?= htmlspecialchars($_SESSION['admin_username'] ?? '') ?>)</a>
            </div>
        </nav>
    </aside>
    <main class="main">
