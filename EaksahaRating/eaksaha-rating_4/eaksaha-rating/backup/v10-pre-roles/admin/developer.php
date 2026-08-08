<?php
require '../conn/config.php';
// หน้านี้เปิดสาธารณะ — ไม่ต้องล็อกอิน

$pageTitle = 'ผู้พัฒนาระบบ';

// ═══ แก้ไขรายชื่อทีมผู้พัฒนาได้ที่นี่ ═══
$developers = [
    [
        'group' => 'lead',
        'name'  => 'ทีมพัฒนาระบบ Eaksaha',
        'role'  => 'ผู้ดูแลและพัฒนาระบบ',
        'dept'  => 'IT',
        'photo' => '',
        'email' => 'it@eaksaha.co.th',
        'phone' => '',
        'line'  => '',
    ],
];

function devCard(array $dev, int $idx = 0): string {
    $parts   = preg_split('/\s+/', trim($dev['name']));
    $initial = mb_substr($parts[0] ?? '', 0, 1, 'UTF-8');

    if (!empty($dev['photo'])) {
        $src    = htmlspecialchars($dev['photo']);
        $alt    = htmlspecialchars($dev['name']);
        $avatar = "<img src=\"uploads/staff/{$src}\" alt=\"{$alt}\">";
    } else {
        $ini    = htmlspecialchars($initial);
        $avatar = "<span class=\"dev-initial\">{$ini}</span>";
    }

    $contacts = '';
    if (!empty($dev['email'])) {
        $e = htmlspecialchars($dev['email']);
        $contacts .= "<a href=\"mailto:{$e}\" class=\"contact-pill\"><span class=\"cp-icon\">✉</span><span>{$e}</span></a>";
    }
    if (!empty($dev['phone'])) {
        $tel = htmlspecialchars(preg_replace('/[^0-9+]/', '', $dev['phone']));
        $ph  = htmlspecialchars($dev['phone']);
        $contacts .= "<a href=\"tel:{$tel}\" class=\"contact-pill\"><span class=\"cp-icon\">☎</span><span>{$ph}</span></a>";
    }
    if (!empty($dev['line'])) {
        $ln = htmlspecialchars($dev['line']);
        $contacts .= "<span class=\"contact-pill line-pill\"><span class=\"cp-icon line-cp\">L</span><span>{$ln}</span></span>";
    }

    $name  = htmlspecialchars($dev['name']);
    $role  = htmlspecialchars($dev['role']);
    $dept  = htmlspecialchars($dev['dept']);
    $delay = $idx * 90;

    return <<<HTML
<div class="dev-card reveal" style="--delay:{$delay}ms">
    <div class="dev-card-inner">
        <div class="dev-avatar-wrap">
            <div class="dev-ring"></div>
            <div class="dev-avatar">{$avatar}</div>
            <div class="dev-badge">{$dept}</div>
        </div>
        <div class="dev-info">
            <div class="dev-name">{$name}</div>
            <div class="dev-role">{$role}</div>
            <div class="dev-contacts">{$contacts}</div>
        </div>
    </div>
</div>
HTML;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ผู้พัฒนาระบบ · <?= htmlspecialchars(ORG_NAME) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@400;500;600;700;800;900&family=Sarabun:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        /* ── โทนสี: ดำ-แดง Eaksaha (ธีมเดียวถาวร) ── */
        :root {
            --accent:#FF3B3B; --accent-deep:#C10500; --mint:#FFB020; --mint-deep:#D98E0B;
            --bg:#12121B; --card:#1E1E29; --line:#363646;
            --text:#F6F7FB; --muted:#B0B2C2; --muted-2:#7E8091;
            /* โทนหัวเรื่อง (hero) + เงา */
            --h1:#1A0A0C; --h2:#5E0B0B; --h3:#C10500; --h4:#FF6A2B;
            --orb1:#FF6A5A; --orb2:#FFB020;
            --hi1:#FFC9A8; --hi2:#FFD98A;
            --soft-1:#2A2A36; --soft-2:#33333F;
            --row-line:#2A2A36;
            --glow:225,6,0; --glow2:255,176,32;
            --scheme:dark;
        }

        html { color-scheme: var(--scheme, light); }
        body {
            font-family: 'Sarabun', sans-serif;
            background:
                radial-gradient(900px 460px at 100% 0%, rgba(var(--glow2),0.10), transparent 55%),
                radial-gradient(900px 520px at 0% 100%, rgba(var(--glow),0.10), transparent 55%),
                var(--bg);
            background-attachment: fixed;
            color: var(--text); min-height: 100vh;
        }

        /* ════════════ HERO ════════════ */
        .hero {
            position: relative; overflow: hidden;
            background: linear-gradient(140deg, var(--h1) 0%, var(--h2) 38%, var(--h3) 62%, var(--h4) 100%);
            background-size: 220% 220%;
            animation: hero-shift 14s ease-in-out infinite alternate;
            padding: 60px 24px 100px;
            text-align: center;
            color: #FFF;
        }
        @keyframes hero-shift {
            from { background-position: 0% 30%; }
            to   { background-position: 100% 70%; }
        }
        .hero::before {
            content: ''; position: absolute; inset: 0;
            background-image:
                linear-gradient(rgba(255,255,255,0.05) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,0.05) 1px, transparent 1px);
            background-size: 44px 44px;
            mask-image: radial-gradient(600px 400px at 50% 30%, #000 40%, transparent 100%);
            pointer-events: none;
        }
        .hero-orb {
            position: absolute; border-radius: 50%; pointer-events: none;
            filter: blur(50px); opacity: 0.35;
        }
        .hero-orb-1 { width: 340px; height: 340px; background: var(--orb1); top: -100px; right: -70px; animation: orb-drift 9s ease-in-out infinite alternate; }
        .hero-orb-2 { width: 260px; height: 260px; background: var(--orb2); bottom: -40px; left: -60px; animation: orb-drift 11s ease-in-out infinite alternate-reverse; }
        @keyframes orb-drift {
            from { transform: translate(0,0) scale(1); }
            to   { transform: translate(26px,18px) scale(1.08); }
        }

        .hero-back { position: absolute; top: 20px; left: 24px; z-index: 2; }
        .hero-back a {
            display: inline-flex; align-items: center; gap: 6px;
            color: #FFF; font-family: 'Kanit', sans-serif; font-size: 13px; font-weight: 500;
            text-decoration: none; background: rgba(255,255,255,0.14);
            border: 1px solid rgba(255,255,255,0.35); border-radius: 30px; padding: 6px 16px;
            transition: background 0.2s; backdrop-filter: blur(6px);
        }
        .hero-back a:hover { background: rgba(255,255,255,0.28); }

        .hero-content { position: relative; z-index: 1; max-width: 680px; margin: 0 auto; }
        .hero-logo {
            font-family: 'Kanit', sans-serif; font-size: 27px; font-weight: 800;
            letter-spacing: 0.14em; margin-bottom: 20px;
        }
        .hero-logo span { color: var(--hi2); }
        .hero-eyebrow {
            display: inline-flex; align-items: center; gap: 7px;
            background: rgba(255,255,255,0.14); border: 1px solid rgba(255,255,255,0.4);
            color: #FFF; font-family: 'Kanit', sans-serif;
            font-size: 11px; font-weight: 700; letter-spacing: 0.14em; text-transform: uppercase;
            border-radius: 30px; padding: 6px 18px; margin-bottom: 20px; backdrop-filter: blur(6px);
        }
        .hero-eyebrow::before { content: '⚡'; font-size: 11px; }
        .hero h1 {
            font-family: 'Kanit', sans-serif;
            font-size: clamp(30px, 5.4vw, 48px);
            font-weight: 900; line-height: 1.15; margin-bottom: 14px;
            letter-spacing: -0.01em;
        }
        .hero h1 .grad {
            background: linear-gradient(90deg, var(--hi1), var(--hi2));
            -webkit-background-clip: text; background-clip: text; color: transparent;
        }
        .hero-sub { font-size: 15px; color: rgba(255,255,255,0.85); line-height: 1.8; }

        /* stat pills นับเลข */
        .hero-stats { display: flex; justify-content: center; gap: 14px; flex-wrap: wrap; margin-top: 28px; }
        .hero-stat {
            background: rgba(255,255,255,0.13); border: 1px solid rgba(255,255,255,0.3);
            border-radius: 16px; padding: 14px 26px; backdrop-filter: blur(8px);
            min-width: 110px;
        }
        .hero-stat .hs-num {
            font-family: 'Kanit', sans-serif; font-size: 28px; font-weight: 800; line-height: 1;
        }
        .hero-stat .hs-label { font-size: 11.5px; color: rgba(255,255,255,0.75); margin-top: 4px; }

        .hero-wave { position: absolute; bottom: -1px; left: 0; right: 0; line-height: 0; }

        /* ════════════ PAGE ════════════ */
        .page-wrap { max-width: 940px; margin: -48px auto 0; padding: 0 24px; position: relative; z-index: 2; }

        .section-head { display: flex; align-items: center; gap: 14px; margin: 46px 0 24px; }
        .section-pill {
            display: inline-flex; align-items: center; gap: 7px;
            background: linear-gradient(135deg, var(--accent), var(--mint)); color: #fff;
            font-family: 'Kanit', sans-serif; font-size: 11px; font-weight: 700;
            letter-spacing: 0.09em; text-transform: uppercase; border-radius: 30px; padding: 7px 18px;
            white-space: nowrap; box-shadow: 0 6px 16px -6px rgba(var(--glow),0.5);
        }
        .section-pill .sp-dot { width: 6px; height: 6px; border-radius: 50%; background: #fff; }
        .section-head .s-line { flex: 1; height: 1px; background: var(--line); }

        /* ════════════ DEV CARDS (gradient border + glass) ════════════ */
        .dev-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; justify-content: center; }
        .dev-card {
            position: relative; border-radius: 22px; padding: 2px;
            background: linear-gradient(135deg, rgba(var(--glow),0.55), rgba(var(--glow2),0.55));
            max-width: 440px; width: 100%;
            transition: transform 0.25s, box-shadow 0.25s;
        }
        .dev-card:hover { transform: translateY(-6px); box-shadow: 0 26px 50px -20px rgba(var(--glow),0.45); }
        .dev-card-inner {
            background: var(--card); border-radius: 20px;
            padding: 26px 24px; display: flex; gap: 20px; align-items: flex-start;
            height: 100%;
        }
        .dev-avatar-wrap { position: relative; flex-shrink: 0; }
        .dev-ring {
            position: absolute; inset: -5px; border-radius: 50%;
            background: conic-gradient(var(--accent), var(--mint), var(--orb1), var(--accent));
            animation: ring-spin 5s linear infinite;
        }
        @keyframes ring-spin { to { transform: rotate(360deg); } }
        .dev-avatar {
            position: relative;
            width: 78px; height: 78px; border-radius: 50%;
            background: linear-gradient(135deg, var(--soft-1), var(--soft-2));
            display: flex; align-items: center; justify-content: center;
            overflow: hidden; border: 3px solid #FFF;
        }
        .dev-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .dev-initial {
            font-family: 'Kanit', sans-serif; font-size: 28px; font-weight: 700;
            background: linear-gradient(135deg, var(--accent-deep), var(--mint-deep));
            -webkit-background-clip: text; background-clip: text; color: transparent;
        }
        .dev-badge {
            position: absolute; bottom: -7px; left: 50%; transform: translateX(-50%);
            background: linear-gradient(90deg, var(--accent), var(--mint)); color: #fff;
            font-family: 'Kanit', sans-serif; font-size: 9.5px; font-weight: 700;
            padding: 3px 10px; border-radius: 20px; white-space: nowrap; border: 2px solid #FFF;
            box-shadow: 0 3px 8px -2px rgba(var(--glow),0.5);
        }
        .dev-info { flex: 1; min-width: 0; padding-top: 4px; }
        .dev-name { font-family: 'Kanit', sans-serif; font-size: 16.5px; font-weight: 700; color: var(--text); margin-bottom: 3px; }
        .dev-role { font-size: 12.5px; font-weight: 600; color: var(--accent-deep); margin-bottom: 14px; display: flex; align-items: center; gap: 6px; }
        .dev-role::before { content: ''; width: 15px; height: 2.5px; border-radius: 2px; background: linear-gradient(90deg, var(--accent), var(--mint)); }
        .dev-contacts { display: flex; flex-direction: column; gap: 7px; }
        .contact-pill { display: inline-flex; align-items: center; gap: 8px; font-size: 12.5px; color: var(--muted); text-decoration: none; transition: color 0.15s; }
        .contact-pill:hover { color: var(--accent-deep); }
        .cp-icon {
            width: 25px; height: 25px; border-radius: 8px; background: var(--soft-1);
            display: inline-flex; align-items: center; justify-content: center; font-size: 11px;
            flex-shrink: 0; color: var(--accent-deep); border: 1px solid var(--line);
        }
        .line-pill .line-cp { background: #E9FBEF; color: #06C755; font-weight: 800; border-color: #C3F0D0; }

        /* ════════════ TECH CHIPS ════════════ */
        .tech-row { display: flex; flex-wrap: wrap; gap: 12px; justify-content: center; }
        .tech-chip {
            display: inline-flex; align-items: center; gap: 9px;
            background: var(--card); border: 1px solid var(--line);
            border-radius: 14px; padding: 10px 18px;
            font-family: 'Kanit', sans-serif; font-size: 13.5px; font-weight: 600; color: var(--text);
            box-shadow: 0 8px 20px -14px rgba(var(--glow),0.35);
            transition: transform 0.2s, box-shadow 0.2s, border-color 0.2s;
        }
        .tech-chip:hover {
            transform: translateY(-3px) scale(1.03);
            border-color: rgba(var(--glow),0.5);
            box-shadow: 0 14px 28px -12px rgba(var(--glow),0.4);
        }
        .tech-chip .tc-icon { font-size: 17px; }

        /* ════════════ SYSTEM INFO ════════════ */
        .sys-card {
            background: var(--card); border-radius: 22px; border: 1px solid var(--line);
            overflow: hidden; box-shadow: 0 16px 40px -22px rgba(var(--glow),0.4);
        }
        .sys-card-head {
            background: linear-gradient(90deg, var(--h1), var(--accent-deep) 45%, var(--mint) 100%);
            padding: 18px 26px; display: flex; align-items: center; gap: 12px;
        }
        .sys-icon {
            width: 38px; height: 38px; border-radius: 11px;
            background: rgba(255,255,255,0.16); border: 1px solid rgba(255,255,255,0.3);
            display: flex; align-items: center; justify-content: center; font-size: 17px; color: #FFF;
        }
        .sys-card-head h3 { font-family: 'Kanit', sans-serif; font-size: 15.5px; font-weight: 700; color: #fff; }
        .sys-row { display: flex; align-items: center; padding: 15px 26px; font-size: 14px; gap: 16px; border-bottom: 1px solid var(--row-line); }
        .sys-row:last-child { border-bottom: none; }
        .sys-label { width: 145px; flex-shrink: 0; font-family: 'Kanit', sans-serif; font-size: 11px; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; color: var(--muted-2); }
        .sys-value { color: var(--text); font-weight: 600; font-size: 14px; }
        .sys-badge { display: inline-block; background: linear-gradient(90deg, var(--accent), var(--mint)); color: #fff; font-size: 11px; font-weight: 700; padding: 3px 13px; border-radius: 20px; }

        /* ════════════ FOOTER ════════════ */
        .page-footer {
            margin-top: 56px; position: relative; overflow: hidden;
            background: linear-gradient(135deg, var(--h1), var(--h2) 55%, var(--mint-deep));
            text-align: center; padding: 42px 24px 38px; color: #FFF;
        }
        .page-footer::before {
            content: ''; position: absolute; inset: 0;
            background-image: radial-gradient(circle, rgba(255,255,255,0.08) 1px, transparent 1px);
            background-size: 22px 22px; pointer-events: none;
        }
        .footer-inner { position: relative; z-index: 1; }
        .footer-brand { font-family: 'Kanit', sans-serif; font-size: 16px; font-weight: 800; letter-spacing: 0.12em; margin-bottom: 4px; }
        .footer-brand span { color: var(--hi2); }
        .footer-dept { font-size: 13px; color: rgba(255,255,255,0.75); margin-bottom: 14px; }
        .footer-copy { font-size: 12px; color: rgba(255,255,255,0.55); }

        /* ════════════ REVEAL ════════════ */
        .reveal { opacity: 0; transform: translateY(26px); transition: opacity 0.6s ease var(--delay, 0ms), transform 0.6s ease var(--delay, 0ms); }
        .reveal.visible { opacity: 1; transform: translateY(0); }
        @media (prefers-reduced-motion: reduce) {
            .reveal { opacity: 1; transform: none; }
            .hero, .hero-orb, .dev-ring { animation: none; }
        }
        @media (max-width: 600px) {
            .dev-card-inner { flex-direction: column; align-items: center; text-align: center; }
            .dev-role { justify-content: center; } .dev-contacts { align-items: center; }
            .sys-row { flex-direction: column; align-items: flex-start; gap: 4px; }
            .hero-stats { gap: 8px; }
        }
    </style>
</head>
<body>

<!-- ══ HERO ══ -->
<header class="hero">
    <div class="hero-orb hero-orb-1"></div>
    <div class="hero-orb hero-orb-2"></div>
    <div class="hero-back"><a href="login.php">← กลับหน้าเข้าสู่ระบบ</a></div>

    <div class="hero-content">
        <div class="hero-logo">EAKSAHA<span>GROUP</span></div>
        <div class="hero-eyebrow">Development Team</div>
        <h1>ทีมผู้อยู่เบื้องหลัง<br><span class="grad">ระบบประเมินความพึงพอใจ</span></h1>
        <p class="hero-sub">
            <?= htmlspecialchars(ORG_NAME) ?> · <?= htmlspecialchars(ORG_NAME_TH) ?><br>
            <?= htmlspecialchars(ORG_DEPT) ?>
        </p>
        <div class="hero-stats">
            <div class="hero-stat"><div class="hs-num" data-count="9">0</div><div class="hs-label">แบรนด์รถ</div></div>
            <div class="hero-stat"><div class="hs-num" data-count="5">0</div><div class="hs-label">ระดับคะแนน</div></div>
            <div class="hero-stat"><div class="hs-num">1.0</div><div class="hs-label">เวอร์ชัน</div></div>
            <div class="hero-stat"><div class="hs-num" data-count="<?= date('Y') + 543 ?>" data-plain>0</div><div class="hs-label">ปีที่พัฒนา (พ.ศ.)</div></div>
        </div>
    </div>

    <div class="hero-wave">
        <svg viewBox="0 0 1440 70" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none" style="display:block; width:100%; height:70px;">
            <path d="M0,42 C280,74 560,10 840,34 C1100,56 1300,28 1440,42 L1440,70 L0,70 Z" fill="var(--bg)"/>
        </svg>
    </div>
</header>

<!-- ══ CONTENT ══ -->
<div class="page-wrap">

    <div class="section-head reveal">
        <div class="section-pill"><span class="sp-dot"></span>ทีมผู้พัฒนา</div>
        <div class="s-line"></div>
    </div>
    <div class="dev-grid">
        <?php foreach ($developers as $i => $dev) echo devCard($dev, $i); ?>
    </div>

    <div class="section-head reveal">
        <div class="section-pill"><span class="sp-dot"></span>เทคโนโลยีที่ใช้พัฒนา</div>
        <div class="s-line"></div>
    </div>
    <div class="tech-row">
        <?php
        $techs = [['🐘','PHP'],['🗄️','MySQL'],['🎨','HTML / CSS'],['⚡','JavaScript'],['📊','Chart.js'],['📱','Responsive Design'],['🔗','QR Code']];
        foreach ($techs as $i => [$icon, $label]):
        ?>
        <div class="tech-chip reveal" style="--delay:<?= $i * 60 ?>ms"><span class="tc-icon"><?= $icon ?></span><?= $label ?></div>
        <?php endforeach; ?>
    </div>

    <div class="section-head reveal">
        <div class="section-pill"><span class="sp-dot"></span>ข้อมูลระบบ</div>
        <div class="s-line"></div>
    </div>
    <div class="sys-card reveal" style="--delay:100ms">
        <div class="sys-card-head"><div class="sys-icon">⚙</div><h3>รายละเอียดระบบ</h3></div>
        <div class="sys-row"><span class="sys-label">ชื่อระบบ</span><span class="sys-value">ระบบประเมินความพึงพอใจพนักงานขาย</span></div>
        <div class="sys-row"><span class="sys-label">องค์กร</span><span class="sys-value"><?= htmlspecialchars(ORG_NAME) ?></span></div>
        <div class="sys-row"><span class="sys-label">ฝ่ายที่ดูแล</span><span class="sys-value"><?= htmlspecialchars(ORG_DEPT) ?></span></div>
        <div class="sys-row"><span class="sys-label">เวอร์ชัน</span><span class="sys-value"><span class="sys-badge">v 1.0.0</span></span></div>
        <div class="sys-row"><span class="sys-label">ปีที่พัฒนา</span><span class="sys-value"><?= date('Y') ?> (พ.ศ. <?= date('Y') + 543 ?>)</span></div>
    </div>

</div><!-- /page-wrap -->

<!-- ══ FOOTER ══ -->
<footer class="page-footer">
    <div class="footer-inner">
        <div class="footer-brand">EAKSAHA<span>GROUP</span></div>
        <div class="footer-dept"><?= htmlspecialchars(ORG_DEPT) ?> · <?= htmlspecialchars(ORG_DEPT2) ?></div>
        <div class="footer-copy">Copyright &copy; <?= date('Y') ?> All Rights Reserved</div>
    </div>
</footer>

<script>
// ── Scroll reveal ──
const revealObs = new IntersectionObserver((entries) => {
    entries.forEach(e => { if (e.isIntersecting) { e.target.classList.add('visible'); revealObs.unobserve(e.target); } });
}, { threshold: 0.12 });
document.querySelectorAll('.reveal').forEach(el => revealObs.observe(el));

// ── ตัวเลขนับขึ้น (count-up) ──
function countUp(el) {
    const target = parseInt(el.dataset.count, 10);
    if (isNaN(target)) return;
    const dur = 1200, t0 = performance.now();
    const step = (t) => {
        const p = Math.min((t - t0) / dur, 1);
        const eased = 1 - Math.pow(1 - p, 3);
        const v = Math.round(target * eased);
        // ปีพุทธศักราชไม่ต้องใส่ลูกน้ำคั่นหลัก
        el.textContent = el.hasAttribute('data-plain') ? String(v) : v.toLocaleString();
        if (p < 1) requestAnimationFrame(step);
    };
    requestAnimationFrame(step);
}
const countObs = new IntersectionObserver((entries) => {
    entries.forEach(e => { if (e.isIntersecting) { countUp(e.target); countObs.unobserve(e.target); } });
}, { threshold: 0.4 });
document.querySelectorAll('[data-count]').forEach(el => countObs.observe(el));
</script>

</body>
</html>