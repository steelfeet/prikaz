<?php
declare(strict_types=1);
?>
<section class="panel">
    <div class="panel__head">
        <div>
            <h2 class="panel__title">Предметы</h2>
            <p class="panel__subtitle">
                Каждый учебный год — свой набор предметов. Предмет привязан к группе,
                у каждого семестра или практики — свой предмет. Темы и КТП
                показываются под предметом.
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
              style="margin-bottom: var(--space-sm);">
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
            <div class="pzk-table-wrap pzk-table-wrap--subjects">
                <table class="pzk-table">
                    <thead>
                        <tr>
                            <th style="width:50px;">ID</th>
                            <th style="width:130px;">Учебный год</th>
                            <th style="width:80px;">Группа</th>
                            <th style="width:120px;">Краткое</th>
                            <th>Полное название</th>
                            <th style="width:70px;">Часов</th>
                            <th style="width:150px;">Семестр / Практика</th>
                            <th style="width:260px;">Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($adminSubjects as $s): ?>
                            <?php
                                $sTopics = $s['topics'] ?? [];
                            ?>
                            <tr>
                                <td><?= (int) $s['id'] ?></td>
                                <td>
                                    <strong><?= e($s['year_name']) ?></strong>
                                    <?php if ((int) $s['year_is_current'] === 1): ?>
                                        <br><span class="pzk-status pzk-status--active" style="margin-top:2px;">текущий</span>
                                    <?php endif; ?>
                                </td>
                                <td><strong><?= e($s['group_name']) ?></strong></td>
                                <td><strong><?= e($s['short_name']) ?></strong></td>
                                <td><?= e($s['full_name']) ?></td>
                                <td><?= (int) $s['hours'] ?></td>
                                <td style="font-size:0.82rem; color:var(--color-tertiary);">
                                    <?= $s['semester_name'] !== '' ? e($s['semester_name']) : '—' ?>
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
                                                <span class="pzk-field__label">Семестр / Практика</span>
                                                <select name="semester_id" required
                                                        class="pzk-field__input pzk-field__input--sm">
                                                    <option value="">— выберите семестр —</option>
                                                    <?php foreach ($adminAllSemesters as $sm): ?>
                                                        <option value="<?= (int) $sm['id'] ?>"
                                                                data-year="<?= (int) $sm['academic_year_id'] ?>"
                                                                <?= (int) $sm['id'] === (int) $s['semester_id'] ? 'selected' : '' ?>>
                                                            <?= e($sm['name']) ?> · <?= e($sm['year_name']) ?>
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
                            <tr class="pzk-topics-row">
                                <td colspan="8">
                                    <details class="pzk-subject-topics">
                                        <summary class="pzk-subject-topics__summary">
                                            <?php if ($sTopics !== []): ?>
                                                📋 Темы (<?= count($sTopics) ?>) — развернуть
                                            <?php else: ?>
                                                📋 + Добавить темы
                                            <?php endif; ?>
                                        </summary>
                                        <div class="pzk-subject-topics__body">
                                            <?php if ($sTopics !== []): ?>
                                                <table class="pzk-topics-table">
                                                    <thead>
                                                        <tr>
                                                            <th style="width:50px;">№</th>
                                                            <th>Тема</th>
                                                            <th style="width:220px;">Действия</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($sTopics as $t): ?>
                                                            <tr>
                                                                <td><?= (int) $t['ord'] ?></td>
                                                                <td><?= e($t['topic']) ?></td>
                                                                <td class="pzk-table__actions">
                                                                    <details class="pzk-row-details">
                                                                        <summary class="pzk-btn pzk-btn--sm">Редактировать</summary>
                                                                        <form method="post" action="index.php?action=admin_curriculum_save"
                                                                              class="pzk-edit-form" autocomplete="off">
                                                                            <?= csrf_field() ?>
                                                                            <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                                                                            <input type="hidden" name="subject_id" value="<?= (int) $s['id'] ?>">
                                                                            <label class="pzk-field">
                                                                                <span class="pzk-field__label">№</span>
                                                                                <input type="number" name="ord" value="<?= (int) $t['ord'] ?>"
                                                                                       required min="1" autocomplete="off"
                                                                                       class="pzk-field__input pzk-field__input--sm">
                                                                            </label>
                                                                            <label class="pzk-field">
                                                                                <span class="pzk-field__label">Тема</span>
                                                                                <input type="text" name="topic" value="<?= e($t['topic']) ?>"
                                                                                       required autocomplete="off"
                                                                                       class="pzk-field__input pzk-field__input--sm">
                                                                            </label>
                                                                            <button type="submit" class="pzk-btn pzk-btn--sm pzk-btn--primary">Сохранить</button>
                                                                        </form>
                                                                    </details>
                                                                    <form method="post" action="index.php?action=admin_curriculum_delete"
                                                                          onsubmit="return confirm('Удалить тему «<?= e($t['topic']) ?>»?');">
                                                                        <?= csrf_field() ?>
                                                                        <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                                                                        <button type="submit" class="pzk-btn pzk-btn--sm">Удалить</button>
                                                                    </form>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            <?php else: ?>
                                                <p class="pzk-form__hint" style="margin:0 0 8px;">
                                                    У этого предмета пока нет тем.
                                                </p>
                                            <?php endif; ?>

                                            <div class="pzk-topics-forms">
                                                <form method="post" action="index.php?action=admin_curriculum_save"
                                                      class="pzk-inline-form pzk-topics-add-form" autocomplete="off">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="subject_id" value="<?= (int) $s['id'] ?>">
                                                    <label>№
                                                        <input type="number" name="ord" min="1"
                                                               value="<?= count($sTopics) + 1 ?>" required
                                                               autocomplete="off"
                                                               class="pzk-field__input pzk-field__input--sm"
                                                               style="width:70px;">
                                                    </label>
                                                    <label style="flex:1;">Тема
                                                        <input type="text" name="topic" required
                                                               placeholder="Название темы"
                                                               autocomplete="off"
                                                               class="pzk-field__input pzk-field__input--sm"
                                                               style="width:100%;">
                                                    </label>
                                                    <button type="submit" class="pzk-btn pzk-btn--sm pzk-btn--primary">Добавить тему</button>
                                                </form>

                                                <details class="pzk-details" style="margin:0;">
                                                    <summary class="pzk-details__summary" style="padding:6px 12px; font-size:0.95rem;">📋 Массовое добавление тем</summary>
                                                    <form method="post" action="index.php?action=admin_curriculum_bulk_add"
                                                          class="pzk-form" autocomplete="off">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="subject_id" value="<?= (int) $s['id'] ?>">
                                                        <p class="pzk-form__hint" style="margin:0 0 4px;">
                                                            Одна строка — одна тема. Повторы в списке и уже имеющиеся темы игнорируются.
                                                            Нумерация продолжается от последней темы<?= $sTopics !== [] ? ' (сейчас ' . count($sTopics) . ')' : '' ?>.
                                                        </p>
                                                        <label class="pzk-field">
                                                            <span class="pzk-field__label">Темы</span>
                                                            <textarea class="pzk-field__textarea" name="topics" rows="8" required
                                                                      placeholder="Введение в предмет&#10;Основные понятия и определения&#10;..."></textarea>
                                                        </label>
                                                        <div class="pzk-form__actions">
                                                            <button type="submit" class="pzk-btn pzk-btn--sm pzk-btn--primary">Добавить темы списком</button>
                                                        </div>
                                                    </form>
                                                </details>
                                            </div>
                                        </div>
                                    </details>

                                    <?php require __DIR__ . '/_ktp_block.php'; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <details class="pzk-details" id="add-subject" style="margin-top: var(--space-sm);">
            <summary class="pzk-details__summary">➕ Добавить предмет</summary>
            <form method="post" action="index.php?action=admin_subject_save"
                  class="pzk-form pzk-subject-form" autocomplete="off">
                <?= csrf_field() ?>
                <div>
                    <h3 class="pzk-form__title">Новый предмет</h3>
                    <p class="pzk-form__hint">
                        Предмет принадлежит одному учебному году, одной группе этого года
                        и одному семестру. Если у группы два семестра — это две отдельные
                        записи предмета. Темы и КТП добавляются после создания предмета.
                    </p>
                </div>
                <div class="pzk-form__grid">
                    <label class="pzk-field">
                        <span class="pzk-field__label">Учебный год</span>
                        <select class="pzk-field__input" name="academic_year_id" required>
                            <?php foreach ($adminAcademicYears as $ay): ?>
                                <option value="<?= (int) $ay['id'] ?>"
                                        <?= (int) $ay['id'] === (int) $adminSubjectsFilterYear ? 'selected' : '' ?>>
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
                        <span class="pzk-field__label">Семестр / Практика</span>
                        <select class="pzk-field__input" name="semester_id" required>
                            <option value="">— выберите семестр —</option>
                            <?php foreach ($adminAllSemesters as $sm): ?>
                                <option value="<?= (int) $sm['id'] ?>"
                                        data-year="<?= (int) $sm['academic_year_id'] ?>"
                                        <?= (int) $sm['id'] === (int) $defaultSubjectSemesterId ? 'selected' : '' ?>>
                                    <?= e($sm['name']) ?> · <?= e($sm['year_name']) ?>
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
                </div>
                <div class="pzk-form__actions">
                    <button type="submit" class="pzk-btn pzk-btn--primary">Создать предмет</button>
                </div>
            </form>
        </details>

        <script>
        (function () {
            'use strict';
            document.querySelectorAll('.pzk-subject-form').forEach(function (form) {
                var yearSel  = form.querySelector('select[name="academic_year_id"]');
                var groupSel = form.querySelector('select[name="group_id"]');
                var semSel   = form.querySelector('select[name="semester_id"]');
                if (!yearSel) return;

                function filterByYear(sel) {
                    if (!sel) return;
                    var year = yearSel.value;
                    var current = sel.value;
                    var stillValid = false;
                    sel.querySelectorAll('option').forEach(function (opt) {
                        if (opt.value === '') return;
                        var ok = opt.getAttribute('data-year') === year;
                        opt.hidden = !ok;
                        opt.disabled = !ok;
                        if (ok && opt.value === current) stillValid = true;
                    });
                    if (!stillValid) sel.value = '';
                }

                function syncAll() {
                    filterByYear(groupSel);
                    filterByYear(semSel);
                }

                yearSel.addEventListener('change', syncAll);
                syncAll();
            });
        })();
        </script>
    <?php endif; ?>
</section>