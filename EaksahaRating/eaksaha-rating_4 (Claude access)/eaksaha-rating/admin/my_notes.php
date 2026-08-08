<?php
// ============================================================
//  ข้อความจากหัวหน้า — สำหรับบัญชีพนักงานขาย
// ------------------------------------------------------------
//  แสดงข้อความที่ Admin หรือ Manager ฝากไว้กับรีวิวของตัวเอง
//  พร้อมรีวิวต้นเรื่อง เพื่อให้เห็นบริบทว่าหมายถึงเคสไหน
//
//  กด "รับทราบ" เพื่อเคลียร์ตัวเลขแจ้งเตือน
//  ข้อความไม่หายไปไหน ยังย้อนดูได้ตลอด
// ============================================================

require '../conn/config.php';
require 'includes/auth.php';

// ทุกคำขอที่เปลี่ยนแปลงข้อมูลต้องมาจากหน้าจอของเราจริง
csrf_check();

$pageTitle  = 'ข้อความจากหัวหน้า';
$activePage = 'my_notes';

if (user_role() !== 'sales') {
    header('Location: ' . role_home());
    exit;
}

$myId = user_staff_id();
if ($myId === null) {
    header('Location: my_reviews.php');
    exit;
}

// ----- กดรับทราบ -----
// ใช้รูปแบบ POST แล้ว redirect กลับ กันไม่ให้กดรีเฟรชแล้วส่งซ้ำ
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (($_POST['action'] ?? '') === 'read_one') {
            // WHERE staff_id ด้วยเสมอ กันคนแก้ id ใน form เพื่อไปอ่านโน้ตของคนอื่น
            $pdo->prepare('UPDATE review_notes SET is_read = 1, read_at = NOW() WHERE id = ? AND staff_id = ?')
                ->execute([(int) ($_POST['id'] ?? 0), $myId]);
        } elseif (($_POST['action'] ?? '') === 'read_all') {
            $pdo->prepare('UPDATE review_notes SET is_read = 1, read_at = NOW() WHERE staff_id = ? AND is_read = 0')
                ->execute([$myId]);
        }
    } catch (PDOException $e) { /* ยังไม่ได้รัน roles_migration.sql */ }

    header('Location: my_notes.php');
    exit;
}

$notes  = [];
$unread = 0;
try {
    $st = $pdo->prepare("
        SELECT n.*, r.score, r.feedback, r.created_at AS review_at
        FROM review_notes n
        JOIN ratings r ON r.id = n.rating_id
        WHERE n.staff_id = ?
        ORDER BY n.is_read ASC, n.created_at DESC
        LIMIT 200
    ");
    $st->execute([$myId]);
    $notes = $st->fetchAll();
    $unread = count(array_filter($notes, fn($n) => (int) $n['is_read'] === 0));
} catch (PDOException $e) { /* ตารางยังไม่มี */ }

$THAI_MON = ['', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
$fmt = fn($ts) => date('j', strtotime($ts)) . ' ' . $THAI_MON[(int) date('n', strtotime($ts))] . ' ' . date('Y H:i', strtotime($ts));

require 'includes/head.php';
?>
<style>
.nt {
  background: var(--panel); border: 1px solid var(--line); border-radius: 12px;
  padding: 16px 18px; margin-bottom: 12px; box-shadow: var(--sh-1);
}
.nt.unread { border-left: 3px solid var(--warn); }
.nt-head { display: flex; align-items: center; gap: 9px; flex-wrap: wrap; }
.nt-from { font-weight: 600; color: var(--text); font-size: 14px; }
.nt-role {
  padding: 2px 9px; border-radius: 999px; font-size: 11px; font-weight: 500;
  color: var(--muted); background: var(--panel-3); border: 1px solid var(--line);
}
.nt-when { font-size: 12.5px; color: var(--muted-2); margin-left: auto; }
.nt-msg { margin-top: 10px; color: var(--text); line-height: 1.8; font-size: 14.5px; }
.nt-src {
  margin-top: 12px; padding: 11px 13px;
  background: var(--panel-2); border: 1px solid var(--line); border-radius: 9px;
}
.nt-src-l { font-size: 11.5px; color: var(--muted-2); margin-bottom: 5px; }
.nt-src-t { font-size: 13px; color: var(--muted); line-height: 1.7; }
.nt-foot { margin-top: 12px; display: flex; align-items: center; gap: 10px; }
.nt-read { font-size: 12.5px; color: var(--good); }
</style>

<h1>ข้อความจากหัวหน้า</h1>
<p class="page-sub">
    ข้อความที่หัวหน้าฝากไว้กับรีวิวของคุณ พร้อมรีวิวต้นเรื่องให้ดูประกอบ —
    กดรับทราบเพื่อเคลียร์แจ้งเตือน ข้อความจะยังอยู่ให้ย้อนดูได้เสมอ
</p>

<?php if ($unread > 0): ?>
    <form method="POST" style="margin-bottom:16px;">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="read_all">
        <button type="submit" class="btn btn-secondary">รับทราบทั้งหมด (<?= $unread ?>)</button>
    </form>
<?php endif; ?>

<?php if (empty($notes)): ?>
    <div class="nt" style="text-align:center; padding:40px 20px; color:var(--muted);">
        ยังไม่มีข้อความจากหัวหน้า
    </div>
<?php else: ?>
    <?php foreach ($notes as $n): $sc = (int) $n['score']; $isUnread = (int) $n['is_read'] === 0; ?>
        <div class="nt <?= $isUnread ? 'unread' : '' ?>">
            <div class="nt-head">
                <span class="nt-from"><?= htmlspecialchars($n['author_name']) ?></span>
                <span class="nt-role"><?= htmlspecialchars(role_label($n['author_role'])) ?></span>
                <span class="nt-when"><?= htmlspecialchars($fmt($n['created_at'])) ?></span>
            </div>

            <div class="nt-msg"><?= nl2br(htmlspecialchars($n['note'])) ?></div>

            <div class="nt-src">
                <div class="nt-src-l">
                    เกี่ยวกับรีวิวเมื่อ <?= htmlspecialchars($fmt($n['review_at'])) ?> ·
                    <span class="stars-display"><span aria-hidden="true"><?= str_repeat('★', $sc) . str_repeat('☆', 5 - $sc) ?></span></span>
                </div>
                <div class="nt-src-t"><?= $n['feedback'] !== null && $n['feedback'] !== '' ? nl2br(htmlspecialchars($n['feedback'])) : '-' ?></div>
            </div>

            <div class="nt-foot">
                <?php if ($isUnread): ?>
                    <form method="POST">
        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="read_one">
                        <input type="hidden" name="id" value="<?= (int) $n['id'] ?>">
                        <button type="submit" class="btn btn-primary btn-sm">รับทราบ</button>
                    </form>
                <?php else: ?>
                    <span class="nt-read">รับทราบแล้ว<?= $n['read_at'] ? ' · ' . htmlspecialchars($fmt($n['read_at'])) : '' ?></span>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php require 'includes/footer.php'; ?>
