<?php
declare(strict_types=1);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>CRM «ИТ Школа РТК» — вход</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@300;400;500;600&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/styles.css">
</head>
<body class="auth-body">
    <div class="auth-card">
        <h1 class="auth-card__brand">Кладезь</h1>
        <p class="auth-card__subtitle">CRM «ИТ Школа РТК» — вход в систему</p>

        <?php if ($error !== ''): ?>
            <div class="auth-error"><?= e($error) ?></div>
        <?php endif; ?>

        <p class="auth-oidc-hint">
            Авторизация через <strong>Keycloak</strong> (OIDC).
            Выберите роль для входа в демо:
        </p>

        <div class="auth-roles">
            <a class="auth-role-btn" href="index.php?action=oidc_login&login_hint=manager">
                <span class="auth-role-btn__icon">📋</span>
                <span>Менеджер</span>
            </a>
            <a class="auth-role-btn" href="index.php?action=oidc_login&login_hint=supervisor">
                <span class="auth-role-btn__icon">🧭</span>
                <span>Руководитель</span>
            </a>
            <a class="auth-role-btn" href="index.php?action=oidc_login&login_hint=university_rep">
                <span class="auth-role-btn__icon">🎓</span>
                <span>Представитель ВУЗа</span>
            </a>
            <a class="auth-role-btn" href="index.php?action=oidc_login&login_hint=student">
                <span class="auth-role-btn__icon">🎒</span>
                <span>Студент</span>
            </a>
            <a class="auth-role-btn" href="index.php?action=oidc_login&login_hint=parent">
                <span class="auth-role-btn__icon">👪</span>
                <span>Родитель</span>
            </a>
        </div>

        <p class="auth-oidc-note">
            Сессия подтверждена цифровой подписью JWT.
            После входа CRM и LMS используют один и тот же токен —
            это сквозная авторизация.
        </p>
    </div>
</body>
</html>