<?php
declare(strict_types=1);

if (!in_array('student', $currentUser['roles'], true)) {
    echo '<div class="workspace workspace--student"><div class="uni-empty">Эта страница доступна только студентам.</div></div>';
    return;
}

if (!$studentProfile) {
    echo '<div class="workspace workspace--student"><div class="uni-empty">Профиль студента не найден. Обратитесь к администратору.</div></div>';
    return;
}

$sid            = (int) $studentProfile['id'];
$studentMarks   = $journalMarks[$sid] ?? [];
$studentNotes   = $journalNotes[$sid] ?? [];
$myProjectIds   = $projectsByStudent[$sid] ?? [];

$phaseByNum = [];
foreach ($projectPhases as $ph) {
    $phaseByNum[(int) $ph['num']] = $ph;
}

$displayName = (string) $studentProfile['full_name'];
$avatarLetter = mb_substr(
    trim(($studentProfile['last_name'] ?? '') !== '' ? $studentProfile['last_name'] : $displayName),
    0, 1, 'UTF-8'
);
$groupLabel = trim((string) ($studentProfile['group_name'] ?? ''));
if ($groupLabel === '' && !empty($studentProfile['group_id'])) {
    $groupLabel = '#' . (int) $studentProfile['group_id'];
}
?>
<div class="workspace workspace--student">
    <section class="panel" style="flex:1; min-height:0; display:flex; flex-direction:column;">

        <div class="student-header" style="margin-bottom: var(--space-md); display:flex; align-items:center; gap:16px;">
            <div class="student-header__avatar"><?= e($avatarLetter) ?></div>
            <div class="student-header__info" style="flex:1;">
                <div class="student-header__name"><?= e($displayName) ?></div>
                <div class="student-header__meta">
                    <?php if ($groupLabel !== ''): ?>
                        Группа <?= e($groupLabel) ?> ·
                    <?php endif; ?>
                    Логин: <code><?= e((string) $studentProfile['login']) ?></code> ·
                    Email: <a href="mailto:<?= e((string) $studentProfile['email']) ?>"><?= e((string) $studentProfile['email']) ?></a>
                    <?php if (!empty($studentProfile['vk_url'])): ?>
                        · <a href="<?= e($studentProfile['vk_url']) ?>" target="_blank" rel="noopener">VK</a>
                    <?php endif; ?>
                    <?php if (!empty($studentProfile['telegram'])): ?>
                        · Telegram: <code>@<?= e($studentProfile['telegram']) ?></code>
                    <?php endif; ?>
                    · Проектов: <?= count($myProjectIds) ?>
                </div>
            </div>
            <div>
                <a href="index.php?page=profile"
                   style="display:inline-block; padding:6px 14px; background:#8b6914; color:#fff; text-decoration:none; border-radius:2px; font-weight:600; letter-spacing:0.03em; white-space:nowrap;">
                    ⚙ Настройки профиля
                </a>
            </div>
        </div>

        <div class="tabs" role="tablist">
            <button class="tab is-active" data-tab="student-project" role="tab" aria-selected="true">📋 Мои проекты</button>
            <button class="tab" data-tab="student-journal" role="tab" aria-selected="false">📓 Журнал</button>
        </div>

        <div class="tab-content is-active" data-tab-content="student-project" role="tabpanel">
            <?php if ($myProjectIds === []): ?>
                <div class="uni-empty">У вас пока нет проектов.</div>
            <?php else: ?>
                <div class="projects-grid">
                    <?php foreach ($myProjectIds as $pid): ?>
                        <?php
                        $proj = $projectById[$pid] ?? null;
                        if (!$proj) continue;
                        $curPhase = (int) ($proj['current_phase'] ?? 1);
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
                                            $names[] = e($st['full_name']);
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
                                <?php foreach ($projectPhases as $sp): ?>
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
                                            $sp = $phaseByNum[(int) $h['num']] ?? null;
                                            if (!$sp) continue;
                                            $isReturn = $prevNum > 0 && (int) $h['num'] < $prevNum;
                                            ?>
                                            <?php if ($idx > 0): ?>
                                                <span class="phase-arrow <?= $isReturn ? 'phase-arrow--return' : '' ?>"><?= $isReturn ? '↩' : '→' ?></span>
                                            <?php endif; ?>
                                            <span class="phase-chip" style="--phase-color: <?= e($sp['color']) ?>;" title="<?= e((string) ($h['note'] ?? '')) ?>">
                                                <span class="phase-chip__num"><?= (int) $sp['num'] ?></span><?= e($sp['name']) ?><span class="phase-chip__date"><?= e((string) ($h['date'] ?? '')) ?></span>
                                            </span>
                                            <?php $prevNum = (int) $h['num']; ?>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($proj['git'])): ?>
                                <div>
                                    <div class="project-card__section-label">Git-история проекта</div>
                                    <div class="git-history">
                                        <div class="git-history__header">main <span class="git-history__branch">⎇</span></div>
                                        <div class="git-history__list">
                                            <?php foreach ($proj['git'] as $commit): ?>
                                                <div class="git-history__item">
                                                    <div class="git-history__graph"><span class="git-history__dot"></span></div>
                                                    <span class="git-history__hash"><?= e((string) ($commit['hash'] ?? '')) ?></span>
                                                    <div class="git-history__content">
                                                        <span class="git-history__msg"><?= e((string) ($commit['message'] ?? '')) ?></span>
                                                        <span class="git-history__meta"><?= e((string) ($commit['author'] ?? '')) ?> · <?= e((string) ($commit['date'] ?? '')) ?></span>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="tab-content" data-tab-content="student-journal" role="tabpanel">
            <p class="panel__subtitle" style="margin-bottom: var(--space-sm);">
                Мои оценки и заметки преподавателя. Клик по дате — тема урока и домашнее задание.
            </p>
            <div class="journal-wrap">
                <table class="journal" id="journal-table-student">
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
                            $mark = $studentMarks[$i] ?? '';
                            $markClass = '';
                            if ($mark === '5') $markClass = 'journal-mark--grade-5';
                            elseif ($mark === '4') $markClass = 'journal-mark--grade-4';
                            elseif ($mark === '3') $markClass = 'journal-mark--grade-3';
                            elseif ($mark === '2') $markClass = 'journal-mark--grade-2';
                            elseif ($mark === '·') $markClass = 'journal-mark--dot';
                            elseif ($mark === 'н') $markClass = 'journal-mark--absent';
                            $noteText = $studentNotes[$i] ?? '';
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

            <div class="journal-lesson journal-lesson--empty" id="student-journal-lesson">
                <div class="journal-lesson__date" id="student-journal-date">Урок не выбран</div>
                <div class="journal-lesson__title" id="student-journal-topic">Выберите дату в таблице, чтобы увидеть тему урока и домашнее задание.</div>
                <div class="journal-lesson__homework" id="student-journal-homework"></div>
            </div>
        </div>

    </section>
</div>

<script type="application/json" id="student-lessons-json"><?= json_encode($journalLessons, JSON_UNESCAPED_UNICODE) ?></script>
<script type="application/json" id="student-dates-json"><?= json_encode($journalDates, JSON_UNESCAPED_UNICODE) ?></script>
<script>
(function () {
    'use strict';

    var tabs = document.querySelectorAll('.tab[data-tab]');
    var contents = document.querySelectorAll('.tab-content[data-tab-content]');
    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            var target = tab.dataset.tab;
            tabs.forEach(function (t) {
                t.classList.toggle('is-active', t === tab);
                t.setAttribute('aria-selected', t === tab ? 'true' : 'false');
            });
            contents.forEach(function (c) {
                c.classList.toggle('is-active', c.dataset.tabContent === target);
            });
        });
    });

    var lessonsJson = document.getElementById('student-lessons-json');
    var datesJson = document.getElementById('student-dates-json');
    if (!lessonsJson || !datesJson) return;
    var lessons = JSON.parse(lessonsJson.textContent);
    var dates = JSON.parse(datesJson.textContent);

    var table = document.getElementById('journal-table-student');
    if (!table) return;
    var rows = table.querySelectorAll('tbody tr[data-date-index]');
    var panelDate = document.getElementById('student-journal-date');
    var panelTopic = document.getElementById('student-journal-topic');
    var panelHw = document.getElementById('student-journal-homework');
    var panel = document.getElementById('student-journal-lesson');

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