<?php
// ============================================================
//  ชิ้นส่วนหน้ารายงานคะแนน (report.php)
//  ------------------------------------------------------------
//  แยกออกมาเป็นฟังก์ชัน เพื่อให้ประกอบ HTML ชุดเดียวกันได้ 2 ทาง:
//    • เปิดหน้าปกติ  → echo ลงไปในหน้า
//    • กดปุ่มกรอง    → ส่งกลับเป็น JSON ให้ JS สลับเฉพาะจุด (ไม่โหลดหน้าใหม่)
//  ถ้าจะแก้หน้าตาส่วนไหน แก้ที่นี่ที่เดียวพอ ทั้งสองทางจะตรงกันเสมอ
// ============================================================

/** ไอคอนเล็กๆ ที่ใช้ซ้ำ */
function rpIcon(string $name): string
{
    $open = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">';
    switch ($name) {
        case 'check':    return $open . '<polyline points="20 6 9 17 4 12"/></svg>';
        case 'calendar': return $open . '<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>';
        case 'reset':    return $open . '<path d="M3 12a9 9 0 1 0 3-6.7"/><polyline points="3 3 3 9 9 9"/></svg>';
    }
    return '';
}

/**
 * แถบตัวกรอง
 * ปุ่มช่วงเวลาเป็น <a> ธรรมดา — ถ้าปิด JavaScript ก็ยังกดได้ตามปกติ
 * มีแค่ data-ajax บอก filters.js ว่า "ปุ่มนี้เปลี่ยนค่าโดยไม่ต้องโหลดหน้าใหม่ได้"
 */
function reportFilterBar(
    string $range, string $dateFrom, string $dateTo,
    ?int $brandId, ?int $staffId,
    array $brandOptions, array $staffOptions
): string {
    // ค่าอื่นที่ต้องพกติดไปกับลิงก์ เพื่อไม่ให้ตัวกรองที่เลือกไว้หายไป
    $keep = array_filter([
        'brand_id' => $brandId !== null ? (string) $brandId : '',
        'staff_id' => $staffId !== null ? (string) $staffId : '',
    ], fn($v) => $v !== '');

    $link = function (array $ov) use ($keep) {
        return '?' . http_build_query(array_merge($keep, $ov));
    };

    $ranges = ['all' => 'ทั้งหมด', 'today' => 'วันนี้', '7d' => '7 วัน', '30d' => '30 วัน', 'month' => 'เดือนนี้'];

    ob_start(); ?>
<div class="filter-bar" style="flex-direction:column; align-items:stretch; gap:14px;">

    <div class="filter-group">
        <span class="filter-label">ช่วงเวลา</span>
        <?php foreach ($ranges as $k => $label): ?>
            <a class="pill <?= $range === $k ? 'active' : '' ?>" data-ajax
               href="<?= htmlspecialchars($link(['range' => $k])) ?>"><?= $label ?></a>
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

    <form class="filter-group" method="GET" data-ajax data-ajax-auto>
        <span class="filter-label">เจาะจง</span>
        <input type="hidden" name="range" value="<?= htmlspecialchars($range) ?>">
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

        <a href="report.php" class="pill" data-ajax style="margin-left:auto;"><?= rpIcon('reset') ?>ล้างตัวกรอง</a>
    </form>
</div>
<?php
    return trim(ob_get_clean());
}

/** แถบสรุปผล + กราฟแท่งการกระจายคะแนน */
function reportResults(
    string $rangeText, int $fCount, ?float $fAvg,
    array $dist, int $distTotal, array $distColors, array $distLabels,
    int $fPending = 0
): string {
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
        <?php foreach ($dist as $score => $cnt): $pct = round($cnt / $distTotal * 100); ?>
            <div class="dist-row" title="<?= $distLabels[$score] ?>">
                <span class="dist-score"><?= $score ?> <span aria-hidden="true">★</span><span class="sr-only">ดาว</span></span>
                <div class="dist-track">
                    <div class="dist-bar" style="width:<?= max($cnt > 0 ? 2 : 0, $pct) ?>%; background:<?= $distColors[$score] ?>;"></div>
                </div>
                <span class="dist-count"><?= number_format($cnt) ?></span>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php
    return trim(ob_get_clean());
}

/** ตารางรายการรีวิว */
function reportList(array $ratings): string
{
    ob_start(); ?>
<table>
    <thead>
        <tr><th>พนักงานขาย</th><th>แบรนด์</th><th>ตำแหน่ง</th><th>คะแนน</th><th>สิ่งที่ลูกค้าเขียน</th><th>วันที่</th></tr>
    </thead>
    <tbody>
        <?php if (empty($ratings)): ?>
            <tr><td colspan="6" class="empty-cell">ไม่พบรีวิวตามเงื่อนไขที่เลือก — ลองขยายช่วงเวลา หรือกด "ล้างตัวกรอง"</td></tr>
        <?php else: ?>
            <?php foreach ($ratings as $row):
                $scored = $row['score'] !== null;
                $sc     = (int) $row['score'];
                $conf   = isset($row['ai_confidence']) ? (float) $row['ai_confidence'] : null;
            ?>
                <tr>
                    <td class="wrap-cell"><?= htmlspecialchars($row['name']) ?></td>
                    <td>
                        <?php if (!empty($row['brand_name'])): ?>
                            <span class="brand-tag" style="<?= brand_tag_style($row['brand_color'] ?? '#D81300') ?>"><?= htmlspecialchars($row['brand_name']) ?></span>
                        <?php else: ?>
                            <span class="brand-tag none">-</span>
                        <?php endif; ?>
                    </td>
                    <td class="wrap-cell"><?= htmlspecialchars($row['position']) ?></td>

                    <!-- คะแนน + เหตุผลที่ AI ให้ดาวนั้น + ปุ่มแก้
                         เหตุผลต้องเห็นได้เสมอ เพราะนี่คือคะแนนที่ใช้ประเมินคนจริงๆ
                         และต้องแก้ทับได้ ถ้า AI อ่านประชดไม่ออกหรือตีความผิด -->
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

                            <button type="button" class="score-edit-btn" aria-label="แก้ดาวรายการนี้">แก้</button>

                            <?php
                            // ถ้าผู้ดูแลเคยแก้ทับ ให้เห็นด้วยว่า AI เดิมให้เท่าไหร่
                            $aiSc = isset($row['ai_score']) && $row['ai_score'] !== null ? (int) $row['ai_score'] : null;
                            if (($row['ai_status'] ?? '') === 'manual' && $aiSc !== null && $aiSc !== $sc): ?>
                                <div class="ai-why">AI เคยให้ <?= $aiSc ?> ดาว</div>
                            <?php endif; ?>

                            <?php if (!empty($row['ai_reason'])): ?>
                                <div class="ai-why"><?= htmlspecialchars($row['ai_reason']) ?></div>
                            <?php endif; ?>
                        </div>

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

                    <td class="wrap-cell muted-cell">
                        <?= $row['feedback'] !== null && $row['feedback'] !== ''
                            ? nl2br(htmlspecialchars($row['feedback']))
                            : '<span class="dash-cell">-</span>' ?>
                    </td>
                    <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($row['created_at']))) ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>
<?php
    return trim(ob_get_clean());
}
