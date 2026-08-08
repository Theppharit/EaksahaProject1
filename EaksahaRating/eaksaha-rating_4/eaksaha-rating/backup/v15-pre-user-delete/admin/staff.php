<?php
require '../conn/config.php';
require 'includes/auth.php';
// ทุกคำขอที่เปลี่ยนแปลงข้อมูลต้องมาจากหน้าจอของเราจริง
csrf_check();

require 'includes/flash.php';

require_perm('manage_staff');
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
    flash('success', 'ลบพนักงานขายแล้ว', 'ลิงก์และ QR ของคนนี้ใช้ไม่ได้อีกต่อไป');
    flash_redirect('staff.php');
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
                $newId = (int) $pdo->lastInsertId();
                flash('success', 'เพิ่ม ' . $name . ' แล้ว',
                      'ระบบสร้างโค้ด ' . $code . ' พร้อม QR และลิงก์ประเมินให้เรียบร้อย',
                      ['text' => 'ดูในรายชื่อด้านล่าง', 'href' => '#row-' . $newId]);
                flash_redirect('staff.php?hl=' . $newId);
            } else {
                $stmt = $pdo->prepare('UPDATE staff SET name = ?, position = ?, brand_id = ?, photo = ? WHERE id = ?');
                $stmt->execute([$name, $position, $brandId, $photoName, $id]);
                flash('success', 'บันทึกการแก้ไขแล้ว', $name . ' · โค้ด ' . $code . ' (ลิงก์และ QR เดิมยังใช้ได้ตามปกติ)',
                      ['text' => 'ดูในรายชื่อด้านล่าง', 'href' => '#row-' . $id]);
                flash_redirect('staff.php?hl=' . $id);
            }
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

// ══════════════════════════════════════════════════════════
//  base URL ของหน้าให้คะแนน (ใช้ทำ QR / ลิงก์แจกลูกค้า)
// ══════════════════════════════════════════════════════════
// ตรรกะทั้งหมดอยู่ที่ rate_base_url() ใน conn/config.php ที่เดียว
// หน้าไหนก็ตามที่ต้องสร้างลิงก์ให้ลูกค้า ต้องเรียกตัวนี้ ห้ามประกอบ URL เอง
$qrInfo      = rate_base_url();
$rateBaseUrl = $qrInfo['url'];
$qrHost      = $qrInfo['host'];
$qrSource    = $qrInfo['source'];
$qrIsLocal   = ($qrSource === 'local');   // ใช้กับมือถือลูกค้าไม่ได้จริงๆ
$qrIsGuess   = ($qrSource === 'guess');   // ใช้ได้ แต่ระบบเดา IP ให้ ควรยืนยันก่อน

require 'includes/head.php';
?>
<h1>พนักงานขาย</h1>
<p class="page-sub">จัดการรายชื่อเซลล์และลิงก์ประเมินของแต่ละคน — ทำตาม 3 ขั้นตอนนี้ได้เลย</p>

<?php if ($qrIsLocal): ?>
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
                                <div class="st-code">โค้ด <code><?= htmlspecialchars($row['code']) ?></code></div>
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
                            </div>
                        </td>

                        <td>
                            <a href="staff.php?edit=<?= (int) $row['id'] ?>#edit-form" class="btn btn-secondary btn-sm">แก้ไข</a>
                            <form method="POST" style="display:inline;"
                                  onsubmit="return confirm('ลบ <?= htmlspecialchars($row['name'], ENT_QUOTES) ?>
        <?= csrf_field() ?> ออกจากระบบ?\n\nรีวิวเก่าของคนนี้จะถูกลบตามไปด้วย และ QR ที่แจกไปแล้วจะใช้ไม่ได้อีก');">
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
