<?php
declare(strict_types=1);
?>
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
              style="margin-bottom: var(--space-sm);">
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
                            <th style="width:50px;">ID</th>
                            <th style="width:180px;">Учебный год</th>
                            <th>Название</th>
                            <th style="width:160px;">Куратор</th>
                            <th>Заметки</th>
                            <th style="width:190px;">Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($adminGroups as $g): ?>
                            <?php $gid = (int) $g['id']; ?>
                            <tr>
                                <td><?= $gid ?></td>
                                <td>
                                    <strong><?= e($g['year_name']) ?></strong>
                                    <?php if ((int) $g['year_is_current'] === 1): ?>
                                        <br><span class="pzk-status pzk-status--active" style="margin-top:2px;">текущий</span>
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
                                    <button type="button" class="pzk-btn pzk-btn--sm"
                                            data-open-group-edit="group-edit-<?= $gid ?>">
                                        Редактировать
                                    </button>
                                    <form method="post" action="index.php?action=admin_group_delete"
                                          onsubmit="return confirm('Удалить группу «<?= e($g['name']) ?>» (<?= e($g['year_name']) ?>)? Студенты останутся, группа исчезнет из списка.');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= $gid ?>">
                                        <button type="submit" class="pzk-btn pzk-btn--sm">Удалить</button>
                                    </form>
                                </td>
                            </tr>
                            <tr class="pzk-group-edit-row">
                                <td colspan="6">
                                    <details class="pzk-group-edit" id="group-edit-<?= $gid ?>">
                                        <summary style="display:none"></summary>
                                        <form method="post" action="index.php?action=admin_group_save"
                                              class="pzk-group-edit__form" autocomplete="off">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= $gid ?>">
                                            <div class="pzk-form__grid">
                                                <label class="pzk-field">
                                                    <span class="pzk-field__label">Название</span>
                                                    <input type="text" name="name" required
                                                           value="<?= e($g['name']) ?>"
                                                           autocomplete="off"
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
                                            </div>
                                            <div class="pzk-group-edit__actions">
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

        <details class="pzk-details" id="add-group" style="margin-top: var(--space-sm);">
            <summary class="pzk-details__summary">➕ Добавить группу</summary>
            <form method="post" action="index.php?action=admin_group_save"
                  class="pzk-form" autocomplete="off">
                <?= csrf_field() ?>
                <div>
                    <h3 class="pzk-form__title">Новая группа</h3>
                    <p class="pzk-form__hint">
                        Группа принадлежит одному учебному году. В разных годах могут
                        быть группы с одинаковыми названиями — это разные сущности.
                    </p>
                </div>
                <div class="pzk-form__grid">
                    <label class="pzk-field">
                        <span class="pzk-field__label">Название группы</span>
                        <input class="pzk-field__input" type="text" name="name" required
                               placeholder="46 гр." autocomplete="off">
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