<?php
declare(strict_types=1);

$viewParentChildId = (int) ($_GET['child_id'] ?? 0);
if ($viewParentChildId === 0 && $mockStudents !== []) $viewParentChildId = (int) $mockStudents[0]['id'];
$parentChild = $studentById[$viewParentChildId] ?? null;

if (!isset($_SESSION['parent_visits'])) $_SESSION['parent_visits'] = [];
$todayKey = date('Y-m-d');
if ($parentChild !== null) {
    $cid = (int) $parentChild['id'];
    if (!isset($_SESSION['parent_visits'][$cid])) $_SESSION['parent_visits'][$cid] = [];
    $_SESSION['parent_visits'][$cid][$todayKey] = ($_SESSION['parent_visits'][$cid][$todayKey] ?? 0) + 1;
}

$parentBadMarks = [];
if ($parentChild !== null) {
    $cid = (int) $parentChild['id'];
    $marks = $journalMarks[$cid] ?? [];
    foreach ($marks as $i => $m) {
        if ($m === '2' || $m === '3') {
            $parentBadMarks[] = [
                'date'  => $journalDates[$i] ?? '',
                'mark'  => $m,
                'topic' => $journalLessons[$i]['topic'] ?? '',
            ];
        }
    }
}
?>
<div class="workspace workspace--parent">
    <?php if ($parentChild === null): ?>
        <div class="uni-empty">Ребёнок не выбран.</div>
    <?php else: ?>
        <?php
        $cid = (int) $parentChild['id'];
        $childMarks = $journalMarks[$cid] ?? [];
        $childProjectIds = $projectsByStudent[$cid] ?? [];
        $pv = $parentVisitsMock[$cid] ?? null;
        $myVisits = $_SESSION['parent_visits'][$cid] ?? [];
        $myTotalVisits = array_sum($myVisits);
        ?>

        <section class="parent-child-picker">
            <span class="parent-child-picker__label">Ребёнок:</span>
            <select onchange="location.href='index.php?role=parent&child_id=' + this.value;">
                <?php foreach ($mockStudents as $s): ?>
                    <option value="<?= (int) $s['id'] ?>" <?= (int) $s['id'] === $cid ? 'selected' : '' ?>><?= e($s['name']) ?> · <?= e($s['group']) ?></option>
                <?php endforeach; ?>
            </select>
            <span style="flex:1"></span>
            <span class="parent-visits-chip parent-visits-chip--active" title="Ваши визиты в CRM (всего за сессию)">
                👪 Ваши визиты: <?= (int) $myTotalVisits ?>
            </span>
            <?php if ($pv !== null): ?>
                <span class="parent-visits-chip" title="Ваша активность за последний месяц">
                    За месяц: <?= (int) $pv['count'] ?> · последний: <?= e($pv['last']) ?>
                </span>
            <?php endif; ?>
        </section>

        <?php if (!empty($parentBadMarks)): ?>
            <div class="bad-marks-banner">
                <span class="bad-marks-banner__icon">⚠️</span>
                <div class="bad-marks-banner__body">
                    <strong>Внимание!</strong> У вашего ребёнка <?= count($parentBadMarks) ?> <?= count($parentBadMarks) === 1 ? 'низкая отметка' : 'низких отметок' ?> за последний период.
                    <div class="bad-marks-banner__list">
                        <?php foreach ($parentBadMarks as $bm): ?>
                            <span class="bad-marks-banner__pill">
                                <strong><?= e($bm['mark']) ?></strong>
                                <?= e($bm['date']) ?> — <?= e($bm['topic']) ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="notification notification--success">
                <span class="notification__icon">✓</span>
                <div class="notification__body">Низких отметок за период не зафиксировано. Так держать!</div>
            </div>
        <?php endif; ?>

        <section class="panel">
            <div class="student-header">
                <div class="student-header__avatar"><?= e(mb_substr($parentChild['name'], 0, 1, 'UTF-8')) ?></div>
                <div class="student-header__info">
                    <div class="student-header__name"><?= e($parentChild['name']) ?></div>
                    <div class="student-header__meta">Группа <?= e($parentChild['group']) ?> · курс «<?= e($currentProject['title']) ?>» · проектов: <?= count($childProjectIds) ?></div>
                </div>
            </div>
        </section>

        <section class="panel panel--tabs">
            <div class="tabs" role="tablist">
                <button class="tab is-active" data-tab="journal" role="tab" aria-selected="true">📓 Журнал</button>
                <button class="tab" data-tab="project" role="tab" aria-selected="false">📋 Проекты</button>
            </div>

            <div class="tab-content is-active" data-tab-content="journal" role="tabpanel">
                <p class="panel__subtitle" style="margin-bottom: var(--space-sm);">
                    Оценки вашего ребёнка и заметки преподавателя. Клик по дате — тема урока и домашнее задание.
                </p>
                <div class="journal-wrap">
                    <table class="journal" id="journal-table-parent">
                        <thead>
                            <tr>
                                <th>Дата</th>
                                <th>Отметка</th>
                                <th>Заметка преподавателя</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($journalDates as $i => $d): ?>
                                <?php
                                $mark = $childMarks[$i] ?? '';
                                $markClass = '';
                                if ($mark === '5') $markClass = 'journal-mark--grade-5';
                                elseif ($mark === '4') $markClass = 'journal-mark--grade-4';
                                elseif ($mark === '3') $markClass = 'journal-mark--grade-3';
                                elseif ($mark === '2') $markClass = 'journal-mark--grade-2';
                                elseif ($mark === '·') $markClass = 'journal-mark--dot';
                                elseif ($mark === 'н') $markClass = 'journal-mark--absent';
                                $noteText = $journalNotes[$cid][$i] ?? '';
                                $showMark = ($mark !== '' && $mark !== '·');
                                ?>
                                <tr data-date-index="<?= (int) $i ?>">
                                    <td style="font-weight: 600;"><?= e($d) ?></td>
                                    <td style="text-align: center;">
                                        <?php if ($showMark): ?>
                                            <span class="journal-mark <?= $markClass ?>" style="font-size:1rem; min-width:32px; height:32px;"><?= e($mark) ?></span>
                                        <?php else: ?>
                                            <span style="color:rgba(26,26,26,0.15);">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align:left; font-size:0.82rem; color: var(--color-secondary);">
                                        <?= $noteText !== '' ? e($noteText) : '<span style="color:rgba(26,26,26,0.35);font-style:italic;">—</span>' ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="journal-lesson journal-lesson--empty" id="parent-journal-lesson">
                    <div class="journal-lesson__date" id="parent-journal-date">Урок не выбран</div>
                    <div class="journal-lesson__title" id="parent-journal-topic">Выберите дату в таблице, чтобы увидеть тему урока и домашнее задание.</div>
                    <div class="journal-lesson__homework" id="parent-journal-homework"></div>
                </div>
            </div>

            <div class="tab-content" data-tab-content="project" role="tabpanel">
                <p class="panel__subtitle" style="margin-bottom: var(--space-sm);">
                    Проекты вашего ребёнка. Возвраты к предыдущим фазам (↩) — нормальный процесс доработки.
                </p>

                <?php if ($childProjectIds === []): ?>
                    <div class="uni-empty">Пока нет записей по проектам.</div>
                <?php else: ?>
                    <div class="projects-grid">
                        <?php foreach ($childProjectIds as $pid): ?>
                            <?php
                            $proj = $projectById[$pid] ?? null;
                            if (!$proj) continue;
                            $curPhase = project_current_phase($proj);
                            $type = $projectTypes[$proj['type']] ?? ['label' => $proj['type'], 'color' => '#8b6914', 'icon' => '📌'];
                            ?>
                            <article class="project-card" style="--type-color: <?= e($type['color']) ?>;">
                                <header class="project-card__head">
                                    <div>
                                        <div class="project-card__title"><?= e($proj['title']) ?></div>
                                        <div class="project-card__participants">
                                            <strong>Участники:</strong>
                                            <?php
                                            $names = [];
                                            foreach ($proj['students'] as $psid) {
                                                $st = $studentById[(int) $psid] ?? null;
                                                if (!$st) continue;
                                                $names[] = e($st['name']);
                                            }
                                            echo implode(', ', $names);
                                            ?>
                                        </div>
                                    </div>
                                    <span class="project-type-chip" style="--type-color: <?= e($type['color']) ?>;">
                                        <?= $type['icon'] ?> <?= e($type['label']) ?>
                                    </span>
                                </header>

                                <div class="project-card__section-label">Текущая фаза</div>
                                <div class="project-card__stepper">
                                    <?php foreach ($studentPhases as $sp): ?>
                                        <?php
                                        $spNum = (int) $sp['num'];
                                        $cls = 'step-mini--pending';
                                        if ($spNum < $curPhase) $cls = 'step-mini--done';
                                        elseif ($spNum === $curPhase) $cls = 'step-mini--current';
                                        ?>
                                        <div class="step-mini <?= $cls ?>">
                                            <span class="step-mini__num"><?= $spNum ?></span>
                                            <span class="step-mini__name"><?= e($sp['name']) ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <?php if (!empty($proj['history'])): ?>
                                    <div>
                                        <div class="project-card__section-label">История фаз</div>
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
                                                <span class="phase-chip" style="--phase-color: <?= e($sp['color']) ?>;" title="<?= e($h['note']) ?>">
                                                    <span class="phase-chip__num"><?= (int) $sp['num'] ?></span><?= e($sp['name']) ?><span class="phase-chip__date"><?= e($h['date']) ?></span>
                                                </span>
                                                <?php $prevNum = (int) $h['num']; ?>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>
</div>

<script type="application/json" id="parent-lessons-json"><?= json_encode($journalLessons, JSON_UNESCAPED_UNICODE) ?></script>
<script type="application/json" id="parent-dates-json"><?= json_encode($journalDates, JSON_UNESCAPED_UNICODE) ?></script>
<script>
(function () {
    'use strict';
    var tabs = document.querySelectorAll('.tab[data-tab]');
    var contents = document.querySelectorAll('.tab-content[data-tab-content]');
    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            var target = tab.dataset.tab;
            tabs.forEach(function (t) { t.classList.toggle('is-active', t === tab); });
            contents.forEach(function (c) { c.classList.toggle('is-active', c.dataset.tabContent === target); });
        });
    });

    var lessonsJson = document.getElementById('parent-lessons-json');
    var datesJson = document.getElementById('parent-dates-json');
    if (!lessonsJson || !datesJson) return;
    var lessons = JSON.parse(lessonsJson.textContent);
    var dates = JSON.parse(datesJson.textContent);

    var table = document.getElementById('journal-table-parent');
    if (!table) return;
    var rows = table.querySelectorAll('tbody tr[data-date-index]');
    var panelDate = document.getElementById('parent-journal-date');
    var panelTopic = document.getElementById('parent-journal-topic');
    var panelHw = document.getElementById('parent-journal-homework');
    var panel = document.getElementById('parent-journal-lesson');

    function selectRow(idx) {
        rows.forEach(function (r) {
            var i = parseInt(r.dataset.dateIndex, 10);
            r.style.background = (i === idx) ? 'rgba(139,105,20,0.08)' : '';
        });
        var lesson = lessons[idx];
        if (lesson) {
            panel.classList.remove('journal-lesson--empty');
            panelDate.textContent = 'Дата: ' + (dates[idx] || '—');
            panelTopic.textContent = lesson.topic;
            panelHw.innerHTML = '<strong>Домашнее задание:</strong> ' + lesson.homework;
        }
    }

    rows.forEach(function (r) {
        r.style.cursor = 'pointer';
        r.addEventListener('click', function () { selectRow(parseInt(r.dataset.dateIndex, 10)); });
    });

    if (rows.length > 0) selectRow(0);
})();
</script>