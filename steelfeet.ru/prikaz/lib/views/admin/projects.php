<?php
declare(strict_types=1);

$typesMeta = project_types_meta();
?>
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
                        <th style="width:50px;">ID</th>
                        <th>Название</th>
                        <th style="width:120px;">Тип</th>
                        <th style="width:110px;">Участники</th>
                        <th style="width:80px;">Фаза</th>
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

    <details class="pzk-details" id="add-project" style="margin-top: var(--space-sm);">
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