<?php
declare(strict_types=1);

/**
 * Партиал журнала для студента (без вкладок).
 *
 * Ожидает: $subj, $columns, $columnsDesc, $pageLessons, $dateInfo,
 * $page, $totalPagesOut, $startIdx, $endIdx, $kind, $subjectId.
 *
 * В $dateInfo[$d] теперь есть 'weights' (параллельно 'marks').
 *
 * Сверху — горизонтальная полоса дат с оценками (ASC). Ниже — пагинация.
 * Ниже — единый список «Темы журнала» в DESC (свежие сверху).
 *
 * Вес оценки показывается маленьким бейджем рядом с каждой оценкой:
 *   --low  (1)   серый,
 *   --mid  (3–5) оливковый,
 *   --high (10+) тёмно-красный.
 */

$today = date('Y-m-d');
$monthNames = [
    1 => 'Январь', 2 => 'Февраль', 3 => 'Март', 4 => 'Апрель',
    5 => 'Май', 6 => 'Июнь', 7 => 'Июль', 8 => 'Август',
    9 => 'Сентябрь', 10 => 'Октябрь', 11 => 'Ноябрь', 12 => 'Декабрь',
];

$weightClass = static function (int $w): string {
    if ($w >= 10) return 'pzk-weight--high';
    if ($w >= 3)  return 'pzk-weight--mid';
    return 'pzk-weight--low';
};

$monthGroups = [];
foreach ($columns as $col) {
    $ts       = strtotime($col['date']);
    $monthKey = date('Y-m', $ts);
    $mNum     = (int) date('n', $ts);
    $year     = date('Y', $ts);
    if (!isset($monthGroups[$monthKey])) {
        $monthGroups[$monthKey] = [
            'label' => $monthNames[$mNum] . ' ' . $year,
            'count' => 0,
        ];
    }
    $monthGroups[$monthKey]['count']++;
}
?>
<style>
.sj-strip-wrap {
    overflow-x: auto;
    border: 1px solid rgba(26, 26, 26, 0.1);
    background: #fff;
}
.sj-strip {
    width: max-content;
    min-width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    font-size: 0.82rem;
}
.sj-strip th,
.sj-strip td {
    border-right: 1px solid rgba(26, 26, 26, 0.06);
    border-bottom: 1px solid rgba(26, 26, 26, 0.06);
    padding: 3px 4px;
    text-align: center;
    vertical-align: middle;
    min-width: 38px;
}
.sj-strip__corner {
    background: #efe2c0;
    color: #3a2a08;
    font-weight: 700;
    font-size: 0.72rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    text-align: left !important;
    padding-left: 12px !important;
    min-width: 140px;
    position: sticky;
    left: 0;
    z-index: 3;
}
.sj-strip__month {
    background: #6b5010;
    color: #fff;
    font-weight: 700;
    font-size: 0.75rem;
    padding: 4px 6px;
    letter-spacing: 0.02em;
    white-space: nowrap;
    text-transform: capitalize;
}
.sj-strip__day {
    background: #fbf7ec;
    color: #1a1a1a;
    font-weight: 700;
    font-size: 0.78rem;
    padding: 4px 3px;
    border-bottom: 2px solid #8b6914;
    font-variant-numeric: tabular-nums;
}
.sj-strip__label {
    background: #fbf7ec;
    color: #6b5010;
    font-weight: 600;
    font-size: 0.78rem;
    text-align: left !important;
    padding-left: 12px !important;
    position: sticky;
    left: 0;
    z-index: 2;
}
.sj-strip__cell {
    background: #fff;
    font-weight: 600;
    font-size: 0.92rem;
    color: #999;
    font-variant-numeric: tabular-nums;
}
.sj-strip__cell.is-filled { color: #1a1a1a; }
.sj-strip__cell[data-mark="5"],
.sj-strip__cell[data-mark="5-"] { color: #15803d; }
.sj-strip__cell[data-mark="4"],
.sj-strip__cell[data-mark="4-"] { color: #1e40af; }
.sj-strip__cell[data-mark="3"],
.sj-strip__cell[data-mark="3-"] { color: #92400e; }
.sj-strip__cell[data-mark="2"] { color: #b91c1c; }
.sj-strip__cell[data-mark="н"],
.sj-strip__cell[data-mark="ну"] { color: #6b7280; font-style: italic; }

/* Бейдж веса */
.pzk-weight {
    display: inline-block;
    font-size: 0.6rem;
    font-weight: 700;
    line-height: 1;
    padding: 1px 3px;
    border-radius: 3px;
    margin-left: 2px;
    vertical-align: super;
    cursor: help;
    font-variant-numeric: tabular-nums;
}
.pzk-weight--low  { background: rgba(107, 114, 128, 0.15); color: #4b5563; }
.pzk-weight--mid  { background: rgba(139, 105, 20, 0.18);  color: #6b5010; }
.pzk-weight--high { background: rgba(139, 26, 26, 0.18);   color: #8b1a1a; }

.sj-pagination {
    display: flex;
    gap: 10px;
    align-items: center;
    justify-content: center;
    margin: var(--space-sm) 0;
}
.sj-pagination__info {
    font-size: 0.85rem;
    color: #666;
}
.sj-pagination .is-disabled {
    opacity: 0.4;
    pointer-events: none;
}

.sj-section-title {
    font-family: 'Cormorant Garamond', 'Inter', serif;
    font-size: 1.3rem;
    font-weight: 600;
    color: #6b5010;
    margin: var(--space-md) 0 var(--space-sm);
    padding-bottom: 4px;
    border-bottom: 1px solid #e8dcc0;
}

.sj-entry {
    padding: 12px 16px;
    margin-bottom: 10px;
    background: #fbf7ec;
    border: 1px solid #e8dcc0;
    border-left: 4px solid #8b6914;
    border-radius: 3px;
    font-size: 1.02rem;
    line-height: 1.55;
}
.sj-entry__head {
    display: flex;
    align-items: baseline;
    gap: 12px;
    flex-wrap: wrap;
    padding-bottom: 8px;
    margin-bottom: 8px;
    border-bottom: 1px dashed #e8dcc0;
}
.sj-entry__date {
    font-weight: 700;
    color: #6b5010;
    font-size: 0.88rem;
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
}
.sj-entry__topic {
    color: #1a1a1a;
    font-weight: 600;
    font-size: 0.9rem;
    line-height: 1.4;
}
.sj-entry__note-label {
    font-family: 'Cormorant Garamond', 'Inter', serif;
    font-size: 1rem;
    font-weight: 600;
    color: #8b6914;
    letter-spacing: 0.02em;
    margin-bottom: 4px;
}
.sj-entry__lesson-note {
    padding: 6px 0;
    color: #1a1a1a;
    line-height: 1.6;
    font-size: 1.12rem;
}
.sj-entry__hw {
    color: #92400e;
    margin-top: 4px;
    font-size: 1.12rem;
}
.sj-entry__mark-note {
    padding-top: 10px;
    margin-top: 10px;
    border-top: 1px dashed rgba(232, 220, 192, 0.7);
    color: #1a1a1a;
    line-height: 1.6;
    font-size: 1.12rem;
}
.sj-entry__mark-note .sj-entry__note-label {
    color: #d97706;
    display: flex;
    align-items: center;
    gap: 8px;
}
.sj-entry__mark-badge {
    display: inline-block;
    min-width: 36px;
    padding: 2px 10px;
    border-radius: 3px;
    background: rgba(217, 119, 6, 0.16);
    color: #92400e;
    font-weight: 700;
    font-variant-numeric: tabular-nums;
    text-align: center;
    font-size: 1.15rem;
    line-height: 1.2;
}

.sj-empty {
    font-size: 0.95rem;
    font-style: italic;
    color: #999;
    padding: 8px 0;
}

.sj-filter-form {
    display: flex;
    gap: 8px;
    align-items: center;
    flex-wrap: wrap;
    padding: 8px 10px;
    background: #fbf7ec;
    border: 1px solid #e8dcc0;
    margin-bottom: var(--space-sm);
}
.sj-filter-form label {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 0.88rem;
    color: #2d2d2d;
}
.sj-filter-form .pzk-field__input {
    padding: 6px 10px;
    border: 1px solid #d4c8a8;
    background: #fff;
    font-family: 'Inter', -apple-system, sans-serif;
    font-size: 0.9rem;
    color: #1a1a1a;
    border-radius: 2px;
}
.sj-pagination .pzk-btn {
    padding: 4px 10px;
    font-family: 'Cormorant Garamond', 'Inter', serif;
    font-size: 0.88rem;
    font-weight: 600;
    letter-spacing: 0.03em;
    border: 1px solid rgba(26, 26, 26, 0.2);
    background: #fff;
    color: #2d2d2d;
    cursor: pointer;
    border-radius: 2px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    line-height: 1.2;
}
.sj-pagination .pzk-btn:hover {
    background: rgba(139, 105, 20, 0.06);
    border-color: rgba(139, 105, 20, 0.4);
    color: #1a1a1a;
}
</style>

<?php if ($columns === []): ?>
    <div class="sj-empty">На этой странице нет уроков.</div>
<?php else: ?>
    <div class="sj-strip-wrap">
        <table class="sj-strip">
            <thead>
                <tr>
                    <th class="sj-strip__corner" rowspan="2">Мои оценки</th>
                    <?php foreach ($monthGroups as $mk => $mg): ?>
                        <th class="sj-strip__month" colspan="<?= (int) $mg['count'] ?>">
                            <?= e($mg['label']) ?>
                        </th>
                    <?php endforeach; ?>
                </tr>
                <tr>
                    <?php foreach ($columns as $col): ?>
                        <th class="sj-strip__day"
                            title="<?= e(date('d.m.Y', strtotime($col['date'])) . ' · ' . implode('; ', $col['topics'])) ?>">
                            <?= e(date('d', strtotime($col['date']))) ?>
                        </th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <th class="sj-strip__label">Оценка</th>
                    <?php foreach ($columns as $col): ?>
                        <?php
                            $info  = $dateInfo[$col['date']] ?? ['mark_display' => '', 'marks' => [], 'weights' => []];
                            $mDisp = (string) ($info['mark_display'] ?? '');
                            $first = (string) ($info['marks'][0] ?? '');
                            $firstW = max(1, (int) ($info['weights'][0] ?? 10));
                            $firstIsNum = (bool) preg_match('/^[2-5]/', $first);
                        ?>
                        <td class="sj-strip__cell <?= $mDisp !== '' ? 'is-filled' : '' ?>"
                            <?= $mDisp !== '' ? 'data-mark="' . e($first) . '"' : '' ?>>
                            <?= $mDisp !== '' ? e($mDisp) : '—' ?><?php if ($firstIsNum): ?><span class="pzk-weight <?= $weightClass($firstW) ?>" title="Вес: <?= $firstW ?>"><?= $firstW ?></span><?php endif; ?>
                        </td>
                    <?php endforeach; ?>
                </tr>
            </tbody>
        </table>
    </div>

    <?php if ($totalPagesOut > 1): ?>
        <?php
            $base = 'index.php?page=student&tab=journal'
                . '&journal_subject=' . (int) $subjectId
                . '&journal_kind='    . rawurlencode($kind)
                . '&p=';
        ?>
        <nav class="sj-pagination" aria-label="Страницы журнала">
            <a class="pzk-btn <?= $page <= 0 ? 'is-disabled' : '' ?>"
               href="<?= $page > 0 ? $base . ($page - 1) : '#' ?>"
               <?= $page > 0 ? 'data-p="' . ($page - 1) . '"' : '' ?>
               <?= $page <= 0 ? 'tabindex="-1" aria-disabled="true"' : '' ?>>← Назад</a>
            <span class="sj-pagination__info">
                Страница <?= $page + 1 ?> из <?= $totalPagesOut ?>
                (уроки <?= (int) $startIdx ?>–<?= (int) $endIdx ?>)
            </span>
            <a class="pzk-btn <?= $page >= $totalPagesOut - 1 ? 'is-disabled' : '' ?>"
               href="<?= $page < $totalPagesOut - 1 ? $base . ($page + 1) : '#' ?>"
               <?= $page < $totalPagesOut - 1 ? 'data-p="' . ($page + 1) . '"' : '' ?>
               <?= $page >= $totalPagesOut - 1 ? 'tabindex="-1" aria-disabled="true"' : '' ?>>Вперёд →</a>
        </nav>
    <?php endif; ?>

    <h3 class="sj-section-title">Темы журнала</h3>
    <?php foreach ($columnsDesc as $col): ?>
        <?php
            $info = $dateInfo[$col['date']] ?? [
                'marks' => [], 'weights' => [], 'mark_display' => '', 'mark_note' => '',
                'lesson_note' => '', 'homework' => '',
            ];
            $firstMark = (string) ($info['marks'][0] ?? '');
            $firstWeight = max(1, (int) ($info['weights'][0] ?? 10));
            $firstIsNum = (bool) preg_match('/^[2-5]/', $firstMark);
        ?>
        <div class="sj-entry">
            <div class="sj-entry__head">
                <span class="sj-entry__date"><?= e(date('d.m.Y', strtotime($col['date']))) ?></span>
                <span class="sj-entry__topic"><?= e(implode('; ', $col['topics'])) ?></span>
            </div>

            <?php if ($info['lesson_note'] !== '' || $info['homework'] !== ''): ?>
                <div class="sj-entry__lesson-note">
                    <div class="sj-entry__note-label">Примечание к уроку</div>
                    <?php if ($info['lesson_note'] !== ''): ?>
                        <div><?= e($info['lesson_note']) ?></div>
                    <?php endif; ?>
                    <?php if ($info['homework'] !== ''): ?>
                        <div class="sj-entry__hw"><strong>ДЗ:</strong> <?= e($info['homework']) ?></div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($info['mark_note'] !== ''): ?>
                <div class="sj-entry__mark-note">
                    <div class="sj-entry__note-label">
                        <?php if ($info['mark_display'] !== ''): ?>
                            <span class="sj-entry__mark-badge"><?= e($info['mark_display']) ?></span>
                            <?php if ($firstIsNum): ?>
                                <span class="pzk-weight <?= $weightClass($firstWeight) ?>" title="Вес: <?= $firstWeight ?>"><?= $firstWeight ?></span>
                            <?php endif; ?>
                        <?php endif; ?>
                        Примечание к оценке
                    </div>
                    <div><?= e($info['mark_note']) ?></div>
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
<?php endif; ?>