<?php
require '../conn/config.php';
require 'includes/auth.php';

$pageTitle  = 'พนักงานขาย';
$activePage = 'staff';

$message = '';
$messageType = '';

$uploadDir = 'uploads/staff/';

// ----- ลบพนักงานขาย -----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $id = (int) ($_POST['id'] ?? 0);

    $stmt = $pdo->prepare('SELECT photo FROM staff WHERE id = ?');
    $stmt->execute([$id]);
    $old = $stmt->fetch();
    if ($old && $old['photo'] && file_exists($uploadDir . $old['photo'])) {
        unlink($uploadDir . $old['photo']);
    }

    $pdo->prepare('DELETE FROM staff WHERE id = ?')->execute([$id]);
    $message = 'ลบข้อมูลพนักงานขายสำเร็จ';
    $messageType = 'success';
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
            if ($_POST['action'] === 'create') {
                $stmt = $pdo->prepare('INSERT INTO staff (code, name, position, brand_id, photo) VALUES (?, ?, ?, ?, ?)');
                $stmt->execute([$code, $name, $position, $brandId, $photoName]);
                $message = 'เพิ่มพนักงานขายสำเร็จ (โค้ด: ' . $code . ')';
            } else {
                $stmt = $pdo->prepare('UPDATE staff SET name = ?, position = ?, brand_id = ?, photo = ? WHERE id = ?');
                $stmt->execute([$name, $position, $brandId, $photoName, $id]);
                $message = 'บันทึกการแก้ไขสำเร็จ';
            }
            $messageType = 'success';
        }
    }
}

// ----- ข้อมูลประกอบ -----
$brandOptions = $pdo->query('SELECT id, name, color FROM brands ORDER BY sort_order ASC, id ASC')->fetchAll();

$staffList = $pdo->query("
    SELECT s.*, b.name AS brand_name, b.color AS brand_color
    FROM staff s
    LEFT JOIN brands b ON b.id = s.brand_id
    ORDER BY s.id ASC
")->fetchAll();

$editData = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM staff WHERE id = ?');
    $stmt->execute([(int) $_GET['edit']]);
    $editData = $stmt->fetch();
}

// base URL ของหน้าให้คะแนน
$protocol    = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host        = $_SERVER['HTTP_HOST'];
$scriptDir   = dirname(dirname($_SERVER['SCRIPT_NAME'])); // ขึ้นหนึ่งระดับจาก /admin/
$rateBaseUrl = $protocol . '://' . $host . rtrim($scriptDir, '/') . '/rate.php?code=';

require 'includes/head.php';
?>
<h1>พนักงานขาย</h1>
<p class="page-sub">จัดการรายชื่อเซลล์และลิงก์ประเมินของแต่ละคน — ทำตาม 3 ขั้นตอนนี้ได้เลย</p>

<div class="info-steps">
    <div class="info-step">
        <div class="num">1</div>
        <div class="txt"><b>เพิ่มพนักงานขาย</b>กรอกชื่อ ตำแหน่ง เลือกแบรนด์ แล้วกดบันทึก ระบบจะสร้างโค้ดและลิงก์ประเมินให้อัตโนมัติ</div>
    </div>
    <div class="info-step">
        <div class="num">2</div>
        <div class="txt"><b>แจกลิงก์ / QR ให้เซลล์</b>ดาวน์โหลด QR ไปติดที่โต๊ะหรือนามบัตร หรือคัดลอกลิงก์ส่งให้ลูกค้าโดยตรง</div>
    </div>
    <div class="info-step">
        <div class="num">3</div>
        <div class="txt"><b>ลูกค้าสแกนและให้คะแนน</b>ผลจะเข้าแดชบอร์ดและรายงานทันที ไม่ต้องทำอะไรเพิ่ม</div>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?= htmlspecialchars($messageType) ?>"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<h2 class="section-title"><?= $editData ? 'แก้ไขข้อมูลพนักงานขาย' : 'เพิ่มพนักงานขายใหม่' ?></h2>

<div class="form-card wide">
    <form method="POST" enctype="multipart/form-data">
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

<h2 class="section-title">รายชื่อพนักงานขายทั้งหมด</h2>

<script id="staffData" type="application/json">
<?= json_encode(array_map(fn($r) => [
    'id'   => $r['id'],
    'url'  => $rateBaseUrl . rawurlencode($r['code']),
    'name' => $r['name'],
], $staffList), JSON_UNESCAPED_UNICODE) ?>
</script>

<div class="table-card">
    <table>
        <thead>
            <tr>
                <th>รูปภาพ</th>
                <th>ชื่อ-นามสกุล</th>
                <th>แบรนด์</th>
                <th>ตำแหน่ง</th>
                <th>โค้ด</th>
                <th>QR Code</th>
                <th>ลิงก์ให้คะแนน</th>
                <th>จัดการ</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($staffList)): ?>
                <tr><td colspan="8">ยังไม่มีข้อมูลพนักงานขาย</td></tr>
            <?php else: ?>
                <?php foreach ($staffList as $row): ?>
                    <?php $rateUrl = $rateBaseUrl . rawurlencode($row['code']); ?>
                    <tr>
                        <td>
                            <?php if (!empty($row['photo'])): ?>
                                <img src="uploads/staff/<?= htmlspecialchars($row['photo']) ?>" alt="<?= htmlspecialchars($row['name']) ?>" class="table-thumb">
                            <?php else: ?>
                                <div class="table-thumb-initial"><?= htmlspecialchars(mb_substr($row['name'], 0, 1, 'UTF-8')) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="wrap-cell"><?= htmlspecialchars($row['name']) ?></td>
                        <td>
                            <?php if (!empty($row['brand_name'])): ?>
                                <span class="brand-tag" style="background:<?= htmlspecialchars($row['brand_color'] ?? '#0EA5E9') ?>"><?= htmlspecialchars($row['brand_name']) ?></span>
                            <?php else: ?>
                                <span class="brand-tag none">ไม่ระบุ</span>
                            <?php endif; ?>
                        </td>
                        <td class="wrap-cell"><?= htmlspecialchars($row['position']) ?></td>
                        <td><code><?= htmlspecialchars($row['code']) ?></code></td>
                        <td>
                            <div id="qr-<?= (int)$row['id'] ?>" class="qr-thumb"></div>
                            <a href="#" id="dl-<?= (int)$row['id'] ?>" class="btn btn-secondary btn-sm" style="margin-top:6px;font-size:11px;"
                               onclick="downloadQR(<?= (int)$row['id'] ?>, '<?= htmlspecialchars($row['name']) ?>'); return false;">ดาวน์โหลด QR</a>
                        </td>
                        <td>
                            <div class="link-actions">
                                <button type="button" class="btn btn-secondary btn-sm"
                                        onclick="copyRateLink(this, '<?= htmlspecialchars($rateUrl, ENT_QUOTES) ?>')">คัดลอกลิงก์</button>
                                <a href="<?= htmlspecialchars($rateUrl) ?>" target="_blank">เปิดลิงก์</a>
                            </div>
                        </td>
                        <td>
                            <a href="staff.php?edit=<?= (int) $row['id'] ?>" class="btn btn-secondary btn-sm">แก้ไข</a>
                            <form method="POST" style="display:inline;margin-top:4px;" onsubmit="return confirm('ยืนยันการลบพนักงานขายนี้?');">
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

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
(function () {
    const staffData = JSON.parse(document.getElementById('staffData').textContent);

    window.copyRateLink = function (btn, url) {
        const original = btn.textContent;
        const done = ok => { btn.textContent = ok ? 'คัดลอกแล้ว ✓' : 'คัดลอกไม่สำเร็จ'; setTimeout(() => btn.textContent = original, 1500); };
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
        new QRCode(el, { text: s.url, width: 56, height: 56, colorDark: '#000000', colorLight: '#FFFFFF', correctLevel: QRCode.CorrectLevel.M });
    });

    window.downloadQR = function (id, name) {
        const s = staffData.find(x => String(x.id) === String(id));
        if (!s) return;
        const btn = document.getElementById('dl-' + id);
        if (btn) { btn.textContent = 'กำลังสร้างไฟล์...'; btn.style.pointerEvents = 'none'; }
        const holder = document.getElementById('qrHiddenHolder');
        if (!holder) return;
        holder.innerHTML = '';
        new QRCode(holder, { text: s.url, width: 1000, height: 1000, colorDark: '#000000', colorLight: '#FFFFFF', correctLevel: QRCode.CorrectLevel.M });

        const finish = function (dataUrl) {
            const a = document.createElement('a');
            a.href = dataUrl; a.download = 'QR_' + name + '.png';
            document.body.appendChild(a); a.click(); document.body.removeChild(a);
            if (btn) { btn.textContent = 'ดาวน์โหลด QR'; btn.style.pointerEvents = 'auto'; }
        };
        const tryExport = function () {
            const canvas = holder.querySelector('canvas');
            const img = holder.querySelector('img');
            if (canvas) { try { finish(canvas.toDataURL('image/png')); } catch (e) { if (btn) { btn.textContent = 'โหลดไม่สำเร็จ ลองใหม่'; btn.style.pointerEvents = 'auto'; } } return true; }
            if (img && img.src) { finish(img.src); return true; }
            return false;
        };
        if (tryExport()) return;
        const observer = new MutationObserver(() => { if (tryExport()) observer.disconnect(); });
        observer.observe(holder, { childList: true, subtree: true });
        setTimeout(() => { observer.disconnect(); if (btn && btn.textContent === 'กำลังสร้างไฟล์...') { btn.textContent = 'โหลดไม่สำเร็จ ลองใหม่'; btn.style.pointerEvents = 'auto'; } }, 3000);
    };
})();
</script>

<div id="qrHiddenHolder" style="position:absolute; width:1000px; height:1000px; opacity:0; pointer-events:none; top:0; left:0; z-index:-1;"></div>

<?php require 'includes/footer.php'; ?>
