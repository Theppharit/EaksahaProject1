<?php
// ============================================================
//  ผู้ใช้งานระบบ — เฉพาะผู้ดูแลระบบ
// ------------------------------------------------------------
//  เพิ่ม/แก้/ปิดการใช้งานบัญชี และกำหนดสิทธิ์
//
//  กฎกันพลาดที่ใส่ไว้
//    • ห้ามปิดหรือลดสิทธิ์บัญชีตัวเอง — กันล็อกตัวเองออกจากระบบ
//    • ต้องเหลือ admin ที่ใช้งานได้อย่างน้อย 1 บัญชีเสมอ
//    • บัญชี sales ต้องผูกกับพนักงานขาย ไม่งั้นล็อกอินได้แต่ไม่เห็นอะไร
//    • ปิดการใช้งานแทนการลบ เพื่อให้ประวัติข้อความที่เคยฝากไว้ไม่หาย
// ============================================================

require '../conn/config.php';
require 'includes/auth.php';
require_perm('manage_users');

$pageTitle  = 'ผู้ใช้งานระบบ';
$activePage = 'users';

$message = '';
$messageType = '';
$meId = (int) ($_SESSION['admin_id'] ?? 0);

/** จำนวน admin ที่ยังใช้งานได้ */
function activeAdminCount(PDO $pdo, int $exceptId = 0): int
{
    $st = $pdo->prepare("SELECT COUNT(*) FROM admin_users WHERE role = 'admin' AND is_active = 1 AND id <> ?");
    $st->execute([$exceptId]);
    return (int) $st->fetchColumn();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id     = (int) ($_POST['id'] ?? 0);

    try {
        // ---------- เพิ่ม / แก้ไข ----------
        if ($action === 'create' || $action === 'update') {
            $username = trim($_POST['username'] ?? '');
            $display  = trim($_POST['display_name'] ?? '');
            $role     = in_array($_POST['role'] ?? '', ['admin', 'manager', 'sales'], true) ? $_POST['role'] : 'sales';
            $staffId  = ($_POST['staff_id'] ?? '') !== '' ? (int) $_POST['staff_id'] : null;
            $password = $_POST['password'] ?? '';
            $active   = isset($_POST['is_active']) ? 1 : 0;

            if ($username === '') {
                throw new RuntimeException('กรุณากรอกชื่อผู้ใช้');
            }
            if ($role === 'sales' && $staffId === null) {
                throw new RuntimeException('บัญชีพนักงานขายต้องเลือกว่าเป็นพนักงานคนไหน');
            }
            if ($role !== 'sales') {
                $staffId = null;   // มีเฉพาะ sales ที่ต้องผูกกับพนักงาน
            }

            // ห้ามลดสิทธิ์หรือปิดบัญชีตัวเอง
            if ($action === 'update' && $id === $meId) {
                if ($role !== 'admin')  throw new RuntimeException('เปลี่ยนสิทธิ์ของบัญชีตัวเองไม่ได้ ให้ผู้ดูแลคนอื่นเป็นคนเปลี่ยนให้');
                if ($active !== 1)      throw new RuntimeException('ปิดการใช้งานบัญชีตัวเองไม่ได้');
            }
            // ต้องเหลือ admin ที่ใช้งานได้อย่างน้อย 1 คน
            if ($action === 'update' && ($role !== 'admin' || $active !== 1) && activeAdminCount($pdo, $id) === 0) {
                throw new RuntimeException('ต้องมีผู้ดูแลระบบที่ใช้งานได้อย่างน้อย 1 บัญชี');
            }

            if ($action === 'create') {
                if (strlen($password) < 8) {
                    throw new RuntimeException('รหัสผ่านต้องยาวอย่างน้อย 8 ตัวอักษร');
                }
                $st = $pdo->prepare(
                    'INSERT INTO admin_users (username, password, role, staff_id, display_name, is_active)
                     VALUES (?, ?, ?, ?, ?, ?)'
                );
                $st->execute([$username, password_hash($password, PASSWORD_DEFAULT), $role, $staffId, $display ?: null, $active]);
                $message = 'เพิ่มผู้ใช้เรียบร้อย';
            } else {
                if ($password !== '') {
                    if (strlen($password) < 8) {
                        throw new RuntimeException('รหัสผ่านต้องยาวอย่างน้อย 8 ตัวอักษร');
                    }
                    $pdo->prepare('UPDATE admin_users SET password = ? WHERE id = ?')
                        ->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
                }
                $st = $pdo->prepare(
                    'UPDATE admin_users SET username = ?, role = ?, staff_id = ?, display_name = ?, is_active = ? WHERE id = ?'
                );
                $st->execute([$username, $role, $staffId, $display ?: null, $active, $id]);
                $message = 'บันทึกการแก้ไขเรียบร้อย';
            }
            $messageType = 'success';
        }

        // ---------- สลับเปิด/ปิดการใช้งาน ----------
        if ($action === 'toggle') {
            if ($id === $meId) {
                throw new RuntimeException('ปิดการใช้งานบัญชีตัวเองไม่ได้');
            }
            $cur = $pdo->prepare('SELECT role, is_active FROM admin_users WHERE id = ?');
            $cur->execute([$id]);
            $u = $cur->fetch();
            if (!$u) throw new RuntimeException('ไม่พบบัญชีนี้');

            $newActive = (int) $u['is_active'] === 1 ? 0 : 1;
            if ($newActive === 0 && $u['role'] === 'admin' && activeAdminCount($pdo, $id) === 0) {
                throw new RuntimeException('ต้องมีผู้ดูแลระบบที่ใช้งานได้อย่างน้อย 1 บัญชี');
            }
            $pdo->prepare('UPDATE admin_users SET is_active = ? WHERE id = ?')->execute([$newActive, $id]);
            $message = $newActive === 1 ? 'เปิดการใช้งานบัญชีแล้ว' : 'ปิดการใช้งานบัญชีแล้ว';
            $messageType = 'success';
        }
    } catch (RuntimeException $e) {
        $message = $e->getMessage();
        $messageType = 'error';
    } catch (PDOException $e) {
        $message = 'บันทึกไม่สำเร็จ — ชื่อผู้ใช้อาจซ้ำกับที่มีอยู่แล้ว';
        $messageType = 'error';
    }
}

// ----- ข้อมูลสำหรับหน้าจอ -----
$editData = null;
if (isset($_GET['edit'])) {
    $st = $pdo->prepare('SELECT * FROM admin_users WHERE id = ?');
    $st->execute([(int) $_GET['edit']]);
    $editData = $st->fetch() ?: null;
}

$users = $pdo->query("
    SELECT u.*, s.name AS staff_name
    FROM admin_users u
    LEFT JOIN staff s ON s.id = u.staff_id
    ORDER BY FIELD(u.role,'admin','manager','sales'), u.username
")->fetchAll();

$staffList = $pdo->query('SELECT id, name, code FROM staff ORDER BY name ASC')->fetchAll();

$roleNames = ['admin' => 'ผู้ดูแลระบบ', 'manager' => 'ผู้จัดการ', 'sales' => 'พนักงานขาย'];
$roleHelp  = [
    'admin'   => 'ทำได้ทุกอย่าง รวมถึงแก้คะแนนและจัดการผู้ใช้',
    'manager' => 'เห็นได้ทุกอย่าง ส่งออกไฟล์และฝากข้อความถึงพนักงานได้ แต่แก้ไขข้อมูลไม่ได้',
    'sales'   => 'เห็นเฉพาะคะแนนและรีวิวของตัวเอง',
];

require 'includes/head.php';
?>
<style>
.role-tag { padding: 3px 10px; border-radius: 999px; font-size: 11.5px; font-weight: 500; white-space: nowrap; }
.role-tag.admin   { color: var(--accent-soft); background: rgba(var(--glow),0.09);  border: 1px solid rgba(var(--glow),0.26); }
.role-tag.manager { color: var(--mint-soft);   background: rgba(var(--glow2),0.10); border: 1px solid rgba(var(--glow2),0.26); }
.role-tag.sales   { color: var(--muted);       background: var(--panel-3);          border: 1px solid var(--line); }
.u-off td { opacity: 0.55; }
.u-name { font-weight: 600; color: var(--text); }
.u-sub  { font-size: 12.5px; color: var(--muted-2); margin-top: 3px; }
.role-help { font-size: 12.5px; color: var(--muted); line-height: 1.7; margin-top: 6px; }
.me-tag { font-size: 11.5px; color: var(--good); margin-left: 6px; }
</style>

<h1>ผู้ใช้งานระบบ</h1>
<p class="page-sub">กำหนดว่าใครเข้าระบบได้ และเห็นอะไรได้บ้าง</p>

<?php if ($message): ?>
    <div class="alert alert-<?= htmlspecialchars($messageType) ?>"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<h2 class="section-title" id="form"><?= $editData ? 'แก้ไขบัญชี: ' . htmlspecialchars($editData['username']) : 'เพิ่มผู้ใช้ใหม่' ?></h2>
<div class="form-card">
    <form method="POST" action="#form">
        <input type="hidden" name="action" value="<?= $editData ? 'update' : 'create' ?>">
        <?php if ($editData): ?><input type="hidden" name="id" value="<?= (int) $editData['id'] ?>"><?php endif; ?>

        <div class="field">
            <label for="username">ชื่อผู้ใช้ (สำหรับล็อกอิน)</label>
            <input type="text" id="username" name="username" required
                   value="<?= htmlspecialchars($editData['username'] ?? '') ?>" placeholder="เช่น manager1">
        </div>

        <div class="field">
            <label for="display_name">ชื่อที่แสดง</label>
            <input type="text" id="display_name" name="display_name"
                   value="<?= htmlspecialchars($editData['display_name'] ?? '') ?>" placeholder="เช่น คุณสมชาย (ผู้จัดการสาขา)">
            <div class="hint">เว้นว่างได้ ระบบจะใช้ชื่อผู้ใช้แทน</div>
        </div>

        <div class="field">
            <label for="role">สิทธิ์การใช้งาน</label>
            <select id="role" name="role" class="filter-select" style="width:100%;" onchange="syncStaff()">
                <?php foreach ($roleNames as $k => $v): ?>
                    <option value="<?= $k ?>" <?= ($editData['role'] ?? 'sales') === $k ? 'selected' : '' ?>><?= $v ?></option>
                <?php endforeach; ?>
            </select>
            <div class="role-help" id="roleHelp"></div>
        </div>

        <div class="field" id="staffField">
            <label for="staff_id">ผูกกับพนักงานขาย</label>
            <select id="staff_id" name="staff_id" class="filter-select" style="width:100%;">
                <option value="">— เลือกพนักงานขาย —</option>
                <?php foreach ($staffList as $s): ?>
                    <option value="<?= (int) $s['id'] ?>" <?= (int) ($editData['staff_id'] ?? 0) === (int) $s['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($s['name']) ?> (<?= htmlspecialchars($s['code']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
            <div class="hint">จำเป็นสำหรับบัญชีพนักงานขาย — ระบบใช้ค่านี้ตัดสินว่ารีวิวไหนคือ "ของตัวเอง"</div>
        </div>

        <div class="field">
            <label for="password">รหัสผ่าน<?= $editData ? ' (เว้นว่างไว้ถ้าไม่เปลี่ยน)' : '' ?></label>
            <input type="password" id="password" name="password" <?= $editData ? '' : 'required' ?>
                   placeholder="อย่างน้อย 8 ตัวอักษร" autocomplete="new-password">
        </div>

        <div class="field">
            <label class="check-pill" style="display:inline-flex;">
                <input type="checkbox" name="is_active" value="1" <?= ($editData === null || (int) $editData['is_active'] === 1) ? 'checked' : '' ?>>
                เปิดใช้งานบัญชีนี้
            </label>
        </div>

        <button type="submit" class="btn btn-primary"><?= $editData ? 'บันทึกการแก้ไข' : 'เพิ่มผู้ใช้' ?></button>
        <?php if ($editData): ?><a href="users.php" class="btn btn-secondary">ยกเลิก</a><?php endif; ?>
    </form>
</div>

<h2 class="section-title">บัญชีทั้งหมด<span class="count-badge"><?= number_format(count($users)) ?></span></h2>
<div class="table-card">
    <table>
        <thead>
            <tr><th>ผู้ใช้</th><th>สิทธิ์</th><th>ผูกกับพนักงานขาย</th><th>สถานะ</th><th>จัดการ</th></tr>
        </thead>
        <tbody>
            <?php foreach ($users as $u): $isMe = (int) $u['id'] === $meId; ?>
                <tr class="<?= (int) $u['is_active'] === 1 ? '' : 'u-off' ?>">
                    <td class="wrap-cell">
                        <div class="u-name">
                            <?= htmlspecialchars($u['display_name'] ?: $u['username']) ?>
                            <?php if ($isMe): ?><span class="me-tag">(คุณ)</span><?php endif; ?>
                        </div>
                        <div class="u-sub"><?= htmlspecialchars($u['username']) ?></div>
                    </td>
                    <td><span class="role-tag <?= htmlspecialchars($u['role']) ?>"><?= htmlspecialchars($roleNames[$u['role']] ?? $u['role']) ?></span></td>
                    <td class="wrap-cell">
                        <?php if ($u['role'] === 'sales'): ?>
                            <?= $u['staff_name'] ? htmlspecialchars($u['staff_name']) : '<span style="color:var(--bad);">ยังไม่ได้ผูก</span>' ?>
                        <?php else: ?>
                            <span class="dash-cell">-</span>
                        <?php endif; ?>
                    </td>
                    <td><?= (int) $u['is_active'] === 1 ? 'ใช้งานได้' : '<span style="color:var(--muted-2);">ปิดใช้งาน</span>' ?></td>
                    <td>
                        <a href="users.php?edit=<?= (int) $u['id'] ?>#form" class="btn btn-secondary btn-sm">แก้ไข</a>
                        <?php if (!$isMe): ?>
                            <form method="POST" style="display:inline;"
                                  onsubmit="return confirm('<?= (int) $u['is_active'] === 1 ? 'ปิดการใช้งานบัญชีนี้?' : 'เปิดการใช้งานบัญชีนี้?' ?>');">
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                                <button type="submit" class="btn <?= (int) $u['is_active'] === 1 ? 'btn-danger' : 'btn-secondary' ?> btn-sm">
                                    <?= (int) $u['is_active'] === 1 ? 'ปิดใช้งาน' : 'เปิดใช้งาน' ?>
                                </button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script>
// ช่อง "ผูกกับพนักงานขาย" จำเป็นเฉพาะบัญชีพนักงานขาย
// ซ่อนไปเลยเมื่อไม่เกี่ยว จะได้ไม่ต้องเดาว่าต้องกรอกหรือเปล่า
var ROLE_HELP = <?= json_encode($roleHelp, JSON_UNESCAPED_UNICODE) ?>;
function syncStaff() {
    var role = document.getElementById('role').value;
    document.getElementById('staffField').style.display = role === 'sales' ? '' : 'none';
    document.getElementById('roleHelp').textContent = ROLE_HELP[role] || '';
}
syncStaff();
</script>

<?php require 'includes/footer.php'; ?>
