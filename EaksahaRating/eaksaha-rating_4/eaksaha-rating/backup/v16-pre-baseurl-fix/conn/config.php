<?php
// ============================================================
//  ตั้งค่าองค์กร (แก้ไขตรงนี้)
// ============================================================
define('ORG_NAME',  'EAKSAHA GROUP');                       // ชื่อกลุ่มบริษัท
define('ORG_NAME_TH', 'เอกสหกรุ๊ป');                          // ชื่อภาษาไทย
define('ORG_TAGLINE', 'ตัวแทนจำหน่ายรถยนต์พลังงานไฟฟ้า');      // คำโปรย
define('ORG_DEPT',  'ฝ่ายพัฒนาธุรกิจและประสบการณ์ลูกค้า');     // ฝ่ายที่ดูแลระบบ
define('ORG_DEPT2', 'ศูนย์ดิจิทัลและเทคโนโลยีสารสนเทศ');       // ฝ่าย/งานที่พัฒนาระบบ

// ============================================================
//  ความปลอดภัยของ session
// ------------------------------------------------------------
//  ตั้งก่อนที่ session จะเริ่ม จึงต้องอยู่บนสุดของ config
//    httponly — JavaScript อ่านคุกกี้ล็อกอินไม่ได้ (กันขโมย session ผ่าน XSS)
//    samesite — เบราว์เซอร์ไม่ส่งคุกกี้ไปกับคำขอที่มาจากเว็บอื่น
//               เป็นเกราะชั้นที่สองคู่กับ CSRF token
//    use_strict_mode — ไม่ยอมรับ session id ที่ระบบไม่ได้เป็นคนสร้าง
// ============================================================
if (session_status() === PHP_SESSION_NONE) {
    @ini_set('session.cookie_httponly', '1');
    @ini_set('session.cookie_samesite', 'Lax');
    @ini_set('session.use_strict_mode', '1');
    // ถ้าวันไหนขึ้น https ให้เปิดบรรทัดนี้ด้วย
    // @ini_set('session.cookie_secure', '1');
}

// ============================================================
//  ชื่อระบบ
// ------------------------------------------------------------
//  ระบบนี้ใช้ประเมินพนักงานขายเฉพาะในกิจกรรม Test Drive เท่านั้น
//  ไม่ใช่ระบบประเมินความพึงพอใจทั่วไป ชื่อจึงต้องสื่อขอบเขตให้ชัด
//  แก้ที่นี่ที่เดียว ทุกหน้าจะเปลี่ยนตาม
// ============================================================
define('SYS_CODE', 'eTDR');                                   // ชื่อย่อที่ใช้เรียกกันในองค์กร
define('SYS_NAME', 'Eaksaha Test Drive Rating');              // ชื่อเต็ม
define('SYS_DESC', 'ระบบประเมินพนักงานขายในกิจกรรม Test Drive'); // คำอธิบายหนึ่งบรรทัด

// ============================================================
//  ที่อยู่เว็บสำหรับทำ QR / ลิงก์ให้ลูกค้า  (สำคัญมาก)
// ============================================================
//  เว้นว่าง = ระบบเดาจากที่อยู่ที่กำลังเปิดอยู่
//  ปัญหา: ถ้าผู้ดูแลเปิดหลังบ้านผ่าน http://localhost/... QR ที่ได้
//         จะชี้ไปที่ "เครื่องนี้" ลูกค้าสแกนด้วยมือถือแล้วเปิดไม่ขึ้น
//
//  วิธีตั้งค่า: ใส่ที่อยู่ที่เครื่องอื่นเข้าถึงได้จริง (ไม่ต้องมี / ปิดท้าย)
//    ทดสอบในวง LAN     : 'http://192.168.1.50/eaksaha-rating'
//                        (ดู IP ด้วยคำสั่ง ipconfig บน Windows)
//    ขึ้นเซิร์ฟเวอร์จริง : 'https://rating.eaksaha.com'
define('PUBLIC_BASE_URL', 'http://192.168.1.102/eaksaha-rating');

/**
 * เดา IP ของเครื่องนี้ในวง LAN
 * ------------------------------------------------------------
 * ทำไมต้องมี: เจ้าของงานเปิดหลังบ้านผ่าน http://localhost เกือบทุกครั้ง
 * ถ้าไม่มีตัวช่วยนี้ QR จะชี้ไป localhost แล้วมือถือลูกค้าเปิดไม่ขึ้น
 * ซึ่งเป็นความผิดพลาดที่ "ดูเหมือนใช้ได้" จนกว่าจะเอาไปใช้จริงหน้างาน
 *
 * รับเฉพาะ IP วง private (10.x / 172.16-31.x / 192.168.x) เท่านั้น
 * เพราะถ้าได้ IP สาธารณะมา แปลว่าเดาผิด และจะพาลูกค้าไปผิดที่
 *
 * คืนค่าว่างถ้าเดาไม่ได้ — ให้ระบบไปขึ้นแถบเตือนแทน ไม่เดามั่ว
 */
function lan_ip_guess(): string
{
    static $cached = null;              // เรียกหลายรอบในหน้าเดียวได้ ไม่ต้อง resolve ซ้ำ
    if ($cached !== null) { return $cached; }
    $cached = '';

    $host = @gethostname();
    if ($host === false || $host === '') { return $cached; }

    $ip = @gethostbyname($host);        // บน Windows คืน IP ของ NIC ที่ใช้ออกเน็ต
    if (!$ip || $ip === $host) { return $cached; }
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) { return $cached; }

    // ต้องเป็นวง private และไม่ใช่ loopback / link-local
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
        $cached = $ip;                  // ตกเงื่อนไข = เป็น private จริง (ตามที่ต้องการ)
    }
    if (str_starts_with($cached, '127.') || str_starts_with($cached, '169.254.')) {
        $cached = '';
    }
    return $cached;
}

/**
 * ที่อยู่ตั้งต้นของหน้าให้คะแนน สำหรับทำ QR / ลิงก์แจกลูกค้า
 * ------------------------------------------------------------
 * รวมตรรกะไว้ที่เดียว เพื่อไม่ให้หน้าไหนสร้าง URL เองแล้วเพี้ยนกัน
 *
 * คืน array:
 *   url    — ลงท้ายด้วย 'rate.php?code=' ต่อโค้ดพนักงานได้เลย
 *   host   — โฮสต์ที่ใช้จริง (ไว้โชว์ในแถบเตือน)
 *   source — 'config' ตั้งเองใน config (ดีที่สุด)
 *          | 'auto'   เดาจากที่อยู่ที่กำลังเปิดอยู่ และใช้ได้จริง
 *          | 'guess'  เปิดผ่าน localhost แต่ระบบหา IP ในวง LAN ให้แทนได้
 *          | 'local'  ยังชี้มาที่เครื่องนี้ ใช้กับมือถือลูกค้าไม่ได้
 */
function rate_base_url(): array
{
    $suffix = '/rate.php?code=';

    if (defined('PUBLIC_BASE_URL') && PUBLIC_BASE_URL !== '') {
        $url = rtrim(PUBLIC_BASE_URL, '/') . $suffix;
        return ['url' => $url, 'host' => parse_url($url, PHP_URL_HOST) ?: '', 'source' => 'config'];
    }

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';

    // เข้ารหัสทีละส่วนของ path — ชื่อโฟลเดอร์จริงมีทั้งช่องว่างและวงเล็บ
    // ถ้าไม่เข้ารหัส QR จะสแกนไม่ติดหรือถูกตัดกลางทาง
    $scriptDir = dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/admin/x.php'));
    $segs      = array_map('rawurlencode', array_filter(explode('/', $scriptDir), 'strlen'));
    $dir       = $segs ? '/' . implode('/', $segs) : '';

    $hostOnly = strtolower(parse_url($scheme . '://' . $host, PHP_URL_HOST) ?: $host);
    $isLocal  = in_array($hostOnly, ['localhost', '127.0.0.1', '::1'], true);

    if (!$isLocal) {
        return ['url' => $scheme . '://' . $host . $dir . $suffix, 'host' => $hostOnly, 'source' => 'auto'];
    }

    // เปิดผ่าน localhost — ลองสลับเป็น IP ในวง LAN ให้อัตโนมัติ
    $lan = lan_ip_guess();
    if ($lan !== '') {
        $port = parse_url($scheme . '://' . $host, PHP_URL_PORT);
        $auth = $lan . ($port && !in_array((int) $port, [80, 443], true) ? ':' . $port : '');
        return ['url' => $scheme . '://' . $auth . $dir . $suffix, 'host' => $lan, 'source' => 'guess'];
    }

    return ['url' => $scheme . '://' . $host . $dir . $suffix, 'host' => $hostOnly, 'source' => 'local'];
}

// ============================================================
//  เลือกสีตัวอักษรให้อ่านออกบนสีพื้นที่กำหนดเอง
//  แบรนด์บางยี่ห้อใช้สีอ่อน (เช่น ชมพู/ฟ้าอ่อน) ถ้าใส่ตัวหนังสือ
//  สีขาวตายตัวจะอ่านไม่ออก ฟังก์ชันนี้เลือกดำ/ขาวตามความสว่างจริง
// ============================================================
function brand_ink(?string $hex): string
{
    $h = ltrim((string) $hex, '#');
    if (strlen($h) === 3) { $h = $h[0].$h[0].$h[1].$h[1].$h[2].$h[2]; }
    if (strlen($h) !== 6 || !ctype_xdigit($h)) { return '#FFFFFF'; }

    // ความสว่างสัมพัทธ์ตามสูตร WCAG
    $lin = function ($v) {
        $v /= 255;
        return $v <= 0.03928 ? $v / 12.92 : pow(($v + 0.055) / 1.055, 2.4);
    };
    $L = 0.2126 * $lin(hexdec(substr($h,0,2)))
       + 0.7152 * $lin(hexdec(substr($h,2,2)))
       + 0.0722 * $lin(hexdec(substr($h,4,2)));

    // เทียบอัตราส่วนความต่างกับขาวและดำ แล้วเลือกตัวที่ชัดกว่า
    $vsWhite = 1.05 / ($L + 0.05);
    $vsBlack = ($L + 0.05) / 0.05;
    return $vsWhite >= $vsBlack ? '#FFFFFF' : '#16181D';
}

// ============================================================
//  ป้ายแบรนด์ (brand-tag) — สีจุด + พื้นจาง
//  ------------------------------------------------------------
//  แนวคิด: ตัวอักษรบนป้ายจะใช้สีตาม "ธีม" เสมอ
//          (ธีมสว่าง = เกือบดำ / ธีมมืด = เกือบขาว)
//          ส่วนสีประจำแบรนด์ย้ายไปอยู่ที่จุดกลม + พื้นจาง + เส้นขอบ
//  ข้อดี : ทุกแบรนด์อ่านง่ายเท่ากันหมด ไม่ว่าสีโลโก้จะเข้มหรืออ่อน
//          และยังคงจำแบรนด์จากสีได้อยู่
//
//  ปัญหาที่ต้องแก้: สีโลโก้บางแบรนด์เข้มมาก (เช่น Ford #00095B)
//  ถ้าเอาไปวางบนพื้นมืดจะจมหายไป จึงต้องปรับความสว่างให้พอดี
//  กับแต่ละธีมก่อน — ทำผ่าน brand_shade() ด้านล่าง
// ============================================================

/** แปลง #RGB / #RRGGBB เป็น [r, g, b] (คืน null ถ้ารูปแบบไม่ถูกต้อง) */
function brand_rgb(?string $hex): ?array
{
    $h = ltrim((string) $hex, '#');
    if (strlen($h) === 3) { $h = $h[0].$h[0].$h[1].$h[1].$h[2].$h[2]; }
    if (strlen($h) !== 6 || !ctype_xdigit($h)) { return null; }
    return [hexdec(substr($h,0,2)), hexdec(substr($h,2,2)), hexdec(substr($h,4,2))];
}

/**
 * ปรับสีแบรนด์ให้ "มองเห็นชัด" บนพื้นหลังของแต่ละธีม
 *   $mode = 'light' : บีบความสว่างไว้ในช่วง 22–62% (สีอ่อนเกินจะจมบนพื้นขาว)
 *   $mode = 'dark'  : ดันความสว่างไว้ในช่วง 62–80% (สีเข้มเกินจะจมบนพื้นดำ)
 * ทั้งสองโหมดจะดันความอิ่มสีขั้นต่ำไว้ เพื่อไม่ให้สีเทาจนดูไม่ออกว่าเป็นแบรนด์อะไร
 */
function brand_shade(?string $hex, string $mode = 'light'): string
{
    $rgb = brand_rgb($hex);
    if ($rgb === null) { return $mode === 'dark' ? '#9AA0AE' : '#565C69'; }

    $r = $rgb[0] / 255; $g = $rgb[1] / 255; $b = $rgb[2] / 255;
    $max = max($r, $g, $b); $min = min($r, $g, $b);
    $l = ($max + $min) / 2;
    $d = $max - $min;

    $s = 0.0; $h = 0.0;
    if ($d > 0.00001) {
        $s = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);
        if ($max === $r)      { $h = fmod((($g - $b) / $d) + ($g < $b ? 6 : 0), 6); }
        elseif ($max === $g)  { $h = (($b - $r) / $d) + 2; }
        else                  { $h = (($r - $g) / $d) + 4; }
        $h /= 6;
    }

    if ($mode === 'dark') {
        $l = min(max($l, 0.62), 0.80);
        // เพดานความอิ่มสี 0.85 กันสีนีออนแสบตาบนพื้นมืด (เช่น ฟ้า/เขียวสด)
        if ($s > 0.05) { $s = min(max($s, 0.55), 0.85); }
    } else {
        $l = min(max($l, 0.22), 0.62);
        if ($s > 0.05) { $s = max($s, 0.45); }
    }

    // HSL -> RGB
    if ($s <= 0.00001) {
        $r2 = $g2 = $b2 = $l;
    } else {
        $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
        $p = 2 * $l - $q;
        $hue = function ($p, $q, $t) {
            if ($t < 0) { $t += 1; }
            if ($t > 1) { $t -= 1; }
            if ($t < 1/6) { return $p + ($q - $p) * 6 * $t; }
            if ($t < 1/2) { return $q; }
            if ($t < 2/3) { return $p + ($q - $p) * (2/3 - $t) * 6; }
            return $p;
        };
        $r2 = $hue($p, $q, $h + 1/3);
        $g2 = $hue($p, $q, $h);
        $b2 = $hue($p, $q, $h - 1/3);
    }

    return sprintf('#%02X%02X%02X',
        (int) round($r2 * 255), (int) round($g2 * 255), (int) round($b2 * 255));
}

/**
 * สร้างค่า style="" ให้ป้ายแบรนด์ — ส่งสีเข้าไปเป็น CSS variable
 * ให้ไฟล์ admin.css เป็นคนตัดสินใจว่าจะเอาไปใช้ยังไงในแต่ละธีม
 * ใช้แบบ: <span class="brand-tag" style="[echo brand_tag_style($color)]">ชื่อแบรนด์</span>
 */
function brand_tag_style(?string $hex): string
{
    $light = brand_shade($hex, 'light');
    $dark  = brand_shade($hex, 'dark');
    $lRgb  = implode(',', brand_rgb($light) ?? [86, 92, 105]);
    $dRgb  = implode(',', brand_rgb($dark)  ?? [154, 160, 174]);

    return "--bd:{$light};--bd-rgb:{$lRgb};--bd-dk:{$dark};--bd-dk-rgb:{$dRgb}";
}

// ============================================================
//  ตรวจว่าอัปเดตโครงสร้างตารางสำหรับ AI แล้วหรือยัง
//  ------------------------------------------------------------
//  ถ้ายังไม่ได้รัน ai_migration.sql คอลัมน์ ai_status จะยังไม่มี
//  หน้าหลังบ้านจะได้ไม่พังทั้งหน้า แค่ซ่อนส่วนที่เกี่ยวกับ AI ไว้ก่อน
//  ตรวจครั้งเดียวต่อการโหลดหนึ่งหน้า (static) ไม่เปลืองคิวรี
// ============================================================
function ai_columns_ready(PDO $pdo): bool
{
    static $ready = null;
    if ($ready === null) {
        try {
            $pdo->query('SELECT ai_status FROM ratings LIMIT 1');
            $ready = true;
        } catch (PDOException $e) {
            $ready = false;
        }
    }
    return $ready;
}

// ============================================================
//  ตรวจว่ามีคอลัมน์ "ซ่อนรีวิว" แล้วหรือยัง (hardening_migration.sql)
// ============================================================
function hidden_columns_ready(PDO $pdo): bool
{
    static $ready = null;
    if ($ready === null) {
        try { $pdo->query('SELECT hidden_at FROM ratings LIMIT 1'); $ready = true; }
        catch (PDOException $e) { $ready = false; }
    }
    return $ready;
}

/**
 * เงื่อนไข SQL "เอาเฉพาะรีวิวที่นับในสถิติ" (ไม่รวมรายการที่ถูกซ่อน)
 * ใส่ต่อท้าย WHERE ของทุกคิวรีที่คิดสถิติ
 *
 * @param string $alias ชื่อย่อของตาราง ratings ในคิวรีนั้น ('' = ไม่มี alias)
 * @param bool   $first true = ขึ้นต้นด้วย WHERE, false = ต่อด้วย AND
 */
function sql_visible(PDO $pdo, string $alias = 'r', bool $first = false): string
{
    if (!hidden_columns_ready($pdo)) return '';
    $col = ($alias !== '' ? $alias . '.' : '') . 'hidden_at IS NULL';
    return ($first ? ' WHERE ' : ' AND ') . $col;
}

// ============================================================
//  ตั้งค่าการเชื่อมต่อฐานข้อมูล  (แก้ไขให้ตรงกับ server จริง)
// ============================================================
$db_host = 'localhost';
$db_name = 'eaksaha_rating';
$db_user = 'root';
$db_pass = '';

try {
    $pdo = new PDO(
        "mysql:host={$db_host};dbname={$db_name};charset=utf8mb4",
        $db_user,
        $db_pass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    die('เชื่อมต่อฐานข้อมูลไม่สำเร็จ: ' . $e->getMessage());
}
