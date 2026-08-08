<?php
require '../conn/config.php';
require 'includes/auth.php';
// ทุกคำขอที่เปลี่ยนแปลงข้อมูลต้องมาจากหน้าจอของเราจริง
csrf_check();

require 'includes/flash.php';

require_perm('manage_staff');
$pageTitle  = 'พนักงานและผู้ใช้งาน';
$activePage = 'staff';

// ══════════════════════════════════════════════════════════
//  หน้านี้รวม "พนักงานขาย" กับ "ผู้ใช้งานระบบ" ไว้ด้วยกัน
//  ------------------------------------------------------------
//  เจ้าของงานสั่งไว้ (2026-08-08) ว่าเมนูหลังบ้านรกเกินไป
//  และการเพิ่มพนักงาน 1 คนต้องไปกรอก 2 หน้าซ้ำกันสองรอบ
//
//  ตอนนี้: กรอกครั้งเดียวได้ทั้งข้อมูลพนักงาน (มี QR ให้ลูกค้าประเมิน)
//  และบัญชีล็อกอินของคนนั้น (ถ้าต้องการให้เขาเข้ามาดูคะแนนตัวเองได้)
//
//  ยังต้องรองรับบัญชีที่ "ไม่ใช่พนักงานขาย" ด้วย (ผู้ดูแล/ผู้จัดการ)
//  จึงแยกเป็นอีกส่วนล่างสุดของหน้า พับเก็บไว้ ไม่ต้องเห็นทุกครั้ง
// ══════════════════════════════════════════════════════════

$message = '';
$messageType = '';

$uploadDir = 'uploads/staff/';
$roleNames = ['admin' => 'ผู้ดูแลระบบ', 'manager' => 'ผู้จัดการ', 'sales' => 'พนักงานขาย'];
$meId      = (int) ($_SESSION['admin_id'] ?? 0);

/** จำนวน admin ที่ยังใช้งานได้ (ไม่นับ id ที่กำลังจะแก้/ลบ) */
function activeAdminCount(PDO $pdo, int $exceptId = 0): int
{
    $st = $pdo->prepare("SELECT COUNT(*) FROM admin_users WHERE role = 'admin' AND is_active = 1 AND id <> ?");
    $st->execute([$exceptId]);
    return (int) $st->fetchColumn();
}

// ----- ลบพนักงานขาย -----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $id = (int) ($_POST['id'] ?? 0);

    $stmt = $pdo->prepare('SELECT photo FROM staff WHERE id = ?');
    $stmt->execute([$id]);
    $old = $stmt->fetch();
    if ($old && $old['photo'] && file_exists($uploadDir . $old['photo'])) {
        unlink($uploadDir . $old['photo']);
    }

    // ลบบัญชีล็อกอินที่ผูกกับพนักงานคนนี้ไปด้วย
    // ถ้าปล่อยไว้จะกลายเป็นบัญชีลอยที่ล็อกอินได้แต่ไม่เห็นอะไรเลย
    // (ตาราง admin_users ไม่ได้ตั้ง FK ไว้ จึงต้องลบเองตรงนี้)
    $acctDeleted = 0;
    try {
        $st = $pdo->prepare('DELETE FROM admin_users WHERE staff_id = ?');
        $st->execute([$id]);
        $acctDeleted = $st->rowCount();
    } catch (PDOException $e) {
        // ยังไม่ได้รัน roles_migration.sql — ไม่ใช่เหตุให้ลบพนักงานไม่ได้
    }

    $pdo->prepare('DELETE FROM staff WHERE id = ?')->execute([$id]);
    flash('success', 'ลบพนักงานขายแล้ว',
          'ลิงก์และ QR ของคนนี้ใช้ไม่ได้อีกต่อไป'
          . ($acctDeleted > 0 ? ' และบัญชีล็อกอินของคนนี้ถูกลบไปด้วย' : ''));
    flash_redirect('staff.php');
}

// ══════════════════════════════════════════════════════════
//  จัดการบัญชีล็อกอิน (เปิด/ปิด · ลบ · เพิ่มบัญชีผู้ดูแล/ผู้จัดการ)
//  ด่านกันพลาดชุดเดียวกับหน้าผู้ใช้งานระบบเดิมทุกข้อ
//    • ห้ามปิดหรือลบบัญชีตัวเอง — กันล็อกตัวเองออกจากระบบ
//    • ต้องเหลือ admin ที่ใช้งานได้อย่างน้อย 1 บัญชีเสมอ
// ══════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && in_array($_POST['action'] ?? '', ['acct_toggle', 'acct_delete', 'acct_save'], true)) {

    if (!can('manage_users')) { require_perm('manage_users'); }

    $action = $_POST['action'];
    $aid    = (int) ($_POST['id'] ?? 0);

    try {
        // ---------- เปิด/ปิดการใช้งาน ----------
        if ($action === 'acct_toggle') {
            if ($aid === $meId) throw new RuntimeException('ปิดการใช้งานบัญชีตัวเองไม่ได้');

            $cur = $pdo->prepare('SELECT username, role, is_active FROM admin_users WHERE id = ?');
            $cur->execute([$aid]);
            $u = $cur->fetch();
            if (!$u) throw new RuntimeException('ไม่พบบัญชีนี้');

            $newActive = (int) $u['is_active'] === 1 ? 0 : 1;
            if ($newActive === 0 && $u['role'] === 'admin' && activeAdminCount($pdo, $aid) === 0) {
                throw new RuntimeException('ต้องมีผู้ดูแลระบบที่ใช้งานได้อย่างน้อย 1 บัญชี');
            }
            $pdo->prepare('UPDATE admin_users SET is_active = ? WHERE id = ?')->execute([$newActive, $aid]);
            flash('success',
                  $newActive === 1 ? 'เปิดการใช้งานบัญชีแล้ว' : 'ปิดการใช้งานบัญชีแล้ว',
                  $u['username'] . ' · ' . ($newActive === 1
                      ? 'ล็อกอินเข้าระบบได้แล้ว'
                      : 'จะล็อกอินไม่ได้ แต่ประวัติข้อความที่เคยฝากไว้ยังอยู่ครบ'));
            flash_redirect('staff.php');
        }

        // ---------- ลบบัญชีถาวร ----------
        // ข้อความที่บัญชีนี้เคยฝากถึงพนักงานจะไม่หาย
        // เพราะตาราง review_notes เก็บ author_name ไว้เป็นสำเนาตั้งแต่ตอนส่ง
        if ($action === 'acct_delete') {
            if ($aid === $meId) throw new RuntimeException('ลบบัญชีตัวเองไม่ได้ ให้ผู้ดูแลคนอื่นเป็นคนลบให้');

            $cur = $pdo->prepare('SELECT username, role FROM admin_users WHERE id = ?');
            $cur->execute([$aid]);
            $u = $cur->fetch();
            if (!$u) throw new RuntimeException('ไม่พบบัญชีนี้ — อาจถูกลบไปแล้ว');

            if ($u['role'] === 'admin' && activeAdminCount($pdo, $aid) === 0) {
                throw new RuntimeException('ต้องมีผู้ดูแลระบบที่ใช้งานได้อย่างน้อย 1 บัญชี — สร้างบัญชีผู้ดูแลอีกคนก่อนแล้วค่อยลบบัญชีนี้');
            }

            $pdo->prepare('DELETE FROM admin_users WHERE id = ?')->execute([$aid]);
            flash('success', 'ลบบัญชี ' . $u['username'] . ' แล้ว',
                  'ข้อความที่บัญชีนี้เคยฝากถึงพนักงานยังอยู่ครบ พร้อมชื่อผู้ส่งเดิม');
            flash_redirect('staff.php');
        }

        // ---------- เพิ่ม/แก้บัญชีผู้ดูแลหรือผู้จัดการ (ไม่ผูกกับพนักงานขาย) ----------
        if ($action === 'acct_save') {
            $username = trim($_POST['acct_username'] ?? '');
            $display  = trim($_POST['acct_display'] ?? '');
            $role     = in_array($_POST['acct_role'] ?? '', ['admin', 'manager'], true) ? $_POST['acct_role'] : 'manager';
            $password = $_POST['acct_password'] ?? '';
            $active   = isset($_POST['acct_active']) ? 1 : 0;

            if ($username === '') throw new RuntimeException('กรุณากรอกชื่อผู้ใช้');

            if ($aid > 0) {
                if ($aid === $meId && ($role !== 'admin' || $active !== 1)) {
                    throw new RuntimeException('เปลี่ยนสิทธิ์หรือปิดบัญชีตัวเองไม่ได้ ให้ผู้ดูแลคนอื่นเป็นคนทำให้');
                }
                if (($role !== 'admin' || $active !== 1) && activeAdminCount($pdo, $aid) === 0) {
                    throw new RuntimeException('ต้องมีผู้ดูแลระบบที่ใช้งานได้อย่างน้อย 1 บัญชี');
                }
                if ($password !== '') {
                    if (strlen($password) < 8) throw new RuntimeException('รหัสผ่านต้องยาวอย่างน้อย 8 ตัวอักษร');
                    $pdo->prepare('UPDATE admin_users SET password = ? WHERE id = ?')
                        ->execute([password_hash($password, PASSWORD_DEFAULT), $aid]);
                }
                $pdo->prepare('UPDATE admin_users SET username = ?, role = ?, display_name = ?, is_active = ?, staff_id = NULL WHERE id = ?')
                    ->execute([$username, $role, $display ?: null, $active, $aid]);
                flash('success', 'บันทึกบัญชีแล้ว', $username . ' · ' . ($role === 'admin' ? 'ผู้ดูแลระบบ' : 'ผู้จัดการ'));
            } else {
                if (strlen($password) < 8) throw new RuntimeException('รหัสผ่านต้องยาวอย่างน้อย 8 ตัวอักษร');
                $pdo->prepare('INSERT INTO admin_users (username, password, role, staff_id, display_name, is_active) VALUES (?, ?, ?, NULL, ?, ?)')
                    ->execute([$username, password_hash($password, PASSWORD_DEFAULT), $role, $display ?: null, $active]);
                flash('success', 'เพิ่มบัญชี ' . $username . ' แล้ว',
                      'สิทธิ์: ' . ($role === 'admin' ? 'ผู้ดูแลระบบ' : 'ผู้จัดการ') . ' — ล็อกอินได้ทันที');
            }
            flash_redirect('staff.php#accounts');
        }
    } catch (RuntimeException $e) {
        $message = $e->getMessage();
        $messageType = 'error';
    } catch (PDOException $e) {
        $message = 'บันทึกไม่สำเร็จ — ชื่อผู้ใช้อาจซ้ำกับที่มีอยู่แล้ว';
        $messageType = 'error';
    }
}

// ----- สร้างโค้ดพนักงานถัดไปอัตโนมัติ (emp001, emp002, ...) -----
function generateNextStaffCode(PDO $pdo): string
{
    $codes = $pdo->query("SELECT code FROM staff WHERE code REGEXP '^emp[0-9]+$'")->fetchAll(PDO::FETCH_COLUMN);
    $maxNumber = 0;
    foreach ($codes as $c) {
        $num = (int) substr($c, 3);
        if ($num > $maxNumber) $maxNumber = $num;
    }
    return 'emp' . str_pad((string) ($maxNumber + 1), 3, '0', STR_PAD_LEFT);
}

// ----- เพิ่ม / แก้ไข พนักงานขาย -----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action'] ?? '', ['create', 'update'], true)) {
    $id       = (int) ($_POST['id'] ?? 0);
    $name     = trim($_POST['name'] ?? '');
    $position = trim($_POST['position'] ?? '');
    $brandId  = ($_POST['brand_id'] ?? '') !== '' ? (int) $_POST['brand_id'] : null;

    if ($_POST['action'] === 'create') {
        $code = generateNextStaffCode($pdo);
    } else {
        $stmt = $pdo->prepare('SELECT code FROM staff WHERE id = ?');
        $stmt->execute([$id]);
        $code = (string) $stmt->fetchColumn();
    }

    if ($name === '' || $position === '') {
        $message = 'กรุณากรอกข้อมูลให้ครบถ้วน';
        $messageType = 'error';
    } elseif ($code === '') {
        $message = 'ไม่พบโค้ดพนักงานเดิม กรุณาลองใหม่';
        $messageType = 'error';
    } else {
        $photoName = null;
        $oldPhoto  = null;

        if ($_POST['action'] === 'update') {
            $stmt = $pdo->prepare('SELECT photo FROM staff WHERE id = ?');
            $stmt->execute([$id]);
            $existing  = $stmt->fetch();
            $photoName = $existing['photo'] ?? null;
            $oldPhoto  = $photoName;
        }

        if (!empty($_FILES['photo']['name']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $allowedExt  = ['jpg', 'jpeg', 'png', 'webp'];
            $ext         = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
            $finfo       = finfo_open(FILEINFO_MIME_TYPE);
            $mime        = finfo_file($finfo, $_FILES['photo']['tmp_name']);
            finfo_close($finfo);
            $allowedMime = ['image/jpeg', 'image/png', 'image/webp'];
            $imageInfo   = @getimagesize($_FILES['photo']['tmp_name']);

            if (!in_array($ext, $allowedExt, true) || !in_array($mime, $allowedMime, true) || $imageInfo === false) {
                $message     = 'ไฟล์ที่อัปโหลดไม่ใช่รูปภาพที่ถูกต้อง รองรับเฉพาะ JPG, PNG, WEBP เท่านั้น';
                $messageType = 'error';
            } elseif ($_FILES['photo']['size'] > 2 * 1024 * 1024) {
                $message     = 'ขนาดไฟล์รูปต้องไม่เกิน 2MB';
                $messageType = 'error';
            } else {
                $newName = $code . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);

                $diagnostic = '';
                if (!is_dir($uploadDir)) {
                    $diagnostic = 'ไม่สามารถสร้างโฟลเดอร์ ' . $uploadDir . ' ได้';
                } elseif (!is_writable($uploadDir)) {
                    $diagnostic = 'โฟลเดอร์ ' . $uploadDir . ' ไม่มีสิทธิ์ให้เขียนไฟล์ (ตั้งเป็น 755 หรือ 775)';
                }

                if ($diagnostic !== '') {
                    $message     = 'อัปโหลดรูปภาพไม่สำเร็จ: ' . $diagnostic;
                    $messageType = 'error';
                } elseif (move_uploaded_file($_FILES['photo']['tmp_name'], $uploadDir . $newName)) {
                    if ($oldPhoto && file_exists($uploadDir . $oldPhoto)) {
                        unlink($uploadDir . $oldPhoto);
                    }
                    $photoName = $newName;
                } else {
                    $lastError = error_get_last();
                    $detail    = $lastError ? $lastError['message'] : 'ไม่ทราบสาเหตุ';
                    $message     = 'อัปโหลดรูปภาพไม่สำเร็จ: ' . $detail;
                    $messageType = 'error';
                }
            }
        }

        if ($messageType !== 'error') {
            // ── บัญชีล็อกอินของพนักงานคนนี้ (ไม่บังคับ) ──
            // กรอกในฟอร์มเดียวกัน จะได้ไม่ต้องไปกรอกชื่อคนเดิมซ้ำอีกหน้า
            $wantAcct = isset($_POST['has_account']);
            $acctUser = trim($_POST['login_username'] ?? '');
            $acctPass = $_POST['login_password'] ?? '';
            $acctOn   = isset($_POST['login_active']) ? 1 : 0;

            // ตรวจเงื่อนไขบัญชีให้ครบ "ก่อน" แตะฐานข้อมูล
            // ไม่งั้นพนักงานจะถูกสร้างไปแล้วแต่บัญชีล้มเหลว กลายเป็นข้อมูลค้างครึ่งทาง
            $acctId = 0;
            if ($_POST['action'] === 'update' && $id > 0) {
                try {
                    $q = $pdo->prepare('SELECT id FROM admin_users WHERE staff_id = ? LIMIT 1');
                    $q->execute([$id]);
                    $acctId = (int) ($q->fetchColumn() ?: 0);
                } catch (PDOException $e) { $acctId = 0; }
            }

            if ($wantAcct) {
                if ($acctUser === '') {
                    $message = 'ถ้าจะให้พนักงานคนนี้ล็อกอินได้ ต้องตั้งชื่อผู้ใช้ด้วย';
                    $messageType = 'error';
                } elseif ($acctId === 0 && strlen($acctPass) < 8) {
                    $message = 'รหัสผ่านของบัญชีใหม่ต้องยาวอย่างน้อย 8 ตัวอักษร';
                    $messageType = 'error';
                } elseif ($acctPass !== '' && strlen($acctPass) < 8) {
                    $message = 'รหัสผ่านต้องยาวอย่างน้อย 8 ตัวอักษร';
                    $messageType = 'error';
                }
            }
        }

        if ($messageType !== 'error') {
            if ($_POST['action'] === 'create') {
                $stmt = $pdo->prepare('INSERT INTO staff (code, name, position, brand_id, photo) VALUES (?, ?, ?, ?, ?)');
                $stmt->execute([$code, $name, $position, $brandId, $photoName]);
                $newId = (int) $pdo->lastInsertId();
                $id    = $newId;
            } else {
                $stmt = $pdo->prepare('UPDATE staff SET name = ?, position = ?, brand_id = ?, photo = ? WHERE id = ?');
                $stmt->execute([$name, $position, $brandId, $photoName, $id]);
                $newId = $id;
            }

            // ── สร้าง/แก้/ลบ บัญชีล็อกอินตามที่ติ๊กไว้ ──
            // role เป็น 'sales' เสมอ เพราะคนที่มี QR ให้ลูกค้าประเมิน = พนักงานขาย
            // ถ้าอยากได้บัญชีผู้ดูแล/ผู้จัดการ ให้ไปสร้างที่ส่วนล่างของหน้าแทน
            $acctNote = '';
            try {
                if ($wantAcct) {
                    if ($acctId > 0) {
                        $pdo->prepare('UPDATE admin_users SET username = ?, role = \'sales\', staff_id = ?, display_name = ?, is_active = ? WHERE id = ?')
                            ->execute([$acctUser, $id, $name, $acctOn, $acctId]);
                        if ($acctPass !== '') {
                            $pdo->prepare('UPDATE admin_users SET password = ? WHERE id = ?')
                                ->execute([password_hash($acctPass, PASSWORD_DEFAULT), $acctId]);
                            $acctNote = ' · อัปเดตบัญชี ' . $acctUser . ' และตั้งรหัสผ่านใหม่แล้ว';
                        } else {
                            $acctNote = ' · อัปเดตบัญชี ' . $acctUser . ' แล้ว';
                        }
                    } else {
                        $pdo->prepare('INSERT INTO admin_users (username, password, role, staff_id, display_name, is_active) VALUES (?, ?, \'sales\', ?, ?, ?)')
                            ->execute([$acctUser, password_hash($acctPass, PASSWORD_DEFAULT), $id, $name, $acctOn]);
                        $acctNote = ' · สร้างบัญชี ' . $acctUser . ' ให้ล็อกอินดูคะแนนตัวเองได้แล้ว';
                    }
                } elseif ($acctId > 0) {
                    // เอาติ๊กออก = ไม่อยากให้ล็อกอินอีกแล้ว → ปิดบัญชี ไม่ลบ
                    // เก็บไว้เผื่อเปิดใหม่ และเพื่อไม่ให้ประวัติที่ผูกกับบัญชีนี้ขาด
                    $pdo->prepare('UPDATE admin_users SET is_active = 0 WHERE id = ?')->execute([$acctId]);
                    $acctNote = ' · ปิดบัญชีล็อกอินของคนนี้แล้ว (ยังไม่ได้ลบ)';
                }
            } catch (PDOException $e) {
                $acctNote = ' · แต่บันทึกบัญชีล็อกอินไม่สำเร็จ (ชื่อผู้ใช้อาจซ้ำ)';
            }

            if ($_POST['action'] === 'create') {
                flash('success', 'เพิ่ม ' . $name . ' แล้ว',
                      'ระบบสร้างโค้ด ' . $code . ' พร้อม QR และลิงก์ประเมินให้เรียบร้อย' . $acctNote,
                      ['text' => 'ดูในรายชื่อด้านล่าง', 'href' => '#row-' . $newId]);
            } else {
                flash('success', 'บันทึกการแก้ไขแล้ว',
                      $name . ' · โค้ด ' . $code . ' (ลิงก์และ QR เดิมยังใช้ได้ตามปกติ)' . $acctNote,
                      ['text' => 'ดูในรายชื่อด้านล่าง', 'href' => '#row-' . $newId]);
            }
            flash_redirect('staff.php?hl=' . $newId);
        }
    }
}

// ----- ข้อมูลประกอบ -----
$brandOptions = $pdo->query('SELECT id, name, color FROM brands ORDER BY sort_order ASC, id ASC')->fetchAll();

// ดึงบัญชีล็อกอินมาพร้อมกันเลย จะได้เห็นในตารางเดียวว่าใครล็อกอินได้บ้าง
// ห่อ try/catch เพราะถ้ายังไม่ได้รัน roles_migration.sql จะยังไม่มีคอลัมน์ staff_id
$hasAccounts = true;
try {
    $staffList = $pdo->query("
        SELECT s.*, b.name AS brand_name, b.color AS brand_color,
               u.id AS acct_id, u.username AS acct_username, u.is_active AS acct_active
        FROM staff s
        LEFT JOIN brands b ON b.id = s.brand_id
        LEFT JOIN admin_users u ON u.staff_id = s.id
        ORDER BY s.id ASC
    ")->fetchAll();
} catch (PDOException $e) {
    $hasAccounts = false;
    $staffList = $pdo->query("
        SELECT s.*, b.name AS brand_name, b.color AS brand_color,
               NULL AS acct_id, NULL AS acct_username, NULL AS acct_active
        FROM staff s
        LEFT JOIN brands b ON b.id = s.brand_id
        ORDER BY s.id ASC
    ")->fetchAll();
}

// บัญชีที่ไม่ได้ผูกกับพนักงานขาย = ผู้ดูแล / ผู้จัดการ
$otherAccounts = [];
if ($hasAccounts && can('manage_users')) {
    try {
        $otherAccounts = $pdo->query("
            SELECT id, username, display_name, role, is_active
            FROM admin_users
            WHERE staff_id IS NULL
            ORDER BY FIELD(role,'admin','manager','sales'), username
        ")->fetchAll();
    } catch (PDOException $e) {
        $otherAccounts = [];
    }
}

$editData = null;
$editAcct = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM staff WHERE id = ?');
    $stmt->execute([(int) $_GET['edit']]);
    $editData = $stmt->fetch();

    if ($editData && $hasAccounts) {
        try {
            $q = $pdo->prepare('SELECT id, username, is_active FROM admin_users WHERE staff_id = ? LIMIT 1');
            $q->execute([(int) $editData['id']]);
            $editAcct = $q->fetch() ?: null;
        } catch (PDOException $e) { $editAcct = null; }
    }
}

// บัญชีผู้ดูแล/ผู้จัดการที่กำลังแก้ (ส่วนล่างของหน้า)
$editOther = null;
if (isset($_GET['acct']) && can('manage_users')) {
    try {
        $q = $pdo->prepare('SELECT * FROM admin_users WHERE id = ? AND staff_id IS NULL');
        $q->execute([(int) $_GET['acct']]);
        $editOther = $q->fetch() ?: null;
    } catch (PDOException $e) { $editOther = null; }
}

// ══════════════════════════════════════════════════════════
//  base URL ของหน้าให้คะแนน (ใช้ทำ QR / ลิงก์แจกลูกค้า)
// ══════════════════════════════════════════════════════════
// ตรรกะทั้งหมดอยู่ที่ rate_base_url() ใน conn/config.php ที่เดียว
// หน้าไหนก็ตามที่ต้องสร้างลิงก์ให้ลูกค้า ต้องเรียกตัวนี้ ห้ามประกอบ URL เอง
$qrInfo      = rate_base_url();
$rateBaseUrl = $qrInfo['url'];
$qrHost      = $qrInfo['host'];
$qrSource    = $qrInfo['source'];
$qrIsLocal    = ($qrSource === 'local');   // ใช้กับมือถือลูกค้าไม่ได้จริงๆ
$qrIsGuess    = ($qrSource === 'guess');   // ใช้ได้ แต่ระบบเดา IP ให้ ควรยืนยันก่อน
$qrMismatch   = !empty($qrInfo['mismatch']);          // path ที่ตั้งไว้ไม่ตรงกับที่วางจริง
$qrExpected   = $qrInfo['expected'] ?? '';

require 'includes/head.php';
?>
<h1>พนักงานและผู้ใช้งาน</h1>
<p class="page-sub">
    เพิ่มพนักงานขาย พร้อมสร้างบัญชีให้เขาล็อกอินได้ในฟอร์มเดียว — ระบบสร้างโค้ดและ QR ให้อัตโนมัติ<br>
    <span class="as-of">บัญชีผู้ดูแลและผู้จัดการอยู่ในส่วนล่างสุดของหน้า</span>
</p>

<?php if ($qrMismatch): ?>
<div class="warn-strip">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
    <div>
        <b>ที่อยู่ใน PUBLIC_BASE_URL ไม่ตรงกับตำแหน่งจริงของระบบ — สแกน QR แล้วจะได้ 404</b>
        <details class="warn-more" open><summary>แก้ยังไง</summary>
            ระบบนี้วางอยู่ที่ <code><?= htmlspecialchars(rawurldecode($qrExpected)) ?></code>
            แต่ค่าที่ตั้งไว้ชี้ไปคนละที่<br>
            เปิด <code>conn/config.php</code> บรรทัด <code>PUBLIC_BASE_URL</code> แล้วแก้เป็น<br>
            <code>http://<?= htmlspecialchars($qrHost) ?><?= htmlspecialchars(rawurldecode($qrExpected)) ?></code><br>
            (พิมพ์ช่องว่างกับวงเล็บลงไปได้ตรงๆ ระบบเข้ารหัสให้เอง)
        </details>
    </div>
</div>
<?php elseif ($qrIsLocal): ?>
<div class="warn-strip">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
    <div>
        <b>QR ตอนนี้ยังใช้กับมือถือลูกค้าไม่ได้</b> — ลิงก์ชี้ไปที่ <code><?= htmlspecialchars($qrHost) ?></code> ซึ่งหมายถึงเครื่องนี้เท่านั้น
        <details class="warn-more"><summary>วิธีแก้</summary>
            เปิดไฟล์ <code>conn/config.php</code> แล้วตั้งค่า <code>PUBLIC_BASE_URL</code>
            เป็นที่อยู่ที่เครื่องอื่นเข้าถึงได้จริง เช่น <code>http://192.168.1.50/eaksaha-rating</code>
            (IP ของเครื่องนี้ในวง LAN) หรือชื่อโดเมนจริงเมื่อขึ้นเซิร์ฟเวอร์
        </details>
    </div>
</div>
<?php elseif ($qrIsGuess): ?>
<div class="warn-strip warn-soft">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
    <div>
        <b>QR ใช้ IP ที่ระบบเดาให้ — <code><?= htmlspecialchars($qrHost) ?></code></b>
        เพราะคุณเปิดหลังบ้านผ่าน localhost ซึ่งมือถือลูกค้าเข้าไม่ได้
        <details class="warn-more"><summary>ควรทำอะไรต่อ</summary>
            ลองสแกน QR ด้วยมือถือที่ต่อ wifi วงเดียวกันหนึ่งครั้ง ถ้าเปิดหน้าประเมินได้ก็ใช้งานได้เลย<br>
            ถ้าเปิดไม่ขึ้น ให้ตั้ง <code>PUBLIC_BASE_URL</code> ใน <code>conn/config.php</code> เป็นที่อยู่ที่ถูกต้องเอง<br>
            <b>หมายเหตุ:</b> IP ที่แจกโดย wifi อาจเปลี่ยนเมื่อรีสตาร์ตเครื่อง QR ที่พิมพ์แจกไว้จะใช้ไม่ได้
            — ถ้าจะพิมพ์แจกถาวร ควรตั้ง <code>PUBLIC_BASE_URL</code> เป็นโดเมนจริงหรือจอง IP คงที่
        </details>
    </div>
</div>
<?php endif; ?>

<!-- ขั้นตอนใช้งาน — กางเองเฉพาะตอนยังไม่มีพนักงานในระบบ
     คนที่ใช้เป็นแล้วไม่ต้องเห็นกล่องนี้ทุกครั้งที่เข้าหน้า -->
<details class="how-to" <?= empty($staffList) ? 'open' : '' ?>>
    <summary>วิธีใช้งาน 3 ขั้นตอน</summary>
    <div class="info-steps">
        <div class="info-step">
            <div class="num">1</div>
            <div class="txt"><b>เพิ่มพนักงานขาย</b>กรอกชื่อ ตำแหน่ง เลือกแบรนด์ แล้วกดบันทึก ระบบสร้างโค้ดและ QR ให้อัตโนมัติ</div>
        </div>
        <div class="info-step">
            <div class="num">2</div>
            <div class="txt"><b>แจก QR ให้เซลล์</b>ดาวน์โหลดไปติดที่โต๊ะหรือนามบัตร หรือคัดลอกลิงก์ส่งให้ลูกค้าตรงๆ</div>
        </div>
        <div class="info-step">
            <div class="num">3</div>
            <div class="txt"><b>ลูกค้าสแกนแล้วเขียนรีวิว</b>AI ให้ดาวอัตโนมัติ ผลเข้าแดชบอร์ดทันที ไม่ต้องทำอะไรเพิ่ม</div>
        </div>
    </div>
</details>

<?php if ($message): ?>
    <script>window.toast(<?= json_encode($messageType === 'error' ? 'error' : 'success', JSON_UNESCAPED_UNICODE) ?>,
                         <?= json_encode($messageType === 'error' ? 'บันทึกไม่สำเร็จ' : 'สำเร็จ', JSON_UNESCAPED_UNICODE) ?>,
                         <?= json_encode($message, JSON_UNESCAPED_UNICODE) ?>);</script>
<?php endif; ?>

<h2 class="section-title" id="edit-form"><?= $editData
        ? 'แก้ไขข้อมูล: ' . htmlspecialchars($editData['name'])
        : 'เพิ่มพนักงานขายใหม่' ?></h2>

<div class="form-card wide">
    <form method="POST" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="<?= $editData ? 'update' : 'create' ?>">
        <div class="form-grid">
        <?php if ($editData): ?>
            <input type="hidden" name="id" value="<?= (int) $editData['id'] ?>">
            <div class="field span-2">
                <label for="code">โค้ดพนักงาน (สำหรับลิงก์/QR)</label>
                <input type="text" id="code" value="<?= htmlspecialchars($editData['code']) ?>" readonly
                       style="background:var(--panel-3); color:var(--muted-2); cursor:not-allowed;">
                <div class="hint">แก้ไขไม่ได้ เนื่องจากผูกกับลิงก์/QR ที่แจกไปแล้ว</div>
            </div>
        <?php else: ?>
            <div class="field span-2">
                <div class="hint" style="margin-bottom:0;">ระบบจะสร้างโค้ดพนักงาน (เช่น emp004) ให้อัตโนมัติเมื่อบันทึก</div>
            </div>
        <?php endif; ?>

        <div class="field">
            <label for="name">ชื่อ-นามสกุล</label>
            <input type="text" id="name" name="name" value="<?= htmlspecialchars($editData['name'] ?? '') ?>" required>
        </div>
        <div class="field">
            <label for="position">ตำแหน่ง</label>
            <input type="text" id="position" name="position" value="<?= htmlspecialchars($editData['position'] ?? '') ?>" placeholder="เช่น ที่ปรึกษาการขาย" required>
        </div>
        <div class="field">
            <label for="brand_id">แบรนด์ที่สังกัด</label>
            <select id="brand_id" name="brand_id">
                <option value="">— ไม่ระบุแบรนด์ —</option>
                <?php foreach ($brandOptions as $b): ?>
                    <option value="<?= (int) $b['id'] ?>" <?= (isset($editData['brand_id']) && (int)$editData['brand_id'] === (int)$b['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($b['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <div class="hint">เซลล์ 1 คนสังกัดได้ 1 แบรนด์</div>
        </div>
        <div class="field">
            <label for="photo">รูปภาพ (ไม่บังคับ)</label>
            <div class="file-field">
                <input type="file" id="photo" name="photo" accept=".jpg,.jpeg,.png,.webp" onchange="previewPhoto(this)">
                <label for="photo" class="file-btn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>เลือกรูปภาพ</label>
                <span class="file-name" data-empty="ยังไม่ได้เลือกไฟล์">ยังไม่ได้เลือกไฟล์</span>
            </div>
            <div class="hint">JPG, PNG, WEBP ขนาดไม่เกิน 2MB</div>
            <div id="photoPreviewBox" style="margin-top:10px; <?= empty($editData['photo']) ? 'display:none;' : '' ?>">
                <img id="photoPreviewImg"
                     src="<?= !empty($editData['photo']) ? htmlspecialchars('uploads/staff/' . $editData['photo']) : '' ?>"
                     alt="ตัวอย่างรูปภาพ"
                     style="width:110px;height:110px;border-radius:50%;object-fit:cover;border:2px solid #0EA5E9;display:block;">
                <div class="hint" id="photoPreviewLabel"><?= !empty($editData['photo']) ? 'รูปปัจจุบัน' : '' ?></div>
            </div>
        </div>

        <?php if ($hasAccounts && can('manage_users')): ?>
        <!-- ── บัญชีล็อกอินของคนนี้ ──
             อยู่ในฟอร์มเดียวกับข้อมูลพนักงาน เพราะเป็นคนคนเดียวกัน
             เดิมต้องไปกรอกชื่อซ้ำอีกหน้าหนึ่ง ซึ่งพลาดง่ายและเสียเวลา -->
        <div class="field span-2 acct-block">
            <label class="check-pill" style="display:inline-flex;">
                <input type="checkbox" name="has_account" value="1" id="hasAccount"
                       <?= $editAcct && (int) $editAcct['is_active'] === 1 ? 'checked' : ($editAcct ? '' : '') ?>
                       onchange="toggleAcct()">
                ให้พนักงานคนนี้ล็อกอินเข้ามาดูคะแนนของตัวเองได้
            </label>
            <div class="hint">ไม่ติ๊ก = มี QR ให้ลูกค้าประเมินได้ตามปกติ แต่ตัวพนักงานเข้าระบบไม่ได้</div>

            <div id="acctFields" class="acct-fields" style="display:none;">
                <div class="form-grid">
                    <div class="field">
                        <label for="login_username">ชื่อผู้ใช้ (สำหรับล็อกอิน)</label>
                        <input type="text" id="login_username" name="login_username" autocomplete="off"
                               value="<?= htmlspecialchars($editAcct['username'] ?? '') ?>"
                               placeholder="เช่น somchai">
                    </div>
                    <div class="field">
                        <label for="login_password">รหัสผ่าน<?= $editAcct ? ' (เว้นว่างไว้ถ้าไม่เปลี่ยน)' : '' ?></label>
                        <input type="password" id="login_password" name="login_password"
                               placeholder="อย่างน้อย 8 ตัวอักษร" autocomplete="new-password">
                    </div>
                </div>
                <label class="check-pill" style="display:inline-flex;">
                    <input type="checkbox" name="login_active" value="1"
                           <?= (!$editAcct || (int) $editAcct['is_active'] === 1) ? 'checked' : '' ?>>
                    เปิดใช้งานบัญชีนี้
                </label>
                <div class="hint">สิทธิ์เป็น "พนักงานขาย" เสมอ — เห็นเฉพาะคะแนนและข้อความของตัวเอง</div>
            </div>
        </div>
        <?php endif; ?>

        </div><!-- /.form-grid -->

        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?= $editData ? 'บันทึกการแก้ไข' : 'เพิ่มพนักงานขาย' ?></button>
            <?php if ($editData): ?>
                <a href="staff.php" class="btn btn-secondary">ยกเลิก</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<script>
    // ซ่อนช่องบัญชีไว้จนกว่าจะติ๊ก — ฟอร์มจะได้ไม่ยาวเกินจำเป็นสำหรับคนที่ไม่ใช้
    function toggleAcct() {
        var cb = document.getElementById('hasAccount');
        var box = document.getElementById('acctFields');
        if (!cb || !box) return;
        box.style.display = cb.checked ? 'block' : 'none';
    }
    toggleAcct();

    function previewPhoto(input) {
        const box = document.getElementById('photoPreviewBox');
        const img = document.getElementById('photoPreviewImg');
        const label = document.getElementById('photoPreviewLabel');
        const file = input.files && input.files[0];
        if (!file) return;
        if (!file.type.startsWith('image/')) { box.style.display = 'none'; return; }
        const reader = new FileReader();
        reader.onload = function (e) {
            img.src = e.target.result;
            label.textContent = 'ตัวอย่างรูปที่เลือก: ' + file.name;
            box.style.display = 'block';
        };
        reader.readAsDataURL(file);
    }
</script>

<h2 class="section-title">รายชื่อพนักงานขายทั้งหมด<span class="count-badge"><?= number_format(count($staffList)) ?> คน</span></h2>

<script id="staffData" type="application/json">
<?= json_encode(array_map(fn($r) => [
    'id'   => $r['id'],
    'url'  => $rateBaseUrl . rawurlencode($r['code']),
    'name' => $r['name'],
], $staffList), JSON_UNESCAPED_UNICODE) ?>
</script>

<div class="table-card">
    <table class="staff-table">
        <thead>
            <tr>
                <th>พนักงานขาย</th>
                <th>ลิงก์ประเมินของคนนี้</th>
                <th>จัดการ</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($staffList)): ?>
                <tr><td colspan="3" class="empty-cell">ยังไม่มีพนักงานขาย — เพิ่มคนแรกได้ที่ฟอร์มด้านบน</td></tr>
            <?php else: ?>
                <?php foreach ($staffList as $row): ?>
                    <?php $rateUrl = $rateBaseUrl . rawurlencode($row['code']); ?>
                    <tr id="row-<?= (int) $row['id'] ?>">
                        <!-- รูป ชื่อ แบรนด์ ตำแหน่ง โค้ด รวมไว้ช่องเดียว
                             เดิมแยกเป็น 5 คอลัมน์ ทำให้ตารางกว้างจนต้องเลื่อนแนวนอน -->
                        <td class="st-cell">
                          <!-- ต้องมี div ห่อชั้นนี้ — flex ต้องอยู่ที่ div ไม่ใช่ที่ td
                               ไม่งั้นช่องนี้จะไม่ยืดเต็มความสูงแถว แล้วเส้นคั่นจะเบี้ยว -->
                          <div class="st-cell-in">
                            <?php if (!empty($row['photo'])): ?>
                                <img src="uploads/staff/<?= htmlspecialchars($row['photo']) ?>" alt="" class="table-thumb">
                            <?php else: ?>
                                <div class="table-thumb-initial"><?= htmlspecialchars(mb_substr($row['name'], 0, 1, 'UTF-8')) ?></div>
                            <?php endif; ?>
                            <div class="st-info">
                                <div class="st-name"><?= htmlspecialchars($row['name']) ?></div>
                                <div class="st-meta">
                                    <?php if (!empty($row['brand_name'])): ?>
                                        <span class="brand-tag" style="<?= brand_tag_style($row['brand_color'] ?? '#D81300') ?>"><?= htmlspecialchars($row['brand_name']) ?></span>
                                    <?php endif; ?>
                                    <span class="st-pos"><?= htmlspecialchars($row['position']) ?></span>
                                </div>
                                <div class="st-code">
                                    โค้ด <code><?= htmlspecialchars($row['code']) ?></code>
                                    <?php
                                    // บอกสถานะบัญชีล็อกอินตรงนี้เลย จะได้ไม่ต้องเปิดอีกหน้าเพื่อดู
                                    if (!empty($row['acct_id'])):
                                        $on = (int) $row['acct_active'] === 1; ?>
                                        <span class="acct-chip <?= $on ? 'on' : 'off' ?>"
                                              title="<?= $on ? 'ล็อกอินเข้าระบบได้' : 'บัญชีถูกปิดใช้งานอยู่' ?>">
                                            <?= $on ? 'ล็อกอินได้' : 'บัญชีปิดอยู่' ?> · <?= htmlspecialchars($row['acct_username']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="acct-chip none" title="คนนี้ยังไม่มีบัญชีเข้าระบบ">ไม่มีบัญชี</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                          </div>
                        </td>

                        <!-- QR กับลิงก์เป็นเรื่องเดียวกัน (ทางที่ลูกค้าจะเข้าหน้าประเมิน) รวมไว้ช่องเดียว -->
                        <td>
                            <div class="qr-cell">
                                <div id="qr-<?= (int)$row['id'] ?>" class="qr-thumb"></div>
                                <div class="qr-btns">
                                    <button type="button" class="btn btn-secondary btn-sm"
                                            onclick="downloadQR(<?= (int)$row['id'] ?>, '<?= htmlspecialchars($row['name'], ENT_QUOTES) ?>')">ดาวน์โหลด QR</button>
                                    <button type="button" class="btn btn-secondary btn-sm"
                                            onclick="copyRateLink(this, '<?= htmlspecialchars($rateUrl, ENT_QUOTES) ?>')">คัดลอกลิงก์</button>
                                    <a href="<?= htmlspecialchars($rateUrl) ?>" target="_blank" rel="noopener" class="btn btn-secondary btn-sm">เปิดดู</a>
                                </div>
                                <!-- โชว์ที่อยู่จริงที่ฝังอยู่ใน QR
                                     ตอนสแกนแล้วไม่ขึ้น จะได้เทียบด้วยตาได้ทันทีว่าลิงก์ผิดตรงไหน
                                     ไม่ต้องเดาว่า QR ข้างในเขียนว่าอะไร -->
                                <div class="qr-url" title="ที่อยู่ที่ฝังอยู่ใน QR"><?= htmlspecialchars(rawurldecode($rateUrl)) ?></div>
                            </div>
                        </td>

                        <td>
                            <a href="staff.php?edit=<?= (int) $row['id'] ?>#edit-form" class="btn btn-secondary btn-sm">แก้ไข</a>
                            <form method="POST" style="display:inline;"
                                  onsubmit="return confirm('ลบ <?= htmlspecialchars(addslashes($row['name']), ENT_QUOTES) ?> ออกจากระบบ?\n\nรีวิวเก่าของคนนี้จะถูกลบตามไปด้วย และ QR ที่แจกไปแล้วจะใช้ไม่ได้อีก');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm">ลบ</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php if ($hasAccounts && can('manage_users')): ?>
<!-- ══════════════════════════════════════════════════════════
     บัญชีที่ไม่ใช่พนักงานขาย (ผู้ดูแล / ผู้จัดการ)
     พับเก็บไว้เพราะนานๆ ใช้ที — คนที่เข้าหน้านี้ 9 ใน 10 ครั้ง
     มาเพื่อจัดการพนักงานขาย ไม่ใช่มาสร้างบัญชีผู้ดูแล
     ══════════════════════════════════════════════════════════ -->
<details class="how-to acct-section" id="accounts" <?= $editOther ? 'open' : '' ?>>
    <summary>บัญชีผู้ดูแลและผู้จัดการ<span class="count-badge"><?= number_format(count($otherAccounts)) ?></span></summary>

    <p class="hint" style="margin-top:2px;">
        บัญชีกลุ่มนี้ไม่มี QR และไม่ถูกลูกค้าประเมิน — ใช้สำหรับคนที่เข้ามาดูภาพรวมหรือดูแลระบบเท่านั้น<br>
        ถ้าเป็นพนักงานขาย ให้เพิ่มที่ฟอร์มด้านบนแทน แล้วติ๊ก "ให้ล็อกอินได้" ในฟอร์มเดียวกัน
    </p>

    <div class="form-card" style="margin-bottom:16px;">
        <form method="POST" action="staff.php#accounts">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="acct_save">
            <?php if ($editOther): ?><input type="hidden" name="id" value="<?= (int) $editOther['id'] ?>"><?php endif; ?>

            <div class="form-grid">
                <div class="field">
                    <label for="acct_username">ชื่อผู้ใช้</label>
                    <input type="text" id="acct_username" name="acct_username" required autocomplete="off"
                           value="<?= htmlspecialchars($editOther['username'] ?? '') ?>" placeholder="เช่น manager1">
                </div>
                <div class="field">
                    <label for="acct_display">ชื่อที่แสดง</label>
                    <input type="text" id="acct_display" name="acct_display"
                           value="<?= htmlspecialchars($editOther['display_name'] ?? '') ?>"
                           placeholder="เช่น คุณสมชาย (ผู้จัดการสาขา)">
                    <div class="hint">เว้นว่างได้ ระบบจะใช้ชื่อผู้ใช้แทน</div>
                </div>
                <div class="field">
                    <label for="acct_role">สิทธิ์</label>
                    <select id="acct_role" name="acct_role" class="filter-select" style="width:100%;">
                        <option value="manager" <?= ($editOther['role'] ?? 'manager') === 'manager' ? 'selected' : '' ?>>ผู้จัดการ — เห็นทุกอย่าง ส่งข้อความถึงพนักงานได้ แต่แก้ไขข้อมูลไม่ได้</option>
                        <option value="admin"   <?= ($editOther['role'] ?? '') === 'admin' ? 'selected' : '' ?>>ผู้ดูแลระบบ — ทำได้ทุกอย่าง</option>
                    </select>
                </div>
                <div class="field">
                    <label for="acct_password">รหัสผ่าน<?= $editOther ? ' (เว้นว่างไว้ถ้าไม่เปลี่ยน)' : '' ?></label>
                    <input type="password" id="acct_password" name="acct_password" <?= $editOther ? '' : 'required' ?>
                           placeholder="อย่างน้อย 8 ตัวอักษร" autocomplete="new-password">
                </div>
            </div>

            <label class="check-pill" style="display:inline-flex; margin-bottom:12px;">
                <input type="checkbox" name="acct_active" value="1"
                       <?= ($editOther === null || (int) $editOther['is_active'] === 1) ? 'checked' : '' ?>>
                เปิดใช้งานบัญชีนี้
            </label>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><?= $editOther ? 'บันทึกการแก้ไข' : 'เพิ่มบัญชี' ?></button>
                <?php if ($editOther): ?><a href="staff.php#accounts" class="btn btn-secondary">ยกเลิก</a><?php endif; ?>
            </div>
        </form>
    </div>

    <div class="table-card">
        <table>
            <thead><tr><th>บัญชี</th><th>สิทธิ์</th><th>สถานะ</th><th>จัดการ</th></tr></thead>
            <tbody>
            <?php if (empty($otherAccounts)): ?>
                <tr><td colspan="4" class="empty-cell">ยังไม่มีบัญชีผู้ดูแลหรือผู้จัดการเพิ่มเติม</td></tr>
            <?php else: ?>
                <?php foreach ($otherAccounts as $a): $isMe = (int) $a['id'] === $meId; ?>
                    <tr class="<?= (int) $a['is_active'] === 1 ? '' : 'u-off' ?>">
                        <td class="wrap-cell">
                            <div class="u-name">
                                <?= htmlspecialchars($a['display_name'] ?: $a['username']) ?>
                                <?php if ($isMe): ?><span class="me-tag">(คุณ)</span><?php endif; ?>
                            </div>
                            <div class="u-sub"><?= htmlspecialchars($a['username']) ?></div>
                        </td>
                        <td><span class="role-tag <?= htmlspecialchars($a['role']) ?>"><?= htmlspecialchars($roleNames[$a['role']] ?? $a['role']) ?></span></td>
                        <td><?= (int) $a['is_active'] === 1 ? 'ใช้งานได้' : '<span style="color:var(--muted-2);">ปิดใช้งาน</span>' ?></td>
                        <td>
                            <a href="staff.php?acct=<?= (int) $a['id'] ?>#accounts" class="btn btn-secondary btn-sm">แก้ไข</a>
                            <?php if (!$isMe): ?>
                                <form method="POST" style="display:inline;"
                                      onsubmit="return confirm('<?= (int) $a['is_active'] === 1 ? 'ปิดการใช้งานบัญชีนี้?' : 'เปิดการใช้งานบัญชีนี้?' ?>');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="acct_toggle">
                                    <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                                    <button type="submit" class="btn btn-secondary btn-sm"><?= (int) $a['is_active'] === 1 ? 'ปิดใช้งาน' : 'เปิดใช้งาน' ?></button>
                                </form>
                                <form method="POST" style="display:inline;"
                                      onsubmit="return confirm('<?= htmlspecialchars(addslashes($a['username']), ENT_QUOTES) ?> — ลบบัญชีนี้ถาวร?\n\nลบแล้วเอากลับมาไม่ได้ ถ้าแค่ไม่อยากให้ล็อกอิน ให้กด ปิดใช้งาน แทน\n\n(ข้อความที่บัญชีนี้เคยฝากถึงพนักงานจะยังอยู่ครบ)');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="acct_delete">
                                    <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">ลบ</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</details>
<?php endif; ?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
(function () {
    const staffData = JSON.parse(document.getElementById('staffData').textContent);

    window.copyRateLink = function (btn, url) {
        const done = ok => {
            if (ok) {
                window.toast('success', 'คัดลอกลิงก์แล้ว',
                             'วางในไลน์ อีเมล หรือช่องแชทที่ต้องการส่งให้ลูกค้าได้เลย');
            } else {
                window.toast('error', 'คัดลอกไม่สำเร็จ', 'ลองกด "ลองเปิดดู" แล้วคัดลอกจากแถบที่อยู่ของเบราว์เซอร์แทน');
            }
        };
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(url).then(() => done(true)).catch(() => done(false));
        } else {
            const temp = document.createElement('textarea');
            temp.value = url; temp.style.position = 'fixed'; temp.style.opacity = '0';
            document.body.appendChild(temp); temp.select();
            try { document.execCommand('copy'); done(true); } catch (e) { done(false); }
            document.body.removeChild(temp);
        }
    };

    staffData.forEach(function (s) {
        const el = document.getElementById('qr-' + s.id);
        if (!el) return;
        new QRCode(el, { text: s.url, width: 64, height: 64, colorDark: '#000000', colorLight: '#FFFFFF', correctLevel: QRCode.CorrectLevel.M });
    });

    window.downloadQR = function (id, name) {
        const s = staffData.find(x => String(x.id) === String(id));
        if (!s) return;
        const btn = (window.event && window.event.currentTarget) || null;
        if (btn) { btn.textContent = 'กำลังสร้าง...'; btn.style.pointerEvents = 'none'; }
        const reset = function (ok) {
            if (btn) { btn.textContent = 'ดาวน์โหลด QR'; btn.style.pointerEvents = 'auto'; }
            if (ok) {
                window.toast('success', 'ดาวน์โหลด QR แล้ว',
                             'ไฟล์ QR_' + name + '.png อยู่ในโฟลเดอร์ดาวน์โหลด — พิมพ์ติดโต๊ะหรือนามบัตรได้เลย');
            } else {
                window.toast('error', 'สร้างไฟล์ QR ไม่สำเร็จ', 'ลองรีเฟรชหน้าแล้วกดใหม่อีกครั้ง');
            }
        };
        const holder = document.getElementById('qrHiddenHolder');
        if (!holder) return;
        holder.innerHTML = '';
        // ระดับการกู้คืน H (30%) — ทนรอยขีดข่วน/สติกเกอร์โค้งงอได้ดีกว่าตอนพิมพ์จริง
        new QRCode(holder, { text: s.url, width: 1000, height: 1000, colorDark: '#000000', colorLight: '#FFFFFF', correctLevel: QRCode.CorrectLevel.H });

        // เติมขอบขาวรอบโค้ด (quiet zone) — ถ้าพิมพ์แบบชิดขอบ เครื่องสแกนมักอ่านไม่ออก
        const withQuietZone = function (srcCanvas) {
            try {
                const pad = Math.round(srcCanvas.width * 0.08);
                const out = document.createElement('canvas');
                out.width  = srcCanvas.width  + pad * 2;
                out.height = srcCanvas.height + pad * 2;
                const ctx = out.getContext('2d');
                ctx.fillStyle = '#FFFFFF';
                ctx.fillRect(0, 0, out.width, out.height);
                ctx.drawImage(srcCanvas, pad, pad);
                return out.toDataURL('image/png');
            } catch (e) {
                return srcCanvas.toDataURL('image/png');
            }
        };

        const finish = function (dataUrl) {
            const a = document.createElement('a');
            a.href = dataUrl; a.download = 'QR_' + name + '.png';
            document.body.appendChild(a); a.click(); document.body.removeChild(a);
            reset(true);
        };
        const tryExport = function () {
            const canvas = holder.querySelector('canvas');
            const img = holder.querySelector('img');
            if (canvas) { try { finish(withQuietZone(canvas)); } catch (e) { reset(false); } return true; }
            if (img && img.src) { finish(img.src); return true; }
            return false;
        };
        if (tryExport()) return;
        const observer = new MutationObserver(() => { if (tryExport()) observer.disconnect(); });
        observer.observe(holder, { childList: true, subtree: true });
        setTimeout(() => { observer.disconnect(); if (btn && btn.textContent === 'กำลังสร้าง...') { reset(false); } }, 3000);
    };
})();
</script>

<div id="qrHiddenHolder" style="position:absolute; width:1000px; height:1000px; opacity:0; pointer-events:none; top:0; left:0; z-index:-1;"></div>

<?php require 'includes/footer.php'; ?>
