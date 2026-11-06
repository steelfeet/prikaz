<?php
declare(strict_types=1);

// Хелпер: русская дата + день недели для чипа праздника
$holidayChip = function (string $iso): string {
    $ts = strtotime($iso);
    if ($ts === false) return $iso;
    $dow = ['пн','вт','ср','чт','пт','сб','вс'][(int) date('N', $ts) - 1];
    return date('d.m.Y', $ts) . ' (' . $dow . ')';
};
?>
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
                        <th style="width:50px;">ID</th>
                        <th>Название</th>
                        <th style="width:130px;">Начало</th>
                        <th style="width:130px;">Окончание</th>
                        <th style="width:110px;">Текущий</th>
                        <th style="width:320px;">Действия</th>
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
                                          onsubmit="return confirm('Удалить учебный год «<?= e($ay['name']) ?>»? Все его семестры и группы будут удалены.');">
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

    <details class="pzk-details" id="add-academic-year" style="margin-top: var(--space-sm);">
        <summary class="pzk-details__summary">➕ Добавить учебный год</summary>
        <form method="post" action="index.php?action=admin_academic_year_save"
              class="pzk-form" autocomplete="off">
            <?= csrf_field() ?>
            <div>
                <h3 class="pzk-form__title">Новый учебный год</h3>
                <p class="pzk-form__hint">
                    Название в формате «2025/2026». Текущий год — один на всю систему.
                    Семестры «1 семестр» и «2 семестр» создаются автоматически — их можно
                    переименовать или удалить в блоке «Семестры и праздничные дни» ниже.
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

    <?php if ($adminAcademicYears !== []): ?>
        <div style="margin-top: var(--space-md);">
            <h3 style="font-family:'Cormorant Garamond','Inter',serif; font-size:1.4rem; font-weight:600; color:#6b5010; margin: 0 0 var(--space-sm); padding-bottom: 6px; border-bottom: 2px solid #e8dcc0;">
                Семестры и праздничные дни
            </h3>

            <?php foreach ($adminAcademicYears as $ay): ?>
                <?php
                    $yearId = (int) $ay['id'];
                    $yearSems = $adminSemestersByYear[$yearId] ?? [];
                ?>
                <div class="pzk-year-block">
                    <div class="pzk-year-block__head">
                        <div>
                            <span class="pzk-year-block__title"><?= e($ay['name']) ?></span>
                            <?php if ((int) $ay['is_current'] === 1): ?>
                                <span class="pzk-status pzk-status--active" style="margin-left:8px;">текущий</span>
                            <?php endif; ?>
                            <span class="pzk-year-block__meta">
                                <?= e($ay['start_date']) ?> — <?= e($ay['end_date']) ?>
                            </span>
                        </div>
                        <details class="pzk-row-details">
                            <summary class="pzk-btn pzk-btn--sm">➕ Добавить семестр</summary>
                            <form method="post" action="index.php?action=admin_semester_save"
                                  class="pzk-edit-form" autocomplete="off">
                                <?= csrf_field() ?>
                                <input type="hidden" name="academic_year_id" value="<?= $yearId ?>">
                                <label class="pzk-field">
                                    <span class="pzk-field__label">Название</span>
                                    <input type="text" name="name" required
                                           placeholder="Например: 3 семестр, Учебная практика"
                                           autocomplete="off"
                                           class="pzk-field__input pzk-field__input--sm">
                                </label>
                                <label class="pzk-field">
                                    <span class="pzk-field__label">Порядковый номер</span>
                                    <input type="number" name="ord" min="1" max="9999"
                                           value="<?= count($yearSems) + 1 ?>"
                                           autocomplete="off"
                                           class="pzk-field__input pzk-field__input--sm">
                                </label>
                                <label class="pzk-checkbox">
                                    <input type="checkbox" name="is_practice" value="1">
                                    Практика
                                </label>
                                <button type="submit" class="pzk-btn pzk-btn--sm pzk-btn--primary">Создать</button>
                            </form>
                        </details>
                    </div>

                    <?php if ($yearSems === []): ?>
                        <div class="pzk-sem-empty">В этом году нет семестров. Добавьте первый.</div>
                    <?php else: ?>
                        <?php foreach ($yearSems as $sm): ?>
                            <?php $smId = (int) $sm['id']; ?>
                            <div class="pzk-sem-card" id="sem-<?= $smId ?>">
                                <div class="pzk-sem-card__head">
                                    <div>
                                        <span class="pzk-sem-card__title"><?= e($sm['name']) ?></span>
                                        <?php if ((int) $sm['is_practice'] === 1): ?>
                                            <span class="pzk-sem-tag">практика</span>
                                        <?php endif; ?>
                                        <span class="pzk-sem-card__meta">
                                            праздников: <?= count($sm['holidays']) ?>
                                        </span>
                                    </div>
                                    <div style="display:flex; gap:6px; flex-wrap:wrap;">
                                        <details class="pzk-row-details">
                                            <summary class="pzk-btn pzk-btn--sm">Редактировать</summary>
                                            <form method="post" action="index.php?action=admin_semester_save"
                                                  class="pzk-edit-form" autocomplete="off">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="id" value="<?= $smId ?>">
                                                <input type="hidden" name="academic_year_id" value="<?= $yearId ?>">
                                                <label class="pzk-field">
                                                    <span class="pzk-field__label">Название</span>
                                                    <input type="text" name="name" value="<?= e($sm['name']) ?>"
                                                           required autocomplete="off"
                                                           class="pzk-field__input pzk-field__input--sm">
                                                </label>
                                                <label class="pzk-field">
                                                    <span class="pzk-field__label">Порядковый номер</span>
                                                    <input type="number" name="ord" min="1" max="9999"
                                                           value="<?= (int) $sm['ord'] ?>"
                                                           autocomplete="off"
                                                           class="pzk-field__input pzk-field__input--sm">
                                                </label>
                                                <label class="pzk-checkbox">
                                                    <input type="checkbox" name="is_practice" value="1"
                                                           <?= (int) $sm['is_practice'] === 1 ? 'checked' : '' ?>>
                                                    Практика
                                                </label>
                                                <button type="submit" class="pzk-btn pzk-btn--sm pzk-btn--primary">Сохранить</button>
                                            </form>
                                        </details>
                                        <form method="post" action="index.php?action=admin_semester_delete"
                                              onsubmit="return confirm('Удалить семестр «<?= e($sm['name']) ?>»? Все его предметы и расписание тоже будут удалены.');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= $smId ?>">
                                            <button type="submit" class="pzk-btn pzk-btn--sm">Удалить</button>
                                        </form>
                                    </div>
                                </div>

                                <div class="pzk-sem-card__body">
                                    <?php if ($sm['holidays'] === []): ?>
                                        <p class="pzk-form__hint" style="margin:0 0 6px;">
                                            В семестре нет праздничных дней.
                                        </p>
                                    <?php else: ?>
                                        <div class="pzk-holiday-list">
                                            <?php foreach ($sm['holidays'] as $h): ?>
                                                <span class="pzk-holiday-chip" title="<?= e($h['date']) ?>">
                                                    <span class="pzk-holiday-chip__date"><?= e($holidayChip((string) $h['date'])) ?></span>
                                                    <?php if (!empty($h['name'])): ?>
                                                        <span class="pzk-holiday-chip__name"><?= e($h['name']) ?></span>
                                                    <?php endif; ?>
                                                    <form method="post" action="index.php?action=admin_holiday_delete"
                                                          onsubmit="return confirm('Удалить праздничный день <?= e($holidayChip((string) $h['date'])) ?>?');"
                                                          style="display:inline; margin:0;">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="id" value="<?= (int) $h['id'] ?>">
                                                        <input type="hidden" name="semester_id" value="<?= $smId ?>">
                                                        <button type="submit" class="pzk-holiday-chip__remove" title="Удалить">×</button>
                                                    </form>
                                                </span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>

                                    <form method="post" action="index.php?action=admin_holiday_add"
                                          class="pzk-holiday-form" autocomplete="off">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="semester_id" value="<?= $smId ?>">
                                        <label class="pzk-field" style="flex: 0 0 160px;">
                                            <span class="pzk-field__label">Дата</span>
                                            <input class="pzk-field__input" type="date" name="date" required>
                                        </label>
                                        <label class="pzk-field" style="flex: 1 1 200px;">
                                            <span class="pzk-field__label">Название (опционально)</span>
                                            <input class="pzk-field__input" type="text" name="name"
                                                   placeholder="Например: День народного единства" autocomplete="off">
                                        </label>
                                        <button type="submit" class="pzk-btn pzk-btn--sm pzk-btn--primary" style="align-self: flex-end;">
                                            Добавить
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>