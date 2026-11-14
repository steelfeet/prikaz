<?php
declare(strict_types=1);

/**
 * Секция «Типы проектов».
 *
 * Ожидает:
 *   $adminProjectTypes        — list<project_types>
 *   $adminProjectTypesPhases  — [type_id => list<project_type_phases>]
 *   $adminProjectTypesChecks  — [type_id => list<project_type_checks>]
 */

$types       = $adminProjectTypes        ?? [];
$typesPhases = $adminProjectTypesPhases  ?? [];
$typesChecks = $adminProjectTypesChecks  ?? [];

/**
 * Рендер поля «Цвет» с нативным пикером, hex-инпутом и свотчами.
 * Скрипт внизу страницы связывает их через data-color-picker.
 */
$renderPhaseColor = static function (string $name, string $value): string {
    $value  = strtolower($value);
    $palette = project_type_phase_palette();
    ob_start(); ?>
    <div class="pzk-color-picker" data-color-picker>
        <input type="color"
               value="<?= e($value) ?>"
               class="pzk-color-picker__native"
               aria-label="Выбрать цвет">
        <input type="text" name="<?= e($name) ?>"
               value="<?= e($value) ?>"
               pattern="#[0-9a-fA-F]{6}"
               maxlength="7"
               autocomplete="off"
               spellcheck="false"
               class="pzk-field__input pzk-field__input--sm pzk-color-picker__text">
    </div>
    <div class="pzk-color-picker__swatches">
        <?php foreach ($palette as $c): ?>
            <button type="button"
                    class="pzk-color-picker__swatch<?= strtolower($c) === $value ? ' is-active' : '' ?>"
                    data-color="<?= e($c) ?>"
                    style="background:<?= e($c) ?>;"
                    title="<?= e($c) ?>"
                    aria-label="Выбрать <?= e($c) ?>"></button>
        <?php endforeach; ?>
    </div>
    <?php return (string) ob_get_clean();
};
?>
<section class="panel">
    <div class="panel__head">
        <div>
            <h2 class="panel__title">Типы проектов</h2>
            <p class="panel__subtitle">
                Каждый тип — свои фазы и своя папка <code>projects/&lt;code&gt;/</code>
                с <code>check.php</code> (заглушка CI/CD). Код используется в URL репозитория,
                папке и как значение <code>projects.type</code>.
            </p>
        </div>
        <a href="#add-project-type" class="pzk-btn pzk-btn--primary" data-open-details>
            ➕ Добавить
        </a>
    </div>

    <?php if ($types === []): ?>
        <div class="uni-empty">Типов пока нет. Добавьте первый.</div>
    <?php else: ?>
        <?php foreach ($types as $t): ?>
            <?php
                $tid    = (int) $t['id'];
                $phases = $typesPhases[$tid]  ?? [];
                $checks = $typesChecks[$tid]  ?? [];
                $dir    = project_type_dir((string) $t['code']);
                $exists = is_dir($dir);
                $nextPhaseColor = project_type_next_phase_color($phases);
            ?>
            <div class="pzk-ptype" id="type-<?= $tid ?>">
                <div class="pzk-ptype__head">
                    <div class="pzk-ptype__title-wrap">
                        <span class="pzk-ptype__icon"><?= e((string) ($t['icon'] ?? '📌')) ?></span>
                        <span class="pzk-ptype__name" style="--type-color:<?= e((string) $t['color']) ?>;">
                            <?= e((string) $t['name']) ?>
                        </span>
                        <code class="pzk-ptype__code"><?= e((string) $t['code']) ?></code>
                        <span class="pzk-ptype__folder <?= $exists ? 'pzk-ptype__folder--ok' : 'pzk-ptype__folder--bad' ?>"
                              title="<?= e($dir) ?>">
                            <?= $exists ? 'папка есть' : 'папки нет' ?>
                        </span>
                    </div>
                    <div class="pzk-ptype__actions">
                        <details class="pzk-row-details">
                            <summary class="pzk-btn pzk-btn--sm">Редактировать</summary>
                            <form method="post" action="index.php?action=admin_project_type_save"
                                  class="pzk-edit-form" autocomplete="off">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= $tid ?>">
                                <label class="pzk-field">
                                    <span class="pzk-field__label">Название</span>
                                    <input type="text" name="name" required
                                           value="<?= e((string) $t['name']) ?>"
                                           autocomplete="off"
                                           class="pzk-field__input pzk-field__input--sm">
                                </label>
                                <label class="pzk-field">
                                    <span class="pzk-field__label">Код (латиница, нижний регистр)</span>
                                    <input type="text" name="code" required
                                           value="<?= e((string) $t['code']) ?>"
                                           pattern="[a-z][a-z0-9_-]{1,31}"
                                           autocomplete="off" spellcheck="false"
                                           class="pzk-field__input pzk-field__input--sm">
                                </label>
                                <label class="pzk-field">
                                    <span class="pzk-field__label">Описание</span>
                                    <input type="text" name="description"
                                           value="<?= e((string) ($t['description'] ?? '')) ?>"
                                           autocomplete="off"
                                           class="pzk-field__input pzk-field__input--sm">
                                </label>
                                <label class="pzk-field">
                                    <span class="pzk-field__label">Цвет (hex)</span>
                                    <input type="text" name="color"
                                           value="<?= e((string) $t['color']) ?>"
                                           pattern="#[0-9a-fA-F]{6}"
                                           autocomplete="off"
                                           class="pzk-field__input pzk-field__input--sm">
                                </label>
                                <label class="pzk-field">
                                    <span class="pzk-field__label">Иконка (эмодзи или 1–2 символа)</span>
                                    <input type="text" name="icon"
                                           value="<?= e((string) $t['icon']) ?>"
                                           autocomplete="off"
                                           class="pzk-field__input pzk-field__input--sm">
                                </label>
                                <label class="pzk-field">
                                    <span class="pzk-field__label">Gogs: владелец (опц.)</span>
                                    <input type="text" name="gogs_owner"
                                           value="<?= e((string) ($t['gogs_owner'] ?? '')) ?>"
                                           autocomplete="off" spellcheck="false"
                                           placeholder="например, kolledzh"
                                           class="pzk-field__input pzk-field__input--sm">
                                </label>
                                <label class="pzk-field">
                                    <span class="pzk-field__label">Gogs: префикс репозитория (опц.)</span>
                                    <input type="text" name="gogs_repo_prefix"
                                           value="<?= e((string) ($t['gogs_repo_prefix'] ?? '')) ?>"
                                           autocomplete="off" spellcheck="false"
                                           placeholder="например, proj-"
                                           class="pzk-field__input pzk-field__input--sm">
                                </label>
                                <button type="submit" class="pzk-btn pzk-btn--sm pzk-btn--primary">Сохранить</button>
                            </form>
                        </details>

                        <form method="post" action="index.php?action=admin_project_type_check" style="margin:0;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= $tid ?>">
                            <button type="submit" class="pzk-btn pzk-btn--sm"
                                    title="Запустить projects/<?= e((string) $t['code']) ?>/check.php">
                                ✅ Проверить
                            </button>
                        </form>

                        <form method="post" action="index.php?action=admin_project_type_regen_folder"
                              style="margin:0;"
                              onsubmit="return confirm('Перезаписать projects/<?= e((string) $t['code']) ?>/check.php из шаблона _templates/check.php? Все ручные правки в check.php будут потеряны.');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= $tid ?>">
                            <button type="submit" class="pzk-btn pzk-btn--sm"
                                    title="Перезаписать check.php из шаблона">
                                ♻ Пересоздать папку
                            </button>
                        </form>

                        <form method="post" action="index.php?action=admin_project_type_delete" style="margin:0;"
                              onsubmit="return confirm('Удалить тип «<?= e((string) $t['name']) ?>»? Проекты этого типа должны быть удалены заранее. Папка projects/<?= e((string) $t['code']) ?>/ останется на диске.');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= $tid ?>">
                            <button type="submit" class="pzk-btn pzk-btn--sm">Удалить</button>
                        </form>
                    </div>
                </div>

                <?php if (!empty($t['description'])): ?>
                    <div class="pzk-ptype__desc"><?= e((string) $t['description']) ?></div>
                <?php endif; ?>

                <div class="pzk-ptype__grid">
                    <div>
                        <div class="pzk-ptype__section-title">
                            Фазы
                            <details class="pzk-row-details" style="display:inline-block;">
                                <summary class="pzk-btn pzk-btn--sm">➕ Добавить фазу</summary>
                                <form method="post" action="index.php?action=admin_project_type_phase_save"
                                      class="pzk-edit-form" autocomplete="off">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="project_type_id" value="<?= $tid ?>">
                                    <label class="pzk-field">
                                        <span class="pzk-field__label">Номер</span>
                                        <input type="number" name="ord" min="1" max="99"
                                               value="<?= count($phases) + 1 ?>" required
                                               autocomplete="off"
                                               class="pzk-field__input pzk-field__input--sm">
                                    </label>
                                    <label class="pzk-field">
                                        <span class="pzk-field__label">Название</span>
                                        <input type="text" name="name" required
                                               placeholder="Например: Реализация"
                                               autocomplete="off"
                                               class="pzk-field__input pzk-field__input--sm">
                                    </label>
                                    <label class="pzk-field">
                                        <span class="pzk-field__label">Цвет</span>
                                        <?= $renderPhaseColor('color', $nextPhaseColor) ?>
                                    </label>
                                    <label class="pzk-field">
                                        <span class="pzk-field__label">Описание (опц.)</span>
                                        <input type="text" name="description"
                                               autocomplete="off"
                                               class="pzk-field__input pzk-field__input--sm">
                                    </label>
                                    <button type="submit" class="pzk-btn pzk-btn--sm pzk-btn--primary">Добавить</button>
                                </form>
                            </details>
                        </div>
                        <?php if ($phases === []): ?>
                            <p class="pzk-form__hint" style="margin:0;">У типа пока нет фаз. Проект не сможет двигаться по фазам.</p>
                        <?php else: ?>
                            <table class="pzk-table pzk-table--ptype-phases">
                                <thead>
                                    <tr>
                                        <th style="width:50px;">№</th>
                                        <th>Название</th>
                                        <th style="width:90px;">Цвет</th>
                                        <th style="width:220px;">Действия</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($phases as $ph): ?>
                                        <tr>
                                            <td><?= (int) $ph['ord'] ?></td>
                                            <td>
                                                <span class="pzk-ptype__phase-color" style="background:<?= e((string) $ph['color']) ?>;"></span>
                                                <strong><?= e((string) $ph['name']) ?></strong>
                                                <?php if (!empty($ph['description'])): ?>
                                                    <br><span style="font-size:0.78rem;color:var(--color-tertiary);"><?= e((string) $ph['description']) ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td><code><?= e((string) $ph['color']) ?></code></td>
                                            <td class="pzk-table__actions">
                                                <details class="pzk-row-details">
                                                    <summary class="pzk-btn pzk-btn--sm">Редактировать</summary>
                                                    <form method="post" action="index.php?action=admin_project_type_phase_save"
                                                          class="pzk-edit-form" autocomplete="off">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="id" value="<?= (int) $ph['id'] ?>">
                                                        <input type="hidden" name="project_type_id" value="<?= $tid ?>">
                                                        <label class="pzk-field">
                                                            <span class="pzk-field__label">Номер</span>
                                                            <input type="number" name="ord" min="1" max="99"
                                                                   value="<?= (int) $ph['ord'] ?>" required
                                                                   autocomplete="off"
                                                                   class="pzk-field__input pzk-field__input--sm">
                                                        </label>
                                                        <label class="pzk-field">
                                                            <span class="pzk-field__label">Название</span>
                                                            <input type="text" name="name" required
                                                                   value="<?= e((string) $ph['name']) ?>"
                                                                   autocomplete="off"
                                                                   class="pzk-field__input pzk-field__input--sm">
                                                        </label>
                                                        <label class="pzk-field">
                                                            <span class="pzk-field__label">Цвет</span>
                                                            <?= $renderPhaseColor('color', (string) $ph['color']) ?>
                                                        </label>
                                                        <label class="pzk-field">
                                                            <span class="pzk-field__label">Описание (опц.)</span>
                                                            <input type="text" name="description"
                                                                   value="<?= e((string) ($ph['description'] ?? '')) ?>"
                                                                   autocomplete="off"
                                                                   class="pzk-field__input pzk-field__input--sm">
                                                        </label>
                                                        <button type="submit" class="pzk-btn pzk-btn--sm pzk-btn--primary">Сохранить</button>
                                                    </form>
                                                </details>
                                                <form method="post" action="index.php?action=admin_project_type_phase_delete"
                                                      onsubmit="return confirm('Удалить фазу «<?= e((string) $ph['name']) ?>»?');">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="id" value="<?= (int) $ph['id'] ?>">
                                                    <input type="hidden" name="project_type_id" value="<?= $tid ?>">
                                                    <button type="submit" class="pzk-btn pzk-btn--sm">Удалить</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>

                    <div>
                        <div class="pzk-ptype__section-title">Последние проверки</div>
                        <?php if ($checks === []): ?>
                            <p class="pzk-form__hint" style="margin:0;">Ещё не запускались. Нажмите «Проверить».</p>
                        <?php else: ?>
                            <table class="pzk-table pzk-table--ptype-checks">
                                <thead>
                                    <tr>
                                        <th style="width:150px;">Когда</th>
                                        <th style="width:110px;">Кто</th>
                                        <th style="width:90px;">Итог</th>
                                        <th>Сообщения</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($checks as $chk): ?>
                                        <?php
                                            $ok  = (int) $chk['ok'] === 1;
                                            $err = [];
                                            $wrn = [];
                                            $det = [];
                                            if (!empty($chk['errors']))   $err = json_decode((string) $chk['errors'],   true) ?: [];
                                            if (!empty($chk['warnings'])) $wrn = json_decode((string) $chk['warnings'], true) ?: [];
                                            if (!empty($chk['details']))  $det = json_decode((string) $chk['details'],  true) ?: [];
                                        ?>
                                        <tr>
                                            <td style="white-space:nowrap;"><?= e((string) $chk['checked_at']) ?></td>
                                            <td><?= e((string) ($chk['checked_login'] ?? '—')) ?></td>
                                            <td>
                                                <?php if ($ok): ?>
                                                    <span class="pzk-status pzk-status--active">ok</span>
                                                <?php else: ?>
                                                    <span class="pzk-status pzk-status--inactive">fail</span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="font-size:0.82rem;">
                                                <?php if ($err !== []): ?>
                                                    <div style="color:#b91c1c;">
                                                        <?php foreach ($err as $msg): ?>
                                                            • <?= e((string) $msg) ?><br>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if ($wrn !== []): ?>
                                                    <div style="color:#92400e;">
                                                        <?php foreach ($wrn as $msg): ?>
                                                            • <?= e((string) $msg) ?><br>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if ($det !== []): ?>
                                                    <div style="color:#6b5010;">
                                                        <?php foreach ($det as $msg): ?>
                                                            • <?= e((string) $msg) ?><br>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if ($err === [] && $wrn === [] && $det === []): ?>
                                                    <span style="color:var(--color-tertiary);">—</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <details class="pzk-details" id="add-project-type" style="margin-top: var(--space-md);">
        <summary class="pzk-details__summary">➕ Добавить тип проекта</summary>
        <form method="post" action="index.php?action=admin_project_type_save"
              class="pzk-form" autocomplete="off">
            <?= csrf_field() ?>
            <div>
                <h3 class="pzk-form__title">Новый тип проекта</h3>
                <p class="pzk-form__hint">
                    После создания будет создана папка <code>projects/&lt;код&gt;/</code>
                    и скопирован шаблон <code>check.php</code> из <code>_templates/</code>.
                    Фазы типа можно добавить ниже в карточке типа.
                </p>
            </div>
            <div class="pzk-form__grid">
                <label class="pzk-field">
                    <span class="pzk-field__label">Название</span>
                    <input class="pzk-field__input" type="text" name="name" required
                           placeholder="Например: Лабораторная" autocomplete="off">
                </label>
                <label class="pzk-field">
                    <span class="pzk-field__label">Код</span>
                    <input class="pzk-field__input" type="text" name="code" required
                           pattern="[a-z][a-z0-9_-]{1,31}"
                           placeholder="lab" autocomplete="off" spellcheck="false">
                </label>
                <label class="pzk-field">
                    <span class="pzk-field__label">Описание (опц.)</span>
                    <input class="pzk-field__input" type="text" name="description"
                           placeholder="Короткое пояснение для преподавателя" autocomplete="off">
                </label>
                <label class="pzk-field">
                    <span class="pzk-field__label">Цвет (hex)</span>
                    <input class="pzk-field__input" type="text" name="color" value="#8b6914"
                           pattern="#[0-9a-fA-F]{6}" autocomplete="off">
                </label>
                <label class="pzk-field">
                    <span class="pzk-field__label">Иконка</span>
                    <input class="pzk-field__input" type="text" name="icon" value="📌"
                           autocomplete="off">
                </label>
                <label class="pzk-field">
                    <span class="pzk-field__label">Gogs: владелец (опц.)</span>
                    <input class="pzk-field__input" type="text" name="gogs_owner"
                           placeholder="например, kolledzh"
                           autocomplete="off" spellcheck="false">
                </label>
                <label class="pzk-field">
                    <span class="pzk-field__label">Gogs: префикс репозитория (опц.)</span>
                    <input class="pzk-field__input" type="text" name="gogs_repo_prefix"
                           placeholder="например, proj-"
                           autocomplete="off" spellcheck="false">
                </label>
            </div>
            <div class="pzk-form__actions">
                <button type="submit" class="pzk-btn pzk-btn--primary">Создать тип</button>
            </div>
        </form>
    </details>
</section>

<script>
(function () {
    'use strict';
    var wrappers = document.querySelectorAll('[data-color-picker]');
    if (!wrappers.length) return;

    wrappers.forEach(function (wrap) {
        var native = wrap.querySelector('.pzk-color-picker__native');
        var text   = wrap.querySelector('.pzk-color-picker__text');
        if (!native || !text) return;

        var field   = wrap.closest('.pzk-field') || wrap.parentElement;
        var swatches = field ? field.querySelectorAll('.pzk-color-picker__swatch') : [];

        function isHex(v) { return /^#[0-9a-fA-F]{6}$/.test(v); }

        function paint(v, fromText) {
            if (!isHex(v)) return;
            var lower = v.toLowerCase();
            if (native.value.toLowerCase() !== lower) native.value = lower;
            if (!fromText && text.value.toLowerCase() !== lower) text.value = lower;
            swatches.forEach(function (sw) {
                sw.classList.toggle(
                    'is-active',
                    (sw.getAttribute('data-color') || '').toLowerCase() === lower
                );
            });
        }

        native.addEventListener('input', function () { paint(native.value, false); });
        text.addEventListener('input', function () { paint(text.value, true); });

        swatches.forEach(function (sw) {
            sw.addEventListener('click', function () {
                paint(sw.getAttribute('data-color') || '', false);
            });
        });

        // Начальная синхронизация подсветки
        paint(text.value, true);
    });
})();
</script>