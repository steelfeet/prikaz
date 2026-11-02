<?php
declare(strict_types=1);
?>
<div class="workspace">
    <?php if ($managerNotification !== null): ?>
        <div class="notification notification--<?= $managerNotification['type'] === 'confirmed' ? 'success' : 'danger' ?>">
            <span class="notification__icon"><?= $managerNotification['type'] === 'confirmed' ? '✓' : '✕' ?></span>
            <div class="notification__body"><?= e($managerNotification['message']) ?></div>
        </div>
    <?php endif; ?>

    <section class="panel panel--workflow">
        <div class="panel__head">
            <div>
                <h2 class="panel__title">Рабочий процесс текущего проекта</h2>
                <p class="panel__subtitle">
                    Текущее взаимодействие:
                    <strong title="<?= e($currentProject['title']) ?>"><?= e($currentProject['short']) ?></strong>
                    <?php if ($currentProject['phase'] !== null): ?>
                        · фаза <?= (int) $currentProject['phase']['num'] ?>
                        «<?= e($currentProject['phase']['name']) ?>»
                    <?php endif; ?>
                    <?php if (isset($pendingByProject[(int) $currentProject['id']])): ?>
                        · <span style="color:#b45309;font-weight:600;">⏳ ожидает подтверждения вуза</span>
                    <?php endif; ?>
                </p>
                <?php if ($currentPhaseNum === 11): ?>
                    <span class="panel__hint panel__hint--phase">
                        👨‍🎓 Проект в фазе «Ведение занятий» — ниже статистика студентов и сводка по журналу
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <div class="workflow-scroll">
            <div class="workflow-track">
                <?php foreach ($workflow as $item): ?>
                    <?php
                    $stateClass = 'wstep--' . $item['state'];
                    $sideClass = $item['side'] === 'both' ? ' wstep__side--both' : '';
                    ?>
                    <div class="wstep <?= $stateClass ?><?= $item['isCurrent'] ? ' current' : '' ?>"
                         title="<?= e($item['name']) ?> · <?= e($item['sideLabel']) ?> · <?= e($item['stateLabel']) ?>">
                        <span class="wstep__num"><?= (int) $item['num'] ?></span>
                        <span class="wstep__side<?= $sideClass ?>"><?= e($item['side']) ?></span>
                        <?php if ($item['isCurrent']): ?><span class="wstep__flag">◆</span><?php endif; ?>
                        <span class="wstep__name"><?= e($item['name']) ?></span>
                        <span class="wstep__status"><?= e($item['stateLabel']) ?></span>
                        <span class="wstep__bar" style="background-color: <?= e($item['color']) ?>33">
                            <span class="wstep__bar-fill" style="width: <?= (int) $item['fill'] ?>%; background-color: <?= e($item['color']) ?>"></span>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <?php if ($currentPhaseNum === 11): ?>
        <section class="panel panel--students">
            <div class="panel__head">
                <div>
                    <h2 class="panel__title">Статистика студентов</h2>
                    <p class="panel__subtitle">
                        Проектная работа и сводка по классно-урочной системе
                        · курс «<?= e($currentProject['title']) ?>»
                        · студентов: <?= count($mockStudents) ?>
                        · проектов: <?= count($mockProjects) ?>
                    </p>
                </div>
            </div>

            <div class="tabs" role="tablist">
                <button class="tab is-active" data-tab="mgr-project" role="tab" aria-selected="true">📋 Проекты</button>
                <button class="tab" data-tab="mgr-journal-summary" role="tab" aria-selected="false">📊 Сводка по журналу</button>
            </div>

            <div class="tab-content is-active" data-tab-content="mgr-project" role="tabpanel">
                <p class="panel__subtitle" style="margin-bottom: var(--space-sm);">
                    Строки — студенческие проекты. У одного студента может быть несколько проектов,
                    несколько студентов могут работать над одним проектом.
                </p>

                <div class="phase-timeline-wrap">
                    <table class="students-timeline">
                        <thead>
                            <tr>
                                <th style="width: 250px;">Проект</th>
                                <th style="width: 130px;">Тип</th>
                                <th style="width: 240px;">Участники</th>
                                <th>История проектных фаз</th>
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
                                                $pv = $parentVisitsMock[(int) $sid] ?? null;
                                                $chipCls = '';
                                                if ($pv !== null) {
                                                    if ($pv['count'] >= 10) $chipCls = 'parent-visits-chip--active';
                                                    elseif ($pv['count'] >= 5) $chipCls = 'parent-visits-chip--low';
                                                    else $chipCls = 'parent-visits-chip--cold';
                                                }
                                                ?>
                                                <span class="participant-row" title="<?= e($st['name']) ?> · <?= e($st['group']) ?>">
                                                    <span class="participant-row__name"><?= e($st['name']) ?></span>
                                                    <?php if ($pv !== null): ?>
                                                        <span class="parent-visits-chip <?= $chipCls ?>" title="Активность родителя: <?= (int) $pv['count'] ?> визитов, последний <?= e($pv['last']) ?>">
                                                            👪 <?= (int) $pv['count'] ?>
                                                        </span>
                                                    <?php endif; ?>
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
                                                        <span class="phase-arrow <?= $isReturn ? 'phase-arrow--return' : '' ?>" title="<?= $isReturn ? 'Возврат' : 'Переход' ?>"><?= $isReturn ? '↩' : '→' ?></span>
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
                        <span class="legend__item"><span class="parent-visits-chip parent-visits-chip--active" style="margin:0;">👪 N</span>Активность родителя</span>
                    </div>
                </section>
            </div>

            <div class="tab-content" data-tab-content="mgr-journal-summary" role="tabpanel">
                <p class="panel__subtitle" style="margin-bottom: var(--space-md);">
                    Агрегированные показатели классно-урочной работы по курсу.
                    Детальный журнал доступен преподавателю; менеджеру — только сводка.
                </p>

                <div class="summary-grid">
                    <div class="summary-card summary-card--blue">
                        <div class="summary-card__label">Средний балл по курсу</div>
                        <div class="summary-card__value"><?= $avgGrade !== null ? e((string) $avgGrade) : '—' ?></div>
                        <div class="summary-card__hint">по <?= (int) $journalSummary['gradeCount'] ?> выставленным оценкам</div>
                    </div>
                    <div class="summary-card summary-card--green">
                        <div class="summary-card__label">Посещаемость</div>
                        <div class="summary-card__value"><?= $attendance !== null ? e((string) $attendance) . '%' : '—' ?></div>
                        <div class="summary-card__hint"><?= (int) $totalPresent ?> присутствий · <?= (int) $totalAbsent ?> пропусков</div>
                    </div>
                    <div class="summary-card summary-card--red">
                        <div class="summary-card__label">Пропуски занятий</div>
                        <div class="summary-card__value"><?= (int) $totalAbsent ?></div>
                        <div class="summary-card__hint">всего за период</div>
                    </div>
                    <div class="summary-card summary-card--orange">
                        <div class="summary-card__label">Проведено занятий</div>
                        <div class="summary-card__value"><?= count($journalDates) ?></div>
                        <div class="summary-card__hint">по расписанию</div>
                    </div>
                </div>

                <h3 class="panel__title" style="font-size:1.05rem; margin-bottom: var(--space-sm);">Распределение оценок по курсу</h3>
                <?php
                $distMax = max(1, max(array_values($journalSummary['dist'])));
                $distTotal = array_sum($journalSummary['dist']);
                ?>
                <div class="grade-dist">
                    <?php foreach ([5, 4, 3, 2] as $g): ?>
                        <?php $c = (int) $journalSummary['dist'][$g]; ?>
                        <div class="grade-dist__item">
                            <div class="grade-dist__mark grade-dist__mark--<?= $g ?>"><?= $g ?></div>
                            <div class="grade-dist__count">
                                <?= $c ?> шт.
                                <?php if ($distTotal > 0): ?> · <?= round($c / $distTotal * 100, 0) ?>%<?php endif; ?>
                            </div>
                            <div class="grade-dist__bar">
                                <div class="grade-dist__bar-fill grade-dist__bar-fill--<?= $g ?>"
                                     style="width: <?= (int) round($c / $distMax * 100) ?>%"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <h3 class="panel__title" style="font-size:1.05rem; margin: var(--space-md) 0 var(--space-sm);">Разбивка по студентам</h3>
                <div style="overflow:auto; max-height: 460px;">
                    <table class="student-summary">
                        <thead>
                            <tr>
                                <th>Студент</th>
                                <th style="text-align:center;">Средний балл</th>
                                <th style="text-align:center;">5 / 4 / 3 / 2</th>
                                <th style="text-align:center;">Посещаемость</th>
                                <th style="text-align:center;">Пропуски</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($perStudentSummary as $sid => $row): ?>
                                <?php
                                $avg = $row['avg'];
                                $avgCls = 'avg--none';
                                if ($avg !== null) {
                                    if ($avg >= 4.5) $avgCls = 'avg--5';
                                    elseif ($avg >= 3.5) $avgCls = 'avg--4';
                                    elseif ($avg >= 2.5) $avgCls = 'avg--3';
                                    else $avgCls = 'avg--2';
                                }
                                $att = $row['attendance'];
                                ?>
                                <tr>
                                    <td>
                                        <strong><?= e($row['student']['name']) ?></strong>
                                        <span class="student-sub" style="display:block; font-size:0.7rem; color:var(--color-tertiary);"><?= e($row['student']['group']) ?></span>
                                    </td>
                                    <td class="avg <?= $avgCls ?>">
                                        <?= $avg !== null ? e(number_format($avg, 2)) : '—' ?>
                                    </td>
                                    <td class="num">
                                        <?php foreach ([5, 4, 3, 2] as $g): ?>
                                            <span class="journal-mark journal-mark--grade-<?= $g ?>" style="min-width:20px; height:20px; font-size:0.75rem;"><?= (int) $row['dist'][$g] ?></span>
                                        <?php endforeach; ?>
                                    </td>
                                    <td class="num">
                                        <?php if ($att !== null): ?>
                                            <span class="summary-bar"><span class="summary-bar__fill" style="width:<?= (int) $att ?>%"></span></span>
                                            <?= e((string) $att) ?>%
                                        <?php else: ?>—<?php endif; ?>
                                    </td>
                                    <td class="num" style="color: <?= (int) $row['absent'] > 0 ? '#dc2626' : 'var(--color-tertiary)' ?>; font-weight: 600;">
                                        <?= (int) $row['absent'] ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <section class="panel panel--matrix">
        <div class="panel__head">
            <div>
                <h2 class="panel__title">Сетка проектов</h2>
                <p class="panel__subtitle">
                    Строки — ВУЗы, столбцы — ИТ-продукты. Клетки с взаимодействием — цветные листочки с номером фазы.
                </p>
                <span class="panel__hint">
                    ◆ Перетащите листок — рядом появятся карманы «Предыдущая» и «Следующая» фаза. Фазы с 🔒 требуют подтверждения вуза.
                </span>
            </div>
        </div>

        <div class="matrix-scroll">
            <table class="matrix">
                <thead>
                    <tr>
                        <th>Вуз \ Продукт</th>
                        <?php foreach ($products as $product): ?>
                            <th title="<?= e($product['name']) ?>"><?= e($product['short']) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($universities as $university): ?>
                        <tr>
                            <th title="<?= e($university['name']) ?>">
                                <span class="uni">
                                    <?php if ($university['crest'] !== null): ?>
                                        <img class="uni__crest" src="<?= e($university['crest']) ?>" alt="Герб: <?= e($university['short']) ?>" onerror="this.outerHTML='&lt;span class=&quot;uni__initial&quot;&gt;<?= e($university['initial']) ?>&lt;/span&gt;'">
                                    <?php else: ?>
                                        <span class="uni__initial"><?= e($university['initial']) ?></span>
                                    <?php endif; ?>
                                    <span class="uni__code"><?= e($university['short']) ?></span>
                                </span>
                            </th>
                            <?php foreach ($products as $product): ?>
                                <?php
                                $uniId = (int) $university['id'];
                                $prodId = (int) $product['id'];
                                $phaseId = $phaseMap[$uniId][$prodId] ?? null;
                                $phase = $phaseId !== null ? ($phaseById[$phaseId] ?? null) : null;
                                $interactionId = $interactionIdMap[$uniId][$prodId] ?? null;
                                $isCurrent = $interactionId !== null && $interactionId === $currentProject['id'];
                                $isPending = $interactionId !== null && isset($pendingByProject[(int) $interactionId]);
                                $cellClasses = [];
                                if ($phase !== null && (int) $phase['num'] > 0) $cellClasses[] = 'phase';
                                else $cellClasses[] = 'empty';
                                if ($interactionId !== null) $cellClasses[] = 'clickable';
                                if ($isCurrent) $cellClasses[] = 'is-current-project';
                                if ($isPending) $cellClasses[] = 'is-pending';
                                $cellClass = implode(' ', $cellClasses);
                                $cellTitle = $university['name'] . ' · ' . $product['name'];
                                if ($phase !== null && (int) $phase['num'] > 0) $cellTitle .= ' → ' . $phase['name'];
                                ?>
                                <?php if ($interactionId !== null && $phase !== null && (int) $phase['num'] > 0): ?>
                                    <td class="<?= e($cellClass) ?>" title="<?= e($cellTitle) ?>"
                                        data-project="<?= (int) $interactionId ?>"
                                        data-phase-id="<?= (int) $phase['id'] ?>"
                                        data-phase-num="<?= (int) $phase['num'] ?>"
                                        draggable="true" tabindex="0" role="link" aria-label="<?= e($cellTitle) ?>">
                                        <span class="sticker-cell" style="--phase-color: <?= e($phase['color']) ?>;"><?= (int) $phase['num'] ?></span>
                                    </td>
                                <?php elseif ($interactionId !== null): ?>
                                    <td class="<?= e($cellClass) ?>" title="<?= e($cellTitle) ?>" data-project="<?= (int) $interactionId ?>" tabindex="0" role="link">—</td>
                                <?php else: ?>
                                    <td class="<?= e($cellClass) ?>" title="<?= e($cellTitle) ?>">—</td>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <section class="legend">
            <h3 class="legend__title">Легенда фаз взаимодействия</h3>
            <div class="legend__items">
                <?php foreach ($phases as $phase): ?>
                    <span class="legend__item">
                        <span class="legend__swatch" style="background-color: <?= e($phase['color']) ?>"></span>
                        <?php if ((int) $phase['num'] > 0): ?><?= (int) $phase['num'] ?>.<?php endif; ?>
                        <?= e($phase['name']) ?>
                        <?php if ($phase['requires_confirmation']): ?> 🔒<?php endif; ?>
                    </span>
                <?php endforeach; ?>
            </div>
        </section>
    </section>
</div>

<script type="application/json" id="phases-meta"><?= json_encode($phaseMeta, JSON_UNESCAPED_UNICODE) ?></script>
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

    var PHASES = JSON.parse(document.getElementById('phases-meta').textContent);
    var phaseByNum = {};
    PHASES.forEach(function (p) { phaseByNum[p.num] = p; });

    var cells = Array.prototype.slice.call(document.querySelectorAll('table.matrix td.phase[draggable="true"]'));
    if (cells.length === 0) return;

    var source = null;
    var pockets = [];

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function makePocket(phase, direction, rect) {
        var pocket = document.createElement('div');
        pocket.className = 'phase-pocket phase-pocket--' + direction;
        pocket.dataset.phaseId = phase.id;
        pocket.dataset.phaseNum = phase.num;
        var arrow = direction === 'prev' ? '↑' : '↓';
        var label = direction === 'prev' ? 'Предыдущая фаза' : 'Следующая фаза';
        pocket.innerHTML = ''
            + '<div class="phase-pocket__pin"></div>'
            + '<div class="phase-pocket__label"><span class="phase-pocket__label-arrow">' + arrow + '</span>' + label + '</div>'
            + '<div class="phase-pocket__body">'
            +   '<span class="phase-pocket__num" style="background-color: ' + phase.color + '">' + phase.num + '</span>'
            +   '<span class="phase-pocket__name">' + escapeHtml(phase.name) + '</span>'
            +   (phase.requires_confirmation ? '<span class="phase-pocket__lock" title="Требует подтверждения вуза">🔒</span>' : '')
            + '</div>';
        var pw = 240;
        var left = rect.left + rect.width / 2 - pw / 2;
        left = Math.max(8, Math.min(left, window.innerWidth - pw - 8));
        var gap = 14;
        var top;
        if (direction === 'prev') {
            top = rect.top - 90 - gap;
            if (top < 8) { top = rect.bottom + gap; left = Math.max(8, left - 60); }
        } else {
            top = rect.bottom + gap;
            if (top + 90 > window.innerHeight - 8) { top = rect.top - 90 - gap; left = Math.max(8, left + 60); }
        }
        top = Math.max(8, Math.min(top, window.innerHeight - 96));
        pocket.style.left = left + 'px';
        pocket.style.top = top + 'px';
        pocket.style.width = pw + 'px';
        pocket.addEventListener('dragover', function (e) { e.preventDefault(); e.dataTransfer.dropEffect = 'move'; pocket.classList.add('is-hover'); });
        pocket.addEventListener('dragleave', function () { pocket.classList.remove('is-hover'); });
        pocket.addEventListener('drop', function (e) { e.preventDefault(); if (!source) return; doMove(source.dataset.project, pocket.dataset.phaseId); });
        document.body.appendChild(pocket);
        requestAnimationFrame(function () { pocket.classList.add('is-visible'); });
        pockets.push(pocket);
    }

    function cleanup() {
        document.body.classList.remove('is-dragging');
        cells.forEach(function (c) { c.classList.remove('is-drag-source'); });
        pockets.forEach(function (p) { p.remove(); });
        pockets = [];
        source = null;
    }

    function doMove(projectId, targetPhaseId) {
        var pid = parseInt(projectId, 10);
        cleanup();
        fetch('index.php?action=move_phase', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ project_id: pid, target_phase_id: parseInt(targetPhaseId, 10) })
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data || !data.ok) { alert((data && data.error) || 'Не удалось переместить листок'); return; }
            window.location.href = 'index.php?role=manager&project=' + pid;
        })
        .catch(function () { alert('Ошибка сети. Попробуйте ещё раз.'); });
    }

    cells.forEach(function (td) {
        td.addEventListener('dragstart', function (e) {
            source = td;
            var fromNum = parseInt(td.dataset.phaseNum, 10);
            var rect = td.getBoundingClientRect();
            document.body.classList.add('is-dragging');
            td.classList.add('is-drag-source');
            var prev = phaseByNum[fromNum - 1];
            var next = phaseByNum[fromNum + 1];
            if (prev) makePocket(prev, 'prev', rect);
            if (next) makePocket(next, 'next', rect);
            e.dataTransfer.setData('text/plain', JSON.stringify({ projectId: td.dataset.project, fromPhaseId: td.dataset.phaseId }));
            e.dataTransfer.effectAllowed = 'move';
        });
        td.addEventListener('dragend', function () { cleanup(); });
        td.addEventListener('click', function () {
            if (source !== null) return;
            window.location.href = 'index.php?role=manager&project=' + td.dataset.project;
        });
        td.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); window.location.href = 'index.php?role=manager&project=' + td.dataset.project; }
        });
    });
})();
</script>