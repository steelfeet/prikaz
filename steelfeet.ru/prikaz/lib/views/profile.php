<?php
declare(strict_types=1);

// Профиль может рендериться без data.php — подстрахуемся:
// если $studentProfile не передан, загрузим сами (только для роли student).
if (!isset($studentProfile)) {
    $studentProfile = null;
    if (in_array('student', $currentUser['roles'], true)) {
        try {
            $stmt = db()->prepare(
                'SELECT s.*, u.login, u.email
                 FROM students s
                 JOIN users u ON u.id = s.user_id
                 WHERE s.user_id = :u LIMIT 1'
            );
            $stmt->execute([':u' => (int) $currentUser['id']]);
            $studentProfile = $stmt->fetch() ?: null;
        } catch (Throwable $e) {
            $studentProfile = null;
        }
    }
}

$flash = flash_get();
$isStudent = in_array('student', $currentUser['roles'], true);
$hasStudentRow = $isStudent && $studentProfile !== null;
$currentEmail = (string) ($currentUser['email'] ?? '');
$currentVk    = $hasStudentRow ? (string) ($studentProfile['vk_url'] ?? '') : '';
$currentTg    = $hasStudentRow ? (string) ($studentProfile['telegram'] ?? '') : '';
?>
<div class="workspace workspace--profile">
    <section class="panel">
        <div class="panel__head">
            <div>
                <h2 class="panel__title"><?= $isStudent ? 'Личный кабинет' : 'Профиль' ?></h2>
                <p class="panel__subtitle">
                    Логин: <code><?= e((string) ($currentUser['login'] ?? '')) ?></code> ·
                    ФИО: <?= e((string) ($currentUser['full_name'] ?? '')) ?>
                </p>
            </div>
        </div>

        <?php if ($flash !== null): ?>
            <div class="notification notification--<?= $flash['type'] === 'success' ? 'success' : 'danger' ?>">
                <div class="notification__body"><?= e($flash['message']) ?></div>
            </div>
        <?php endif; ?>

        <form method="post" action="index.php?action=change_own_password"
              class="profile-form" autocomplete="off">
            <?= csrf_field() ?>
            <h3 style="font-family:'Cormorant Garamond','Inter',serif; font-size:1.3rem; font-weight:600; color:#6b5010; margin:0 0 var(--space-sm);">
                ⚙ Данные учётной записи
            </h3>
            <p style="margin:0 0 var(--space-sm); font-size:0.9rem; color:#6b5d3f; font-style:italic;">
                Логин и ФИО меняет только администратор. Остальное можно изменить здесь.
                Новый пароль можно не заполнять, если пароль не меняется.
            </p>

            <div class="profile-form__grid">
                <label class="profile-field">
                    <span class="profile-field__label">Email</span>
                    <input class="profile-field__input" type="email" name="email"
                           value="<?= e($currentEmail) ?>"
                           placeholder="you@example.ru"
                           autocomplete="email">
                </label>
                <?php if ($hasStudentRow): ?>
                    <label class="profile-field">
                        <span class="profile-field__label">Страница VK</span>
                        <input class="profile-field__input" type="text" name="vk_url"
                               value="<?= e($currentVk) ?>"
                               placeholder="https://vk.com/..."
                               autocomplete="off">
                    </label>
                    <label class="profile-field">
                        <span class="profile-field__label">Telegram</span>
                        <input class="profile-field__input" type="text" name="telegram"
                               value="<?= e($currentTg) ?>"
                               placeholder="username"
                               autocomplete="off">
                    </label>
                <?php endif; ?>
            </div>

            <div class="profile-form__grid">
                <label class="profile-field">
                    <span class="profile-field__label">Текущий пароль</span>
                    <input class="profile-field__input" type="password" name="current_password" required
                           autocomplete="current-password"
                           placeholder="нужен для подтверждения">
                </label>
                <label class="profile-field">
                    <span class="profile-field__label">Новый пароль (опционально)</span>
                    <input class="profile-field__input" type="password" name="new_password"
                           minlength="<?= PRIKAZ_PASSWORD_MIN ?>"
                           autocomplete="new-password"
                           placeholder="не короче <?= PRIKAZ_PASSWORD_MIN ?> символов">
                </label>
                <label class="profile-field">
                    <span class="profile-field__label">Повтор нового пароля</span>
                    <input class="profile-field__input" type="password" name="repeat_password"
                           autocomplete="new-password"
                           placeholder="только если меняете пароль">
                </label>
            </div>

            <div class="profile-form__actions">
                <button type="submit" class="profile-btn profile-btn--primary">Сохранить</button>
            </div>
        </form>

        <?php if ($isStudent): ?>
            <p class="panel__subtitle" style="margin-top: var(--space-md);">
                <a href="index.php?page=student">← Вернуться на главную страницу профиля</a>
            </p>
        <?php endif; ?>
    </section>
</div>

<style>
.profile-form {
    padding: var(--space-md, 16px);
    display: flex;
    flex-direction: column;
    gap: 12px;
    background: #fbf7ec;
    border: 1px solid #e8dcc0;
    border-left: 4px solid #8b6914;
}
.profile-form__grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 10px 14px;
}
.profile-field { display: flex; flex-direction: column; gap: 4px; }
.profile-field__label {
    font-family: 'Cormorant Garamond', 'Inter', serif;
    font-size: 1rem;
    font-weight: 600;
    letter-spacing: 0.03em;
    color: #6b5010;
}
.profile-field__input {
    padding: 8px 12px;
    border: 1px solid #d4c8a8;
    background: #ffffff;
    font-family: 'Inter', -apple-system, sans-serif;
    font-size: 1.02rem;
    color: #1a1a1a;
    border-radius: 2px;
    width: 100%;
}
.profile-field__input:focus {
    outline: none;
    border-color: #8b6914;
    box-shadow: 0 0 0 3px rgba(139, 105, 20, 0.12);
}
.profile-form__actions {
    display: flex;
    justify-content: flex-end;
    padding-top: 10px;
    border-top: 1px solid #e8dcc0;
}
.profile-btn {
    padding: 6px 14px;
    font-family: 'Cormorant Garamond', 'Inter', serif;
    font-size: 1.05rem;
    font-weight: 600;
    letter-spacing: 0.06em;
    border: 1px solid rgba(26, 26, 26, 0.2);
    background: #fff;
    color: #2d2d2d;
    cursor: pointer;
    border-radius: 2px;
    line-height: 1.2;
}
.profile-btn:hover {
    background: rgba(139, 105, 20, 0.06);
    color: #1a1a1a;
    border-color: rgba(139, 105, 20, 0.4);
}
.profile-btn--primary {
    background: #8b6914;
    color: #fff;
    border-color: #8b6914;
    box-shadow: 0 2px 6px rgba(139, 105, 20, 0.2);
}
.profile-btn--primary:hover {
    background: #a67c00;
    border-color: #a67c00;
    color: #fff;
}
</style>