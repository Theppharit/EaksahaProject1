<?php
require '../conn/config.php';
require 'includes/auth.php';

$pageTitle  = 'แบรนด์รถ';
$activePage = 'brands';

$message = '';
$messageType = '';

// ----- ลบแบรนด์ -----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $id = (int) ($_POST['id'] ?? 0);
    // ถ้ามีเซลล์สังกัดอยู่ จะไม่ถูกลบ (brand_id ถูกตั้งเป็น NULL ตาม FK)
    $pdo->prepare('DELETE FROM brands WHERE id = ?')->execute([$id]);
    $message = 'ลบแบรนด์สำเร็จ';
    $messageType = 'success';
}

// ----- เพิ่ม / แก้ไข แบรนด์ -----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action'] ?? '', ['create', 'update'], true)) {
    $id    = (int) ($_POST['id'] ?? 0);
    $name  = trim($_POST['name'] ?? '');
    $code  = trim($_POST['code'] ?? '');
    $color = trim($_POST['color'] ?? '#0EA5E9');
    $sort  = (int) ($_POST['sort_order'] ?? 0);

    // สร้าง code อัตโนมัติจากชื่อ ถ้าเว้นว่าง
    if ($code === '') {
        $code = strtolower(preg_replace('/[^a-z0-9]+/i', '_', $name));
        $code = trim($code, '_');
    }

    if ($name === '' || $code === '') {
        $message = 'กรุณากรอกชื่อแบรนด์';
        $messageType = 'error';
    } else {
        try {
            if (($_POST['action']) === 'create') {
                $stmt = $pdo->prepare('INSERT INTO brands (name, code, color, sort_order) VALUES (?, ?, ?, ?)');
                $stmt->execute([$name, $code, $color, $sort]);
                $message = 'เพิ่มแบรนด์สำเร็จ';
            } else {
                $stmt = $pdo->prepare('UPDATE brands SET name = ?, code = ?, color = ?, sort_order = ? WHERE id = ?');
                $stmt->execute([$name, $code, $color, $sort, $id]);
                $message = 'บันทึกการแก้ไขสำเร็จ';
            }
            $messageType = 'success';
        } catch (PDOException $e) {
            $message = 'ไม่สามารถบันทึกได้ (โค้ดแบรนด์อาจซ้ำกับที่มีอยู่)';
            $messageType = 'error';
        }
    }
}

// ----- ดึงแบรนด์ทั้งหมด + จำนวนเซลล์ในแต่ละแบรนด์ -----
$brands = $pdo->query("
    SELECT b.*, (SELECT COUNT(*) FROM staff s WHERE s.brand_id = b.id) AS staff_count
    FROM brands b
    ORDER BY b.sort_order ASC, b.id ASC
")->fetchAll();

// ----- ถ้ามาจากปุ่มแก้ไข -----
$editData = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM brands WHERE id = ?');
    $stmt->execute([(int) $_GET['edit']]);
    $editData = $stmt->fetch();
}

require 'includes/head.php';
?>
<h1>แบรนด์รถ</h1>
<p class="page-sub">แบรนด์ที่พนักงานขายสังกัด — สีประจำแบรนด์จะใช้แสดงบนหน้าประเมินของลูกค้าและในกราฟรายงาน</p>

<?php if ($message): ?>
    <div class="alert alert-<?= htmlspecialchars($messageType) ?>"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<h2 class="section-title" id="edit-form"><?= $editData
        ? 'แก้ไขแบรนด์: ' . htmlspecialchars($editData['name'])
        : 'เพิ่มแบรนด์ใหม่' ?></h2>
<div class="form-card">
    <form method="POST">
        <input type="hidden" name="action" value="<?= $editData ? 'update' : 'create' ?>">
        <?php if ($editData): ?>
            <input type="hidden" name="id" value="<?= (int) $editData['id'] ?>">
        <?php endif; ?>

        <div class="field">
            <label for="name">ชื่อแบรนด์</label>
            <input type="text" id="name" name="name" value="<?= htmlspecialchars($editData['name'] ?? '') ?>" placeholder="เช่น DEEPAL" required>
        </div>
        <div class="field">
            <label for="code">โค้ดแบรนด์ (ภาษาอังกฤษ ไม่ซ้ำ)</label>
            <input type="text" id="code" name="code" value="<?= htmlspecialchars($editData['code'] ?? '') ?>" placeholder="เว้นว่างเพื่อสร้างอัตโนมัติ">
            <div class="hint">ใช้อ้างอิงภายในระบบ หากเว้นว่างจะสร้างจากชื่อให้อัตโนมัติ</div>
        </div>
        <div class="field">
            <label for="color">สีประจำแบรนด์</label>
            <input type="color" id="color" name="color" value="<?= htmlspecialchars($editData['color'] ?? '#0EA5E9') ?>" style="height:46px; padding:5px;">
            <div class="hint">ใช้แสดงบนหน้าประเมินและกราฟ</div>
        </div>
        <div class="field">
            <label for="sort_order">ลำดับการแสดง</label>
            <input type="number" id="sort_order" name="sort_order" value="<?= (int) ($editData['sort_order'] ?? 0) ?>">
        </div>

        <button type="submit" class="btn btn-primary"><?= $editData ? 'บันทึกการแก้ไข' : 'เพิ่มแบรนด์' ?></button>
        <?php if ($editData): ?>
            <a href="brands.php" class="btn btn-secondary">ยกเลิก</a>
        <?php endif; ?>
    </form>
</div>

<h2 class="section-title">รายชื่อแบรนด์ทั้งหมด<span class="count-badge"><?= number_format(count($brands)) ?> แบรนด์</span></h2>
<div class="table-card">
    <table>
        <thead>
            <tr>
                <th>ลำดับ</th>
                <th>แบรนด์</th>
                <th>โค้ด</th>
                <th>สี</th>
                <th>จำนวนเซลล์</th>
                <th>จัดการ</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($brands)): ?>
                <tr><td colspan="6">ยังไม่มีข้อมูลแบรนด์</td></tr>
            <?php else: ?>
                <?php foreach ($brands as $b): ?>
                    <tr>
                        <td><?= (int) $b['sort_order'] ?></td>
                        <td><span class="brand-tag" style="background:<?= htmlspecialchars($b['color']) ?>;color:<?= brand_ink($b['color']) ?>;"><?= htmlspecialchars($b['name']) ?></span></td>
                        <td><code><?= htmlspecialchars($b['code']) ?></code></td>
                        <td><span style="display:inline-block;width:22px;height:22px;border-radius:6px;border:1px solid var(--line);vertical-align:middle;background:<?= htmlspecialchars($b['color']) ?>"></span> <?= htmlspecialchars($b['color']) ?></td>
                        <td><?= (int) $b['staff_count'] ?> คน</td>
                        <td>
                            <a href="brands.php?edit=<?= (int) $b['id'] ?>#edit-form" class="btn btn-secondary btn-sm">แก้ไข</a>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('ยืนยันการลบแบรนด์นี้? (เซลล์ที่สังกัดจะถูกตั้งเป็นไม่มีแบรนด์)');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm">ลบ</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require 'includes/footer.php'; ?>
