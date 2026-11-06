<?php
declare(strict_types=1);
?>
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
                    <th style="width:60px;">№</th>
                    <th style="width:110px;">Дата</th>
                    <th>Тема</th>
                    <th>ДЗ</th>
                    <th style="width:160px;"></th>
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

    <details class="pzk-details" id="add-lesson" style="margin-top: var(--space-sm);">
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