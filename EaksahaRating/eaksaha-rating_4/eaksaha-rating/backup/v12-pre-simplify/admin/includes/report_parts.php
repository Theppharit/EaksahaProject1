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

    // ปุ่มลัด: รีวิว 1-2 ดาว ใน 30 วันล่าสุด — งานที่หัวหน้าเปิดหน้านี้มาทำบ่อยที่สุด
    $urgentOn  = $scoreFilter === 'low' && $range === '30d';
    $urgentUrl = $urgentOn
        ? '?' . http_build_query(array_diff_key($keep, ['score' => 1]) + ['range' => '30d'])
        : '?' . http_build_query(array_diff_key($keep, ['score' => 1]) + ['range' => '30d', 'score' => 'low']);

    $isFiltered = $range !== 'all' || $keep;

    ob_start(); ?>
<div class="filter-bar filter-bar-one">

    <a class="pill urgent <?= $urgentOn ? 'active' : '' ?>" data-ajax
       href="<?= htmlspecialchars($urgentUrl) ?>"
       title="รีวิว 1-2 ดาว ใน 30 วันล่าสุด"><?= rpIcon('alert') ?>ต้องดูด่วน</a>

    <div class="filter-sep"></div>

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
 * แถบสรุปผล + แถบกระจายคะแนนที่ "กดกรองได้"
 * เดิมแถบนี้เป็นแค่ภาพประกอบ ทั้งที่มันคือทางลัดที่ตรงกับสิ่งที่คนอยากทำที่สุด
 * คือ "ขอดูเฉพาะรีวิว 1 ดาว" — เลยทำให้กดได้เลย
 */
function reportResults(
    string $rangeText, int $fCount, ?float $fAvg,
    array $dist, int $distTotal, array $distColors, array $distLabels,
    int $fPending, string $scoreFilter, array $keepForLinks
): string {
    $starLink = function ($score) use ($keepForLinks, $scoreFilter) {
        $q = $keepForLinks;
        // กดดาวเดิมซ้ำ = ยกเลิกตัวกรองนั้น
        if ((string) $scoreFilter === (string) $score) { unset($q['score']); }
        else { $q['score'] = (string) $score; }
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
        <div class="rs-dist-hint">กดที่แถบเพื่อดูเฉพาะดาวนั้น</div>
        <?php foreach ($dist as $score => $cnt):
            $pct    = round($cnt / $distTotal * 100);
            $active = (string) $scoreFilter === (string) $score;
        ?>
            <a class="dist-row <?= $active ? 'is-active' : '' ?> <?= $cnt === 0 ? 'is-empty' : '' ?>"
               data-ajax href="<?= htmlspecialchars($starLink($score)) ?>"
               title="<?= $distLabels[$score] ?> — <?= $active ? 'กดอีกครั้งเพื่อยกเลิก' : 'กดเพื่อกรอง' ?>">
                <span class="dist-score"><?= $score ?> <span aria-hidden="true">★</span><span class="sr-only">ดาว</span></span>
                <span class="dist-track">
                    <span class="dist-bar" style="width:<?= max($cnt > 0 ? 2 : 0, $pct) ?>%; background:<?= $distColors[$score] ?>;"></span>
                </span>
                <span class="dist-count"><?= number_format($cnt) ?></span>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<?php if ($scoreFilter !== ''): ?>
    <?php
    $q = $keepForLinks; unset($q['score']);
    $chipText = $scoreFilter === 'low' ? 'เฉพาะรีวิว 1-2 ดาว' : 'เฉพาะรีวิว ' . (int) $scoreFilter . ' ดาว';
    ?>
    <div class="active-filter">
        <span class="af-label"><?= htmlspecialchars($chipText) ?></span>
        <a class="af-clear" data-ajax href="<?= htmlspecialchars('?' . http_build_query($q)) ?>" aria-label="ยกเลิกตัวกรองดาว"><?= rpIcon('x') ?>ยกเลิก</a>
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
function reportList(array $ratings): string
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
            <tr><td colspan="4" class="empty-cell">ไม่พบรีวิวตามเงื่อนไขที่เลือก — ลองขยายช่วงเวลา หรือกด "ล้างตัวกรอง"</td></tr>
        <?php else: ?>
            <?php foreach ($ratings as $row):
                $scored = $row['score'] !== null;
                $sc     = (int) $row['score'];
                $conf   = isset($row['ai_confidence']) ? (float) $row['ai_confidence'] : null;
                $aiSc   = isset($row['ai_score']) && $row['ai_score'] !== null ? (int) $row['ai_score'] : null;
            ?>
                <tr>
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

                    <!-- คะแนน + เหตุผลที่ AI ให้ดาวนั้น + ปุ่มแก้
                         เหตุผลต้องเห็นได้เสมอ เพราะนี่คือคะแนนที่ใช้ประเมินคนจริงๆ -->
                    <td class="score-cell" data-rid="<?= (int) $row['id'] ?>">
                        <div class="score-view">
                            <?php if ($scored): ?>
                                <span class="stars-display" aria-label="<?= $sc ?> จาก 5 คะแนน"><span aria-hidden="true"><?= str_repeat('★', $sc) . str_repeat('☆', 5 - $sc) ?></span></span>
                                <?php if (($row['ai_status'] ?? '') === 'manual'): ?>
                                    <span class="ai-chip manual">ผู้ดูแลกรอกเอง</span>
                                <?php elseif ($conf !== null && $conf < 0.5): ?>
                                    <span class="ai-chip low">AI ไม่ค่อยมั่นใจ <?= number_format($conf * 100) ?>%</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="ai-chip pending">รอ AI ให้ดาว</span>
                            <?php endif; ?>

                            <?php
                            // ปุ่มแก้ดาว = เฉพาะผู้ดูแลระบบ
                            // ผู้จัดการดูได้อย่างเดียว แต่ฝากโน้ตถึงพนักงานได้
                            if (can('edit_score')): ?>
                                <button type="button" class="score-edit-btn" aria-label="แก้ดาวรายการนี้">แก้</button>
                            <?php endif; ?>
                            <?php if (can('note')): ?>
                                <button type="button" class="note-btn" aria-label="ฝากข้อความถึงพนักงานคนนี้">ฝากข้อความ</button>
                            <?php endif; ?>

                            <?php if (($row['ai_status'] ?? '') === 'manual' && $aiSc !== null && $aiSc !== $sc): ?>
                                <div class="ai-why">AI เคยให้ <?= $aiSc ?> ดาว</div>
                            <?php endif; ?>
                            <?php if (!empty($row['ai_reason'])): ?>
                                <div class="ai-why"><?= htmlspecialchars($row['ai_reason']) ?></div>
                            <?php endif; ?>
                            <?php if ((int) ($row['note_count'] ?? 0) > 0): ?>
                                <div class="note-flag"><?= (int) $row['note_count'] ?> ข้อความที่ฝากไว้แล้ว</div>
                            <?php endif; ?>
                        </div>

                        <?php if (can('note')): ?>
                        <div class="note-box" hidden>
                            <textarea class="note-text" rows="3" maxlength="500"
                                placeholder="เช่น เคสนี้ลูกค้ารอนาน ช่วยคุยกับลูกค้าและแจ้งคิวล่วงหน้าด้วยนะ"></textarea>
                            <div class="note-actions">
                                <button type="button" class="btn btn-primary btn-sm note-save">ส่งถึงพนักงาน</button>
                                <button type="button" class="btn btn-secondary btn-sm note-cancel">ยกเลิก</button>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="score-edit" hidden>
                            <select class="score-edit-sel" aria-label="เลือกดาวใหม่">
                                <?php for ($i = 5; $i >= 1; $i--): ?>
                                    <option value="<?= $i ?>" <?= $scored && $sc === $i ? 'selected' : '' ?>><?= $i ?> ดาว</option>
                                <?php endfor; ?>
                            </select>
                            <button type="button" class="btn btn-primary btn-sm score-edit-save">บันทึก</button>
                            <button type="button" class="btn btn-secondary btn-sm score-edit-cancel">ยกเลิก</button>
                        </div>
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
