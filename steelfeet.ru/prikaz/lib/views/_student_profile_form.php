<?php
declare(strict_types=1);

/**
 * Partial: личный кабинет студента — email и смена пароля.
 *
 * Ожидает $currentUser и $studentProfile (из data.php).
 * Вставляется в lib/views/student.php через require.
 */

if (!in_array('student', $currentUser['roles'], true)) return;
if (!$studentProfile) return;

$flash = flash_get();
$currentEmail = (string) ($studentProfile['email'] ?? '');
?>
<details class="pzk-details" open style="margin-bottom: var(--space-md);">
    <summary class="pzk-details__summary">👤 Профиль</summary>
    <form method="post" action="index.php?action=student_profile_save"
          class="pzk-form" autocomplete="off">
        <?= csrf_field() ?>
        <?php if ($flash !== null): ?>
            <div class="notification notification--<?= $flash['type'] === 'success' ? 'success' : 'danger' ?>">
                <div class="notification__body"><?= e($flash['message']) ?></div>
            </div>
        <?php endif; ?>
        <p class="pzk-form__hint" style="margin:0;">
            Здесь можно сменить email и пароль. Логин и ФИО меняет только администратор.
        </p>
        <div class="pzk-form__grid">
            <label class="pzk-field">
                <span class="pzk-field__label">Email</span>
                <input class="pzk-field__input" type="email" name="email" required
                       value="<?= e($currentEmail) ?>" autocomplete="email">
            </label>
            <label class="pzk-field">
                <span class="pzk-field__label">Новый пароль (опционально)</span>
                <input class="pzk-field__input" type="password" name="new_password"
                       autocomplete="new-password"
                       minlength="<?= PRIKAZ_PASSWORD_MIN ?>"
                       placeholder="не короче <?= PRIKAZ_PASSWORD_MIN ?> символов">
            </label>
            <label class="pzk-field">
                <span class="pzk-field__label">Повтор пароля</span>
                <input class="pzk-field__input" type="password" name="repeat_password"
                       autocomplete="new-password">
            </label>
        </div>
        <div class="pzk-form__actions">
            <button type="submit" class="pzk-btn pzk-btn--primary">Сохранить</button>
        </div>
    </form>
</details>