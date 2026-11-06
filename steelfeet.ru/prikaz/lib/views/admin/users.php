<?php
declare(strict_types=1);
?>
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
                    <th style="width:50px;">ID</th>
                    <th style="width:130px;">Логин</th>
                    <th>ФИО</th>
                    <th style="width:110px;">Роли</th>
                    <th style="width:120px;">Статус</th>
                    <th style="width:150px;">Последний вход</th>
                    <th style="width:260px;">Действия</th>
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

    <details class="pzk-details" id="add-user" style="margin-top: var(--space-sm);">
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