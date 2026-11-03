<?php
declare(strict_types=1);

$section = $_GET['section'] ?? 'users';
if (!in_array($section, ['users', 'students', 'parents', 'lessons', 'projects', 'audit'], true)) {
    $section = 'users';
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

    <?php if ($section === 'users'): ?>
        <section class="panel">
            <div class="panel__head">
                <div>
                    <h2 class="panel__title">Пользователи</h2>
                    <p class="panel__subtitle">Создание, деактивация и сброс паролей. Регистрация — только через админку.</p>
                </div>
            </div>

            <details class="pzk-details">
                <summary class="pzk-details__summary">➕ Создать пользователя</summary>
                <form method="post" action="index.php?action=admin_user_create" class="pzk-form">
                    <?= csrf_field() ?>
                    <div class="pzk-form__grid">
                        <label class="pzk-field">
                            <span class="pzk-field__label">Логин</span>
                            <input class="pzk-field__input" type="text" name="login" required
                                   pattern="[a-zA-Z0-9_.-]{3,64}"
                                   title="3-64 символа: латиница, цифры, точка, дефис, подчёркивание">
                        </label>
                        <label class="pzk-field">
                            <span class="pzk-field__label">ФИО</span>
                            <input class="pzk-field__input" type="text" name="full_name" required>
                        </label>
                        <label class="pzk-field">
                            <span class="pzk-field__label">Email</span>
                            <input class="pzk-field__input" type="email" name="email">
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
                            <input class="pzk-field__input" type="text" name="group_name" placeholder="46">
                        </label>
                        <label class="pzk-field">
                            <span class="pzk-field__label">Псевдоним (для студента)</span>
                            <input class="pzk-field__input" type="text" name="alias" placeholder="s-46-01">
                        </label>
                        <label class="pzk-field">
                            <span class="pzk-field__label">Временный пароль</span>
                            <input class="pzk-field__input" type="text" name="password" required
                                   minlength="<?= PRIKAZ_PASSWORD_MIN ?>">
                        </label>
                    </div>
                    <button type="submit" class="pzk-btn pzk-btn--primary">Создать</button>
                </form>
            </details>

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
                                        <form method="post" action="index.php?action=admin_user_reset_password" class="pzk-row-details__form">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                                            <input type="text" name="password" placeholder="Новый пароль"
                                                   required minlength="<?= PRIKAZ_PASSWORD_MIN ?>"
                                                   class="pzk-field__input">
                                            <button type="submit" class="pzk-btn pzk-btn--sm pzk-btn--primary">ОК</button>
                                        </form>
                                    </details>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
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
                  class="pzk-inline-form"
                  style="margin-bottom: var(--space-md);">
                <?= csrf_field() ?>
                <label>Родитель:
                    <select name="parent_id" class="pzk-field__input">
                        <?php foreach ($adminParents as $p): ?>
                            <option value="<?= (int) $p['id'] ?>">
                                <?= e($p['full_name']) ?> (#<?= (int) $p['id'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Студент:
                    <select name="student_id" class="pzk-field__input">
                        <?php foreach ($adminStudents as $s): ?>
                            <option value="<?= (int) $s['id'] ?>">
                                <?= e($s['full_name']) ?> · <?= e($s['group_name']) ?>
                            </option>
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
                            <tr>
                                <td colspan="3" class="pzk-table__empty">
                                    Пока ни одна связка не создана.
                                </td>
                            </tr>
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
            </div>

            <details class="pzk-details">
                <summary class="pzk-details__summary">➕ Добавить урок</summary>
                <form method="post" action="index.php?action=admin_lesson_save" class="pzk-form">
                    <?= csrf_field() ?>
                    <div class="pzk-form__grid">
                        <label class="pzk-field">
                            <span class="pzk-field__label">№</span>
                            <input class="pzk-field__input" type="number" name="ord" required min="1">
                        </label>
                        <label class="pzk-field">
                            <span class="pzk-field__label">Дата</span>
                            <input class="pzk-field__input" type="text" name="date_str" required placeholder="03.09">
                        </label>
                        <label class="pzk-field">
                            <span class="pzk-field__label">Тема</span>
                            <input class="pzk-field__input" type="text" name="topic" required>
                        </label>
                        <label class="pzk-field">
                            <span class="pzk-field__label">Домашнее задание</span>
                            <input class="pzk-field__input" type="text" name="homework">
                        </label>
                    </div>
                    <button type="submit" class="pzk-btn pzk-btn--primary">Добавить</button>
                </form>
            </details>

            <div class="pzk-table-wrap" style="margin-top: var(--space-md);">
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
                                    <form method="post" action="index.php?action=admin_lesson_save" class="pzk-lesson-form">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= (int) $l['id'] ?>">
                                        <input class="pzk-field__input pzk-field__input--sm" name="ord" type="number"
                                               value="<?= (int) $l['ord'] ?>" required>
                                        <input class="pzk-field__input pzk-field__input--sm" name="date_str"
                                               value="<?= e($l['date_str']) ?>" required>
                                        <input class="pzk-field__input pzk-field__input--sm" name="topic"
                                               value="<?= e($l['topic']) ?>" required>
                                        <input class="pzk-field__input pzk-field__input--sm" name="homework"
                                               value="<?= e($l['homework']) ?>">
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
                            <tr>
                                <td colspan="5" class="pzk-table__empty">
                                    Уроков пока нет. Добавьте первый.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

    <?php elseif ($section === 'projects'): ?>
        <section class="panel">
            <div class="panel__head">
                <div>
                    <h2 class="panel__title">Проекты</h2>
                    <p class="panel__subtitle">Создание проектов, назначение участников, тип (оригинал/форк).</p>
                </div>
            </div>

            <details class="pzk-details">
                <summary class="pzk-details__summary">➕ Создать проект</summary>
                <form method="post" action="index.php?action=admin_project_create" class="pzk-form">
                    <?= csrf_field() ?>
                    <div class="pzk-form__grid">
                        <label class="pzk-field">
                            <span class="pzk-field__label">Название</span>
                            <input class="pzk-field__input" type="text" name="title" required>
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
                                    <option value="<?= (int) $p['id'] ?>">
                                        #<?= (int) $p['id'] ?> · <?= e($p['title']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="pzk-field">
                            <span class="pzk-field__label">URL репозитория</span>
                            <input class="pzk-field__input" type="url" name="repo_url" placeholder="https://gogs.example/...">
                        </label>
                    </div>

                    <fieldset class="pzk-fieldset">
                        <legend class="pzk-field__label">Участники</legend>
                        <?php if ($adminStudents === []): ?>
                            <p class="muted">Сначала создайте студентов в разделе «Пользователи».</p>
                        <?php else: ?>
                            <div class="pzk-checkbox-grid">
                                <?php foreach ($adminStudents as $s): ?>
                                    <label class="pzk-checkbox">
                                        <input type="checkbox" name="students[]" value="<?= (int) $s['id'] ?>">
                                        <?= e($s['full_name']) ?>
                                        <?php if (!empty($s['alias'])): ?>
                                            <code>(<?= e($s['alias']) ?>)</code>
                                        <?php endif; ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </fieldset>

                    <button type="submit" class="pzk-btn pzk-btn--primary">Создать</button>
                </form>
            </details>

            <?php if ($adminProjects !== []): ?>
                <div class="pzk-table-wrap" style="margin-top: var(--space-md);">
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
                                <?php
                                $typeInfo = $typesMeta[$p['type']] ?? ['label' => $p['type'], 'color' => '#8b6914'];
                                ?>
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
            <?php endif; ?>
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
                                        <?php if (!empty($a['target_id'])): ?>
                                            #<?= (int) $a['target_id'] ?>
                                        <?php endif; ?>
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
                            <tr>
                                <td colspan="6" class="pzk-table__empty">
                                    Записей пока нет.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>
</div>

<style>
/* =============================================================================
   Локальные стили админки. Применяются только на страницах ?page=admin.
   Не трогают prikaz.css, чтобы не менять вид других разделов.
   ============================================================================= */

/* 1. Курсор на раскрывающихся блоках «➕ …» */
.pzk-details__summary {
    cursor: pointer;
    transition: background-color 0.15s, color 0.15s;
}
.pzk-details__summary:hover {
    background-color: rgba(139, 105, 20, 0.08);
    color: #6b5010;
}
.pzk-details[open] .pzk-details__summary:hover {
    color: #4a3a10;
}

/* 2. Отступы в таблицах — просторнее */
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
.pzk-table td:first-child {
    padding-left: 22px;
}
.pzk-table th:last-child,
.pzk-table td:last-child {
    padding-right: 22px;
}

/* Обёртка таблицы — с небольшим внутренним отступом от рамки */
.pzk-table-wrap {
    overflow: auto;
    border: 1px solid rgba(26, 26, 26, 0.1);
    background: #fff;
}
.pzk-table-wrap > .pzk-table {
    border: none;
}

/* 3. Колонка действий — чтобы кнопки не прилипали друг к другу */
.pzk-table td.pzk-table__actions {
    display: flex;
    gap: 8px;
    align-items: center;
    flex-wrap: wrap;
}
.pzk-table td.pzk-table__actions form {
    display: inline-block;
}

/* 4. Раскрывающийся «Сбросить пароль» в строке */
.pzk-row-details summary {
    list-style: none;
    cursor: pointer;
}
.pzk-row-details summary::-webkit-details-marker {
    display: none;
}
.pzk-row-details summary::marker {
    content: '';
}
.pzk-row-details__form {
    display: flex;
    gap: 6px;
    margin-top: 6px;
    align-items: center;
}
.pzk-row-details__form input {
    padding: 4px 8px;
    font-size: 0.8rem;
    min-width: 160px;
}

/* 5. «Пустая» строка-заглушка в таблице */
.pzk-table__empty {
    text-align: center;
    color: var(--color-tertiary);
    font-style: italic;
    padding: 24px 18px !important;
}

/* 6. Уроки: строка таблицы как форма (без перекоса при большом количестве) */
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
.pzk-lesson-delete {
    display: block;
    margin-top: 6px;
}

/* 7. Инлайн-форма с привязкой родителя */
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
    font-size: 0.85rem;
    color: var(--color-secondary);
}

/* 8. Код псевдонима и IP в ячейках */
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

/* 9. Узкая ширина — прокрутка таблиц без прилипания */
@media (max-width: 900px) {
    .pzk-table th,
    .pzk-table td {
        padding: 10px 12px;
    }
    .pzk-table th:first-child,
    .pzk-table td:first-child {
        padding-left: 14px;
    }
    .pzk-table th:last-child,
    .pzk-table td:last-child {
        padding-right: 14px;
    }
    .pzk-lesson-form {
        grid-template-columns: 1fr;
    }
}
</style>