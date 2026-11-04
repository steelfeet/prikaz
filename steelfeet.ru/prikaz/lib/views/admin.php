<?php
declare(strict_types=1);

$section = $_GET['section'] ?? 'academic_years';
if (!in_array($section, ['academic_years', 'groups', 'subjects', 'curriculum', 'users', 'students', 'parents', 'lessons', 'projects', 'audit'], true)) {
    $section = 'academic_years';
}
$flash = flash_get();

$typesMeta = project_types_meta();
?>
<div class="workspace workspace--prikaz">
    <?php if ($flash !== null): ?>
        <div class="notification notification--<?= $flash['type'] === 'success' ? 'success' : 'danger' ?>">
            <div class="notification__body"><?= e($flash['message']) ?></div>
        </div>
    <?php endif; ?>

    <?php if ($section === 'academic_years'): ?>
        <section class="panel">
            <div class="panel__head">
                <div>
                    <h2 class="panel__title">Учебный год</h2>
                    <p class="panel__subtitle">
                        Текущий учебный год — один на всю систему.
                        <?php if ($adminCurrentYear !== null): ?>
                            Сейчас: <strong><?= e($adminCurrentYear['name']) ?></strong>.
                        <?php endif; ?>
                    </p>
                </div>
                <a href="#add-academic-year" class="pzk-btn pzk-btn--primary" data-open-details>
                    ➕ Добавить
                </a>
            </div>

            <?php if ($adminAcademicYears === []): ?>
                <div class="uni-empty">Учебных годов пока нет. Добавьте первый.</div>
            <?php else: ?>
                <div class="pzk-table-wrap">
                    <table class="pzk-table">
                        <thead>
                            <tr>
                                <th style="width:60px;">ID</th>
                                <th>Название</th>
                                <th style="width:150px;">Начало</th>
                                <th style="width:150px;">Окончание</th>
                                <th style="width:130px;">Текущий</th>
                                <th style="width:340px;">Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($adminAcademicYears as $ay): ?>
                                <tr>
                                    <td><?= (int) $ay['id'] ?></td>
                                    <td><strong><?= e($ay['name']) ?></strong></td>
                                    <td><?= e($ay['start_date']) ?></td>
                                    <td><?= e($ay['end_date']) ?></td>
                                    <td>
                                        <?php if ((int) $ay['is_current'] === 1): ?>
                                            <span class="pzk-status pzk-status--active">текущий</span>
                                        <?php else: ?>
                                            <span style="color:var(--color-tertiary);">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="pzk-table__actions">
                                        <details class="pzk-row-details">
                                            <summary class="pzk-btn pzk-btn--sm">Редактировать</summary>
                                            <form method="post" action="index.php?action=admin_academic_year_save"
                                                  class="pzk-edit-form" autocomplete="off">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="id" value="<?= (int) $ay['id'] ?>">
                                                <label class="pzk-field">
                                                    <span class="pzk-field__label">Название</span>
                                                    <input type="text" name="name" value="<?= e($ay['name']) ?>"
                                                           required autocomplete="off"
                                                           class="pzk-field__input pzk-field__input--sm">
                                                </label>
                                                <label class="pzk-field">
                                                    <span class="pzk-field__label">Начало</span>
                                                    <input type="date" name="start_date" value="<?= e($ay['start_date']) ?>"
                                                           required autocomplete="off"
                                                           class="pzk-field__input pzk-field__input--sm">
                                                </label>
                                                <label class="pzk-field">
                                                    <span class="pzk-field__label">Окончание</span>
                                                    <input type="date" name="end_date" value="<?= e($ay['end_date']) ?>"
                                                           required autocomplete="off"
                                                           class="pzk-field__input pzk-field__input--sm">
                                                </label>
                                                <label class="pzk-checkbox">
                                                    <input type="checkbox" name="is_current" value="1"
                                                           <?= (int) $ay['is_current'] === 1 ? 'checked' : '' ?>>
                                                    Текущий
                                                </label>
                                                <button type="submit" class="pzk-btn pzk-btn--sm pzk-btn--primary">Сохранить</button>
                                            </form>
                                        </details>
                                        <?php if ((int) $ay['is_current'] !== 1): ?>
                                            <form method="post" action="index.php?action=admin_academic_year_set_current">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="id" value="<?= (int) $ay['id'] ?>">
                                                <button type="submit" class="pzk-btn pzk-btn--sm">Сделать текущим</button>
                                            </form>
                                            <form method="post" action="index.php?action=admin_academic_year_delete"
                                                  onsubmit="return confirm('Удалить учебный год «<?= e($ay['name']) ?>»? Все группы этого года тоже будут удалены.');">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="id" value="<?= (int) $ay['id'] ?>">
                                                <button type="submit" class="pzk-btn pzk-btn--sm">Удалить</button>
                                            </form>
                                        <?php else: ?>
                                            <span class="pzk-hint-inline" title="Нельзя удалить текущий учебный год">
                                                🔒 удаление недоступно
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <details class="pzk-details" id="add-academic-year" style="margin-top: var(--space-lg);">
                <summary class="pzk-details__summary">➕ Добавить учебный год</summary>
                <form method="post" action="index.php?action=admin_academic_year_save"
                      class="pzk-form" autocomplete="off">
                    <?= csrf_field() ?>
                    <div>
                        <h3 class="pzk-form__title">Новый учебный год</h3>
                        <p class="pzk-form__hint">
                            Название в формате «2025/2026». Текущий год — один на всю систему:
                            назначение нового автоматически снимает флаг с предыдущего.
                        </p>
                    </div>
                    <div class="pzk-form__grid">
                        <label class="pzk-field">
                            <span class="pzk-field__label">Название</span>
                            <input class="pzk-field__input" type="text" name="name" required
                                   placeholder="2025/2026" autocomplete="off">
                        </label>
                        <label class="pzk-field">
                            <span class="pzk-field__label">Дата начала</span>
                            <input class="pzk-field__input" type="date" name="start_date" required
                                   autocomplete="off" value="<?= date('Y') ?>-09-01">
                        </label>
                        <label class="pzk-field">
                            <span class="pzk-field__label">Дата окончания</span>
                            <input class="pzk-field__input" type="date" name="end_date" required
                                   autocomplete="off" value="<?= (date('Y') + 1) ?>-06-30">
                        </label>
                    </div>
                    <label class="pzk-checkbox">
                        <input type="checkbox" name="is_current" value="1">
                        Сделать текущим учебным годом
                    </label>
                    <div class="pzk-form__actions">
                        <button type="submit" class="pzk-btn pzk-btn--primary">Создать учебный год</button>
                    </div>
                </form>
            </details>
        </section>

    <?php elseif ($section === 'groups'): ?>
        <section class="panel">
            <div class="panel__head">
                <div>
                    <h2 class="panel__title">Группы</h2>
                    <p class="panel__subtitle">
                        Каждый учебный год — свой набор групп.
                        <?php if ($adminCurrentYear !== null): ?>
                            В текущем году <strong><?= e($adminCurrentYear['name']) ?></strong>
                            <?php
                            $currentYearGroupCount = 0;
                            foreach ($adminGroups as $g) {
                                if ((int) $g['academic_year_id'] === (int) $adminCurrentYear['id']) { $currentYearGroupCount++; }
                            }
                            ?>
                            групп: <strong><?= $currentYearGroupCount ?></strong>.
                        <?php endif; ?>
                    </p>
                </div>
                <a href="#add-group" class="pzk-btn pzk-btn--primary" data-open-details>
                    ➕ Добавить
                </a>
            </div>

            <?php if ($adminAcademicYears === []): ?>
                <div class="uni-empty">
                    Сначала создайте хотя бы один учебный год — в разделе «Учебный год».
                </div>
            <?php else: ?>
                <form method="get" action="index.php" class="pzk-inline-form" autocomplete="off"
                      style="margin-bottom: var(--space-md);">
                    <input type="hidden" name="page" value="admin">
                    <input type="hidden" name="section" value="groups">
                    <label>Учебный год:
                        <select name="group_year" class="pzk-field__input" onchange="this.form.submit();">
                            <option value="0" <?= $adminGroupsFilterYear === 0 ? 'selected' : '' ?>>— все —</option>
                            <?php foreach ($adminAcademicYears as $ay): ?>
                                <option value="<?= (int) $ay['id'] ?>"
                                        <?= (int) $adminGroupsFilterYear === (int) $ay['id'] ? 'selected' : '' ?>>
                                    <?= e($ay['name']) ?><?= (int) $ay['is_current'] === 1 ? ' (текущий)' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </form>

                <?php if ($adminGroups === []): ?>
                    <div class="uni-empty">
                        <?php if ($adminGroupsFilterYear > 0): ?>
                            В выбранном учебном году групп пока нет. Добавьте первую.
                        <?php else: ?>
                            Групп пока нет. Добавьте первую.
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="pzk-table-wrap">
                        <table class="pzk-table">
                            <thead>
                                <tr>
                                    <th style="width:60px;">ID</th>
                                    <th style="width:200px;">Учебный год</th>
                                    <th>Название</th>
                                    <th style="width:180px;">Куратор</th>
                                    <th>Заметки</th>
                                    <th style="width:280px;">Действия</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($adminGroups as $g): ?>
                                    <tr>
                                        <td><?= (int) $g['id'] ?></td>
                                        <td>
                                            <strong><?= e($g['year_name']) ?></strong>
                                            <?php if ((int) $g['year_is_current'] === 1): ?>
                                                <br><span class="pzk-status pzk-status--active" style="margin-top:4px;">текущий</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><strong><?= e($g['name']) ?></strong></td>
                                        <td>
                                            <?php if (!empty($g['curator'])): ?>
                                                <?= e($g['curator']) ?>
                                            <?php else: ?>
                                                <span style="color:var(--color-tertiary);">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="font-size:0.82rem; color:var(--color-tertiary);">
                                            <?= e((string) ($g['notes'] ?? '')) ?>
                                        </td>
                                        <td class="pzk-table__actions">
                                            <details class="pzk-row-details">
                                                <summary class="pzk-btn pzk-btn--sm">Редактировать</summary>
                                                <form method="post" action="index.php?action=admin_group_save"
                                                      class="pzk-edit-form" autocomplete="off">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="id" value="<?= (int) $g['id'] ?>">
                                                    <label class="pzk-field">
                                                        <span class="pzk-field__label">Название</span>
                                                        <input type="text" name="name" value="<?= e($g['name']) ?>"
                                                               required autocomplete="off"
                                                               class="pzk-field__input pzk-field__input--sm">
                                                    </label>
                                                    <label class="pzk-field">
                                                        <span class="pzk-field__label">Учебный год</span>
                                                        <select name="academic_year_id" required
                                                                class="pzk-field__input pzk-field__input--sm">
                                                            <?php foreach ($adminAcademicYears as $ay): ?>
                                                                <option value="<?= (int) $ay['id'] ?>"
                                                                        <?= (int) $ay['id'] === (int) $g['academic_year_id'] ? 'selected' : '' ?>>
                                                                    <?= e($ay['name']) ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </label>
                                                    <label class="pzk-field">
                                                        <span class="pzk-field__label">Куратор</span>
                                                        <input type="text" name="curator"
                                                               value="<?= e((string) ($g['curator'] ?? '')) ?>"
                                                               autocomplete="off"
                                                               class="pzk-field__input pzk-field__input--sm">
                                                    </label>
                                                    <label class="pzk-field">
                                                        <span class="pzk-field__label">Заметки</span>
                                                        <input type="text" name="notes"
                                                               value="<?= e((string) ($g['notes'] ?? '')) ?>"
                                                               autocomplete="off"
                                                               class="pzk-field__input pzk-field__input--sm">
                                                    </label>
                                                    <button type="submit" class="pzk-btn pzk-btn--sm pzk-btn--primary">Сохранить</button>
                                                </form>
                                            </details>
                                            <form method="post" action="index.php?action=admin_group_delete"
                                                  onsubmit="return confirm('Удалить группу «<?= e($g['name']) ?>» (<?= e($g['year_name']) ?>)? Студенты, привязанные к ней, останутся, но группа исчезнет из списка.');">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="id" value="<?= (int) $g['id'] ?>">
                                                <button type="submit" class="pzk-btn pzk-btn--sm">Удалить</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <details class="pzk-details" id="add-group" style="margin-top: var(--space-lg);">
                    <summary class="pzk-details__summary">➕ Добавить группу</summary>
                    <form method="post" action="index.php?action=admin_group_save"
                          class="pzk-form" autocomplete="off">
                        <?= csrf_field() ?>
                        <div>
                            <h3 class="pzk-form__title">Новая группа</h3>
                            <p class="pzk-form__hint">
                                Группа принадлежит одному учебному году. В разных годах могут
                                быть группы с одинаковыми названиями — это разные сущности.
                                Название группы в году должно быть уникальным.
                            </p>
                        </div>
                        <div class="pzk-form__grid">
                            <label class="pzk-field">
                                <span class="pzk-field__label">Название группы</span>
                                <input class="pzk-field__input" type="text" name="name" required
                                       placeholder="46" autocomplete="off">
                            </label>
                            <label class="pzk-field">
                                <span class="pzk-field__label">Учебный год</span>
                                <select class="pzk-field__input" name="academic_year_id" required>
                                    <?php foreach ($adminAcademicYears as $ay): ?>
                                        <option value="<?= (int) $ay['id'] ?>"
                                                <?= (int) $ay['id'] === (int) $defaultGroupYearId ? 'selected' : '' ?>>
                                            <?= e($ay['name']) ?><?= (int) $ay['is_current'] === 1 ? ' (текущий)' : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label class="pzk-field">
                                <span class="pzk-field__label">Куратор (опционально)</span>
                                <input class="pzk-field__input" type="text" name="curator"
                                       placeholder="Иванов Иван Иванович" autocomplete="off">
                            </label>
                            <label class="pzk-field">
                                <span class="pzk-field__label">Заметки (опционально)</span>
                                <input class="pzk-field__input" type="text" name="notes"
                                       placeholder="Например: специальность 10.02.05" autocomplete="off">
                            </label>
                        </div>
                        <div class="pzk-form__actions">
                            <button type="submit" class="pzk-btn pzk-btn--primary">Создать группу</button>
                        </div>
                    </form>
                </details>
            <?php endif; ?>
        </section>

    <?php elseif ($section === 'subjects'): ?>
        <section class="panel">
            <div class="panel__head">
                <div>
                    <h2 class="panel__title">Предметы</h2>
                    <p class="panel__subtitle">
                        Каждый учебный год — свой набор предметов. Предмет привязан к группе,
                        у каждого семестра или практики — свой предмет.
                        <?php if ($adminCurrentYear !== null): ?>
                            В текущем году <strong><?= e($adminCurrentYear['name']) ?></strong>
                            предметов: <strong><?= $adminCurrentYearSubjectCount ?></strong>.
                        <?php endif; ?>
                    </p>
                </div>
                <?php if ($adminAcademicYears !== [] && $adminAllGroups !== []): ?>
                    <a href="#add-subject" class="pzk-btn pzk-btn--primary" data-open-details>
                        ➕ Добавить
                    </a>
                <?php endif; ?>
            </div>

            <?php if ($adminAcademicYears === []): ?>
                <div class="uni-empty">
                    Сначала создайте хотя бы один учебный год — в разделе «Учебный год».
                </div>
            <?php elseif ($adminAllGroups === []): ?>
                <div class="uni-empty">
                    Сначала создайте хотя бы одну группу — в разделе «Группы».
                </div>
            <?php else: ?>
                <form method="get" action="index.php" class="pzk-inline-form" autocomplete="off"
                      style="margin-bottom: var(--space-md);">
                    <input type="hidden" name="page" value="admin">
                    <input type="hidden" name="section" value="subjects">
                    <label>Учебный год:
                        <select name="subject_year" class="pzk-field__input" onchange="this.form.submit();">
                            <option value="0" <?= $adminSubjectsFilterYear === 0 ? 'selected' : '' ?>>— все —</option>
                            <?php foreach ($adminAcademicYears as $ay): ?>
                                <option value="<?= (int) $ay['id'] ?>"
                                        <?= (int) $adminSubjectsFilterYear === (int) $ay['id'] ? 'selected' : '' ?>>
                                    <?= e($ay['name']) ?><?= (int) $ay['is_current'] === 1 ? ' (текущий)' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Группа:
                        <select name="subject_group" class="pzk-field__input" onchange="this.form.submit();">
                            <option value="0" <?= $adminSubjectsFilterGroup === 0 ? 'selected' : '' ?>>— все —</option>
                            <?php foreach ($adminAllGroups as $g): ?>
                                <?php if ($adminSubjectsFilterYear > 0
                                          && (int) $g['academic_year_id'] !== $adminSubjectsFilterYear) continue; ?>
                                    <option value="<?= (int) $g['id'] ?>"
                                            <?= (int) $adminSubjectsFilterGroup === (int) $g['id'] ? 'selected' : '' ?>>
                                        <?= e($g['name']) ?> · <?= e($g['year_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                        </select>
                    </label>
                </form>

                <?php if ($adminSubjects === []): ?>
                    <div class="uni-empty">
                        <?php if ($adminSubjectsFilterGroup > 0): ?>
                            У выбранной группы предметов в этом году пока нет. Добавьте первый.
                        <?php elseif ($adminSubjectsFilterYear > 0): ?>
                            В выбранном учебном году предметов пока нет. Добавьте первый.
                        <?php else: ?>
                            Предметов пока нет. Добавьте первый.
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="pzk-table-wrap">
                        <table class="pzk-table">
                            <thead>
                                <tr>
                                    <th style="width:60px;">ID</th>
                                    <th style="width:150px;">Учебный год</th>
                                    <th style="width:90px;">Группа</th>
                                    <th style="width:130px;">Краткое</th>
                                    <th>Полное название</th>
                                    <th style="width:90px;">Часов</th>
                                    <th style="width:170px;">Семестр / Практика</th>
                                    <th style="width:280px;">Действия</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($adminSubjects as $s): ?>
                                    <tr>
                                        <td><?= (int) $s['id'] ?></td>
                                        <td>
                                            <strong><?= e($s['year_name']) ?></strong>
                                            <?php if ((int) $s['year_is_current'] === 1): ?>
                                                <br><span class="pzk-status pzk-status--active" style="margin-top:4px;">текущий</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><strong><?= e($s['group_name']) ?></strong></td>
                                        <td><strong><?= e($s['short_name']) ?></strong></td>
                                        <td><?= e($s['full_name']) ?></td>
                                        <td><?= (int) $s['hours'] ?></td>
                                        <td style="font-size:0.82rem; color:var(--color-tertiary);">
                                            <?php if ($s['semester_practice'] !== ''): ?>
                                                <?= e($s['semester_practice']) ?>
                                            <?php else: ?>
                                                —
                                            <?php endif; ?>
                                        </td>
                                        <td class="pzk-table__actions">
                                            <details class="pzk-row-details">
                                                <summary class="pzk-btn pzk-btn--sm">Редактировать</summary>
                                                <form method="post" action="index.php?action=admin_subject_save"
                                                      class="pzk-edit-form pzk-subject-form" autocomplete="off">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                                                    <label class="pzk-field">
                                                        <span class="pzk-field__label">Учебный год</span>
                                                        <select name="academic_year_id" required
                                                                class="pzk-field__input pzk-field__input--sm">
                                                            <?php foreach ($adminAcademicYears as $ay): ?>
                                                                <option value="<?= (int) $ay['id'] ?>"
                                                                        <?= (int) $ay['id'] === (int) $s['academic_year_id'] ? 'selected' : '' ?>>
                                                                    <?= e($ay['name']) ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </label>
                                                    <label class="pzk-field">
                                                        <span class="pzk-field__label">Группа</span>
                                                        <select name="group_id" required
                                                                class="pzk-field__input pzk-field__input--sm">
                                                            <?php foreach ($adminAllGroups as $g): ?>
                                                                <option value="<?= (int) $g['id'] ?>"
                                                                        data-year="<?= (int) $g['academic_year_id'] ?>"
                                                                        <?= (int) $g['id'] === (int) $s['group_id'] ? 'selected' : '' ?>>
                                                                    <?= e($g['name']) ?> · <?= e($g['year_name']) ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </label>
                                                    <label class="pzk-field">
                                                        <span class="pzk-field__label">Краткое название</span>
                                                        <input type="text" name="short_name" value="<?= e($s['short_name']) ?>"
                                                               required autocomplete="off"
                                                               class="pzk-field__input pzk-field__input--sm">
                                                    </label>
                                                    <label class="pzk-field">
                                                        <span class="pzk-field__label">Полное название</span>
                                                        <input type="text" name="full_name" value="<?= e($s['full_name']) ?>"
                                                               required autocomplete="off"
                                                               class="pzk-field__input pzk-field__input--sm">
                                                    </label>
                                                    <label class="pzk-field">
                                                        <span class="pzk-field__label">Количество часов</span>
                                                        <input type="number" name="hours" value="<?= (int) $s['hours'] ?>"
                                                               required min="0" step="1" autocomplete="off"
                                                               class="pzk-field__input pzk-field__input--sm">
                                                    </label>
                                                    <label class="pzk-field">
                                                        <span class="pzk-field__label">Семестр / Практика</span>
                                                        <input type="text" name="semester_practice"
                                                               value="<?= e((string) $s['semester_practice']) ?>"
                                                               autocomplete="off"
                                                               class="pzk-field__input pzk-field__input--sm">
                                                    </label>
                                                    <button type="submit" class="pzk-btn pzk-btn--sm pzk-btn--primary">Сохранить</button>
                                                </form>
                                            </details>
                                            <form method="post" action="index.php?action=admin_subject_delete"
                                                  onsubmit="return confirm('Удалить предмет «<?= e($s['short_name']) ?>» (<?= e($s['group_name']) ?>, <?= e($s['year_name']) ?>)?');">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                                                <button type="submit" class="pzk-btn pzk-btn--sm">Удалить</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <details class="pzk-details" id="add-subject" style="margin-top: var(--space-lg);">
                    <summary class="pzk-details__summary">➕ Добавить предмет</summary>
                    <form method="post" action="index.php?action=admin_subject_save"
                          class="pzk-form pzk-subject-form" autocomplete="off">
                        <?= csrf_field() ?>
                        <div>
                            <h3 class="pzk-form__title">Новый предмет</h3>
                            <p class="pzk-form__hint">
                                Предмет принадлежит одному учебному году и одной группе этого года.
                                Каждому семестру или практике — отдельная запись: разбивать год на семестры не нужно,
                                просто у каждой части свой предмет.
                            </p>
                        </div>
                        <div class="pzk-form__grid">
                            <label class="pzk-field">
                                <span class="pzk-field__label">Учебный год</span>
                                <select class="pzk-field__input" name="academic_year_id" required>
                                    <?php foreach ($adminAcademicYears as $ay): ?>
                                        <option value="<?= (int) $ay['id'] ?>"
                                                <?= (int) $ay['id'] === (int) $defaultSubjectYearId ? 'selected' : '' ?>>
                                            <?= e($ay['name']) ?><?= (int) $ay['is_current'] === 1 ? ' (текущий)' : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label class="pzk-field">
                                <span class="pzk-field__label">Группа</span>
                                <select class="pzk-field__input" name="group_id" required>
                                    <option value="">— выберите группу —</option>
                                    <?php foreach ($adminAllGroups as $g): ?>
                                        <option value="<?= (int) $g['id'] ?>"
                                                data-year="<?= (int) $g['academic_year_id'] ?>"
                                                <?= (int) $g['id'] === (int) $defaultSubjectGroupId ? 'selected' : '' ?>>
                                            <?= e($g['name']) ?> · <?= e($g['year_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label class="pzk-field">
                                <span class="pzk-field__label">Краткое название</span>
                                <input class="pzk-field__input" type="text" name="short_name" required
                                       placeholder="Математика" autocomplete="off">
                            </label>
                            <label class="pzk-field">
                                <span class="pzk-field__label">Полное название</span>
                                <input class="pzk-field__input" type="text" name="full_name" required
                                       placeholder="Математика: дискретная математика" autocomplete="off">
                            </label>
                            <label class="pzk-field">
                                <span class="pzk-field__label">Количество часов</span>
                                <input class="pzk-field__input" type="number" name="hours" min="0" step="1"
                                       autocomplete="off" placeholder="72">
                            </label>
                            <label class="pzk-field">
                                <span class="pzk-field__label">Семестр / Практика</span>
                                <input class="pzk-field__input" type="text" name="semester_practice"
                                       placeholder="1 семестр / производственная практика" autocomplete="off">
                            </label>
                        </div>
                        <div class="pzk-form__actions">
                            <button type="submit" class="pzk-btn pzk-btn--primary">Создать предмет</button>
                        </div>
                    </form>
                </details>

                <script>
                (function () {
                    'use strict';
                    // В формах предмета список групп ограничивается выбранным учебным годом.
                    document.querySelectorAll('.pzk-subject-form').forEach(function (form) {
                        var yearSel  = form.querySelector('select[name="academic_year_id"]');
                        var groupSel = form.querySelector('select[name="group_id"]');
                        if (!yearSel || !groupSel) return;

                        function syncGroups() {
                            var year = yearSel.value;
                            var current = groupSel.value;
                            var stillValid = false;
                            groupSel.querySelectorAll('option').forEach(function (opt) {
                                if (opt.value === '') return;              // служебная «— выберите группу —»
                                var ok = opt.getAttribute('data-year') === year;
                                opt.hidden = !ok;
                                opt.disabled = !ok;
                                if (ok && opt.value === current) stillValid = true;
                            });
                            groupSel.value = stillValid ? current : '';
                        }

                        yearSel.addEventListener('change', syncGroups);
                        syncGroups();
                    });
                })();
                </script>
            <?php endif; ?>
        </section>

    <?php elseif ($section === 'curriculum'): ?>
        <section class="panel">
            <div class="panel__head">
                <div>
                    <h2 class="panel__title">Учебный план</h2>
                    <p class="panel__subtitle">Раздел в разработке.</p>
                </div>
            </div>
            <div class="uni-empty">
                Функционал появится в следующей версии. Пока уроки ведутся через раздел «Уроки».
            </div>
        </section>

    <?php elseif ($section === 'users'): ?>
        <section class="panel">
            <div class="panel__head">
                <div>
                    <h2 class="panel__title">Пользователи</h2>
                    <p class="panel__subtitle">Создание, деактивация и сброс паролей. Регистрация — только через админку.</p>
                </div>
                <a href="#add-user" class="pzk-btn pzk-btn--primary" data-open-details>
                    ➕ Добавить
                </a>
            </div>

            <div class="pzk-table-wrap">
                <table class="pzk-table">
                    <thead>
                        <tr>
                            <th style="width:60px;">ID</th>
                            <th style="width:140px;">Логин</th>
                            <th>ФИО</th>
                            <th style="width:120px;">Роли</th>
                            <th style="width:130px;">Статус</th>
                            <th style="width:160px;">Последний вход</th>
                            <th style="width:280px;">Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($adminUsers as $u): ?>
                            <tr>
                                <td><?= (int) $u['id'] ?></td>
                                <td><strong><?= e($u['login']) ?></strong></td>
                                <td><?= e($u['full_name']) ?></td>
                                <td><?= e((string) ($u['roles'] ?? '—')) ?></td>
                                <td>
                                    <?php if ((int) $u['is_active'] === 1): ?>
                                        <span class="pzk-status pzk-status--active">активен</span>
                                    <?php else: ?>
                                        <span class="pzk-status pzk-status--inactive">заблокирован</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= e((string) ($u['last_login_at'] ?? '—')) ?></td>
                                <td class="pzk-table__actions">
                                    <?php if ((int) $u['id'] !== (int) $currentUser['id']): ?>
                                        <form method="post" action="index.php?action=admin_user_toggle">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                                            <button type="submit" class="pzk-btn pzk-btn--sm">
                                                <?= (int) $u['is_active'] === 1 ? 'Заблокировать' : 'Разблокировать' ?>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <details class="pzk-row-details">
                                        <summary class="pzk-btn pzk-btn--sm">Сбросить пароль</summary>
                                        <form method="post" action="index.php?action=admin_user_reset_password"
                                              class="pzk-row-details__form" autocomplete="off">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                                            <input type="text" name="password" placeholder="Новый пароль"
                                                   required minlength="<?= PRIKAZ_PASSWORD_MIN ?>"
                                                   autocomplete="new-password" class="pzk-field__input">
                                            <button type="submit" class="pzk-btn pzk-btn--sm pzk-btn--primary">ОК</button>
                                        </form>
                                    </details>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <details class="pzk-details" id="add-user" style="margin-top: var(--space-lg);">
                <summary class="pzk-details__summary">➕ Создать пользователя</summary>
                <form method="post" action="index.php?action=admin_user_create"
                      class="pzk-form" autocomplete="off">
                    <?= csrf_field() ?>
                    <div>
                        <h3 class="pzk-form__title">Новый пользователь</h3>
                        <p class="pzk-form__hint">
                            Логин — латиница, цифры, точка, дефис. Передайте пользователю логин и пароль:
                            после первого входа он сможет сменить пароль в «Профиле».
                        </p>
                    </div>
                    <div class="pzk-form__grid">
                        <label class="pzk-field">
                            <span class="pzk-field__label">Логин</span>
                            <input class="pzk-field__input" type="text" name="login" required
                                   pattern="[a-zA-Z0-9_.-]{3,64}"
                                   placeholder="ivanov" autocomplete="off"
                                   autocapitalize="off" spellcheck="false">
                        </label>
                        <label class="pzk-field">
                            <span class="pzk-field__label">ФИО</span>
                            <input class="pzk-field__input" type="text" name="full_name" required
                                   placeholder="Иванов Иван Иванович" autocomplete="off">
                        </label>
                        <label class="pzk-field">
                            <span class="pzk-field__label">Email</span>
                            <input class="pzk-field__input" type="email" name="email"
                                   placeholder="ivanov@example.ru" autocomplete="off">
                        </label>
                        <label class="pzk-field">
                            <span class="pzk-field__label">Роль</span>
                            <select class="pzk-field__input" name="role" required>
                                <option value="student">Студент</option>
                                <option value="teacher">Преподаватель</option>
                                <option value="parent">Родитель</option>
                                <option value="admin">Администратор</option>
                            </select>
                        </label>
                        <label class="pzk-field">
                            <span class="pzk-field__label">Группа (для студента)</span>
                            <input class="pzk-field__input" type="text" name="group_name"
                                   placeholder="46" autocomplete="off">
                        </label>
                        <label class="pzk-field">
                            <span class="pzk-field__label">Псевдоним (для студента)</span>
                            <input class="pzk-field__input" type="text" name="alias"
                                   placeholder="s-46-01" autocomplete="off"
                                   autocapitalize="off" spellcheck="false">
                        </label>
                        <label class="pzk-field">
                            <span class="pzk-field__label">Временный пароль</span>
                            <input class="pzk-field__input" type="text" name="password" required
                                   minlength="<?= PRIKAZ_PASSWORD_MIN ?>"
                                   autocomplete="new-password"
                                   placeholder="не короче <?= PRIKAZ_PASSWORD_MIN ?> символов">
                        </label>
                    </div>
                    <div class="pzk-form__actions">
                        <button type="submit" class="pzk-btn pzk-btn--primary">Создать пользователя</button>
                    </div>
                </form>
            </details>
        </section>

    <?php elseif ($section === 'students'): ?>
        <section class="panel">
            <div class="panel__head">
                <div>
                    <h2 class="panel__title">Студенты</h2>
                    <p class="panel__subtitle">Список студентов и их псевдонимов. Псевдоним используется в репозиториях — расшифровка только здесь.</p>
                </div>
            </div>
            <div class="pzk-table-wrap">
                <table class="pzk-table">
                    <thead>
                        <tr>
                            <th style="width:60px;">ID</th>
                            <th>ФИО</th>
                            <th style="width:100px;">Группа</th>
                            <th style="width:160px;">Псевдоним</th>
                            <th style="width:100px;">User ID</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($adminStudents as $s): ?>
                            <tr>
                                <td><?= (int) $s['id'] ?></td>
                                <td><?= e($s['full_name']) ?></td>
                                <td><?= e($s['group_name']) ?></td>
                                <td>
                                    <?php if (!empty($s['alias'])): ?>
                                        <code><?= e($s['alias']) ?></code>
                                    <?php else: ?>
                                        <span style="color:var(--color-tertiary);">—</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= (int) $s['user_id'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

    <?php elseif ($section === 'parents'): ?>
        <section class="panel">
            <div class="panel__head">
                <div>
                    <h2 class="panel__title">Родители</h2>
                    <p class="panel__subtitle">Привязка родителя к студенту. Родитель видит только своего ребёнка.</p>
                </div>
            </div>

            <form method="post" action="index.php?action=admin_parent_link"
                  class="pzk-inline-form" autocomplete="off"
                  style="margin-bottom: var(--space-md);">
                <?= csrf_field() ?>
                <label>Родитель:
                    <select name="parent_id" class="pzk-field__input">
                        <?php foreach ($adminParents as $p): ?>
                            <option value="<?= (int) $p['id'] ?>"><?= e($p['full_name']) ?> (#<?= (int) $p['id'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Студент:
                    <select name="student_id" class="pzk-field__input">
                        <?php foreach ($adminStudents as $s): ?>
                            <option value="<?= (int) $s['id'] ?>"><?= e($s['full_name']) ?> · <?= e($s['group_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <button type="submit" class="pzk-btn pzk-btn--primary">Привязать</button>
            </form>

            <div class="pzk-table-wrap">
                <table class="pzk-table">
                    <thead>
                        <tr>
                            <th>Родитель</th>
                            <th>Студент</th>
                            <th style="width:140px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($adminParentLinks as $l): ?>
                            <?php
                            $pName = $sName = '';
                            foreach ($adminParents as $p) {
                                if ((int) $p['id'] === (int) $l['parent_id']) { $pName = $p['full_name']; break; }
                            }
                            foreach ($adminStudents as $s) {
                                if ((int) $s['id'] === (int) $l['student_id']) { $sName = $s['full_name']; break; }
                            }
                            ?>
                            <tr>
                                <td><?= e($pName) ?></td>
                                <td><?= e($sName) ?></td>
                                <td class="pzk-table__actions">
                                    <form method="post" action="index.php?action=admin_parent_unlink">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="parent_id" value="<?= (int) $l['parent_id'] ?>">
                                        <input type="hidden" name="student_id" value="<?= (int) $l['student_id'] ?>">
                                        <button type="submit" class="pzk-btn pzk-btn--sm">Отвязать</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($adminParentLinks === []): ?>
                            <tr><td colspan="3" class="pzk-table__empty">Пока ни одна связка не создана.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

    <?php elseif ($section === 'lessons'): ?>
        <section class="panel">
            <div class="panel__head">
                <div>
                    <h2 class="panel__title">Уроки</h2>
                    <p class="panel__subtitle">Темы, даты и домашние задания. Порядковый номер — уникален.</p>
                </div>
                <a href="#add-lesson" class="pzk-btn pzk-btn--primary" data-open-details>
                    ➕ Добавить
                </a>
            </div>

            <div class="pzk-table-wrap">
                <table class="pzk-table pzk-table--lessons">
                    <thead>
                        <tr>
                            <th style="width:70px;">№</th>
                            <th style="width:120px;">Дата</th>
                            <th>Тема</th>
                            <th>ДЗ</th>
                            <th style="width:180px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($adminLessons as $l): ?>
                            <tr>
                                <td colspan="5" class="pzk-table__row-form">
                                    <form method="post" action="index.php?action=admin_lesson_save"
                                          class="pzk-lesson-form" autocomplete="off">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= (int) $l['id'] ?>">
                                        <input class="pzk-field__input pzk-field__input--sm" name="ord" type="number"
                                               value="<?= (int) $l['ord'] ?>" required autocomplete="off">
                                        <input class="pzk-field__input pzk-field__input--sm" name="date_str"
                                               value="<?= e($l['date_str']) ?>" required autocomplete="off">
                                        <input class="pzk-field__input pzk-field__input--sm" name="topic"
                                               value="<?= e($l['topic']) ?>" required autocomplete="off">
                                        <input class="pzk-field__input pzk-field__input--sm" name="homework"
                                               value="<?= e($l['homework']) ?>" autocomplete="off">
                                        <button type="submit" class="pzk-btn pzk-btn--sm pzk-btn--primary">Сохранить</button>
                                    </form>
                                    <form method="post" action="index.php?action=admin_lesson_delete"
                                          class="pzk-lesson-delete"
                                          onsubmit="return confirm('Удалить урок вместе с оценками?');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= (int) $l['id'] ?>">
                                        <button type="submit" class="pzk-btn pzk-btn--sm">Удалить</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($adminLessons === []): ?>
                            <tr><td colspan="5" class="pzk-table__empty">Уроков пока нет. Добавьте первый.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <details class="pzk-details" id="add-lesson" style="margin-top: var(--space-lg);">
                <summary class="pzk-details__summary">➕ Добавить урок</summary>
                <form method="post" action="index.php?action=admin_lesson_save"
                      class="pzk-form" autocomplete="off">
                    <?= csrf_field() ?>
                    <div>
                        <h3 class="pzk-form__title">Новый урок</h3>
                        <p class="pzk-form__hint">
                            Порядковый номер — уникальное число. Дата в свободном формате: 03.09, 12.10 и т.п.
                        </p>
                    </div>
                    <div class="pzk-form__grid">
                        <label class="pzk-field">
                            <span class="pzk-field__label">№</span>
                            <input class="pzk-field__input" type="number" name="ord" required min="1"
                                   autocomplete="off">
                        </label>
                        <label class="pzk-field">
                            <span class="pzk-field__label">Дата</span>
                            <input class="pzk-field__input" type="text" name="date_str" required
                                   placeholder="03.09" autocomplete="off">
                        </label>
                        <label class="pzk-field">
                            <span class="pzk-field__label">Тема</span>
                            <input class="pzk-field__input" type="text" name="topic" required
                                   placeholder="Например: Симметричные шифры" autocomplete="off">
                        </label>
                        <label class="pzk-field">
                            <span class="pzk-field__label">Домашнее задание</span>
                            <input class="pzk-field__input" type="text" name="homework"
                                   placeholder="Что задано к следующему занятию" autocomplete="off">
                        </label>
                    </div>
                    <div class="pzk-form__actions">
                        <button type="submit" class="pzk-btn pzk-btn--primary">Добавить урок</button>
                    </div>
                </form>
            </details>
        </section>

    <?php elseif ($section === 'projects'): ?>
        <section class="panel">
            <div class="panel__head">
                <div>
                    <h2 class="panel__title">Проекты</h2>
                    <p class="panel__subtitle">Создание проектов, назначение участников, тип (оригинал/форк).</p>
                </div>
                <a href="#add-project" class="pzk-btn pzk-btn--primary" data-open-details>
                    ➕ Добавить
                </a>
            </div>

            <?php if ($adminProjects !== []): ?>
                <div class="pzk-table-wrap">
                    <table class="pzk-table">
                        <thead>
                            <tr>
                                <th style="width:60px;">ID</th>
                                <th>Название</th>
                                <th style="width:130px;">Тип</th>
                                <th style="width:120px;">Участники</th>
                                <th style="width:100px;">Фаза</th>
                                <th style="width:100px;">Репозиторий</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($adminProjects as $p): ?>
                                <?php $typeInfo = $typesMeta[$p['type']] ?? ['label' => $p['type'], 'color' => '#8b6914']; ?>
                                <tr>
                                    <td><?= (int) $p['id'] ?></td>
                                    <td><?= e($p['title']) ?></td>
                                    <td>
                                        <span class="project-type-chip" style="--type-color: <?= e($typeInfo['color']) ?>;">
                                            <?= e($typeInfo['label']) ?>
                                        </span>
                                    </td>
                                    <td><?= count($p['students']) ?></td>
                                    <td><?= (int) $p['current_phase'] ?>/4</td>
                                    <td>
                                        <?php if (!empty($p['repo_url'])): ?>
                                            <a href="<?= e($p['repo_url']) ?>" target="_blank" rel="noopener">↗</a>
                                        <?php else: ?>
                                            <span style="color:var(--color-tertiary);">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="uni-empty">Проектов пока нет. Добавьте первый.</div>
            <?php endif; ?>

            <details class="pzk-details" id="add-project" style="margin-top: var(--space-lg);">
                <summary class="pzk-details__summary">➕ Создать проект</summary>
                <form method="post" action="index.php?action=admin_project_create"
                      class="pzk-form" autocomplete="off">
                    <?= csrf_field() ?>
                    <div>
                        <h3 class="pzk-form__title">Новый проект</h3>
                        <p class="pzk-form__hint">
                            Форк — это проект, основанный на другом. Укажите родителя, чтобы
                            отслеживать связь. Ссылку на репозиторий заполняйте, если проект уже создан в Gogs.
                        </p>
                    </div>
                    <div class="pzk-form__grid">
                        <label class="pzk-field">
                            <span class="pzk-field__label">Название</span>
                            <input class="pzk-field__input" type="text" name="title" required
                                   placeholder="Например: Шифр Цезаря с интерфейсом" autocomplete="off">
                        </label>
                        <label class="pzk-field">
                            <span class="pzk-field__label">Тип</span>
                            <select class="pzk-field__input" name="type">
                                <?php foreach ($typesMeta as $code => $meta): ?>
                                    <option value="<?= e($code) ?>"><?= e($meta['label']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="pzk-field">
                            <span class="pzk-field__label">Родительский проект (для форка)</span>
                            <select class="pzk-field__input" name="parent_id">
                                <option value="0">—</option>
                                <?php foreach ($adminProjects as $p): ?>
                                    <option value="<?= (int) $p['id'] ?>">#<?= (int) $p['id'] ?> · <?= e($p['title']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="pzk-field">
                            <span class="pzk-field__label">URL репозитория</span>
                            <input class="pzk-field__input" type="url" name="repo_url"
                                   placeholder="https://gogs.example/..." autocomplete="off">
                        </label>
                    </div>
                    <fieldset class="pzk-fieldset">
                        <legend>Участники</legend>
                        <?php if ($adminStudents === []): ?>
                            <p class="pzk-form__hint" style="margin:0;">
                                Сначала создайте студентов в разделе «Пользователи».
                            </p>
                        <?php else: ?>
                            <div class="pzk-checkbox-grid">
                                <?php foreach ($adminStudents as $s): ?>
                                    <label class="pzk-checkbox">
                                        <input type="checkbox" name="students[]" value="<?= (int) $s['id'] ?>">
                                        <?= e($s['full_name']) ?>
                                        <?php if (!empty($s['alias'])): ?><code>(<?= e($s['alias']) ?>)</code><?php endif; ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </fieldset>
                    <div class="pzk-form__actions">
                        <button type="submit" class="pzk-btn pzk-btn--primary">Создать проект</button>
                    </div>
                </form>
            </details>
        </section>

    <?php elseif ($section === 'audit'): ?>
        <section class="panel">
            <div class="panel__head">
                <div>
                    <h2 class="panel__title">Аудит</h2>
                    <p class="panel__subtitle">Последние 100 действий: логины, оценки, смены фаз, действия админа. С IP и временем.</p>
                </div>
            </div>
            <div class="pzk-table-wrap" style="max-height:600px;">
                <table class="pzk-table pzk-table--audit">
                    <thead>
                        <tr>
                            <th style="width:170px;">Время</th>
                            <th style="width:120px;">Пользователь</th>
                            <th style="width:160px;">Действие</th>
                            <th style="width:160px;">Объект</th>
                            <th>Мета</th>
                            <th style="width:130px;">IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($adminAudit as $a): ?>
                            <tr>
                                <td style="white-space:nowrap;"><?= e($a['created_at']) ?></td>
                                <td><?= e((string) ($a['login'] ?? '—')) ?></td>
                                <td><code><?= e($a['action']) ?></code></td>
                                <td>
                                    <?php if (!empty($a['target_type'])): ?>
                                        <?= e($a['target_type']) ?>
                                        <?php if (!empty($a['target_id'])): ?>#<?= (int) $a['target_id'] ?><?php endif; ?>
                                    <?php else: ?>
                                        <span style="color:var(--color-tertiary);">—</span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size:0.78rem; color:var(--color-tertiary);">
                                    <?= e((string) ($a['meta'] ?? '')) ?>
                                </td>
                                <td><code class="pzk-ip"><?= e((string) ($a['ip'] ?? '')) ?></code></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($adminAudit === []): ?>
                            <tr><td colspan="6" class="pzk-table__empty">Записей пока нет.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>
</div>

<style>
/* =============================================================================
   Стили админки. Применяются только на ?page=admin.
   ============================================================================= */

.pzk-table th,
.pzk-table td {
    padding: 14px 18px;
    line-height: 1.5;
}
.pzk-table thead th {
    padding-top: 13px;
    padding-bottom: 13px;
    font-size: 0.72rem;
}
.pzk-table th:first-child,
.pzk-table td:first-child { padding-left: 22px; }
.pzk-table th:last-child,
.pzk-table td:last-child  { padding-right: 22px; }

.pzk-table-wrap {
    overflow: auto;
    border: 1px solid rgba(26, 26, 26, 0.1);
    background: #fff;
}
.pzk-table-wrap > .pzk-table { border: none; }

.pzk-table td.pzk-table__actions {
    display: flex;
    gap: 8px;
    align-items: flex-start;
    flex-wrap: wrap;
}
.pzk-table td.pzk-table__actions form { display: inline-block; margin: 0; }

.pzk-table__empty {
    text-align: center;
    color: var(--color-tertiary);
    font-style: italic;
    padding: 24px 18px !important;
}

.pzk-table--lessons td.pzk-table__row-form {
    padding: 8px 22px;
    background: #fbfaf5;
}
.pzk-lesson-form {
    display: grid;
    grid-template-columns: 70px 110px 1fr 1fr 130px;
    gap: 8px;
    align-items: center;
    width: 100%;
}
.pzk-lesson-delete { display: block; margin-top: 6px; }

.pzk-table td code {
    background: rgba(139, 105, 20, 0.08);
    padding: 2px 6px;
    border-radius: 2px;
    font-size: 0.85em;
}
.pzk-table code.pzk-ip {
    background: rgba(26, 26, 26, 0.05);
    font-size: 0.75rem;
}

.pzk-status {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 10px;
    font-size: 0.72rem;
    font-weight: 600;
    white-space: nowrap;
}
.pzk-status--active   { background: rgba(22, 163, 74, 0.12); color: #15803d; }
.pzk-status--inactive { background: rgba(220, 38, 38, 0.12); color: #b91c1c; }

.pzk-hint-inline {
    font-size: 0.78rem;
    color: var(--color-tertiary);
    padding: 6px 4px;
    display: inline-block;
}

.pzk-inline-form {
    display: flex;
    gap: 14px;
    align-items: center;
    flex-wrap: wrap;
    padding: 14px 18px;
    background: var(--color-light);
    border: 1px solid rgba(26, 26, 26, 0.08);
}
.pzk-inline-form label {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.88rem;
    color: var(--color-secondary);
}

.pzk-row-details summary {
    list-style: none;
    cursor: pointer;
}
.pzk-row-details summary::-webkit-details-marker { display: none; }
.pzk-row-details summary::marker { content: ''; }
.pzk-row-details__form {
    display: flex;
    gap: 6px;
    margin-top: 6px;
    align-items: center;
    flex-wrap: wrap;
}
.pzk-row-details__form input {
    padding: 4px 8px;
    font-size: 0.8rem;
    min-width: 160px;
}
.pzk-edit-form {
    display: flex;
    flex-direction: column;
    gap: 10px;
    background: #fff;
    padding: 14px;
    border: 1px solid rgba(26, 26, 26, 0.1);
    min-width: 300px;
    margin-top: 6px;
}
.pzk-edit-form .pzk-field { gap: 4px; }
.pzk-edit-form .pzk-field__label { font-size: 0.78rem; }
.pzk-edit-form .pzk-field__input { padding: 6px 10px; font-size: 0.85rem; }
.pzk-edit-form .pzk-btn { align-self: flex-start; margin-top: 6px; }

.pzk-details {
    background: #fbf7ec;
    border: 1px solid #e8dcc0;
    border-left: 4px solid #8b6914;
    box-shadow: 0 2px 8px rgba(80, 60, 20, 0.06);
    margin-bottom: var(--space-md);
    transition: box-shadow 0.2s;
}
.pzk-details[open] {
    box-shadow: 0 4px 18px rgba(80, 60, 20, 0.10);
}

.pzk-details__summary {
    padding: 18px 24px;
    font-family: 'Cormorant Garamond', 'Inter', serif;
    font-size: 1.2rem;
    font-weight: 600;
    letter-spacing: 0.03em;
    color: #6b5010;
    background: linear-gradient(135deg, #fbf7ec 0%, #f5ecd5 100%);
    cursor: pointer;
    user-select: none;
    list-style: none;
    display: flex;
    align-items: center;
    gap: 10px;
    transition: background 0.2s, color 0.2s;
}
.pzk-details__summary::-webkit-details-marker { display: none; }
.pzk-details__summary::marker { content: ''; }
.pzk-details__summary:hover {
    background: linear-gradient(135deg, #f5ecd5 0%, #efe2c0 100%);
    color: #4a3a10;
}
.pzk-details[open] .pzk-details__summary {
    border-bottom: 1px solid #e8dcc0;
}

.pzk-form__title {
    font-family: 'Cormorant Garamond', 'Inter', serif;
    font-size: 1.5rem;
    font-weight: 600;
    color: #8b1a1a;
    letter-spacing: 0.02em;
    margin: 0 0 6px;
    line-height: 1.2;
}
.pzk-form__hint {
    font-family: 'Inter', sans-serif;
    font-size: 0.95rem;
    color: #6b5d3f;
    font-style: italic;
    line-height: 1.55;
    margin: 0;
}

.pzk-form {
    padding: 26px 28px 24px;
    display: flex;
    flex-direction: column;
    gap: 22px;
}

.pzk-form__grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: 18px 22px;
}

.pzk-field {
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.pzk-field__label {
    font-family: 'Cormorant Garamond', 'Inter', serif;
    font-size: 1rem;
    font-weight: 600;
    letter-spacing: 0.03em;
    text-transform: none;
    color: #6b5010;
    margin: 0;
}
.pzk-field__input {
    padding: 13px 16px;
    border: 1px solid #d4c8a8;
    background: #ffffff;
    font-family: 'Inter', -apple-system, sans-serif;
    font-size: 1.02rem;
    color: #1a1a1a;
    transition: border-color 0.15s, box-shadow 0.15s;
    border-radius: 2px;
    width: 100%;
}
.pzk-field__input:focus {
    outline: none;
    border-color: #8b6914;
    box-shadow: 0 0 0 3px rgba(139, 105, 20, 0.12);
}
.pzk-field__input::placeholder {
    color: #b8a888;
    font-style: italic;
    font-size: 0.95rem;
}
.pzk-field__input--sm {
    padding: 6px 10px;
    font-size: 0.85rem;
}

.pzk-checkbox {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    font-family: 'Inter', sans-serif;
    font-size: 0.98rem;
    color: #2d2d2d;
    cursor: pointer;
    padding: 4px 0;
}
.pzk-checkbox input[type="checkbox"] {
    width: 18px;
    height: 18px;
    accent-color: #8b6914;
    cursor: pointer;
    flex-shrink: 0;
}

.pzk-fieldset {
    border: 1px solid #e8dcc0;
    background: rgba(255, 255, 255, 0.6);
    padding: 16px 20px 18px;
    border-radius: 2px;
    margin: 0;
}
.pzk-fieldset legend {
    font-family: 'Cormorant Garamond', 'Inter', serif;
    font-size: 1rem;
    font-weight: 600;
    letter-spacing: 0.03em;
    color: #6b5010;
    padding: 0 10px;
}
.pzk-checkbox-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
    gap: 6px 18px;
    max-height: 280px;
    overflow-y: auto;
    padding: 6px 2px;
}

.pzk-btn {
    padding: 10px 22px;
    font-family: 'Cormorant Garamond', 'Inter', serif;
    font-size: 1.05rem;
    font-weight: 600;
    letter-spacing: 0.06em;
    text-transform: none;
    border: 1px solid rgba(26, 26, 26, 0.2);
    background: #fff;
    color: #2d2d2d;
    cursor: pointer;
    transition: background-color 0.15s, color 0.15s, border-color 0.15s, box-shadow 0.15s;
    border-radius: 2px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    line-height: 1.2;
}
.pzk-btn:hover {
    background: rgba(139, 105, 20, 0.06);
    color: #1a1a1a;
    border-color: rgba(139, 105, 20, 0.4);
}
.pzk-btn--primary {
    background: #8b6914;
    color: #fff;
    border-color: #8b6914;
    box-shadow: 0 2px 6px rgba(139, 105, 20, 0.2);
}
.pzk-btn--primary:hover {
    background: #a67c00;
    border-color: #a67c00;
    color: #fff;
    box-shadow: 0 3px 10px rgba(139, 105, 20, 0.3);
}
.pzk-btn--sm {
    padding: 7px 14px;
    font-size: 0.88rem;
    letter-spacing: 0.03em;
}

.pzk-form__actions {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 12px;
    padding-top: 18px;
    border-top: 1px solid #e8dcc0;
    margin-top: 4px;
}

@media (max-width: 900px) {
    .pzk-table th, .pzk-table td { padding: 10px 12px; }
    .pzk-table th:first-child, .pzk-table td:first-child { padding-left: 14px; }
    .pzk-table th:last-child,  .pzk-table td:last-child  { padding-right: 14px; }
    .pzk-lesson-form { grid-template-columns: 1fr; }
    .pzk-form { padding: 20px 18px; }
    .pzk-form__title { font-size: 1.25rem; }
    .pzk-details__summary { padding: 14px 16px; font-size: 1.05rem; }
    .pzk-form__actions { justify-content: stretch; }
    .pzk-form__actions .pzk-btn { width: 100%; justify-content: center; }
}
</style>

<script>
(function () {
    'use strict';
    document.querySelectorAll('[data-open-details]').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            var href = btn.getAttribute('href') || '';
            if (href.charAt(0) !== '#') return;
            var target = document.getElementById(href.slice(1));
            if (!target) return;
            e.preventDefault();
            if (target.tagName === 'DETAILS') {
                target.open = true;
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                setTimeout(function () {
                    var first = target.querySelector('input, select, textarea');
                    if (first) first.focus();
                }, 350);
            }
        });
    });
})();
</script>