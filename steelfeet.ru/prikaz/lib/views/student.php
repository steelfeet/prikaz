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

$pdo = db();

$sid          = (int) $studentProfile['id'];
$myProjectIds = $projectsByStudent[$sid] ?? [];

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

// ===== Журнал: доступные предметы студента =====
$studentGroupId = (int) ($studentProfile['group_id'] ?? 0);
$journalSubjects = [];
if ($studentGroupId > 0) {
    $stmt = $pdo->prepare(
        'SELECT sub.id, sub.short_name, sub.full_name,
                sm.name AS semester_name, sm.ord AS semester_ord,
                ay.name AS year_name
         FROM subjects sub
         JOIN semesters sm      ON sm.id = sub.semester_id
         JOIN academic_years ay ON ay.id = sm.academic_year_id
         WHERE sub.group_id = :g
         ORDER BY ay.start_date DESC, sm.ord, sub.short_name'
    );
    $stmt->execute([':g' => $studentGroupId]);
    $journalSubjects = $stmt->fetchAll();
}

$journalSubjectId = (int) ($_GET['journal_subject'] ?? 0);
$validSubj = false;
foreach ($journalSubjects as $s) if ((int) $s['id'] === $journalSubjectId) { $validSubj = true; break; }
if (!$validSubj) $journalSubjectId = $journalSubjects !== [] ? (int) $journalSubjects[0]['id'] : 0;

$journalKind = (string) ($_GET['journal_kind'] ?? 'all');
if (!in_array($journalKind, ['all', 'group', 'sub'], true)) $journalKind = 'all';

$journalPage = (int) ($_GET['p'] ?? 0);

// Какая вкладка активна по умолчанию: journal. Проекты — только если явно ?tab=projects.
$urlTab = (string) ($_GET['tab'] ?? '');
$activeTab = ($urlTab === 'projects') ? 'student-project' : 'student-journal';
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
            <button class="tab <?= $activeTab === 'student-journal' ? 'is-active' : '' ?>"
                    data-tab="student-journal" role="tab"
                    aria-selected="<?= $activeTab === 'student-journal' ? 'true' : 'false' ?>">📖 Журнал</button>
            <button class="tab <?= $activeTab === 'student-project' ? 'is-active' : '' ?>"
                    data-tab="student-project" role="tab"
                    aria-selected="<?= $activeTab === 'student-project' ? 'true' : 'false' ?>">📋 Мои проекты</button>
        </div>

        <div class="tab-content <?= $activeTab === 'student-journal' ? 'is-active' : '' ?>"
             data-tab-content="student-journal" role="tabpanel">
            <?php if ($journalSubjects === []): ?>
                <div class="uni-empty">
                    Нет предметов: либо у вас не выбрана группа, либо в семестрах этой группы
                    ещё нет предметов. Обратитесь к администратору.
                </div>
            <?php else: ?>
                <form method="get" action="index.php" class="sj-filter-form" autocomplete="off">
                    <input type="hidden" name="page" value="student">
                    <input type="hidden" name="tab" value="journal">
                    <label>Предмет:
                        <select name="journal_subject" class="pzk-field__input" onchange="this.form.submit();">
                            <?php foreach ($journalSubjects as $s): ?>
                                <option value="<?= (int) $s['id'] ?>"
                                        <?= (int) $s['id'] === $journalSubjectId ? 'selected' : '' ?>>
                                    <?= e($s['short_name']) ?> · <?= e($s['semester_name']) ?> · <?= e($s['year_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Тип:
                        <select name="journal_kind" class="pzk-field__input" onchange="this.form.submit();">
                            <option value="all"   <?= $journalKind === 'all'   ? 'selected' : '' ?>>Все</option>
                            <option value="group" <?= $journalKind === 'group' ? 'selected' : '' ?>>Групповое</option>
                            <option value="sub"   <?= $journalKind === 'sub'   ? 'selected' : '' ?>>Практика</option>
                        </select>
                    </label>
                </form>

                <?php if ($journalSubjectId > 0): ?>
                    <?= journal_render_student($journalSubjectId, $journalKind, $journalPage, $currentUser) ?>
                <?php else: ?>
                    <div class="uni-empty">Выберите предмет.</div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <div class="tab-content <?= $activeTab === 'student-project' ? 'is-active' : '' ?>"
             data-tab-content="student-project" role="tabpanel">
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
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    </section>
</div>

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

    // Пагинация: всегда page=student, tab=journal, чтобы после перехода
    // открылась именно вкладка «Журнал», а не «Проекты».
    document.addEventListener('click', function (e) {
        var link = e.target.closest('.sj-pagination a[data-p]');
        if (!link) return;
        e.preventDefault();
        var url = new URL(window.location.href);
        url.searchParams.set('page', 'student');
        url.searchParams.set('tab', 'journal');
        url.searchParams.set('p', link.getAttribute('data-p'));
        window.location.href = url.toString();
    });
})();
</script>