<?php
// หน้าที่แสดงเมื่อพยายามเข้าหน้าที่ไม่มีสิทธิ์
// (เช่น พิมพ์ URL ตรงเข้ามา หรือกดลิงก์เก่าที่บุ๊กมาร์กไว้)
$pageTitle  = 'ไม่มีสิทธิ์เข้าถึง';
$activePage = '';
require __DIR__ . '/head.php';
?>
<div class="no-perm">
    <div class="np-ic" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
        </svg>
    </div>
    <h1>ไม่มีสิทธิ์เข้าถึงหน้านี้</h1>
    <p>
        บัญชีของคุณเป็น <b><?= htmlspecialchars(role_label()) ?></b>
        ซึ่งไม่ได้รับสิทธิ์สำหรับหน้านี้<br>
        ถ้าคิดว่าเป็นความผิดพลาด กรุณาติดต่อผู้ดูแลระบบ
    </p>
    <a href="<?= htmlspecialchars($home ?? role_home()) ?>" class="btn btn-primary">กลับหน้าแรก</a>
</div>

<style>
.no-perm { max-width: 460px; margin: 60px auto; text-align: center; }
.np-ic {
    width: 62px; height: 62px; margin: 0 auto 18px;
    display: flex; align-items: center; justify-content: center;
    border-radius: 16px; color: var(--warn);
    background: rgba(180,83,9,0.09); border: 1px solid rgba(180,83,9,0.24);
}
.np-ic svg { width: 28px; height: 28px; }
.no-perm h1 { margin-bottom: 10px; }
.no-perm p { color: var(--muted); line-height: 1.8; margin-bottom: 22px; }
</style>

<?php require __DIR__ . '/footer.php'; ?>
