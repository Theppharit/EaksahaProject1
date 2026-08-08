<?php
// ============================================================
//  ประกาศระบบ — ผู้ดูแลพิมพ์ข้อความให้ทุกคนในระบบเห็น
// ------------------------------------------------------------
//  ใช้ตอนไหน
//    • ระบบส่วนใดส่วนหนึ่งใช้ไม่ได้ชั่วคราว (AI ไม่ให้ดาว / QR ใช้ไม่ได้)
//    • จะปิดปรับปรุงตามเวลาที่นัดไว้
//    • มีเรื่องต้องบอกพนักงานหน้างานพร้อมกันทุกคน
//
//  ประกาศเก็บเป็นไฟล์ JSON ไม่ใช่ฐานข้อมูล (เหตุผลอยู่ใน includes/notice.php)
// ============================================================

require '../conn/config.php';
require 'includes/auth.php';
csrf_check();
require 'includes/flash.php';
require_perm('manage_users');
require_once 'includes/notice.php';

$pageTitle  = 'ประกาศระบบ';
$activePage = 'notice';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'off') {
        // ปิดประกาศแต่เก็บข้อความไว้ — คราวหน้าเปิดใหม่ไม่ต้องพิมพ์ซ้ำ
        $cur = notice_get();
        $cur['on'] = false;
        if (notice_save($cur)) {
            flash('success', 'ปิดประกาศแล้ว', 'ข้อความเดิมยังเก็บไว้ กดเปิดใหม่ได้ทุกเมื่อ');
            flash_redirect('notice.php');
        }
        $message = 'บันทึกไม่สำเร็จ — เขียนไฟล์ conn/system_notice.json ไม่ได้';
    }

    if ($action === 'save') {
        $title = trim($_POST['title'] ?? '');
        $body  = trim($_POST['body'] ?? '');
        $on    = isset($_POST['on']);

        if ($on && $title === '' && $body === '') {
            $message = 'ต้องกรอกหัวข้อหรือรายละเอียดอย่างน้อยหนึ่งอย่าง ก่อนเปิดประกาศ';
        } else {
            $ok = notice_save([
                'on'            => $on,
                'level'         => $_POST['level'] ?? 'info',
                'title'         => $title,
                'body'          => $body,
                'audience'      => $_POST['audience'] ?? 'all',
                'show_customer' => isset($_POST['show_customer']),
                'until'         => $_POST['until'] ?? '',
            ]);

            if ($ok) {
                flash('success',
                      $on ? 'เปิดประกาศแล้ว' : 'บันทึกแล้ว (ยังไม่เปิด)',
                      $on ? 'ทุกคนที่เปิดหน้าไหนก็ตามในระบบจะเห็นแถบนี้ทันที'
                          : 'ข้อความถูกเก็บไว้แล้ว กดเปิดเมื่อไหร่ก็ได้');
                flash_redirect('notice.php');
            }
            $message = 'บันทึกไม่สำเร็จ — เขียนไฟล์ conn/system_notice.json ไม่ได้ '
                     . '(ตรวจสิทธิ์เขียนของโฟลเดอร์ conn)';
        }
    }
}

$n = notice_get();

// ตัวอย่างสำเร็จรูป — คนใช้จะได้ไม่ต้องคิดคำเองตอนของกำลังพัง
// ซึ่งเป็นตอนที่ไม่มีใครมีเวลาคิดคำสวยๆ
$presets = [
    ['warn', 'ระบบให้ดาวอัตโนมัติขัดข้องชั่วคราว',
     "รีวิวจากลูกค้ายังบันทึกได้ตามปกติ ข้อมูลไม่หาย\nแต่ดาวจะขึ้นช้ากว่าปกติ ไม่ต้องให้ลูกค้าประเมินซ้ำนะครับ"],
    ['down', 'ปิดปรับปรุงระบบชั่วคราว',
     "ช่วงเวลาที่ปิด: (ใส่เวลา)\nระหว่างนี้ยังให้ลูกค้าสแกน QR ประเมินได้ตามปกติ"],
    ['info', 'แจ้งพนักงานขายทุกท่าน',
     "(พิมพ์เรื่องที่ต้องการแจ้ง)"],
];

require 'includes/head.php';
?>
<style>
.nt-grid { display: grid; gap: 16px; }
.lv-pick { display: flex; flex-wrap: wrap; gap: 8px; }
.lv-pick label {
  display: inline-flex; align-items: center; gap: 7px; cursor: pointer;
  padding: 8px 14px; border-radius: 9px; font-size: 13.5px; font-weight: 500;
  border: 1px solid var(--line); background: var(--panel-2); color: var(--muted);
}
.lv-pick input { position: absolute; opacity: 0; pointer-events: none; }
.lv-pick label .dot { width: 9px; height: 9px; border-radius: 50%; background: currentColor; }
.lv-pick label.l-info:has(input:checked) { color: #2563EB; border-color: rgba(37,99,235,.5);  background: rgba(37,99,235,.08); }
.lv-pick label.l-warn:has(input:checked) { color: var(--warn); border-color: rgba(180,83,9,.5); background: rgba(180,83,9,.08); }
.lv-pick label.l-down:has(input:checked) { color: var(--bad);  border-color: rgba(193,18,31,.5); background: rgba(193,18,31,.08); }
.preset-row { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 4px; }
.nt-status { font-size: 13px; color: var(--muted); margin-top: 8px; }
.nt-preview-wrap { margin-top: 4px; }
.nt-preview-wrap > .lbl { font-size: 12.5px; color: var(--muted-2); margin-bottom: 8px; display: block; }
</style>

<h1>ประกาศระบบ</h1>
<p class="page-sub">
    พิมพ์ครั้งเดียว ทุกคนที่เปิดระบบเห็นทันทีทุกหน้า — ใช้ตอนระบบบางส่วนใช้ไม่ได้ หรือจะปิดปรับปรุง<br>
    <span class="as-of">ระบบตรวจเจอปัญหาเอง (เช่น AI ค้างไม่ให้ดาว) จะขึ้นแถบให้อัตโนมัติอยู่แล้ว ไม่ต้องมาพิมพ์เอง</span>
</p>

<?php if ($message): ?>
    <script>window.toast('error', 'ทำรายการไม่สำเร็จ', <?= json_encode($message, JSON_UNESCAPED_UNICODE) ?>);</script>
<?php endif; ?>

<?php if (notice_active($n)): ?>
    <div class="nt-preview-wrap">
        <span class="lbl">ตอนนี้ทุกคนเห็นแถบนี้อยู่</span>
        <?= notice_bar(null, 'admin') ?>
    </div>
<?php endif; ?>

<h2 class="section-title">ข้อความประกาศ</h2>
<div class="form-card wide">
    <form method="POST" class="nt-grid">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">

        <div class="field">
            <label>เลือกจากตัวอย่าง (กดแล้วแก้ข้อความต่อได้)</label>
            <div class="preset-row">
                <?php foreach ($presets as $i => $p): ?>
                    <button type="button" class="btn btn-secondary btn-sm"
                            onclick="usePreset(<?= $i ?>)"><?= htmlspecialchars($p[1]) ?></button>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="field">
            <label>ความรุนแรง</label>
            <div class="lv-pick">
                <label class="l-info"><input type="radio" name="level" value="info" <?= $n['level'] === 'info' ? 'checked' : '' ?>><span class="dot"></span> แจ้งให้ทราบ</label>
                <label class="l-warn"><input type="radio" name="level" value="warn" <?= $n['level'] === 'warn' ? 'checked' : '' ?>><span class="dot"></span> ใช้งานได้บางส่วน</label>
                <label class="l-down"><input type="radio" name="level" value="down" <?= $n['level'] === 'down' ? 'checked' : '' ?>><span class="dot"></span> ใช้งานไม่ได้</label>
            </div>
            <div class="hint">เลือกให้ตรงกับความจริง ถ้าใช้สีแรงกับเรื่องเล็กทุกครั้ง คนจะเริ่มมองข้ามแถบนี้</div>
        </div>

        <div class="field">
            <label for="title">หัวข้อ</label>
            <input type="text" id="title" name="title" maxlength="120"
                   value="<?= htmlspecialchars($n['title']) ?>"
                   placeholder="เช่น ระบบให้ดาวอัตโนมัติขัดข้องชั่วคราว">
        </div>

        <div class="field">
            <label for="body">รายละเอียด</label>
            <textarea id="body" name="body" rows="4" maxlength="600"
                      placeholder="บอกให้ครบ 2 อย่าง: ตอนนี้อะไรใช้ไม่ได้ และพนักงานต้องทำอะไร"><?= htmlspecialchars($n['body']) ?></textarea>
            <div class="hint">เขียนให้พนักงานหน้างานอ่านเข้าใจทันที — เขาต้องรู้ว่างานของเขาหายหรือเปล่า</div>
        </div>

        <div class="field">
            <label for="audience">ให้ใครเห็น</label>
            <select id="audience" name="audience" class="filter-select" style="width:100%;">
                <option value="all"   <?= $n['audience'] === 'all'   ? 'selected' : '' ?>>ทุกคนในระบบ (ผู้ดูแล ผู้จัดการ พนักงานขาย)</option>
                <option value="staff" <?= $n['audience'] === 'staff' ? 'selected' : '' ?>>เฉพาะพนักงานขาย</option>
            </select>
        </div>

        <div class="field">
            <label for="until">ปิดประกาศเองเมื่อ (ไม่บังคับ)</label>
            <input type="datetime-local" id="until" name="until" value="<?= htmlspecialchars($n['until']) ?>">
            <div class="hint">
                เว้นว่าง = แสดงจนกว่าจะกดปิดเอง<br>
                ใส่เวลาไว้ดีกว่า เพราะประกาศที่ค้างอยู่หลังเรื่องจบแล้ว จะทำให้คนเลิกเชื่อแถบนี้
            </div>
        </div>

        <div class="field">
            <label class="check-pill" style="display:inline-flex;">
                <input type="checkbox" name="on" value="1" <?= !empty($n['on']) ? 'checked' : '' ?>>
                เปิดแสดงประกาศนี้
            </label>
        </div>

        <div>
            <button type="submit" class="btn btn-primary">บันทึก</button>
            <?php if (!empty($n['on'])): ?>
                <button type="submit" class="btn btn-secondary" name="action" value="off">ปิดประกาศ</button>
            <?php endif; ?>
        </div>

        <?php if ($n['updated_at'] !== ''): ?>
            <div class="nt-status">แก้ไขล่าสุด <?= htmlspecialchars(date('d/m/Y H:i', strtotime($n['updated_at']))) ?></div>
        <?php endif; ?>
    </form>
</div>

<script>
var PRESETS = <?= json_encode($presets, JSON_UNESCAPED_UNICODE) ?>;
function usePreset(i) {
    var p = PRESETS[i];
    if (!p) return;
    document.querySelector('input[name="level"][value="' + p[0] + '"]').checked = true;
    document.getElementById('title').value = p[1];
    document.getElementById('body').value  = p[2];
    document.getElementById('title').focus();
}
</script>

<?php require 'includes/footer.php'; ?>
