<?php
declare(strict_types=1);

// View-specific: выбранный вуз, его проекты
$viewUniId = (int) ($_GET['uni_id'] ?? 0);
if ($viewUniId === 0 && $universities !== []) $viewUniId = (int) $universities[0]['id'];

$selectedUni = null;
foreach ($universities as $u) {
    if ((int) $u['id'] === $viewUniId) { $selectedUni = $u; break; }
}

$uniProjects = [];
foreach ($projectOptions as $opt) {
    if ((int) $opt['university_id'] === $viewUniId) {
        $opt['workflow'] = build_project_workflow($workflowSteps, $sideLabels, $stepStates, $currentStates, (int) $opt['phase_num'], (int) $opt['id']);
        $uniProjects[] = $opt;
    }
}
?>
<div class="workspace workspace--uni">
    <?php if ($universityNotification !== null): ?>
        <div class="notification notification--<?= $universityNotification['type'] === 'pending' ? 'pending' : 'info' ?>">
            <span class="notification__icon"><?= $universityNotification['type'] === 'pending' ? '⏳' : 'ℹ' ?></span>
            <div class="notification__body"><?= e($universityNotification['message']) ?></div>
            <?php if ($universityNotification['type'] === 'pending' && $pendingChange !== null): ?>
                <div class="notification__actions">
                    <form method="post" action="index.php?action=confirm_phase" style="display:inline;"><button type="submit" class="notification__btn notification__btn--primary">Подтвердить</button></form>
                    <form method="post" action="index.php?action=reject_phase" style="display:inline;"><button type="submit" class="notification__btn notification__btn--danger">Отклонить</button></form>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <section class="panel panel--workflow">
        <div class="panel__head">
            <div>
                <h2 class="panel__title">Рабочий процесс вуза</h2>
                <p class="panel__subtitle">Все взаимодействия: <strong><?= e($selectedUni['name'] ?? '—') ?></strong> · проектов: <?= count($uniProjects) ?></p>
            </div>
            <label class="project-select">
                Вуз:
                <select onchange="location.href = 'index.php?role=university&uni_id=' + this.value;">
                    <?php foreach ($universities as $u): ?>
                        <option value="<?= (int) $u['id'] ?>" <?= (int) $u['id'] === $viewUniId ? 'selected' : '' ?>><?= e($u['short']) ?> — <?= e($u['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>

        <div class="workflow-scroll workflow-scroll--uni">
            <?php if ($uniProjects === []): ?>
                <div class="uni-empty">У выбранного вуза пока нет активных взаимодействий.</div>
            <?php else: ?>
                <?php foreach ($uniProjects as $proj): ?>
                    <div class="uni-workflow">
                        <div class="uni-workflow__head">
                            <span class="uni-workflow__title" title="<?= e($proj['title']) ?>"><?= e($proj['short']) ?></span>
                            <span class="uni-workflow__meta">
                                <?php if ($proj['phase'] !== null): ?>
                                    фаза <?= (int) $proj['phase']['num'] ?> «<?= e($proj['phase']['name']) ?>»
                                    <?php if (!empty($proj['phase']['requires_confirmation'])): ?> 🔒<?php endif; ?>
                                <?php else: ?>фаза не задана<?php endif; ?>
                            </span>
                        </div>
                        <div class="workflow-track workflow-track--compact">
                            <?php foreach ($proj['workflow'] as $item): ?>
                                <?php
                                $stateClass = 'wstep--' . $item['state'];
                                $sideClass = $item['side'] === 'both' ? ' wstep__side--both' : '';
                                ?>
                                <div class="wstep <?= $stateClass ?><?= $item['isCurrent'] ? ' current' : '' ?>" title="<?= e($item['name']) ?> · <?= e($item['sideLabel']) ?> · <?= e($item['stateLabel']) ?>">
                                    <span class="wstep__num"><?= (int) $item['num'] ?></span>
                                    <span class="wstep__side<?= $sideClass ?>"><?= e($item['side']) ?></span>
                                    <?php if ($item['isCurrent']): ?><span class="wstep__flag">◆</span><?php endif; ?>
                                    <span class="wstep__name"><?= e($item['name']) ?></span>
                                    <span class="wstep__status"><?= e($item['stateLabel']) ?></span>
                                    <span class="wstep__bar" style="background-color: <?= e($item['color']) ?>33"><span class="wstep__bar-fill" style="width: <?= (int) $item['fill'] ?>%; background-color: <?= e($item['color']) ?>"></span></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>

    <section class="panel panel--tabs">
        <div class="tabs" role="tablist">
            <button class="tab is-active" data-tab="project" role="tab" aria-selected="true">📋 Проекты</button>
            <button class="tab" data-tab="journal" role="tab" aria-selected="false">📓 Журнал</button>
        </div>

        <div class="tab-content is-active" data-tab-content="project" role="tabpanel">
            <p class="panel__subtitle" style="margin-bottom: var(--space-sm);">
                Проекты студентов и их фазовые истории. Возможны возвраты к предыдущим фазам (↩).
            </p>

            <div class="phase-timeline-wrap">
                <table class="students-timeline">
                    <thead>
                        <tr>
                            <th style="width: 250px;">Проект</th>
                            <th style="width: 130px;">Тип</th>
                            <th style="width: 240px;">Участники</th>
                            <th>История фаз</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($mockProjects as $proj): ?>
                            <?php
                            $curPhase = project_current_phase($proj);
                            $curPhaseName = $studentPhaseByNum[$curPhase]['name'] ?? '—';
                            $type = $projectTypes[$proj['type']] ?? ['label' => $proj['type'], 'color' => '#8b6914', 'icon' => '📌'];
                            ?>
                            <tr>
                                <td class="project-cell-title" title="<?= e($proj['title']) ?>">
                                    <?= e($proj['title']) ?>
                                    <span class="student-sub">
                                        Текущая фаза: <?= e($curPhaseName) ?> (<?= (int) $curPhase ?>/4)
                                    </span>
                                </td>
                                <td>
                                    <span class="project-type-chip" style="--type-color: <?= e($type['color']) ?>;">
                                        <?= $type['icon'] ?> <?= e($type['label']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="participant-list">
                                        <?php foreach ($proj['students'] as $sid): ?>
                                            <?php
                                            $st = $studentById[(int) $sid] ?? null;
                                            if (!$st) continue;
                                            ?>
                                            <span class="participant-row">
                                                <span class="participant-row__name"><?= e($st['name']) ?></span>
                                                <span style="color:var(--color-tertiary);font-size:0.7rem;"><?= e($st['group']) ?></span>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if (empty($proj['history'])): ?>
                                        <span class="phase-timeline-empty">Нет записей</span>
                                    <?php else: ?>
                                        <div class="phase-timeline">
                                            <?php $prevNum = 0; ?>
                                            <?php foreach ($proj['history'] as $idx => $h): ?>
                                                <?php
                                                $sp = $studentPhaseByNum[(int) $h['num']] ?? null;
                                                if (!$sp) continue;
                                                $isReturn = $prevNum > 0 && (int) $h['num'] < $prevNum;
                                                ?>
                                                <?php if ($idx > 0): ?>
                                                    <span class="phase-arrow <?= $isReturn ? 'phase-arrow--return' : '' ?>"><?= $isReturn ? '↩' : '→' ?></span>
                                                <?php endif; ?>
                                                <span class="phase-chip" style="--phase-color: <?= e($sp['color']) ?>;" title="<?= e($sp['name']) ?> · <?= e($h['date']) ?> · <?= e($h['note']) ?>">
                                                    <span class="phase-chip__num"><?= (int) $sp['num'] ?></span><?= e($sp['name']) ?><span class="phase-chip__date"><?= e($h['date']) ?></span>
                                                </span>
                                                <?php $prevNum = (int) $h['num']; ?>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <section class="legend" style="margin-top: var(--space-sm);">
                <h3 class="legend__title">Легенда</h3>
                <div class="legend__items">
                    <?php foreach ($projectTypes as $t): ?>
                        <span class="legend__item">
                            <span class="project-type-chip" style="--type-color: <?= e($t['color']) ?>; margin:0;">
                                <?= $t['icon'] ?> <?= e($t['label']) ?>
                            </span>
                        </span>
                    <?php endforeach; ?>
                    <span class="legend__item"><span class="legend__swatch" style="background:transparent;border:none;color:#b45309;font-weight:700;font-size:1.1rem;">↩</span>Возврат</span>
                    <span class="legend__item"><span class="legend__swatch" style="background:transparent;border:none;color:var(--color-tertiary);font-weight:700;font-size:1.1rem;">→</span>Переход</span>
                </div>
            </section>
        </div>

        <div class="tab-content" data-tab-content="journal" role="tabpanel">
            <div style="display:flex; align-items:flex-start; justify-content:space-between; flex-wrap:wrap; gap: var(--space-sm); margin-bottom: var(--space-sm);">
                <div>
                    <h2 class="panel__title" style="font-size:1.2rem;">Журнал занятий</h2>
                    <p class="panel__subtitle">
                        Клик по дате — тема урока и домашнее задание. Клик по ячейке — заметка преподавателя. Рядом с именем — активность родителя в CRM.
                    </p>
                </div>
                <div class="legend" style="margin:0; padding:6px 12px; background:rgba(139,105,20,0.06);">
                    <div class="legend__items" style="gap:0.5rem var(--space-md);">
                        <span class="legend__item"><span class="journal-mark journal-mark--grade-5">5</span>отлично</span>
                        <span class="legend__item"><span class="journal-mark journal-mark--grade-4">4</span>хорошо</span>
                        <span class="legend__item"><span class="journal-mark journal-mark--grade-3">3</span>удовл.</span>
                        <span class="legend__item"><span class="journal-mark journal-mark--grade-2">2</span>неуд.</span>
                        <span class="legend__item"><span class="journal-mark journal-mark--absent">н</span>отсутствовал</span>
                        <span class="legend__item"><span class="journal-note-dot" style="pointer-events:none;">✎</span>есть заметка</span>
                    </div>
                </div>
            </div>

            <div class="journal-wrap">
                <table class="journal" id="journal-table">
                    <thead>
                        <tr>
                            <th>Студент</th>
                            <?php foreach ($journalDates as $i => $d): ?>
                                <th data-date-index="<?= (int) $i ?>"><?= e($d) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($mockStudents as $st): ?>
                            <?php
                            $sid = (int) $st['id'];
                            $pv = $parentVisitsMock[$sid] ?? null;
                            $badgeCls = '';
                            if ($pv !== null) {
                                if ($pv['count'] >= 10) $badgeCls = '';
                                elseif ($pv['count'] >= 5) $badgeCls = 'journal-parent-badge--low';
                                else $badgeCls = 'journal-parent-badge--cold';
                            }
                            ?>
                            <tr data-student="<?= $sid ?>">
                                <td title="<?= e($st['name']) ?>">
                                    <?= e($st['name']) ?>
                                    <?php if ($pv !== null): ?>
                                        <span class="journal-parent-badge <?= $badgeCls ?>" title="Родитель в CRM: <?= (int) $pv['count'] ?> визитов, последний <?= e($pv['last']) ?>"></span>
                                    <?php endif; ?>
                                </td>
                                <?php foreach ($journalDates as $i => $d): ?>
                                    <?php
                                    $mark = $journalMarks[$sid][$i] ?? '';
                                    $markClass = '';
                                    if ($mark === '5') $markClass = 'journal-mark--grade-5';
                                    elseif ($mark === '4') $markClass = 'journal-mark--grade-4';
                                    elseif ($mark === '3') $markClass = 'journal-mark--grade-3';
                                    elseif ($mark === '2') $markClass = 'journal-mark--grade-2';
                                    elseif ($mark === '·') $markClass = 'journal-mark--dot';
                                    elseif ($mark === 'н') $markClass = 'journal-mark--absent';
                                    $hasNote = isset($journalNotes[$sid][$i]);
                                    $showMark = ($mark !== '' && $mark !== '·');
                                    ?>
                                    <td class="journal-cell <?= $hasNote ? 'has-note' : '' ?>"
                                        data-date-index="<?= (int) $i ?>"
                                        data-student-id="<?= $sid ?>">
                                        <?php if ($showMark): ?>
                                            <span class="journal-mark <?= $markClass ?>"><?= e($mark) ?></span>
                                        <?php elseif ($hasNote): ?>
                                            <span class="journal-note-dot" title="Есть заметка преподавателя">✎</span>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="journal-lesson journal-lesson--empty" id="journal-lesson">
                <div class="journal-lesson__date" id="journal-lesson-date">Урок не выбран</div>
                <div class="journal-lesson__title" id="journal-lesson-topic">Выберите дату в журнале, чтобы увидеть тему урока и домашнее задание.</div>
                <div class="journal-lesson__homework" id="journal-lesson-homework"></div>
            </div>
        </div>
    </section>
</div>

<script type="application/json" id="journal-lessons-json"><?= json_encode($journalLessons, JSON_UNESCAPED_UNICODE) ?></script>
<script type="application/json" id="journal-dates-json"><?= json_encode($journalDates, JSON_UNESCAPED_UNICODE) ?></script>
<script type="application/json" id="journal-notes-json"><?= json_encode($journalNotes, JSON_UNESCAPED_UNICODE) ?></script>
<script type="application/json" id="journal-students-json"><?= json_encode(array_map(function ($s) { return ['id' => $s['id'], 'name' => $s['name']]; }, $mockStudents), JSON_UNESCAPED_UNICODE) ?></script>
<script type="application/json" id="journal-marks-json"><?= json_encode($journalMarks, JSON_UNESCAPED_UNICODE) ?></script>
<script>
(function () {
    'use strict';

    var tabs = document.querySelectorAll('.tab[data-tab]');
    var contents = document.querySelectorAll('.tab-content[data-tab-content]');
    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            var target = tab.dataset.tab;
            tabs.forEach(function (t) { t.classList.toggle('is-active', t === tab); t.setAttribute('aria-selected', t === tab ? 'true' : 'false'); });
            contents.forEach(function (c) { c.classList.toggle('is-active', c.dataset.tabContent === target); });
        });
    });

    var journalTable = document.getElementById('journal-table');
    if (!journalTable) return;

    var lessons = JSON.parse(document.getElementById('journal-lessons-json').textContent);
    var dates = JSON.parse(document.getElementById('journal-dates-json').textContent);
    var notes = JSON.parse(document.getElementById('journal-notes-json').textContent);
    var students = JSON.parse(document.getElementById('journal-students-json').textContent);
    var marks = JSON.parse(document.getElementById('journal-marks-json').textContent);

    var headerCells = journalTable.querySelectorAll('thead th[data-date-index]');
    var cellCells = journalTable.querySelectorAll('tbody td.journal-cell[data-date-index]');
    var panelDate = document.getElementById('journal-lesson-date');
    var panelTopic = document.getElementById('journal-lesson-topic');
    var panelHw = document.getElementById('journal-lesson-homework');
    var panel = document.getElementById('journal-lesson');

    function selectDate(idx) {
        headerCells.forEach(function (th) { th.classList.toggle('is-selected', parseInt(th.dataset.dateIndex, 10) === idx); });
        cellCells.forEach(function (td) { td.classList.toggle('is-highlighted', parseInt(td.dataset.dateIndex, 10) === idx); });
        var lesson = lessons[idx];
        if (lesson) {
            panel.classList.remove('journal-lesson--empty');
            panelDate.textContent = 'Дата: ' + (dates[idx] || '—');
            panelTopic.textContent = lesson.topic;
            panelHw.innerHTML = '<strong>Домашнее задание:</strong> ' + lesson.homework;
        } else {
            panel.classList.add('journal-lesson--empty');
            panelDate.textContent = 'Урок не выбран';
            panelTopic.textContent = 'Для выбранной даты ещё нет темы.';
            panelHw.textContent = '';
        }
    }

    headerCells.forEach(function (th) {
        th.addEventListener('click', function () { selectDate(parseInt(th.dataset.dateIndex, 10)); });
    });

    var popover = document.createElement('div');
    popover.className = 'note-popover';
    popover.innerHTML = ''
        + '<div class="note-popover__header">'
        +   '<div>'
        +     '<div class="note-popover__student" id="np-student">—</div>'
        +     '<div class="note-popover__meta" id="np-meta">—</div>'
        +   '</div>'
        +   '<div class="note-popover__mark" id="np-mark">—</div>'
        + '</div>'
        + '<label class="note-popover__label" for="np-textarea">Заметка преподавателя</label>'
        + '<textarea class="note-popover__textarea" id="np-textarea" placeholder="Например: разобрать подробнее тему..."></textarea>'
        + '<div class="note-popover__actions">'
        +   '<button type="button" class="note-popover__btn note-popover__btn--danger" id="np-delete">Удалить</button>'
        +   '<button type="button" class="note-popover__btn" id="np-cancel">Отмена</button>'
        +   '<button type="button" class="note-popover__btn note-popover__btn--primary" id="np-save">Сохранить</button>'
        + '</div>'
        + '<div class="note-popover__hint">Заметку можно привязать к оценке, к «·» или к «н».</div>';
    document.body.appendChild(popover);

    var npStudent = document.getElementById('np-student');
    var npMeta = document.getElementById('np-meta');
    var npMark = document.getElementById('np-mark');
    var npTextarea = document.getElementById('np-textarea');
    var npSave = document.getElementById('np-save');
    var npCancel = document.getElementById('np-cancel');
    var npDelete = document.getElementById('np-delete');
    var activeCell = null;

    function markClass(m) {
        if (m === '5') return 'note-popover__mark--grade-5';
        if (m === '4') return 'note-popover__mark--grade-4';
        if (m === '3') return 'note-popover__mark--grade-3';
        if (m === '2') return 'note-popover__mark--grade-2';
        if (m === '·') return 'note-popover__mark--dot';
        if (m === 'н') return 'note-popover__mark--absent';
        return 'note-popover__mark--none';
    }

    function markLabel(m) {
        if (m === '·') return '·';
        if (m === 'н') return 'н';
        if (m === '') return '—';
        return m;
    }

    function openPopoverFor(cell) {
        activeCell = cell;
        var sid = parseInt(cell.dataset.studentId, 10);
        var di = parseInt(cell.dataset.dateIndex, 10);
        var student = students.find(function (s) { return s.id === sid; });
        var m = (marks[sid] && marks[sid][di]) || '';
        var text = (notes[sid] && notes[sid][di]) || '';

        npStudent.textContent = student ? student.name : '—';
        npMeta.textContent = 'Дата: ' + (dates[di] || '—') + ' · ' + (lessons[di] ? lessons[di].topic : '');
        npMark.textContent = markLabel(m);
        npMark.className = 'note-popover__mark ' + markClass(m);
        npTextarea.value = text;

        var rect = cell.getBoundingClientRect();
        var pw = 340;
        var left = rect.left + rect.width / 2 - pw / 2;
        left = Math.max(8, Math.min(left, window.innerWidth - pw - 8));
        var top = rect.bottom + 10;
        if (top + 260 > window.innerHeight - 8) top = rect.top - 270;
        top = Math.max(8, Math.min(top, window.innerHeight - 270));
        popover.style.left = left + 'px';
        popover.style.top = top + 'px';
        requestAnimationFrame(function () { popover.classList.add('is-visible'); });
        npTextarea.focus();
    }

    function closePopover() { popover.classList.remove('is-visible'); activeCell = null; }

    function renderCellContent(cell, sid, di) {
        var m = (marks[sid] && marks[sid][di]) || '';
        var hasNote = !!(notes[sid] && notes[sid][di]);
        var html = '';
        if (m !== '' && m !== '·') {
            var cls = '';
            if (m === '5') cls = 'journal-mark--grade-5';
            else if (m === '4') cls = 'journal-mark--grade-4';
            else if (m === '3') cls = 'journal-mark--grade-3';
            else if (m === '2') cls = 'journal-mark--grade-2';
            else if (m === 'н') cls = 'journal-mark--absent';
            html = '<span class="journal-mark ' + cls + '">' + m + '</span>';
        } else if (hasNote) {
            html = '<span class="journal-note-dot" title="Есть заметка преподавателя">✎</span>';
        }
        cell.innerHTML = html;
        cell.classList.toggle('has-note', hasNote);
    }

    cellCells.forEach(function (td) {
        td.addEventListener('click', function (e) { e.stopPropagation(); openPopoverFor(td); });
    });

    document.addEventListener('click', function (e) { if (!popover.contains(e.target)) closePopover(); });
    popover.addEventListener('click', function (e) { e.stopPropagation(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closePopover(); });

    npCancel.addEventListener('click', closePopover);

    npSave.addEventListener('click', function () {
        if (!activeCell) return;
        var sid = parseInt(activeCell.dataset.studentId, 10);
        var di = parseInt(activeCell.dataset.dateIndex, 10);
        var text = npTextarea.value.trim();
        if (!notes[sid]) notes[sid] = {};
        if (text === '') delete notes[sid][di];
        else notes[sid][di] = text;
        renderCellContent(activeCell, sid, di);
        closePopover();
    });

    npDelete.addEventListener('click', function () {
        if (!activeCell) return;
        var sid = parseInt(activeCell.dataset.studentId, 10);
        var di = parseInt(activeCell.dataset.dateIndex, 10);
        if (notes[sid]) delete notes[sid][di];
        renderCellContent(activeCell, sid, di);
        closePopover();
    });

    if (dates.length > 0) selectDate(0);
})();
</script>