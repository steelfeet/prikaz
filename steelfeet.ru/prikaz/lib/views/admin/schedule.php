<?php
declare(strict_types=1);

// Хелпер: подпись группы с номером подгруппы для селекта предмета в расписании.
// «46 гр.» + sub1 → «46 (1) гр.», sub2 → «46 (2) гр.», group → «46 гр.».
$groupWithSubgroup = function (string $groupName, string $type): string {
    $sub = '';
    if ($type === 'sub1')      $sub = ' (1)';
    elseif ($type === 'sub2')  $sub = ' (2)';
    if ($sub === '') return $groupName;

    if (preg_match('/\s*гр\.?\s*$/iu', $groupName, $m, PREG_OFFSET_CAPTURE)) {
        $pos = (int) $m[0][1];
        return substr($groupName, 0, $pos) . $sub . substr($groupName, $pos);
    }
    return $groupName . $sub;
};

$scheduleDays  = schedule_days();
$scheduleTimes = schedule_lesson_times();
$scheduleTypes = schedule_lesson_types();

$scheduleGrid = [];
foreach ($adminScheduleEntries as $row) {
    $scheduleGrid[(int) $row['lesson_num']][(int) $row['weekday']][] = $row;
}
?>
<section class="panel">
    <div class="panel__head">
        <div>
            <h2 class="panel__title">Расписание</h2>
            <p class="panel__subtitle">
                <?php if ($adminScheduleSemesterId > 0): ?>
                    Семестр: <strong><?= e($adminScheduleSemesterName) ?></strong>.
                <?php endif; ?>
                Неделя с <?= e($scheduleDays[1]) ?> по <?= e($scheduleDays[6]) ?>, 9 уроков.
                В ячейке — предмет (с указанием группы и подгруппы) и тип занятия.
                Один урок — одна запись. Расписание одно на семестр, для всех групп.
                Праздничные дни настраиваются в разделе «Учебный год».
            </p>
        </div>
        <?php if ($adminScheduleSemesterId > 0 && $adminScheduleEntries !== []): ?>
            <form method="post" action="index.php?action=admin_schedule_clear" style="display:inline;"
                  onsubmit="return confirm('Очистить всё расписание семестра «<?= e($adminScheduleSemesterName) ?>» (все группы)?');">
                <?= csrf_field() ?>
                <input type="hidden" name="semester_id" value="<?= (int) $adminScheduleSemesterId ?>">
                <input type="hidden" name="schedule_year" value="<?= (int) $adminScheduleYearId ?>">
                <button type="submit" class="pzk-btn pzk-btn--sm">Очистить</button>
            </form>
        <?php endif; ?>
    </div>

    <?php if ($adminAcademicYears === []): ?>
        <div class="uni-empty">
            Сначала создайте учебный год — в разделе «Учебный год».
        </div>
    <?php elseif ($adminScheduleSemesters === []): ?>
        <div class="uni-empty">
            В выбранном учебном году нет семестров. Добавьте их в разделе «Учебный год».
        </div>
    <?php else: ?>
        <form method="get" action="index.php" class="pzk-inline-form pzk-schedule-filter" autocomplete="off"
              style="margin-bottom: var(--space-sm);">
            <input type="hidden" name="page" value="admin">
            <input type="hidden" name="section" value="schedule">
            <label>Учебный год:
                <select name="schedule_year" class="pzk-field__input">
                    <?php foreach ($adminAcademicYears as $ay): ?>
                        <option value="<?= (int) $ay['id'] ?>"
                                <?= (int) $ay['id'] === (int) $adminScheduleYearId ? 'selected' : '' ?>>
                            <?= e($ay['name']) ?><?= (int) $ay['is_current'] === 1 ? ' (текущий)' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Семестр:
                <select name="schedule_semester" class="pzk-field__input">
                    <?php foreach ($adminScheduleSemesters as $sm): ?>
                        <option value="<?= (int) $sm['id'] ?>"
                                <?= (int) $sm['id'] === (int) $adminScheduleSemesterId ? 'selected' : '' ?>>
                            <?= e($sm['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
        </form>

        <script>
        (function () {
            'use strict';
            document.querySelectorAll('.pzk-schedule-filter select').forEach(function (sel) {
                sel.addEventListener('change', function () {
                    sel.closest('form').submit();
                });
            });
        })();
        </script>

        <?php if ($adminScheduleSubjects === []): ?>
            <div class="uni-empty">
                В семестре «<?= e($adminScheduleSemesterName) ?>» нет ни одного предмета.
                <a href="?page=admin&amp;section=subjects">Добавьте предметы</a>, потом вернитесь сюда.
            </div>
        <?php else: ?>
            <form method="post" action="index.php?action=admin_schedule_save" autocomplete="off">
                <?= csrf_field() ?>
                <input type="hidden" name="semester_id" value="<?= (int) $adminScheduleSemesterId ?>">
                <input type="hidden" name="schedule_year" value="<?= (int) $adminScheduleYearId ?>">

                <div class="pzk-table-wrap">
                    <table class="pzk-table pzk-schedule-table">
                        <thead>
                            <tr>
                                <th style="width:40px;">№</th>
                                <th style="width:110px;">Продолжительность</th>
                                <?php foreach ($scheduleDays as $dayNum => $dayLabel): ?>
                                    <th><?= e($dayLabel) ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($scheduleTimes as $lessonNum => $time): ?>
                                <tr>
                                    <td><strong><?= (int) $lessonNum ?></strong></td>
                                    <td style="font-size:0.85rem; color:var(--color-secondary); white-space:nowrap;"><?= e($time) ?></td>
                                    <?php foreach ($scheduleDays as $dayNum => $dayLabel): ?>
                                        <?php
                                            $cells    = $scheduleGrid[$lessonNum][$dayNum] ?? [];
                                            $cell     = $cells[0] ?? null;
                                            $cellType = $cell['lesson_type'] ?? 'group';
                                        ?>
                                        <td class="pzk-schedule-cell">
                                            <div class="pzk-schedule-entry">
                                                <select class="pzk-field__input pzk-field__input--sm"
                                                        name="cell[<?= (int) $dayNum ?>][<?= (int) $lessonNum ?>][1][subject_id]">
                                                    <option value="">— не задано —</option>
                                                    <?php foreach ($adminScheduleSubjects as $subj): ?>
                                                        <?php $groupLabel = $groupWithSubgroup((string) $subj['group_name'], (string) $cellType); ?>
                                                        <option value="<?= (int) $subj['id'] ?>"
                                                                data-short="<?= e($subj['short_name']) ?>"
                                                                data-group="<?= e($subj['group_name']) ?>"
                                                                <?= ($cell && (int) $cell['subject_id'] === (int) $subj['id']) ? 'selected' : '' ?>>
                                                            <?= e($subj['short_name']) ?> · <?= e($groupLabel) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <select class="pzk-field__input pzk-field__input--sm pzk-schedule-type"
                                                        name="cell[<?= (int) $dayNum ?>][<?= (int) $lessonNum ?>][1][lesson_type]">
                                                    <?php foreach ($scheduleTypes as $typeCode => $typeLabel): ?>
                                                        <option value="<?= e($typeCode) ?>"
                                                                <?= ($cell && $cell['lesson_type'] === $typeCode) ? 'selected' : '' ?>>
                                                            <?= e($typeLabel) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="pzk-form__actions" style="margin-top: var(--space-sm);">
                    <button type="submit" class="pzk-btn pzk-btn--primary">Сохранить расписание</button>
                </div>
            </form>

            <script>
            (function () {
                'use strict';

                // Подпись группы с номером подгруппы: «46 гр.» + sub1 → «46 (1) гр.».
                function groupWithSubgroup(groupName, type) {
                    var sub = type === 'sub1' ? ' (1)' : (type === 'sub2' ? ' (2)' : '');
                    if (sub === '') return groupName;
                    var m = groupName.match(/^([\s\S]*?)(\s*гр\.?\s*)$/i);
                    if (m) return m[1] + sub + m[2];
                    return groupName + sub;
                }

                document.querySelectorAll('.pzk-schedule-entry').forEach(function (entry) {
                    var subjSel = entry.querySelector('select[name$="[subject_id]"]');
                    var typeSel = entry.querySelector('select[name$="[lesson_type]"]');
                    if (!subjSel || !typeSel) return;

                    function refresh() {
                        var type = typeSel.value;
                        subjSel.querySelectorAll('option[data-group]').forEach(function (opt) {
                            var short = opt.getAttribute('data-short') || '';
                            var group = opt.getAttribute('data-group') || '';
                            opt.textContent = short + ' · ' + groupWithSubgroup(group, type);
                        });
                    }

                    typeSel.addEventListener('change', refresh);
                });
            })();
            </script>
        <?php endif; ?>
    <?php endif; ?>
</section>