<?php
declare(strict_types=1);

$pdo = db();

$isTeacher = in_array('teacher', $currentUser['roles'], true)
          || in_array('admin',   $currentUser['roles'], true);
if (!$isTeacher && !in_array('student', $currentUser['roles'], true)) {
    echo '<div class="uni-empty">Доступ запрещён.</div>';
    return;
}

// ===== Год =====
$journalYears = $pdo->query('SELECT id, name, is_current FROM academic_years ORDER BY start_date DESC')->fetchAll();
$journalYearId = (int) ($_GET['journal_year'] ?? 0);
if ($journalYearId <= 0) {
    foreach ($journalYears as $y) {
        if ((int) $y['is_current'] === 1) { $journalYearId = (int) $y['id']; break; }
    }
    if ($journalYearId <= 0 && $journalYears !== []) $journalYearId = (int) $journalYears[0]['id'];
}

// ===== Семестр =====
$stmt = $pdo->prepare('SELECT id, name, ord FROM semesters WHERE academic_year_id = :y ORDER BY ord, name');
$stmt->execute([':y' => $journalYearId]);
$journalSemesters = $stmt->fetchAll();

$journalSemesterId = (int) ($_GET['journal_semester'] ?? 0);
$validSem = false;
foreach ($journalSemesters as $s) if ((int) $s['id'] === $journalSemesterId) { $validSem = true; break; }
if (!$validSem) $journalSemesterId = $journalSemesters !== [] ? (int) $journalSemesters[0]['id'] : 0;

// ===== Группа =====
$stmt = $pdo->prepare('SELECT id, name FROM groups WHERE academic_year_id = :y ORDER BY name');
$stmt->execute([':y' => $journalYearId]);
$journalGroups = $stmt->fetchAll();

$journalGroupId = (int) ($_GET['journal_group'] ?? 0);
$validGrp = false;
foreach ($journalGroups as $g) if ((int) $g['id'] === $journalGroupId) { $validGrp = true; break; }
if (!$validGrp) $journalGroupId = $journalGroups !== [] ? (int) $journalGroups[0]['id'] : 0;

// ===== Предмет =====
$journalSubjects = [];
if ($journalSemesterId > 0 && $journalGroupId > 0) {
    $stmt = $pdo->prepare(
        'SELECT id, short_name, full_name, hours
         FROM subjects WHERE semester_id = :sm AND group_id = :g
         ORDER BY short_name'
    );
    $stmt->execute([':sm' => $journalSemesterId, ':g' => $journalGroupId]);
    $journalSubjects = $stmt->fetchAll();
}

$journalSubjectId = (int) ($_GET['subject'] ?? 0);
$validSubj = false;
foreach ($journalSubjects as $s) if ((int) $s['id'] === $journalSubjectId) { $validSubj = true; break; }
if (!$validSubj) $journalSubjectId = $journalSubjects !== [] ? (int) $journalSubjects[0]['id'] : 0;

$journalKind = (string) ($_GET['kind'] ?? 'group');
if (!in_array($journalKind, ['group', 'sub'], true)) $journalKind = 'group';

$journalPage = (int) ($_GET['p'] ?? -1);

// ===== Дефолтный вес =====
$defaultWeight = (int) (prikaz_config()['default_grade_weight'] ?? 10);
$weightOptions = [
    10 => '10 — весомая оценка преподавателя (по умолчанию)',
    5  => '5 — контрольная, важное задание',
    3  => '3 — самостоятельная, домашняя',
    1  => '1 — автоматическая проверка (новости, тесты)',
];
?>
<section class="panel">
    <div class="panel__head">
        <div>
            <h2 class="panel__title">Журнал</h2>
            <p class="panel__subtitle">
                Столбцы — даты уроков по КТП, строки — студенты группы.
                <?= $isTeacher ? 'Клик по ячейке — оценка и её вес. Клик по дате — примечание к уроку и ДЗ.' : 'Клик по дате — примечание к уроку и домашнее задание.' ?>
            </p>
        </div>
    </div>

    <div style="display:none;"><?= csrf_field() ?></div>

    <form method="get" action="index.php" class="pzk-inline-form" style="margin-bottom: var(--space-sm);" autocomplete="off">
        <input type="hidden" name="page" value="journal">

        <label>Год:
            <select name="journal_year" class="pzk-field__input" onchange="this.form.submit();">
                <?php foreach ($journalYears as $y): ?>
                    <option value="<?= (int) $y['id'] ?>" <?= (int) $y['id'] === $journalYearId ? 'selected' : '' ?>>
                        <?= e($y['name']) ?><?= (int) $y['is_current'] === 1 ? ' (текущий)' : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>Семестр:
            <select name="journal_semester" class="pzk-field__input" onchange="this.form.submit();">
                <?php if ($journalSemesters === []): ?>
                    <option value="0">— нет —</option>
                <?php else: ?>
                    <?php foreach ($journalSemesters as $s): ?>
                        <option value="<?= (int) $s['id'] ?>" <?= (int) $s['id'] === $journalSemesterId ? 'selected' : '' ?>>
                            <?= e($s['name']) ?>
                        </option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>
        </label>

        <label>Группа:
            <select name="journal_group" class="pzk-field__input" onchange="this.form.submit();">
                <?php if ($journalGroups === []): ?>
                    <option value="0">— нет —</option>
                <?php else: ?>
                    <?php foreach ($journalGroups as $g): ?>
                        <option value="<?= (int) $g['id'] ?>" <?= (int) $g['id'] === $journalGroupId ? 'selected' : '' ?>>
                            <?= e($g['name']) ?>
                        </option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>
        </label>

        <label>Предмет:
            <select name="subject" class="pzk-field__input" onchange="this.form.submit();">
                <?php if ($journalSubjects === []): ?>
                    <option value="0">— нет предметов —</option>
                <?php else: ?>
                    <?php foreach ($journalSubjects as $s): ?>
                        <option value="<?= (int) $s['id'] ?>" <?= (int) $s['id'] === $journalSubjectId ? 'selected' : '' ?>>
                            <?= e($s['short_name']) ?>
                        </option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>
        </label>

        <label>Тип:
            <select name="kind" class="pzk-field__input" onchange="this.form.submit();">
                <option value="group" <?= $journalKind === 'group' ? 'selected' : '' ?>>Групповое</option>
                <option value="sub"   <?= $journalKind === 'sub'   ? 'selected' : '' ?>>Практика</option>
            </select>
        </label>
    </form>

    <?php if ($journalSubjectId > 0): ?>
        <div id="journal-container">
            <?= journal_render_grid($journalSubjectId, $journalKind, $journalPage, $currentUser) ?>
        </div>
    <?php else: ?>
        <div class="uni-empty">Выберите предмет, чтобы открыть журнал.</div>
    <?php endif; ?>
</section>

<style>
/* ===== Вес в модалке ===== */
.journal-modal__weights {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
    margin-bottom: 12px;
    padding-bottom: 12px;
    border-bottom: 1px solid rgba(0, 0, 0, 0.08);
}
.journal-modal__weights-label {
    font-size: 0.82rem;
    color: #666;
    margin-right: 4px;
    font-variant: small-caps;
    letter-spacing: 0.04em;
}
.journal-weight-btn {
    padding: 5px 12px;
    font-size: 0.9rem;
    font-family: inherit;
    border: 1px solid rgba(0, 0, 0, 0.15);
    background: #fff;
    color: #2d2d2d;
    cursor: pointer;
    border-radius: 2px;
    font-variant-numeric: tabular-nums;
    transition: background 0.15s, border-color 0.15s, color 0.15s;
}
.journal-weight-btn:hover {
    background: rgba(139, 105, 20, 0.06);
    border-color: rgba(139, 105, 20, 0.4);
}
.journal-weight-btn.is-active {
    background: #8b6914;
    color: #fff;
    border-color: #8b6914;
}

/* ===== Бейдж веса в ячейке ===== */
.pzk-weight {
    display: inline-block;
    font-size: 0.62rem;
    font-weight: 700;
    line-height: 1;
    padding: 2px 4px;
    border-radius: 3px;
    margin-left: 3px;
    vertical-align: super;
    cursor: help;
    font-variant-numeric: tabular-nums;
}
.pzk-weight--low  { background: rgba(107, 114, 128, 0.15); color: #4b5563; }
.pzk-weight--mid  { background: rgba(139, 105, 20, 0.18);  color: #6b5010; }
.pzk-weight--high { background: rgba(139, 26, 26, 0.18);   color: #8b1a1a; }
</style>

<?php if ($isTeacher && $journalSubjectId > 0): ?>
<div class="journal-modal" id="journal-mark-modal" aria-hidden="true">
    <div class="journal-modal__backdrop" data-journal-close></div>
    <div class="journal-modal__box">
        <header class="journal-modal__head">
            <div>
                <div class="journal-modal__title" id="jm-title"></div>
                <div class="journal-modal__subtitle" id="jm-subtitle"></div>
            </div>
            <button type="button" class="journal-modal__close" data-journal-close aria-label="Закрыть">×</button>
        </header>
        <div class="journal-modal__body">
            <div class="journal-modal__weights" id="jm-weights">
                <span class="journal-modal__weights-label">Вес оценки:</span>
                <?php foreach ($weightOptions as $w => $title): ?>
                    <button type="button"
                            class="journal-weight-btn <?= $w === $defaultWeight ? 'is-active' : '' ?>"
                            data-weight="<?= (int) $w ?>"
                            title="<?= e($title) ?>"><?= (int) $w ?></button>
                <?php endforeach; ?>
            </div>
            <div class="journal-modal__marks" id="jm-marks">
                <?php foreach (['5','5-','4','4-','3','3-','2','н','ну'] as $m): ?>
                    <button type="button" class="journal-mark-btn" data-mark="<?= e($m) ?>"><?= e($m) ?></button>
                <?php endforeach; ?>
                <button type="button" class="journal-mark-btn journal-mark-btn--clear" data-mark="">—</button>
            </div>
            <label class="pzk-field">
                <span class="pzk-field__label">Примечание к оценке</span>
                <textarea class="pzk-field__textarea" id="jm-note" rows="5"
                          placeholder="Видит студент и преподаватель"></textarea>
            </label>
            <input type="hidden" id="jm-subject" value="">
            <input type="hidden" id="jm-student" value="">
            <input type="hidden" id="jm-date"    value="">
            <input type="hidden" id="jm-kind"    value="<?= e($journalKind) ?>">
            <input type="hidden" id="jm-page"    value="<?= (int) $journalPage ?>">
        </div>
        <footer class="journal-modal__foot">
            <button type="button" class="pzk-btn pzk-btn--sm" data-journal-close>Отмена</button>
            <button type="button" class="pzk-btn pzk-btn--sm pzk-btn--primary" id="jm-save">Сохранить</button>
        </footer>
    </div>
</div>

<div class="journal-modal" id="journal-lesson-modal" aria-hidden="true">
    <div class="journal-modal__backdrop" data-journal-close></div>
    <div class="journal-modal__box">
        <header class="journal-modal__head">
            <div>
                <div class="journal-modal__title" id="jl-title"></div>
                <div class="journal-modal__subtitle" id="jl-subtitle"></div>
            </div>
            <button type="button" class="journal-modal__close" data-journal-close aria-label="Закрыть">×</button>
        </header>
        <div class="journal-modal__body">
            <label class="pzk-field">
                <span class="pzk-field__label">Примечание к уроку (общее)</span>
                <textarea class="pzk-field__textarea" id="jl-note" rows="5"
                          placeholder="Видит вся группа"></textarea>
            </label>
            <label class="pzk-field">
                <span class="pzk-field__label">Домашнее задание</span>
                <textarea class="pzk-field__textarea" id="jl-homework" rows="5"
                          placeholder="ДЗ к следующему уроку"></textarea>
            </label>
            <input type="hidden" id="jl-subject" value="">
            <input type="hidden" id="jl-date"    value="">
            <input type="hidden" id="jl-kind"    value="<?= e($journalKind) ?>">
            <input type="hidden" id="jl-page"    value="<?= (int) $journalPage ?>">
        </div>
        <footer class="journal-modal__foot">
            <button type="button" class="pzk-btn pzk-btn--sm" data-journal-close>Отмена</button>
            <button type="button" class="pzk-btn pzk-btn--sm pzk-btn--primary" id="jl-save">Сохранить</button>
        </footer>
    </div>
</div>

<script>
(function () {
    'use strict';

    function csrf() {
        var el = document.querySelector('input[name="_csrf"]');
        return el ? el.value : '';
    }

    var DEFAULT_WEIGHT = <?= (int) $defaultWeight ?>;

    // ===== Вкладки правой панели — делегирование =====
    document.addEventListener('click', function (e) {
        var tab = e.target.closest('[data-side-tab]');
        if (!tab) return;
        var aside = tab.closest('.journal-notes-aside');
        if (!aside) return;
        var key = tab.getAttribute('data-side-tab');
        aside.querySelectorAll('[data-side-tab]').forEach(function (t) {
            var on = (t === tab);
            t.classList.toggle('is-active', on);
            t.setAttribute('aria-selected', on ? 'true' : 'false');
        });
        aside.querySelectorAll('[data-side-pane]').forEach(function (p) {
            p.classList.toggle('is-active', p.getAttribute('data-side-pane') === key);
        });
    });

    // ===== Пагинация — делегирование =====
    document.addEventListener('click', function (e) {
        var link = e.target.closest('.journal-pagination a[data-p]');
        if (!link) return;
        e.preventDefault();
        var url = new URL(window.location.href);
        url.searchParams.set('p', link.getAttribute('data-p'));
        window.location.href = url.toString();
    });

    var container = document.getElementById('journal-container');
    if (!container) return;

    var markModal = document.getElementById('journal-mark-modal');
    var markIn = {
        subject: document.getElementById('jm-subject'),
        student: document.getElementById('jm-student'),
        date:    document.getElementById('jm-date'),
        kind:    document.getElementById('jm-kind'),
        page:    document.getElementById('jm-page'),
        note:    document.getElementById('jm-note'),
    };
    var markTitle    = document.getElementById('jm-title');
    var markSubtitle = document.getElementById('jm-subtitle');
    var markBtns     = document.querySelectorAll('#jm-marks .journal-mark-btn');
    var weightBtns   = document.querySelectorAll('#jm-weights .journal-weight-btn');
    var currentMark  = '';
    var currentWeight = DEFAULT_WEIGHT;

    var lessonModal = document.getElementById('journal-lesson-modal');
    var lessonIn = {
        subject:  document.getElementById('jl-subject'),
        date:     document.getElementById('jl-date'),
        kind:     document.getElementById('jl-kind'),
        page:     document.getElementById('jl-page'),
        note:     document.getElementById('jl-note'),
        homework: document.getElementById('jl-homework'),
    };
    var lessonTitle    = document.getElementById('jl-title');
    var lessonSubtitle = document.getElementById('jl-subtitle');

    function openModal(m) {
        m.classList.add('is-open');
        m.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    }
    function closeModal(m) {
        m.classList.remove('is-open');
        m.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }
    document.querySelectorAll('[data-journal-close]').forEach(function (el) {
        el.addEventListener('click', function () {
            closeModal(markModal);
            closeModal(lessonModal);
        });
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') { closeModal(markModal); closeModal(lessonModal); }
    });

    function syncMarkButtons(mark) {
        currentMark = mark;
        markBtns.forEach(function (b) {
            b.classList.toggle('is-active', (b.getAttribute('data-mark') || '') === mark);
        });
    }
    markBtns.forEach(function (b) {
        b.addEventListener('click', function () {
            syncMarkButtons(b.getAttribute('data-mark') || '');
        });
    });

    function syncWeightButtons(weight) {
        weight = parseInt(weight, 10);
        if (!weight || weight < 1) weight = DEFAULT_WEIGHT;
        currentWeight = weight;
        weightBtns.forEach(function (b) {
            var w = parseInt(b.getAttribute('data-weight'), 10) || 0;
            b.classList.toggle('is-active', w === weight);
        });
    }
    weightBtns.forEach(function (b) {
        b.addEventListener('click', function () {
            syncWeightButtons(b.getAttribute('data-weight'));
        });
    });

    function bindCells() {
        container.querySelectorAll('.journal-cell[data-student]').forEach(function (td) {
            td.addEventListener('click', function () {
                markIn.subject.value = td.getAttribute('data-subject') || '';
                markIn.student.value = td.getAttribute('data-student') || '';
                markIn.date.value    = td.getAttribute('data-date')    || '';
                markIn.kind.value    = td.getAttribute('data-kind')    || 'group';
                markIn.page.value    = td.getAttribute('data-page')    || '-1';
                markIn.note.value    = td.getAttribute('data-note')    || '';

                markTitle.textContent    = td.getAttribute('data-student-name') || '';
                markSubtitle.textContent = (td.getAttribute('data-date-label') || '') + ' · ' + (td.getAttribute('data-topic') || '');

                syncMarkButtons(td.getAttribute('data-mark') || '');
                syncWeightButtons(td.getAttribute('data-weight') || DEFAULT_WEIGHT);
                openModal(markModal);
                markIn.note.focus();
            });
        });
    }

    function bindColHeads() {
        container.querySelectorAll('.journal-col-head[data-date]').forEach(function (th) {
            th.addEventListener('click', function () {
                lessonIn.subject.value  = th.getAttribute('data-subject')  || '';
                lessonIn.date.value     = th.getAttribute('data-date')     || '';
                lessonIn.kind.value     = th.getAttribute('data-kind')     || 'group';
                lessonIn.page.value     = th.getAttribute('data-page')     || '-1';
                lessonIn.note.value     = th.getAttribute('data-note')     || '';
                lessonIn.homework.value = th.getAttribute('data-homework') || '';

                lessonTitle.textContent    = (th.getAttribute('data-date-label') || '') + ' — ' + (th.getAttribute('data-topic') || '');
                lessonSubtitle.textContent = 'Примечание к уроку и ДЗ';
                openModal(lessonModal);
                lessonIn.note.focus();
            });
        });
    }

    bindCells();
    bindColHeads();

    document.getElementById('jm-save').addEventListener('click', function () {
        var btn = this;
        if (btn.disabled) return;
        btn.disabled = true;
        btn.textContent = 'Сохранение…';

        var fd = new FormData();
        fd.append('_csrf', csrf());
        fd.append('ajax', '1');
        fd.append('subject_id', markIn.subject.value);
        fd.append('student_id', markIn.student.value);
        fd.append('date',       markIn.date.value);
        fd.append('kind',       markIn.kind.value);
        fd.append('mark',       currentMark);
        fd.append('weight',     String(currentWeight));
        fd.append('note',       markIn.note.value);
        fd.append('page',       markIn.page.value);

        fetch('index.php?action=journal_mark_set', {
            method: 'POST',
            body: fd,
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data.ok) { alert(data.error || 'Ошибка'); return; }
            container.innerHTML = data.html;
            closeModal(markModal);
            bindCells();
            bindColHeads();
        })
        .catch(function (e) { alert('Ошибка сети: ' + e.message); })
        .finally(function () { btn.disabled = false; btn.textContent = 'Сохранить'; });
    });

    document.getElementById('jl-save').addEventListener('click', function () {
        var btn = this;
        if (btn.disabled) return;
        btn.disabled = true;
        btn.textContent = 'Сохранение…';

        var fd = new FormData();
        fd.append('_csrf', csrf());
        fd.append('ajax', '1');
        fd.append('subject_id', lessonIn.subject.value);
        fd.append('date',       lessonIn.date.value);
        fd.append('kind',       lessonIn.kind.value);
        fd.append('note',       lessonIn.note.value);
        fd.append('homework',   lessonIn.homework.value);
        fd.append('page',       lessonIn.page.value);

        fetch('index.php?action=journal_lesson_note_set', {
            method: 'POST',
            body: fd,
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data.ok) { alert(data.error || 'Ошибка'); return; }
            container.innerHTML = data.html;
            closeModal(lessonModal);
            bindCells();
            bindColHeads();
        })
        .catch(function (e) { alert('Ошибка сети: ' + e.message); })
        .finally(function () { btn.disabled = false; btn.textContent = 'Сохранить'; });
    });
})();
</script>
<?php endif; ?>