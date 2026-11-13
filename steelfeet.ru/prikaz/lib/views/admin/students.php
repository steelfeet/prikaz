<?php
declare(strict_types=1);
?>
<section class="panel">
    <div class="panel__head">
        <div>
            <h2 class="panel__title">Студенты</h2>
            <p class="panel__subtitle">
                ФИО, группа, логин (он же псевдоним в Gogs), email, VK, Telegram.
                Пароль по умолчанию при массовом добавлении — <code>1111</code>.
                Сменить email и пароль студент может в своём кабинете.
                <?php if ($adminStudents !== []): ?>
                    Показано: <strong><?= count($adminStudents) ?></strong>.
                <?php endif; ?>
            </p>
        </div>
        <a href="#add-student" class="pzk-btn pzk-btn--primary" data-open-details>
            ➕ Добавить
        </a>
    </div>

    <form method="get" action="index.php" class="pzk-inline-form" autocomplete="off"
          style="margin-bottom: var(--space-sm);">
        <input type="hidden" name="page" value="admin">
        <input type="hidden" name="section" value="students">
        <label>Учебный год:
            <select name="student_year" class="pzk-field__input" onchange="this.form.submit();">
                <option value="0" <?= $adminStudentsFilterYear === 0 ? 'selected' : '' ?>>— все —</option>
                <?php foreach ($adminAcademicYears as $ay): ?>
                    <option value="<?= (int) $ay['id'] ?>"
                            <?= (int) $adminStudentsFilterYear === (int) $ay['id'] ? 'selected' : '' ?>>
                        <?= e($ay['name']) ?><?= (int) $ay['is_current'] === 1 ? ' (текущий)' : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Группа:
            <select name="student_group" class="pzk-field__input" onchange="this.form.submit();">
                <option value="0" <?= $adminStudentsFilterGroup === 0 ? 'selected' : '' ?>>— все —</option>
                <?php foreach ($adminAllGroups as $g): ?>
                    <?php if ($adminStudentsFilterYear > 0
                              && (int) $g['academic_year_id'] !== $adminStudentsFilterYear) continue; ?>
                        <option value="<?= (int) $g['id'] ?>"
                                <?= (int) $adminStudentsFilterGroup === (int) $g['id'] ? 'selected' : '' ?>>
                            <?= e($g['name']) ?> · <?= e($g['year_name']) ?>
                        </option>
                    <?php endforeach; ?>
            </select>
        </label>
    </form>

    <?php if ($adminStudents === []): ?>
        <div class="uni-empty">
            <?php if ($adminStudentsFilterGroup > 0): ?>
                В выбранной группе студентов пока нет.
            <?php elseif ($adminStudentsFilterYear > 0): ?>
                В выбранном учебном году студентов пока нет. Добавьте первого или смените фильтр.
            <?php else: ?>
                Студентов пока нет. Добавьте первого.
            <?php endif; ?>
        </div>
    <?php else: ?>
        <?php
            $groupOptions  = $groupOptions ?? students_group_options($pdo ?? null);
            $currentYearId = $adminCurrentYear !== null ? (int) $adminCurrentYear['id'] : 0;
            $currentYearGroups = [];
            foreach ($groupOptions as $g) {
                if ((int) $g['academic_year_id'] === $currentYearId) {
                    $currentYearGroups[] = $g;
                }
            }
        ?>
        <div class="pzk-table-wrap">
            <table class="pzk-table">
                <thead>
                    <tr>
                        <th style="width:50px;">ID</th>
                        <th>ФИО</th>
                        <th style="width:150px;">Группа</th>
                        <th style="width:130px;">Логин</th>
                        <th style="width:200px;">Email</th>
                        <th style="width:170px;">VK</th>
                        <th style="width:150px;">Telegram</th>
                        <th style="width:190px;">Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($adminStudents as $s): ?>
                        <?php $sid = (int) $s['id']; ?>
                        <tr>
                            <td><?= $sid ?></td>
                            <td><strong><?= e($s['full_name']) ?></strong></td>
                            <td>
                                <?php if (!empty($s['group_name'])): ?>
                                    <?= e($s['group_name']) ?>
                                    <?php if (!empty($s['year_name'])): ?>
                                        <br><span style="font-size:0.75rem; color:var(--color-tertiary);">
                                            <?= e($s['year_name']) ?>
                                        </span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span style="color:var(--color-tertiary);">—</span>
                                <?php endif; ?>
                            </td>
                            <td><code><?= e((string) ($s['login'] ?? '')) ?></code></td>
                            <td>
                                <?php if (!empty($s['email'])): ?>
                                    <a href="mailto:<?= e($s['email']) ?>"><?= e($s['email']) ?></a>
                                <?php else: ?>
                                    <span style="color:var(--color-tertiary);">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($s['vk_url'])): ?>
                                    <a href="<?= e($s['vk_url']) ?>" target="_blank" rel="noopener">↗</a>
                                <?php else: ?>
                                    <span style="color:var(--color-tertiary);">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($s['telegram'])): ?>
                                    <code>@<?= e($s['telegram']) ?></code>
                                <?php else: ?>
                                    <span style="color:var(--color-tertiary);">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="pzk-table__actions">
                                <button type="button" class="pzk-btn pzk-btn--sm"
                                        data-open-student-edit="student-edit-<?= $sid ?>">
                                    Редактировать
                                </button>
                                <form method="post" action="index.php?action=admin_student_delete"
                                      onsubmit="return confirm('Удалить студента «<?= e($s['full_name']) ?>»? Удалятся его учётная запись, оценки, участие в проектах и привязки к родителям.');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= $sid ?>">
                                    <button type="submit" class="pzk-btn pzk-btn--sm">Удалить</button>
                                </form>
                            </td>
                        </tr>
                        <tr class="pzk-student-edit-row">
                            <td colspan="8">
                                <details class="pzk-student-edit" id="student-edit-<?= $sid ?>">
                                    <summary style="display:none"></summary>
                                    <form method="post" action="index.php?action=admin_student_update"
                                          class="pzk-student-edit__form pzk-student-form" autocomplete="off">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= $sid ?>">
                                        <div class="pzk-form__grid">
                                            <label class="pzk-field">
                                                <span class="pzk-field__label">Фамилия</span>
                                                <input type="text" name="last_name" required
                                                       value="<?= e((string) ($s['last_name'] ?? '')) ?>"
                                                       autocomplete="off"
                                                       class="pzk-field__input pzk-field__input--sm">
                                            </label>
                                            <label class="pzk-field">
                                                <span class="pzk-field__label">Имя</span>
                                                <input type="text" name="first_name" required
                                                       value="<?= e((string) ($s['first_name'] ?? '')) ?>"
                                                       autocomplete="off"
                                                       class="pzk-field__input pzk-field__input--sm">
                                            </label>
                                            <label class="pzk-field">
                                                <span class="pzk-field__label">Отчество</span>
                                                <input type="text" name="middle_name"
                                                       value="<?= e((string) ($s['middle_name'] ?? '')) ?>"
                                                       autocomplete="off"
                                                       class="pzk-field__input pzk-field__input--sm">
                                            </label>
                                            <label class="pzk-field">
                                                <span class="pzk-field__label">Логин</span>
                                                <input type="text" name="login" required
                                                       pattern="[a-zA-Z0-9_.-]{3,64}"
                                                       value="<?= e((string) ($s['login'] ?? '')) ?>"
                                                       autocomplete="off"
                                                       autocapitalize="off" spellcheck="false"
                                                       class="pzk-field__input pzk-field__input--sm">
                                            </label>
                                            <label class="pzk-field">
                                                <span class="pzk-field__label">Email</span>
                                                <input type="email" name="email" required
                                                       value="<?= e((string) ($s['email'] ?? '')) ?>"
                                                       autocomplete="off"
                                                       class="pzk-field__input pzk-field__input--sm">
                                            </label>
                                            <label class="pzk-field">
                                                <span class="pzk-field__label">Пароль</span>
                                                <input type="text" name="password"
                                                       minlength="<?= PRIKAZ_PASSWORD_MIN ?>"
                                                       placeholder="оставьте пустым, чтобы не менять"
                                                       autocomplete="new-password"
                                                       class="pzk-field__input pzk-field__input--sm">
                                            </label>
                                            <label class="pzk-field">
                                                <span class="pzk-field__label">Страница VK</span>
                                                <input type="text" name="vk_url"
                                                       value="<?= e((string) ($s['vk_url'] ?? '')) ?>"
                                                       placeholder="https://vk.com/..."
                                                       autocomplete="off"
                                                       class="pzk-field__input pzk-field__input--sm">
                                            </label>
                                            <label class="pzk-field">
                                                <span class="pzk-field__label">Telegram</span>
                                                <input type="text" name="telegram"
                                                       value="<?= e((string) ($s['telegram'] ?? '')) ?>"
                                                       placeholder="username"
                                                       autocomplete="off"
                                                       class="pzk-field__input pzk-field__input--sm">
                                            </label>
                                            <label class="pzk-field">
                                                <span class="pzk-field__label">Группа</span>
                                                <select name="group_id" class="pzk-field__input pzk-field__input--sm">
                                                    <option value="0">— не выбрана —</option>
                                                    <?php foreach ($groupOptions as $g): ?>
                                                        <option value="<?= (int) $g['id'] ?>"
                                                                data-year="<?= (int) $g['academic_year_id'] ?>"
                                                                <?= (int) $g['id'] === (int) ($s['group_id'] ?? 0) ? 'selected' : '' ?>>
                                                            <?= e($g['name']) ?> · <?= e($g['year_name']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </label>
                                        </div>
                                        <div class="pzk-student-edit__actions">
                                            <button type="submit" class="pzk-btn pzk-btn--sm pzk-btn--primary">Сохранить</button>
                                        </div>
                                    </form>
                                </details>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <details class="pzk-details" id="add-student" style="margin-top: var(--space-sm);">
        <summary class="pzk-details__summary">➕ Добавить студента</summary>
        <form method="post" action="index.php?action=admin_student_create"
              class="pzk-form pzk-student-form" autocomplete="off">
            <?= csrf_field() ?>
            <div>
                <h3 class="pzk-form__title">Новый студент</h3>
                <p class="pzk-form__hint">
                    Логин совпадает с логином в Gogs. Фамилия, имя, логин, пароль и email — обязательны.
                </p>
            </div>
            <div class="pzk-form__grid">
                <label class="pzk-field">
                    <span class="pzk-field__label">Фамилия</span>
                    <input class="pzk-field__input" type="text" name="last_name" required
                           placeholder="Иванов" autocomplete="off">
                </label>
                <label class="pzk-field">
                    <span class="pzk-field__label">Имя</span>
                    <input class="pzk-field__input" type="text" name="first_name" required
                           placeholder="Иван" autocomplete="off">
                </label>
                <label class="pzk-field">
                    <span class="pzk-field__label">Отчество (опционально)</span>
                    <input class="pzk-field__input" type="text" name="middle_name"
                           placeholder="Иванович" autocomplete="off">
                </label>
                <label class="pzk-field">
                    <span class="pzk-field__label">Логин</span>
                    <input class="pzk-field__input" type="text" name="login" required
                           pattern="[a-zA-Z0-9_.-]{3,64}"
                           placeholder="ivanov" autocomplete="off"
                           autocapitalize="off" spellcheck="false">
                </label>
                <label class="pzk-field">
                    <span class="pzk-field__label">Пароль</span>
                    <input class="pzk-field__input" type="text" name="password" required
                           minlength="<?= PRIKAZ_PASSWORD_MIN ?>"
                           placeholder="не короче <?= PRIKAZ_PASSWORD_MIN ?> символов"
                           autocomplete="new-password">
                </label>
                <label class="pzk-field">
                    <span class="pzk-field__label">Email</span>
                    <input class="pzk-field__input" type="email" name="email" required
                           placeholder="ivanov@example.ru" autocomplete="off">
                </label>
                <label class="pzk-field">
                    <span class="pzk-field__label">Страница VK (опционально)</span>
                    <input class="pzk-field__input" type="text" name="vk_url"
                           placeholder="https://vk.com/..." autocomplete="off">
                </label>
                <label class="pzk-field">
                    <span class="pzk-field__label">Telegram (опционально)</span>
                    <input class="pzk-field__input" type="text" name="telegram"
                           placeholder="username" autocomplete="off">
                </label>
                <label class="pzk-field">
                    <span class="pzk-field__label">Учебный год (для выбора группы)</span>
                    <select class="pzk-field__input pzk-student-year">
                        <?php foreach ($adminAcademicYears as $ay): ?>
                            <option value="<?= (int) $ay['id'] ?>"
                                    <?= $adminCurrentYear !== null && (int) $ay['id'] === (int) $adminCurrentYear['id'] ? 'selected' : '' ?>>
                                <?= e($ay['name']) ?><?= (int) $ay['is_current'] === 1 ? ' (текущий)' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="pzk-field">
                    <span class="pzk-field__label">Группа (опционально)</span>
                    <select class="pzk-field__input" name="group_id">
                        <option value="0">— не выбрана —</option>
                        <?php $groupOptions = $groupOptions ?? students_group_options($pdo ?? null); ?>
                        <?php foreach ($groupOptions as $g): ?>
                            <option value="<?= (int) $g['id'] ?>"
                                    data-year="<?= (int) $g['academic_year_id'] ?>">
                                <?= e($g['name']) ?> · <?= e($g['year_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
            <div class="pzk-form__actions">
                <button type="submit" class="pzk-btn pzk-btn--primary">Создать студента</button>
            </div>
        </form>
    </details>

    <details class="pzk-details" id="bulk-students" style="margin-top: var(--space-sm);">
        <summary class="pzk-details__summary">📋 Массовое добавление студентов</summary>
        <form method="post" action="index.php?action=admin_student_bulk_create"
              class="pzk-form" id="bulk-students-form"
              autocomplete="off" novalidate>
            <?= csrf_field() ?>
            <div>
                <h3 class="pzk-form__title">Массовое добавление</h3>
                <p class="pzk-form__hint">
                    Одна строка — один студент: <code>Фамилия Имя Отчество, логин</code>.
                    Отчество опционально. Пустые строки игнорируются.
                    Пароль по умолчанию — <code>1111</code>, email — <code>логин@mail.ru</code>.
                    Студенты должны сменить пароль при первом входе.
                </p>
            </div>
            <div class="pzk-form__grid">
                <label class="pzk-field">
                    <span class="pzk-field__label">Группа для всех добавляемых</span>
                    <?php
                        $curGroups = $currentYearGroups ?? [];
                        if ($adminCurrentYear !== null) {
                            $curGroups = [];
                            foreach (($groupOptions ?? students_group_options($pdo ?? null)) as $g) {
                                if ((int) $g['academic_year_id'] === (int) $adminCurrentYear['id']) {
                                    $curGroups[] = $g;
                                }
                            }
                        }
                    ?>
                    <select class="pzk-field__input" name="group_id"
                            style="max-width:180px; align-self:flex-start;">
                        <option value="0">— не выбрана —</option>
                        <?php foreach ($curGroups as $g): ?>
                            <option value="<?= (int) $g['id'] ?>">
                                <?= e($g['name']) ?><?= $adminCurrentYear !== null ? ' (текущий год)' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($adminCurrentYear === null): ?>
                        <span class="pzk-form__hint" style="margin-top:4px;">
                            Текущий учебный год не задан — группы недоступны. Назначьте текущий год в разделе «Учебный год».
                        </span>
                    <?php elseif ($curGroups === []): ?>
                        <span class="pzk-form__hint" style="margin-top:4px;">
                            В текущем году нет групп — создайте их в разделе «Группы».
                        </span>
                    <?php endif; ?>
                </label>
            </div>
            <label class="pzk-field">
                <span class="pzk-field__label">Студенты</span>
                <textarea class="pzk-field__textarea" name="students" rows="12" required
                          placeholder="Иванов Иван Иванович, ivanov&#10;Петров Пётр, petrov&#10;&#10;Сидоров Сидор, sidorov"></textarea>
            </label>
            <div class="pzk-form__actions">
                <button type="submit" class="pzk-btn pzk-btn--primary">Добавить студентов</button>
            </div>
        </form>
    </details>

    <script>
    (function () {
        'use strict';

        // Фильтрация групп по выбранному году — только для формы создания.
        // У формы редактирования поля «Учебный год» больше нет, поэтому в
        // ней показываются все группы всех годов.
        document.querySelectorAll('.pzk-student-form').forEach(function (form) {
            var yearSel  = form.querySelector('.pzk-student-year');
            var groupSel = form.querySelector('select[name="group_id"]');
            if (!yearSel || !groupSel) return;

            function filterByYear() {
                var year = yearSel.value;
                var current = groupSel.value;
                var stillValid = (current === '0');
                groupSel.querySelectorAll('option').forEach(function (opt) {
                    if (opt.value === '0') return;
                    var ok = opt.getAttribute('data-year') === year;
                    opt.hidden = !ok;
                    opt.disabled = !ok;
                    if (ok && opt.value === current) stillValid = true;
                });
                if (!stillValid) groupSel.value = '0';
            }

            yearSel.addEventListener('change', filterByYear);
            filterByYear();
        });

        var bulkForm = document.getElementById('bulk-students-form');
        if (bulkForm) {
            bulkForm.addEventListener('submit', function (e) {
                var groupSel = bulkForm.querySelector('select[name="group_id"]');
                var studentsField = bulkForm.querySelector('textarea[name="students"]');
                if (!groupSel || groupSel.value === '0' || groupSel.value === '') {
                    e.preventDefault();
                    alert('Выберите группу');
                    if (groupSel) groupSel.focus();
                    return false;
                }
                if (!studentsField || studentsField.value.trim() === '') {
                    e.preventDefault();
                    alert('Введите хотя бы одного студента');
                    if (studentsField) studentsField.focus();
                    return false;
                }
                return true;
            });
        }
    })();
    </script>
</section>