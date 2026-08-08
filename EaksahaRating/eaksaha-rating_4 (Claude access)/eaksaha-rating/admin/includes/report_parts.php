<?php
// ============================================================
//  ชิ้นส่วนหน้ารายงานคะแนน (report.php)
//  ------------------------------------------------------------
//  แยกออกมาเป็นฟังก์ชัน เพื่อให้ประกอบ HTML ชุดเดียวกันได้ 2 ทาง:
//    • เปิดหน้าปกติ  → echo ลงไปในหน้า
//    • กดปุ่มกรอง    → ส่งกลับเป็น JSON ให้ JS สลับเฉพาะจุด (ไม่โหลดหน้าใหม่)
//  ถ้าจะแก้หน้าตาส่วนไหน แก้ที่นี่ที่เดียวพอ ทั้งสองทางจะตรงกันเสมอ
// ============================================================

const RP_THAI_MON = ['', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.',
                     'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];

/** วันที่แบบสั้น เช่น "6 ส.ค. 12:00" — อ่านเร็วกว่า 06/08/2026 12:00 */
function rpDateTime(string $ts): string
{
    $t = strtotime($ts);
    return date('j', $t) . ' ' . RP_THAI_MON[(int) date('n', $t)] . ' ' . date('H:i', $t);
}

/** ไอคอนเล็กๆ ที่ใช้ซ้ำ */
function rpIcon(string $name): string
{
    $open = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">';
    switch ($name) {
        case 'check':    return $open . '<polyline points="20 6 9 17 4 12"/></svg>';
        case 'calendar': return $open . '<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>';
        case 'reset':    return $open . '<path d="M3 12a9 9 0 1 0 3-6.7"/><polyline points="3 3 3 9 9 9"/></svg>';
        case 'alert':    return $open . '<path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>';
        case 'excel':    return $open . '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>';
        case 'x':        return $open . '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';
    }
    return '';
}

/**
 * แถบตัวกรอง — ยุบเหลือแถวเดียว
 * ปุ่มทุกอันเป็น <a> ธรรมดา ถ้าปิด JavaScript ก็ยังกดได้ตามปกติ
 * data-ajax บอก filters.js ว่าเปลี่ยนค่าได้โดยไม่ต้องโหลดหน้าใหม่
 */
function reportFilterBar(
    string $range, string $dateFrom, string $dateTo,
    ?int $brandId, ?int $staffId, string $scoreFilter,
    array $brandOptions, array $staffOptions
): string {
    // ค่าที่ต้องพกติดไปกับทุกลิงก์ เพื่อไม่ให้ตัวกรองอื่นที่เลือกไว้หายไป
    $keep = array_filter([
        'brand_id' => $brandId !== null ? (string) $brandId : '',
        'staff_id' => $staffId !== null ? (string) $staffId : '',
        'score'    => $scoreFilter,
    ], fn($v) => $v !== '');

    // ลิงก์ส่งออก — พกตัวกรองชุดเดียวกันไปด้วย ไฟล์จะได้ตรงกับที่เห็นบนจอ
    $exportQ = array_merge($keep, ['range' => $range]);
    if ($range === 'custom') {
        $exportQ['date_from'] = $dateFrom;
        $exportQ['date_to']   = $dateTo;
    }
    $exportUrl = 'report_export.php?' . http_build_query($exportQ);

    // ตัดตัวเลือก "วันนี้" ออก — ซ้ำซ้อนกับ 7 วัน และมักว่างจนดูเหมือนระบบพัง
    $ranges = ['all' => 'ทั้งหมด', '7d' => '7 วัน', '30d' => '30 วัน', 'month' => 'เดือนนี้'];

    $isFiltered = $range !== 'all' || $keep;

    ob_start(); ?>
<div class="filter-bar filter-bar-one">

    <div class="filter-group">
        <?php foreach ($ranges as $k => $label): ?>
            <a class="pill <?= $range === $k ? 'active' : '' ?>" data-ajax
               href="<?= htmlspecialchars('?' . http_build_query(array_merge($keep, ['range' => $k]))) ?>"><?= $label ?></a>
        <?php endforeach; ?>

        <details class="range-custom" <?= $range === 'custom' ? 'open' : '' ?>>
            <summary class="pill <?= $range === 'custom' ? 'active' : '' ?>"><?= rpIcon('calendar') ?>กำหนดเอง</summary>
            <div class="range-custom-body">
                <form class="custom-range" method="GET" data-ajax>
                    <input type="hidden" name="range" value="custom">
                    <?php foreach ($keep as $k => $v): ?>
                        <input type="hidden" name="<?= htmlspecialchars($k) ?>" value="<?= htmlspecialchars($v) ?>">
                    <?php endforeach; ?>
                    <label class="filter-label" for="rpFrom">ตั้งแต่</label>
                    <input type="date" id="rpFrom" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>">
                    <label class="filter-label" for="rpTo">ถึง</label>
                    <input type="date" id="rpTo" name="date_to" value="<?= htmlspecialchars($dateTo) ?>">
                    <button type="submit" class="pill mint"><?= rpIcon('check') ?>ใช้ช่วงนี้</button>
                </form>
            </div>
        </details>
    </div>

    <div class="filter-sep"></div>

    <form class="filter-group" method="GET" data-ajax data-ajax-auto>
        <input type="hidden" name="range" value="<?= htmlspecialchars($range) ?>">
        <?php if ($scoreFilter !== ''): ?>
            <input type="hidden" name="score" value="<?= htmlspecialchars($scoreFilter) ?>">
        <?php endif; ?>
        <?php if ($range === 'custom'): ?>
            <input type="hidden" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>">
            <input type="hidden" name="date_to" value="<?= htmlspecialchars($dateTo) ?>">
        <?php endif; ?>

        <select name="brand_id" class="filter-select" aria-label="กรองตามแบรนด์">
            <option value="">ทุกแบรนด์</option>
            <?php foreach ($brandOptions as $b): ?>
                <option value="<?= (int) $b['id'] ?>" <?= $brandId === (int) $b['id'] ? 'selected' : '' ?>><?= htmlspecialchars($b['name']) ?></option>
            <?php endforeach; ?>
        </select>

        <select name="staff_id" class="filter-select" aria-label="กรองตามพนักงานขาย">
            <option value="">พนักงานขายทุกคน</option>
            <?php foreach ($staffOptions as $s): ?>
                <option value="<?= (int) $s['id'] ?>" <?= $staffId === (int) $s['id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['name']) ?></option>
            <?php endforeach; ?>
        </select>

        <noscript><button type="submit" class="pill mint"><?= rpIcon('check') ?>ใช้ตัวกรอง</button></noscript>
    </form>

    <div class="filter-tail">
        <a href="<?= htmlspecialchars($exportUrl) ?>" class="pill"
           title="โหลดรายการที่กรองอยู่เป็นไฟล์"><?= rpIcon('excel') ?>ส่งออก Excel</a>
        <?php if ($isFiltered): ?>
            <a href="report.php" class="pill" data-ajax><?= rpIcon('reset') ?>ล้างตัวกรอง</a>
        <?php endif; ?>
    </div>
</div>
<?php
    return trim(ob_get_clean());
}

/**
 * แถบสรุปผล + แถบกระจายคะแนนที่ "ติ๊กเลือกได้หลายดาว"
 * แถบนี้คือทางลัดที่ตรงกับสิ่งที่คนอยากทำที่สุด — "ขอดูเฉพาะรีวิว 1-2 ดาว"
 */
function reportResults(
    string $rangeText, int $fCount, ?float $fAvg,
    array $dist, int $distTotal, array $distColors, array $distLabels,
    int $fPending, array $selected, array $keepForLinks
): string {
    // ติ๊กเลือกดาวได้หลายค่าพร้อมกัน
    // กดดาวที่ยังไม่ติ๊ก = เพิ่มเข้าไป / กดดาวที่ติ๊กอยู่ = เอาออก
    $starLink = function (int $score) use ($keepForLinks, $selected) {
        $q   = $keepForLinks;
        $now = $selected;

        if (in_array($score, $now, true)) {
            $now = array_values(array_diff($now, [$score]));
        } else {
            $now[] = $score;
            sort($now);
        }

        // ติ๊กครบ 5 ดาว = เท่ากับไม่ได้กรอง ล้างทิ้งให้ URL สะอาด
        if (count($now) === 5 || count($now) === 0) { unset($q['score']); }
        else { $q['score'] = implode(',', $now); }

        return '?' . http_build_query($q);
    };

    ob_start(); ?>
<div class="result-strip">
    <div class="rs-stats">
        <div class="rs-stat">
            <div class="rs-label">ช่วงที่ดู</div>
            <div class="rs-value rs-small"><?= htmlspecialchars($rangeText) ?></div>
        </div>
        <div class="rs-stat">
            <div class="rs-label">จำนวนรีวิวที่พบ</div>
            <div class="rs-value"><?= number_format($fCount) ?> <small>รีวิว</small></div>
            <?php if ($fPending > 0): ?>
                <div class="rs-pending">รอ AI ให้ดาว <?= number_format($fPending) ?> รายการ</div>
            <?php endif; ?>
        </div>
        <div class="rs-stat">
            <div class="rs-label">คะแนนเฉลี่ย</div>
            <div class="rs-value rs-mint"><?= $fAvg !== null && $fCount > 0 ? number_format($fAvg, 2) : '-' ?> <small>/ 5</small></div>
        </div>
    </div>

    <div class="rs-dist">
        <div class="rs-dist-hint">ติ๊กเลือกได้หลายดาวพร้อมกัน · กดซ้ำเพื่อเอาออก</div>
        <?php foreach ($dist as $score => $cnt):
            $pct = round($cnt / $distTotal * 100);
            $on  = in_array((int) $score, $selected, true);
        ?>
            <a class="dist-row <?= $on ? 'is-active' : '' ?> <?= $cnt === 0 ? 'is-empty' : '' ?>"
               data-ajax href="<?= htmlspecialchars($starLink((int) $score)) ?>"
               role="checkbox" aria-checked="<?= $on ? 'true' : 'false' ?>"
               title="<?= $distLabels[$score] ?> — <?= $on ? 'กดเพื่อเอาออก' : 'กดเพื่อเลือก' ?>">
                <span class="dist-check" aria-hidden="true"></span>
                <span class="dist-score"><?= $score ?> <span aria-hidden="true">★</span><span class="sr-only">ดาว</span></span>
                <span class="dist-track">
                    <span class="dist-bar" style="width:<?= max($cnt > 0 ? 2 : 0, $pct) ?>%; background:<?= $distColors[$score] ?>;"></span>
                </span>
                <span class="dist-count"><?= number_format($cnt) ?></span>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<?php if (!empty($selected)): ?>
    <?php
    $q = $keepForLinks; unset($q['score']);
    $chipText = 'เฉพาะรีวิว ' . implode(', ', $selected) . ' ดาว';
    ?>
    <div class="active-filter">
        <span class="af-label"><?= htmlspecialchars($chipText) ?></span>
        <a class="af-clear" data-ajax href="<?= htmlspecialchars('?' . http_build_query($q)) ?>" aria-label="เลิกกรองตามดาว"><?= rpIcon('x') ?>ดูทุกดาว</a>
    </div>
<?php endif; ?>
<?php
    return trim(ob_get_clean());
}

/**
 * ตารางรายการรีวิว — เหลือ 4 คอลัมน์
 * เดิมมี 6 คอลัมน์ โดยตำแหน่งงานซ้ำกันแทบทุกแถว และเบียดช่องข้อความลูกค้า
 * ซึ่งเป็นข้อมูลสำคัญที่สุดจนอ่านไม่ออก จึงยุบแบรนด์กับตำแหน่งไปไว้ใต้ชื่อ
 */
function reportList(array $ratings, bool $showHidden = false): string
{
    ob_start(); ?>
<table class="review-table">
    <thead>
        <tr>
            <th>พนักงานขาย</th>
            <th>คะแนน</th>
            <th>สิ่งที่ลูกค้าเขียน</th>
            <th>วันที่</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($ratings)): ?>
            <tr><td colspan="4" class="empty-cell"><?= $showHidden
                ? 'ไม่มีรายการที่ถูกซ่อนไว้'
                : 'ไม่พบรีวิวตามเงื่อนไขที่เลือก — ลองขยายช่วงเวลา หรือกด "ล้างตัวกรอง"' ?></td></tr>
        <?php else: ?>
            <?php foreach ($ratings as $row):
                $scored = $row['score'] !== null;
                $sc     = (int) $row['score'];
                $conf   = isset($row['ai_confidence']) ? (float) $row['ai_confidence'] : null;
                $isHidden = !empty($row['hidden_at']);
            ?>
                <tr class="<?= $isHidden ? 'is-hidden-row' : '' ?>">
                    <!-- ชื่อ + แบรนด์ + ตำแหน่ง รวมไว้ในช่องเดียว -->
                    <td class="staff-cell">
                        <div class="staff-name"><?= htmlspecialchars($row['name']) ?></div>
                        <div class="staff-meta">
                            <?php if (!empty($row['brand_name'])): ?>
                                <span class="brand-tag" style="<?= brand_tag_style($row['brand_color'] ?? '#D81300') ?>"><?= htmlspecialchars($row['brand_name']) ?></span>
                            <?php endif; ?>
                            <span class="staff-pos"><?= htmlspecialchars($row['position']) ?></span>
                        </div>
                    </td>

                    <!-- คะแนน + เหตุผลที่ AI ให้ + ปุ่มส่งข้อความถึงพนักงาน
                         คะแนนแก้ไม่ได้แล้ว เป็นผลจาก AI อ่านสิ่งที่ลูกค้าเขียนล้วนๆ
                         ถ้าเห็นว่าเคสไหนควรคุย ให้ส่งข้อความถึงพนักงานแทน -->
                    <td class="score-cell" data-rid="<?= (int) $row['id'] ?>">
                        <div class="score-view">
                            <?php if ($scored): ?>
                                <span class="stars-display" aria-label="<?= $sc ?> จาก 5 คะแนน"><span aria-hidden="true"><?= str_repeat('★', $sc) . str_repeat('☆', 5 - $sc) ?></span></span>
                                <?php if ($conf !== null && $conf < 0.5): ?>
                                    <span class="ai-chip low">AI ไม่ค่อยมั่นใจ <?= number_format($conf * 100) ?>%</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="ai-chip pending">รอ AI ให้ดาว</span>
                            <?php endif; ?>

                            <?php if (!empty($row['ai_reason'])): ?>
                                <div class="ai-why"><?= htmlspecialchars($row['ai_reason']) ?></div>
                            <?php endif; ?>

                            <?php if (can('note')): ?>
                                <button type="button" class="msg-btn" aria-expanded="false">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                                    ส่งข้อความ
                                </button>
                            <?php endif; ?>

                            <?php
                            // ซ่อนรีวิว = ตัดออกจากสถิติโดยไม่ลบข้อความ
                            // ผู้ดูแลเท่านั้น เพราะมีผลกับคะแนนของพนักงานโดยตรง
                            if (can('manage_users')): ?>
                                <button type="button" class="hide-btn" data-mode="<?= $isHidden ? 'unhide' : 'hide' ?>">
                                    <?= $isHidden ? 'เอากลับมานับ' : 'ซ่อนจากสถิติ' ?>
                                </button>
                            <?php endif; ?>

                            <?php if ($isHidden): ?>
                                <div class="hidden-why">
                                    ซ่อนโดย <?= htmlspecialchars((string) $row['hidden_by']) ?>
                                    <?php if (!empty($row['hidden_reason'])): ?>
                                        · <?= htmlspecialchars((string) $row['hidden_reason']) ?>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <?php if ((int) ($row['note_count'] ?? 0) > 0): ?>
                                <div class="note-flag"><?= (int) $row['note_count'] ?> ข้อความที่ส่งไปแล้ว</div>
                            <?php endif; ?>
                        </div>

                        <?php if (can('note')): ?>
                        <!-- กล่องพิมพ์ข้อความ — ไม่มีปุ่มยกเลิก กดปุ่ม "ส่งข้อความ" ซ้ำเพื่อปิด -->
                        <div class="note-box" hidden>
                            <textarea class="note-text" rows="3" maxlength="500"
                                placeholder="เช่น เคสนี้ลูกค้ารอนาน ช่วยแจ้งคิวล่วงหน้าด้วยนะ"></textarea>
                            <div class="note-actions">
                                <button type="button" class="btn btn-primary btn-sm note-save">ส่งถึง <?= htmlspecialchars($row['name']) ?></button>
                            </div>
                        </div>
                        <?php endif; ?>
                    </td>

                    <td class="feedback-cell">
                        <?= $row['feedback'] !== null && $row['feedback'] !== ''
                            ? nl2br(htmlspecialchars($row['feedback']))
                            : '<span class="dash-cell">-</span>' ?>
                    </td>

                    <td class="date-cell"><?= htmlspecialchars(rpDateTime($row['created_at'])) ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>
<?php
    return trim(ob_get_clean());
}

/**
 * แถบแบ่งหน้า
 * แสดงเฉพาะเมื่อมีมากกว่า 1 หน้า — หน้าเดียวไม่ต้องรกตา
 */
function reportPager(int $page, int $totalPages, array $query): string
{
    if ($totalPages <= 1) return '';

    $link = function (int $p) use ($query) {
        $q = $query;
        unset($q['ajax']);
        $q['page'] = $p;
        return '?' . http_build_query($q);
    };

    // แสดงเลขหน้ารอบๆ หน้าปัจจุบันเท่านั้น ไม่งั้น 40 หน้าก็ยาวเป็นพืด
    $from = max(1, $page - 2);
    $to   = min($totalPages, $page + 2);

    ob_start(); ?>
<div class="pager">
    <?php if ($page > 1): ?>
        <a class="pg" data-ajax href="<?= htmlspecialchars($link($page - 1)) ?>">← ก่อนหน้า</a>
    <?php else: ?>
        <span class="pg is-off">← ก่อนหน้า</span>
    <?php endif; ?>

    <?php if ($from > 1): ?>
        <a class="pg" data-ajax href="<?= htmlspecialchars($link(1)) ?>">1</a>
        <?php if ($from > 2): ?><span class="pg-gap">…</span><?php endif; ?>
    <?php endif; ?>

    <?php for ($i = $from; $i <= $to; $i++): ?>
        <?php if ($i === $page): ?>
            <span class="pg is-now"><?= $i ?></span>
        <?php else: ?>
            <a class="pg" data-ajax href="<?= htmlspecialchars($link($i)) ?>"><?= $i ?></a>
        <?php endif; ?>
    <?php endfor; ?>

    <?php if ($to < $totalPages): ?>
        <?php if ($to < $totalPages - 1): ?><span class="pg-gap">…</span><?php endif; ?>
        <a class="pg" data-ajax href="<?= htmlspecialchars($link($totalPages)) ?>"><?= $totalPages ?></a>
    <?php endif; ?>

    <?php if ($page < $totalPages): ?>
        <a class="pg" data-ajax href="<?= htmlspecialchars($link($page + 1)) ?>">ถัดไป →</a>
    <?php else: ?>
        <span class="pg is-off">ถัดไป →</span>
    <?php endif; ?>
</div>
<?php
    return trim(ob_get_clean());
}
