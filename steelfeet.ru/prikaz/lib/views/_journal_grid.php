<?php
declare(strict_types=1);

/**
 * Партиал сетки журнала.
 *
 * Ожидает: $subj, $columns, $futureFrom, $students, $marks, $lessonNotes,
 * $isTeacher, $page, $totalPagesOut, $startIdx, $endIdx, $kind, $subjectId,
 * $allLessons.
 *
 * Правая панель — две вкладки:
 *   - «Темы журнала» (открыта по умолчанию): уроки только текущей страницы;
 *   - «Примечания»: только примечания к датам текущей страницы, по одной
 *     карточке на дату (общее примечание к уроку + примечания к оценкам).
 */

static $journalStylesRendered = false;
if (!$journalStylesRendered):
    $journalStylesRendered = true;
?>
<style>
.journal-layout {
    display: flex;
    gap: 16px;
    align-items: flex-start;
    margin-top: var(--space-sm);
}
.journal-grid-wrap {
    flex: 1 1 auto;
    min-width: 0;
    overflow-x: auto;
    border: 1px solid rgba(26, 26, 26, 0.1);
    background: #fff;
}
.journal--grid {
    border-collapse: separate;
    border-spacing: 0;
    font-size: 0.8rem;
    width: max-content;
    min-width: 100%;
}
.journal--grid th,
.journal--grid td {
    border-right: 1px solid rgba(26, 26, 26, 0.06);
    border-bottom: 1px solid rgba(26, 26, 26, 0.06);
    padding: 3px 4px;
    text-align: center;
    vertical-align: middle;
    min-width: 38px;
}

.journal--grid th.journal-sticky-col {
    position: sticky;
    left: 0;
    z-index: 3;
    background: #fbf7ec;
    text-align: left !important;
    min-width: 200px;
    max-width: 260px;
    border-right: 1px solid rgba(26, 26, 26, 0.15) !important;
}
.journal--grid th.journal-head-student {
    top: 0;
    z-index: 5;
    font-weight: 700;
    font-size: 0.72rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #3a2a08;
    background: #efe2c0;
    vertical-align: middle;
}

.journal--grid th.journal-month-head {
    background: #6b5010;
    color: #ffffff;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: capitalize;
    letter-spacing: 0.02em;
    white-space: nowrap;
    padding: 4px 6px;
    border-bottom: 1px solid rgba(0, 0, 0, 0.25);
    position: sticky;
    top: 0;
    z-index: 3;
}
.journal--grid th.journal-month-head.journal-month-head--future {
    opacity: 0.55;
}

.journal--grid th.journal-col-head {
    background: #fbf7ec;
    color: #1a1a1a;
    font-weight: 700;
    font-size: 0.78rem;
    padding: 4px 3px;
    border-bottom: 2px solid #8b6914;
    cursor: pointer;
    user-select: none;
    min-width: 38px;
    position: sticky;
    top: 28px;
    z-index: 2;
}
.journal--grid th.journal-col-head:hover {
    background: #f5ecd5;
}
.journal-col-head__day {
    display: block;
    font-size: 0.78rem;
    color: #1a1a1a;
    line-height: 1.15;
    white-space: nowrap;
    font-variant-numeric: tabular-nums;
    letter-spacing: 0;
}
.journal-col-head__dot {
    position: absolute;
    top: 2px;
    right: 3px;
    width: 5px;
    height: 5px;
    border-radius: 50%;
    background: #dc2626;
}
.journal--grid th.journal-col-head.journal-col-head--future {
    opacity: 0.5;
}

.journal-student {
    font-weight: 500;
    color: #1a1a1a;
    cursor: default;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    font-size: 0.82rem;
}
.journal-cell {
    cursor: pointer;
    position: relative;
    background: #fff;
    transition: background 0.1s;
}
.journal-cell:hover { background: #fef9e7; }
.journal-cell--future { background: rgba(139, 105, 20, 0.04); cursor: default; }
.journal-cell--future:hover { background: rgba(139, 105, 20, 0.04); }
.journal-cell--filled { font-weight: 600; font-size: 0.92rem; }
.journal-cell--has-note .journal-cell__note-mark {
    position: absolute;
    top: 1px;
    right: 2px;
    font-size: 0.62rem;
    color: #dc2626;
}
.journal-mark {
    display: inline-block;
    min-width: 22px;
    padding: 1px 3px;
    border-radius: 2px;
    line-height: 1.15;
}
.journal-mark--5       { color: #15803d; }
.journal-mark--4       { color: #1e40af; }
.journal-mark--3       { color: #92400e; }
.journal-mark--2       { color: #b91c1c; }
.journal-mark--н       { color: #6b7280; font-style: italic; }
.journal-mark--ну      { color: #6b7280; font-style: italic; }
.journal-mark--5minus  { color: #15803d; }
.journal-mark--4minus  { color: #1e40af; }
.journal-mark--3minus  { color: #92400e; }
.journal-empty {
    padding: 20px;
    text-align: center;
    color: #999;
    font-style: italic;
}

.journal-notes-aside {
    flex: 0 0 320px;
    padding: 10px 12px;
    background: #fbf7ec;
    border: 1px solid #e8dcc0;
    border-left: 4px solid #8b6914;
}
.journal-side-tabs {
    display: flex;
    gap: 4px;
    margin-bottom: 8px;
    border-bottom: 1px solid #e8dcc0;
}
.journal-side-tab {
    padding: 6px 10px;
    background: transparent;
    border: 1px solid transparent;
    border-bottom: none;
    margin-bottom: -1px;
    font-family: 'Inter', sans-serif;
    font-size: 0.85rem;
    color: #6b5d3f;
    cursor: pointer;
    border-radius: 2px 2px 0 0;
}
.journal-side-tab:hover { color: #1a1a1a; background: rgba(139, 105, 20, 0.06); }
.journal-side-tab.is-active {
    background: #fff;
    border-color: #e8dcc0;
    color: #6b5010;
    font-weight: 600;
    border-bottom: 1px solid #fff;
}
.journal-side-pane { display: none; }
.journal-side-pane.is-active { display: block; }

.journal-notes-aside__empty {
    font-size: 0.85rem;
    font-style: italic;
    color: #999;
}

.journal-note-card {
    padding: 8px 10px;
    margin-bottom: 8px;
    background: #fff;
    border-left: 3px solid #8b6914;
    border-radius: 2px;
    font-size: 0.82rem;
}
.journal-note-card__date {
    font-weight: 700;
    color: #6b5010;
    font-size: 0.85rem;
}
.journal-note-card__topic {
    color: #4a3a10;
    font-size: 0.8rem;
    margin-top: 2px;
    padding-bottom: 4px;
    border-bottom: 1px dashed #e8dcc0;
}
.journal-note-card__section {
    padding-top: 6px;
    margin-top: 6px;
    border-top: 1px dashed rgba(232, 220, 192, 0.7);
}
.journal-note-card__section:first-of-type {
    border-top: none;
    margin-top: 4px;
    padding-top: 0;
}
.journal-note-card__section-label {
    font-family: 'Cormorant Garamond', 'Inter', serif;
    font-size: 0.82rem;
    font-weight: 600;
    color: #8b6914;
    letter-spacing: 0.02em;
    margin-bottom: 3px;
}
.journal-note-card__section--marks .journal-note-card__section-label {
    color: #d97706;
}
.journal-note-card__note {
    color: #1a1a1a;
    line-height: 1.4;
}
.journal-note-card__hw {
    color: #92400e;
    margin-top: 3px;
}

.journal-note-card__mark-row {
    padding: 4px 0;
    border-bottom: 1px dashed rgba(232, 220, 192, 0.5);
    line-height: 1.4;
}
.journal-note-card__mark-row:last-child {
    border-bottom: none;
    padding-bottom: 0;
}
.journal-note-card__mark-head {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-bottom: 2px;
}
.journal-note-card__mark-value {
    display: inline-block;
    min-width: 26px;
    padding: 0 6px;
    border-radius: 2px;
    background: rgba(217, 119, 6, 0.16);
    color: #92400e;
    font-weight: 700;
    font-variant-numeric: tabular-nums;
    text-align: center;
    font-size: 0.82rem;
}
.journal-note-card__student {
    color: #4a3a10;
    font-weight: 600;
    font-size: 0.8rem;
}
.journal-note-card__mark-note {
    color: #1a1a1a;
}

.journal-side-ktp {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.82rem;
}
.journal-side-ktp th,
.journal-side-ktp td {
    padding: 4px 6px;
    border-bottom: 1px solid rgba(26, 26, 26, 0.06);
    text-align: left;
    vertical-align: top;
}
.journal-side-ktp th {
    background: #f5ecd5;
    color: #4a3a10;
    font-size: 0.72rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    font-weight: 600;
}
.journal-side-ktp td.journal-side-ktp__date {
    white-space: nowrap;
    font-variant-numeric: tabular-nums;
    color: #6b5010;
    font-weight: 600;
    font-size: 0.8rem;
}
.journal-side-ktp td.journal-side-ktp__topic {
    color: #1a1a1a;
}
.journal-side-ktp tr.journal-side-ktp__future td {
    opacity: 0.6;
}

.journal-pagination {
    display: flex;
    gap: 10px;
    align-items: center;
    justify-content: center;
    margin-top: var(--space-sm);
}
.journal-pagination__info {
    font-size: 0.85rem;
    color: #666;
}
.journal-pagination .is-disabled {
    opacity: 0.4;
    pointer-events: none;
}

.journal-modal {
    position: fixed;
    inset: 0;
    z-index: 1000;
    display: none;
}
.journal-modal.is-open {
    display: flex;
    align-items: center;
    justify-content: center;
}
.journal-modal__backdrop {
    position: absolute;
    inset: 0;
    background: rgba(0, 0, 0, 0.4);
}
.journal-modal__box {
    position: relative;
    background: #fff;
    border: 1px solid #e8dcc0;
    border-left: 4px solid #8b6914;
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.2);
    width: 100%;
    max-width: 560px;
    max-height: 90vh;
    overflow-y: auto;
    padding: 14px 18px;
    border-radius: 2px;
}
.journal-modal__head {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 10px;
    padding-bottom: 8px;
    border-bottom: 1px solid #e8dcc0;
    margin-bottom: 10px;
}
.journal-modal__title {
    font-family: 'Cormorant Garamond', 'Inter', serif;
    font-size: 1.2rem;
    font-weight: 600;
    color: #1a1a1a;
}
.journal-modal__subtitle {
    font-size: 0.82rem;
    color: #6b5d3f;
    font-style: italic;
    margin-top: 2px;
}
.journal-modal__close {
    background: transparent;
    border: none;
    font-size: 1.4rem;
    line-height: 1;
    color: #999;
    cursor: pointer;
    padding: 0 4px;
}
.journal-modal__close:hover { color: #b91c1c; }
.journal-modal__body {
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.journal-modal__marks {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
}
.journal-mark-btn {
    padding: 4px 10px;
    font-family: 'Cormorant Garamond', 'Inter', serif;
    font-size: 1rem;
    font-weight: 600;
    border: 1px solid #d4c8a8;
    background: #fff;
    color: #1a1a1a;
    cursor: pointer;
    border-radius: 2px;
    min-width: 40px;
}
.journal-mark-btn:hover { background: #fbf7ec; border-color: #8b6914; }
.journal-mark-btn.is-active {
    background: #8b6914;
    color: #fff;
    border-color: #8b6914;
}
.journal-mark-btn--clear { color: #999; font-size: 0.9rem; }

.journal-modal .pzk-field {
    display: flex;
    flex-direction: column;
    gap: 4px;
    width: 100%;
    min-width: 0;
}
.journal-modal .pzk-field__label {
    font-family: 'Cormorant Garamond', 'Inter', serif;
    font-size: 1rem;
    font-weight: 600;
    letter-spacing: 0.03em;
    color: #6b5010;
    margin: 0;
}
.journal-modal .pzk-field__textarea {
    display: block;
    width: 100%;
    min-height: 140px;
    padding: 8px 12px;
    border: 1px solid #d4c8a8;
    background: #fff;
    font-family: 'Inter', -apple-system, sans-serif;
    font-size: 1rem;
    line-height: 1.5;
    color: #1a1a1a;
    border-radius: 2px;
    resize: vertical;
    box-sizing: border-box;
}
.journal-modal .pzk-field__textarea:focus {
    outline: none;
    border-color: #8b6914;
    box-shadow: 0 0 0 3px rgba(139, 105, 20, 0.12);
}
.journal-modal .pzk-field__textarea::placeholder {
    color: #b8a888;
    font-style: italic;
}

.journal-modal__foot {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    padding-top: 10px;
    border-top: 1px solid #e8dcc0;
    margin-top: 12px;
}
.journal-modal__foot .pzk-btn {
    padding: 6px 14px;
    font-family: 'Cormorant Garamond', 'Inter', serif;
    font-size: 1.05rem;
    font-weight: 600;
    letter-spacing: 0.06em;
    border: 1px solid rgba(26, 26, 26, 0.2);
    background: #fff;
    color: #2d2d2d;
    cursor: pointer;
    border-radius: 2px;
    line-height: 1.2;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.journal-modal__foot .pzk-btn:hover {
    background: rgba(139, 105, 20, 0.06);
    border-color: rgba(139, 105, 20, 0.4);
}
.journal-modal__foot .pzk-btn--primary {
    background: #8b6914;
    color: #fff;
    border-color: #8b6914;
    box-shadow: 0 2px 6px rgba(139, 105, 20, 0.2);
}
.journal-modal__foot .pzk-btn--primary:hover {
    background: #a67c00;
    border-color: #a67c00;
    color: #fff;
}
.journal-modal__foot .pzk-btn--sm {
    padding: 4px 10px;
    font-size: 0.88rem;
    letter-spacing: 0.03em;
}

@media (max-width: 1100px) {
    .journal-layout { flex-direction: column; }
    .journal-notes-aside { flex: 1 1 auto; width: 100%; }
}
</style>
<?php endif; ?>

<?php
    $today      = date('Y-m-d');
    $monthNames = [
        1 => 'Январь', 2 => 'Февраль', 3 => 'Март', 4 => 'Апрель',
        5 => 'Май', 6 => 'Июнь', 7 => 'Июль', 8 => 'Август',
        9 => 'Сентябрь', 10 => 'Октябрь', 11 => 'Ноябрь', 12 => 'Декабрь',
    ];
    $monthGroups = [];
    foreach ($columns as $ci => $col) {
        $ts       = strtotime($col['date']);
        $monthKey = date('Y-m', $ts);
        $mNum     = (int) date('n', $ts);
        $year     = date('Y', $ts);
        if (!isset($monthGroups[$monthKey])) {
            $monthGroups[$monthKey] = [
                'label'      => $monthNames[$mNum] . ' ' . $year,
                'count'      => 0,
                'all_future' => true,
            ];
        }
        $monthGroups[$monthKey]['count']++;
        if ($ci < $futureFrom) $monthGroups[$monthKey]['all_future'] = false;
    }

    // ДАТЫ ТЕКУЩЕЙ СТРАНИЦЫ — по ним фильтруются обе правые вкладки.
    $pageDates = [];
    foreach ($columns as $col) {
        $pageDates[$col['date']] = true;
    }

    // Темы журнала — только уроки этих дат.
    $pageLessons = [];
    foreach ($allLessons as $l) {
        if (isset($pageDates[$l['date']])) $pageLessons[] = $l;
    }

    // Темы по датам — тоже только для дат страницы.
    $topicByDate = [];
    foreach ($pageLessons as $l) {
        $d = (string) $l['date'];
        if (!isset($topicByDate[$d])) $topicByDate[$d] = [];
        if (!in_array($l['topic'], $topicByDate[$d], true)) {
            $topicByDate[$d][] = (string) $l['topic'];
        }
    }

    $visibleSids = [];
    $studentNameById = [];
    foreach ($students as $st) {
        $sid = (int) $st['id'];
        $visibleSids[$sid] = true;
        $studentNameById[$sid] = (string) $st['full_name'];
    }

    // Объединение примечаний по дате — базовая карточка только для дат страницы.
    $notesByDate = [];
    foreach ($pageDates as $d => $_) {
        $notesByDate[$d] = [
            'date'   => (string) $d,
            'topic'  => implode('; ', $topicByDate[$d] ?? []),
            'lesson' => null,
            'marks'  => [],
        ];
    }

    if (isset($lessonNotes[$kind]) && is_array($lessonNotes[$kind])) {
        foreach ($lessonNotes[$kind] as $d => $ln) {
            if (!isset($notesByDate[$d])) continue;               // фильтр по странице
            $note = (string) ($ln['note'] ?? '');
            $hw   = (string) ($ln['homework'] ?? '');
            if ($note === '' && $hw === '') continue;
            $notesByDate[$d]['lesson'] = ['note' => $note, 'homework' => $hw];
        }
    }

    foreach ($marks as $sid => $byKind) {
        if (!isset($visibleSids[(int) $sid])) continue;
        $byDate = $byKind[$kind] ?? [];
        foreach ($byDate as $d => $m) {
            if (!isset($notesByDate[$d])) continue;               // фильтр по странице
            $note = (string) ($m['note'] ?? '');
            if ($note === '') continue;
            $notesByDate[$d]['marks'][] = [
                'mark'    => (string) ($m['mark'] ?? ''),
                'student' => $studentNameById[(int) $sid] ?? '',
                'note'    => $note,
            ];
        }
    }

    foreach ($notesByDate as &$nd) {
        usort($nd['marks'], fn($a, $b) => strcmp($a['student'], $b['student']));
    }
    unset($nd);

    $notesByDate = array_filter($notesByDate, function ($row) {
        return $row['lesson'] !== null || $row['marks'] !== [];
    });
    ksort($notesByDate);
?>

<div class="journal-layout">
    <div class="journal-grid-wrap">
        <table class="journal journal--grid">
            <thead>
                <tr class="journal-month-row">
                    <th class="journal-sticky-col journal-head-student" rowspan="2">Студент</th>
                    <?php foreach ($monthGroups as $mk => $mg): ?>
                        <th class="journal-month-head <?= $mg['all_future'] ? 'journal-month-head--future' : '' ?>"
                            colspan="<?= (int) $mg['count'] ?>">
                            <?= e($mg['label']) ?>
                        </th>
                    <?php endforeach; ?>
                </tr>
                <tr>
                    <?php foreach ($columns as $ci => $col): ?>
                        <?php
                            $isFuture = $ci >= $futureFrom;
                            $cellCls  = 'journal-col-head';
                            if ($isFuture) $cellCls .= ' journal-col-head--future';
                            $dayLabel  = date('d', strtotime($col['date']));
                            $dateFull  = date('d.m.Y', strtotime($col['date']));
                            $topicsStr = implode('; ', $col['topics']);
                            $ln        = $lessonNotes[$kind][$col['date']] ?? ['note' => '', 'homework' => ''];
                            $hasLessonNote = (($ln['note'] ?? '') !== '' || ($ln['homework'] ?? '') !== '');
                        ?>
                        <th class="<?= $cellCls ?>"
                            <?php if ($isTeacher): ?>
                                data-subject="<?= (int) $subjectId ?>"
                                data-date="<?= e($col['date']) ?>"
                                data-kind="<?= e($kind) ?>"
                                data-page="<?= (int) $page ?>"
                                data-date-label="<?= e($dateFull) ?>"
                                data-topic="<?= e($topicsStr) ?>"
                                data-note="<?= e((string) ($ln['note'] ?? '')) ?>"
                                data-homework="<?= e((string) ($ln['homework'] ?? '')) ?>"
                                title="<?= e($dateFull . ' · ' . $topicsStr) ?> — примечание к уроку и ДЗ"
                            <?php else: ?>
                                title="<?= e($dateFull . ' · ' . $topicsStr) ?>"
                            <?php endif; ?>
                        >
                            <span class="journal-col-head__day"><?= e($dayLabel) ?></span>
                            <?php if ($hasLessonNote): ?>
                                <span class="journal-col-head__dot" aria-hidden="true"></span>
                            <?php endif; ?>
                        </th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php if ($students === []): ?>
                    <tr><td colspan="<?= count($columns) + 1 ?>" class="journal-empty">В группе нет студентов.</td></tr>
                <?php else: ?>
                    <?php foreach ($students as $st): ?>
                        <?php $sid = (int) $st['id']; ?>
                        <tr>
                            <th class="journal-sticky-col journal-student">
                                <?= e((string) $st['full_name']) ?>
                            </th>
                            <?php foreach ($columns as $ci => $col): ?>
                                <?php
                                    $m       = $marks[$sid][$kind][$col['date']] ?? ['mark'=>'', 'note'=>''];
                                    $mark    = (string) ($m['mark'] ?? '');
                                    $note    = (string) ($m['note'] ?? '');
                                    $isFuture = $ci >= $futureFrom;
                                    $cls = 'journal-cell';
                                    if ($isFuture) $cls .= ' journal-cell--future';
                                    if ($mark !== '') $cls .= ' journal-cell--filled';
                                    if ($note !== '') $cls .= ' journal-cell--has-note';
                                    $markClass = 'journal-mark--' . str_replace('-', 'minus', $mark);
                                    $dateFull = date('d.m.Y', strtotime($col['date']));
                                    $topicsStr = implode('; ', $col['topics']);
                                ?>
                                <td class="<?= $cls ?>"
                                    <?php if ($isTeacher): ?>
                                        data-subject="<?= (int) $subjectId ?>"
                                        data-student="<?= $sid ?>"
                                        data-student-name="<?= e((string) $st['full_name']) ?>"
                                        data-date="<?= e($col['date']) ?>"
                                        data-date-label="<?= e($dateFull) ?>"
                                        data-kind="<?= e($kind) ?>"
                                        data-page="<?= (int) $page ?>"
                                        data-topic="<?= e($topicsStr) ?>"
                                        data-mark="<?= e($mark) ?>"
                                        data-note="<?= e($note) ?>"
                                    <?php endif; ?>
                                >
                                    <?php if ($mark !== ''): ?>
                                        <span class="journal-mark <?= e($markClass) ?>"><?= e($mark) ?></span>
                                    <?php endif; ?>
                                    <?php if ($note !== '' && !$isTeacher): ?>
                                        <span class="journal-cell__note-mark" title="<?= e($note) ?>">✎</span>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <aside class="journal-notes-aside">
        <div class="journal-side-tabs" role="tablist">
            <button type="button" class="journal-side-tab is-active"
                    data-side-tab="ktp" role="tab" aria-selected="true">Темы журнала</button>
            <button type="button" class="journal-side-tab"
                    data-side-tab="notes" role="tab" aria-selected="false">Примечания</button>
        </div>

        <div class="journal-side-pane is-active" data-side-pane="ktp" role="tabpanel">
            <?php if ($pageLessons === []): ?>
                <p class="journal-notes-aside__empty">На этой странице нет уроков.</p>
            <?php else: ?>
                <table class="journal-side-ktp">
                    <thead>
                        <tr>
                            <th style="width:80px;">Дата</th>
                            <th>Тема</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pageLessons as $col): ?>
                            <?php $isFut = strcmp($col['date'], $today) > 0; ?>
                            <tr class="<?= $isFut ? 'journal-side-ktp__future' : '' ?>">
                                <td class="journal-side-ktp__date"><?= e(date('d.m.Y', strtotime($col['date']))) ?></td>
                                <td class="journal-side-ktp__topic"><?= e($col['topic']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <div class="journal-side-pane" data-side-pane="notes" role="tabpanel">
            <?php if ($notesByDate === []): ?>
                <p class="journal-notes-aside__empty">На этой странице пока нет примечаний.</p>
            <?php else: ?>
                <?php foreach ($notesByDate as $d => $nd): ?>
                    <div class="journal-note-card">
                        <div class="journal-note-card__date"><?= e(date('d.m.Y', strtotime((string) $d))) ?></div>
                        <?php if ($nd['topic'] !== ''): ?>
                            <div class="journal-note-card__topic"><?= e($nd['topic']) ?></div>
                        <?php endif; ?>

                        <?php if ($nd['lesson'] !== null): ?>
                            <div class="journal-note-card__section journal-note-card__section--lesson">
                                <div class="journal-note-card__section-label">Примечание к уроку</div>
                                <?php if ($nd['lesson']['note'] !== ''): ?>
                                    <div class="journal-note-card__note"><?= e($nd['lesson']['note']) ?></div>
                                <?php endif; ?>
                                <?php if ($nd['lesson']['homework'] !== ''): ?>
                                    <div class="journal-note-card__hw"><strong>ДЗ:</strong> <?= e($nd['lesson']['homework']) ?></div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($nd['marks'] !== []): ?>
                            <div class="journal-note-card__section journal-note-card__section--marks">
                                <div class="journal-note-card__section-label">Примечания к оценкам</div>
                                <?php foreach ($nd['marks'] as $mn): ?>
                                    <div class="journal-note-card__mark-row">
                                        <div class="journal-note-card__mark-head">
                                            <?php if ($mn['mark'] !== ''): ?>
                                                <span class="journal-note-card__mark-value"><?= e($mn['mark']) ?></span>
                                            <?php endif; ?>
                                            <span class="journal-note-card__student"><?= e($mn['student']) ?></span>
                                        </div>
                                        <div class="journal-note-card__mark-note"><?= e($mn['note']) ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </aside>
</div>

<?php if ($totalPagesOut > 1): ?>
    <?php
        $ctx = (string) ($_GET['page'] ?? 'journal');
        if ($ctx === 'student') {
            $base = 'index.php?page=student&tab=journal'
                . '&journal_subject=' . (int) $subjectId
                . '&journal_kind='    . rawurlencode($kind)
                . '&p=';
        } else {
            $base = 'index.php?page=journal'
                . '&journal_year='     . (int) ($_GET['journal_year'] ?? 0)
                . '&journal_semester=' . (int) ($_GET['journal_semester'] ?? 0)
                . '&journal_group='    . (int) ($_GET['journal_group'] ?? 0)
                . '&subject='          . (int) $subjectId
                . '&kind='             . rawurlencode($kind)
                . '&p=';
        }
    ?>
    <nav class="journal-pagination" aria-label="Страницы журнала">
        <a class="pzk-btn pzk-btn--sm <?= $page <= 0 ? 'is-disabled' : '' ?>"
           href="<?= $page > 0 ? $base . ($page - 1) : '#' ?>"
           <?= $page > 0 ? 'data-p="' . ($page - 1) . '"' : '' ?>
           <?= $page <= 0 ? 'tabindex="-1" aria-disabled="true"' : '' ?>>← Назад</a>
        <span class="journal-pagination__info">
            Страница <?= $page + 1 ?> из <?= $totalPagesOut ?>
            (уроки <?= (int) $startIdx ?>–<?= (int) $endIdx ?>)
        </span>
        <a class="pzk-btn pzk-btn--sm <?= $page >= $totalPagesOut - 1 ? 'is-disabled' : '' ?>"
           href="<?= $page < $totalPagesOut - 1 ? $base . ($page + 1) : '#' ?>"
           <?= $page < $totalPagesOut - 1 ? 'data-p="' . ($page + 1) . '"' : '' ?>
           <?= $page >= $totalPagesOut - 1 ? 'tabindex="-1" aria-disabled="true"' : '' ?>>Вперёд →</a>
    </nav>
<?php endif; ?>