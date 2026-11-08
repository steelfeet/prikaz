<?php
declare(strict_types=1);

/**
 * Partial: блок «Темы» для одного предмета.
 *
 * Ожидает $s — массив с полями:
 *   id, short_name, topics[]
 *
 * Редактирование — inline в ячейке «Тема»: <details> с summary = сам текст
 * темы, при раскрытии — форма под текстом, textarea на 3 строки.
 * «+ Добавить ниже» — маленькая ссылка под текстом темы (тоже <details>
 * с формой insert_below). Кнопка «Редактировать» в столбце «Действия»
 * открывает форму редактирования через JS.
 */

$sTopics = $s['topics'] ?? [];
?>
<details class="pzk-subject-topics" id="topics-<?= (int) $s['id'] ?>">
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
                        <th style="width:190px;">Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sTopics as $t): ?>
                        <tr>
                            <td><?= (int) $t['ord'] ?></td>
                            <td>
                                <details class="pzk-topic-edit" id="topic-edit-<?= (int) $t['id'] ?>">
                                    <summary class="pzk-topic-edit__summary" title="Редактировать">
                                        <?= e($t['topic']) ?>
                                    </summary>
                                    <form method="post" action="index.php?action=admin_curriculum_save"
                                          class="pzk-topic-edit__form" autocomplete="off">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                                        <input type="hidden" name="subject_id" value="<?= (int) $s['id'] ?>">
                                        <label class="pzk-field">
                                            <span class="pzk-field__label">№</span>
                                            <input type="number" name="ord" value="<?= (int) $t['ord'] ?>"
                                                   required min="1" autocomplete="off"
                                                   class="pzk-field__input pzk-field__input--sm"
                                                   style="width:90px;">
                                        </label>
                                        <label class="pzk-field">
                                            <span class="pzk-field__label">Тема</span>
                                            <textarea name="topic" rows="3" required
                                                      autocomplete="off"
                                                      class="pzk-field__textarea"><?= e($t['topic']) ?></textarea>
                                        </label>
                                        <div class="pzk-topic-edit__actions">
                                            <button type="submit" class="pzk-btn pzk-btn--sm pzk-btn--primary">Сохранить</button>
                                        </div>
                                    </form>
                                </details>

                                <details class="pzk-topic-edit pzk-topic-below" id="topic-below-<?= (int) $t['id'] ?>">
                                    <summary class="pzk-topic-below__summary" title="Добавить ниже">
                                        + Добавить ниже
                                    </summary>
                                    <form method="post" action="index.php?action=admin_curriculum_insert_below"
                                          class="pzk-topic-edit__form" autocomplete="off">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                                        <label class="pzk-field">
                                            <span class="pzk-field__label">Новая тема</span>
                                            <textarea name="topic" rows="3" required
                                                      autocomplete="off"
                                                      placeholder="Название темы"
                                                      class="pzk-field__textarea"></textarea>
                                        </label>
                                        <div class="pzk-topic-edit__actions">
                                            <button type="submit" class="pzk-btn pzk-btn--sm pzk-btn--primary">Добавить</button>
                                        </div>
                                    </form>
                                </details>
                            </td>
                            <td class="pzk-table__actions">
                                <button type="button" class="pzk-btn pzk-btn--sm"
                                        data-open-topic-edit="topic-edit-<?= (int) $t['id'] ?>">
                                    Редактировать
                                </button>

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