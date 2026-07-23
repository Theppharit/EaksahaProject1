<?php
require '../conn/config.php';
// หน้านี้เปิดสาธารณะ — ไม่ต้องล็อกอิน

$pageTitle = 'ผู้พัฒนาระบบ';

// แก้ไขรายชื่อทีมผู้พัฒนาได้ที่นี่
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
    $delay = $idx * 80;

    return <<<HTML
<div class="dev-card reveal" style="--delay:{$delay}ms">
    <div class="card-accent"></div>
    <div class="dev-avatar-wrap">
        <div class="dev-avatar">{$avatar}</div>
        <div class="dev-badge">{$dept}</div>
    </div>
    <div class="dev-info">
        <div class="dev-name">{$name}</div>
        <div class="dev-role">{$role}</div>
        <div class="dev-contacts">{$contacts}</div>
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
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@400;500;600;700;800&family=Sarabun:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --accent: #E10600; --accent2: #FF3B30;
            --bg: #0A0A0B; --bg2: #050506;
            --card: #141416; --line: #2A2A2E;
            --text: #F4F4F5; --muted: #9A9AA2; --muted-2: #6E6E76;
        }
        body {
            font-family: 'Sarabun', sans-serif;
            background: radial-gradient(1000px 500px at 50% -10%, rgba(225,6,0,0.12), transparent 60%),
                        linear-gradient(180deg, var(--bg) 0%, var(--bg2) 100%);
            background-attachment: fixed;
            color: var(--text); min-height: 100vh;
        }
        .hero {
            background: radial-gradient(600px 200px at 50% 0%, rgba(225,6,0,0.25), transparent 70%), #0C0C0E;
            border-bottom: 1px solid var(--line);
            padding: 56px 24px 84px; position: relative; overflow: hidden; text-align: center;
        }
        .hero::before {
            content: ''; position: absolute; inset: 0;
            background-image: radial-gradient(circle, rgba(255,255,255,0.045) 1px, transparent 1px);
            background-size: 28px 28px; pointer-events: none;
        }
        .hero-back { position: absolute; top: 20px; left: 24px; z-index: 2; }
        .hero-back a {
            display: inline-flex; align-items: center; gap: 6px;
            color: var(--muted); font-family: 'Kanit', sans-serif; font-size: 13px; font-weight: 500;
            text-decoration: none; background: rgba(255,255,255,0.06);
            border: 1px solid var(--line); border-radius: 30px; padding: 6px 16px; transition: background 0.2s;
        }
        .hero-back a:hover { background: rgba(255,255,255,0.12); color: #fff; }
        .hero-logo {
            position: relative; z-index: 1;
            font-family: 'Kanit', sans-serif; font-size: 26px; font-weight: 800; letter-spacing: 0.12em;
            color: #FFF; margin-bottom: 18px;
        }
        .hero-logo span { color: var(--accent); }
        .hero-content { position: relative; z-index: 1; max-width: 640px; margin: 0 auto; }
        .hero-eyebrow {
            display: inline-flex; align-items: center; gap: 6px;
            background: rgba(225,6,0,0.14); border: 1px solid rgba(225,6,0,0.4);
            color: var(--accent2); font-family: 'Kanit', sans-serif;
            font-size: 11px; font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase;
            border-radius: 30px; padding: 5px 16px; margin-bottom: 18px;
        }
        .hero h1 {
            font-family: 'Kanit', sans-serif; font-size: clamp(24px, 5vw, 38px); font-weight: 800;
            color: #fff; line-height: 1.2; margin-bottom: 12px;
        }
        .hero h1 .accent { color: var(--accent); }
        .hero-sub { font-size: 14.5px; color: var(--muted); line-height: 1.8; }
        .hero-stats { display: flex; justify-content: center; gap: 12px; flex-wrap: wrap; margin-top: 24px; }
        .hero-stat {
            background: rgba(255,255,255,0.04); border: 1px solid var(--line);
            border-radius: 12px; padding: 10px 20px; text-align: center;
        }
        .hero-stat .hs-num { font-family: 'Kanit', sans-serif; font-size: 22px; font-weight: 800; color: #fff; line-height: 1; }
        .hero-stat .hs-label { font-size: 11px; color: var(--muted); margin-top: 2px; }

        .page-wrap { max-width: 920px; margin: 0 auto; padding: 48px 24px 0; }
        .section-head { display: flex; align-items: center; gap: 14px; margin-bottom: 26px; }
        .section-pill {
            display: inline-flex; align-items: center; gap: 7px;
            background: linear-gradient(135deg, var(--accent), #A50400); color: #fff;
            font-family: 'Kanit', sans-serif; font-size: 11px; font-weight: 700;
            letter-spacing: 0.09em; text-transform: uppercase; border-radius: 30px; padding: 6px 16px; white-space: nowrap;
        }
        .section-pill .sp-dot { width: 6px; height: 6px; border-radius: 50%; background: #fff; flex-shrink: 0; }
        .section-head .s-line { flex: 1; height: 1px; background: var(--line); }

        .dev-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-bottom: 44px; justify-content: center; }
        .dev-card {
            background: linear-gradient(180deg, var(--card), #0F0F11);
            border-radius: 18px; padding: 24px 22px 24px 28px;
            border: 1px solid var(--line); display: flex; gap: 20px; align-items: flex-start;
            width: 100%; max-width: 430px; position: relative; overflow: hidden; transition: transform 0.25s, box-shadow 0.25s;
        }
        .dev-card:hover { transform: translateY(-4px); box-shadow: 0 24px 50px -20px rgba(0,0,0,0.9); }
        .card-accent { position: absolute; left: 0; top: 0; bottom: 0; width: 5px; background: linear-gradient(180deg, var(--accent2), var(--accent)); }
        .dev-avatar-wrap { position: relative; flex-shrink: 0; }
        .dev-avatar {
            width: 76px; height: 76px; border-radius: 50%;
            background: linear-gradient(135deg, #2A2A2E, #0E0E10);
            display: flex; align-items: center; justify-content: center; overflow: hidden; border: 2px solid var(--accent);
        }
        .dev-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .dev-initial { color: #fff; font-family: 'Kanit', sans-serif; font-size: 28px; font-weight: 700; }
        .dev-badge {
            position: absolute; bottom: -6px; left: 50%; transform: translateX(-50%);
            background: linear-gradient(90deg, var(--accent), #A50400); color: #fff;
            font-family: 'Kanit', sans-serif; font-size: 9px; font-weight: 700;
            padding: 3px 9px; border-radius: 20px; white-space: nowrap; border: 2px solid var(--card);
        }
        .dev-info { flex: 1; min-width: 0; padding-top: 2px; }
        .dev-name { font-family: 'Kanit', sans-serif; font-size: 16px; font-weight: 700; color: #FFF; margin-bottom: 3px; }
        .dev-role { font-size: 12px; font-weight: 600; color: var(--accent2); margin-bottom: 14px; display: flex; align-items: center; gap: 5px; }
        .dev-role::before { content: ''; width: 14px; height: 2px; background: var(--accent); border-radius: 2px; }
        .dev-contacts { display: flex; flex-direction: column; gap: 6px; }
        .contact-pill { display: inline-flex; align-items: center; gap: 8px; font-size: 12px; color: var(--muted); text-decoration: none; transition: color 0.15s; }
        .contact-pill:hover { color: #FFF; }
        .cp-icon {
            width: 24px; height: 24px; border-radius: 7px; background: #0D0D0F;
            display: inline-flex; align-items: center; justify-content: center; font-size: 11px;
            flex-shrink: 0; color: var(--accent2); border: 1px solid var(--line);
        }
        .line-pill .line-cp { background: rgba(6,199,85,0.12); color: #06C755; font-weight: 800; border-color: rgba(6,199,85,0.3); }

        .tech-row { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 44px; justify-content: center; }
        .tech-chip {
            display: inline-flex; align-items: center; gap: 8px;
            background: linear-gradient(180deg, var(--card), #0F0F11); border: 1px solid var(--line);
            border-radius: 12px; padding: 8px 16px; font-family: 'Kanit', sans-serif; font-size: 13px; font-weight: 600; color: #E4E4E7; transition: transform 0.2s;
        }
        .tech-chip:hover { transform: translateY(-2px); }
        .tech-chip .tc-icon { font-size: 16px; }

        .sys-card { background: linear-gradient(180deg, var(--card), #0F0F11); border-radius: 18px; border: 1px solid var(--line); overflow: hidden; }
        .sys-card-head { background: linear-gradient(90deg, var(--accent), #A50400); padding: 18px 24px; display: flex; align-items: center; gap: 12px; }
        .sys-icon { width: 36px; height: 36px; border-radius: 10px; background: rgba(255,255,255,0.16); display: flex; align-items: center; justify-content: center; font-size: 16px; }
        .sys-card-head h3 { font-family: 'Kanit', sans-serif; font-size: 15px; font-weight: 700; color: #fff; }
        .sys-row { display: flex; align-items: center; padding: 14px 24px; font-size: 14px; gap: 16px; border-bottom: 1px solid var(--line); }
        .sys-row:last-child { border-bottom: none; }
        .sys-label { width: 140px; flex-shrink: 0; font-family: 'Kanit', sans-serif; font-size: 11px; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; color: var(--muted); }
        .sys-value { color: var(--text); font-weight: 600; font-size: 14px; }
        .sys-badge { display: inline-block; background: linear-gradient(90deg, var(--accent), #A50400); color: #fff; font-size: 11px; font-weight: 700; padding: 3px 12px; border-radius: 20px; }

        .page-footer { margin-top: 48px; background: #050506; border-top: 1px solid var(--line); text-align: center; padding: 40px 24px 36px; }
        .footer-brand { font-family: 'Kanit', sans-serif; font-size: 15px; font-weight: 800; letter-spacing: 0.1em; color: #FFF; margin-bottom: 4px; }
        .footer-brand span { color: var(--accent); }
        .footer-dept { font-size: 13px; color: var(--muted); margin-bottom: 16px; }
        .footer-copy { font-size: 12px; color: var(--muted-2); }

        .reveal { opacity: 0; transform: translateY(24px); transition: opacity 0.55s ease var(--delay, 0ms), transform 0.55s ease var(--delay, 0ms); }
        .reveal.visible { opacity: 1; transform: translateY(0); }
        @media (prefers-reduced-motion: reduce) { .reveal { opacity: 1; transform: none; } }
        @media (max-width: 600px) {
            .dev-card { flex-direction: column; align-items: center; text-align: center; }
            .card-accent { top: 0; left: 0; right: 0; bottom: auto; width: 100%; height: 4px; }
            .dev-role { justify-content: center; } .dev-contacts { align-items: center; }
            .sys-row { flex-direction: column; gap: 4px; } .sys-label { width: auto; }
        }
    </style>
</head>
<body>

<header class="hero">
    <div class="hero-back"><a href="login.php">← กลับหน้าเข้าสู่ระบบ</a></div>
    <div class="hero-content">
        <div class="hero-logo">EAKSAHA<span>GROUP</span></div>
        <div class="hero-eyebrow">ทีมพัฒนาระบบ</div>
        <h1>ระบบประเมิน<span class="accent">ความพึงพอใจ</span><br>พนักงานขายรถ EV</h1>
        <p class="hero-sub">
            <?= htmlspecialchars(ORG_NAME) ?> · <?= htmlspecialchars(ORG_NAME_TH) ?><br>
            <?= htmlspecialchars(ORG_DEPT) ?>
        </p>
        <div class="hero-stats">
            <div class="hero-stat"><div class="hs-num">9</div><div class="hs-label">แบรนด์รถ</div></div>
            <div class="hero-stat"><div class="hs-num">1.0</div><div class="hs-label">เวอร์ชัน</div></div>
            <div class="hero-stat"><div class="hs-num"><?= date('Y') + 543 ?></div><div class="hs-label">ปีที่พัฒนา (พ.ศ.)</div></div>
        </div>
    </div>
</header>

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
        $techs = [['🐘','PHP'],['🗄️','MySQL'],['🎨','HTML / CSS'],['⚡','JavaScript'],['📊','Chart.js'],['📱','Responsive Design']];
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
</div>

<footer class="page-footer">
    <div class="footer-brand">EAKSAHA<span>GROUP</span></div>
    <div class="footer-dept"><?= htmlspecialchars(ORG_DEPT) ?> · <?= htmlspecialchars(ORG_DEPT2) ?></div>
    <div class="footer-copy">Copyright &copy; <?= date('Y') ?> All Rights Reserved</div>
</footer>

<script>
const observer = new IntersectionObserver((entries) => {
    entries.forEach(e => { if (e.isIntersecting) { e.target.classList.add('visible'); observer.unobserve(e.target); } });
}, { threshold: 0.12 });
document.querySelectorAll('.reveal').forEach(el => observer.observe(el));
</script>
</body>
</html>
