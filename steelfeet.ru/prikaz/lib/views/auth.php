<?php
declare(strict_types=1);

$flash = flash_get();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Вход · <?= e(PRIKAZ_APP_NAME) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@300;400;500;600;700&family=Inter:wght@300;400;500;600&family=Ruslan+Display&display=swap" rel="stylesheet">
    <style>
        /* =====================================================================
           Страница входа Приказа — славянская стилистика.
           Буквица «П» без рамки, с завитками по бокам всего слова.
           ===================================================================== */

        * { box-sizing: border-box; margin: 0; padding: 0; }

        .prikaz-auth-body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            background:
                radial-gradient(circle at 18% 12%, rgba(184, 148, 78, 0.10), transparent 42%),
                radial-gradient(circle at 82% 88%, rgba(139, 26, 26, 0.07), transparent 42%),
                #f4eddc;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            color: #1a1a1a;
            -webkit-font-smoothing: antialiased;
            line-height: 1.5;
        }

        /* ===== Карточка — «лист старинной книги» ===== */
        .prikaz-card {
            position: relative;
            width: 100%;
            max-width: 540px;
            padding: 52px 48px 40px;
            background: #fbf7ec;
            border: 1px solid #d4c8a8;
            box-shadow:
                inset 0 0 0 8px #fbf7ec,
                inset 0 0 0 9px #e8dcc0,
                0 12px 40px rgba(80, 60, 20, 0.15);
        }

        .prikaz-card::before,
        .prikaz-card::after {
            content: '';
            position: absolute;
            width: 22px;
            height: 22px;
            border: 1px solid #8b6914;
            opacity: 0.65;
        }
        .prikaz-card::before {
            top: 14px;
            left: 14px;
            border-right: none;
            border-bottom: none;
        }
        .prikaz-card::after {
            bottom: 14px;
            right: 14px;
            border-left: none;
            border-top: none;
        }

        /* ===== Орнамент над заголовком ===== */
        .prikaz-ornament-top {
            text-align: center;
            color: #b8944e;
            font-size: 0.85rem;
            margin-bottom: 18px;
            letter-spacing: 1em;
            padding-left: 1em;
            user-select: none;
        }

        /* ===== Заголовок с буквицей и завитками ===== */
        .prikaz-title-block {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            margin: 0 0 12px;
            line-height: 1;
        }

        /* Завитки-«крины» по бокам */
        .prikaz-curl {
            font-family: 'Cormorant Garamond', serif;
            font-size: 2.4rem;
            line-height: 1;
            color: #b8944e;
            text-shadow: 0 1px 0 rgba(255, 255, 255, 0.6);
            user-select: none;
            flex-shrink: 0;
            position: relative;
            top: -0.15em;
        }
        .prikaz-curl--left  { transform: scaleX(-1); }
        .prikaz-curl--right { transform: none; }

        /* Буквица «П» — без рамки, крупная */
        .prikaz-dropcap {
            font-family: 'Ruslan Display', serif;
            font-size: 5.8rem;
            line-height: 0.85;
            color: #8b1a1a;
            letter-spacing: 0;
            text-shadow:
                0 1px 0 rgba(255, 255, 255, 0.75),
                0 3px 8px rgba(139, 26, 26, 0.20);
            user-select: none;
            flex-shrink: 0;
        }

        /* Остальная часть слова */
        .prikaz-title-rest {
            font-family: 'Ruslan Display', serif;
            font-weight: 400;
            font-size: 3rem;
            line-height: 1;
            color: #8b1a1a;
            letter-spacing: 0.06em;
            text-shadow:
                0 1px 0 rgba(255, 255, 255, 0.7),
                0 2px 6px rgba(139, 26, 26, 0.12);
        }

        .prikaz-subtitle {
            text-align: center;
            font-family: 'Cormorant Garamond', serif;
            font-size: 1rem;
            font-style: italic;
            color: #6b5d3f;
            letter-spacing: 0.04em;
            margin: 0 0 4px;
        }

        /* ===== Разделитель с ромбом ===== */
        .prikaz-divider {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            margin: 22px 0;
            color: #b8944e;
            user-select: none;
        }
        .prikaz-divider::before,
        .prikaz-divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: linear-gradient(90deg, transparent, #d4c8a8 30%, #d4c8a8 70%, transparent);
        }
        .prikaz-divider span {
            font-size: 0.8rem;
            line-height: 1;
            letter-spacing: 0;
        }

        /* ===== Расшифровка ПРИКАЗ ===== */
        .prikaz-acronym {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px 32px;
            list-style: none;
            margin: 0;
            padding: 0 8px;
            font-family: 'Cormorant Garamond', serif;
        }
        .prikaz-acronym li {
            display: flex;
            align-items: baseline;
            line-height: 1.1;
        }
        .prikaz-acronym__letter {
            font-family: 'Ruslan Display', serif;
            font-size: 1.7rem;
            line-height: 1;
            color: #8b1a1a;
            margin-right: 2px;
            text-shadow: 0 1px 0 rgba(255, 255, 255, 0.7);
        }
        .prikaz-acronym__word {
            font-size: 1.1rem;
            color: #2d2d2d;
            letter-spacing: 0.01em;
        }

        /* ===== Ошибка входа ===== */
        .prikaz-auth-error {
            background: #fef0ee;
            border-left: 3px solid #8b1a1a;
            color: #8b1a1a;
            padding: 10px 14px;
            font-size: 0.85rem;
            margin: 0 0 16px;
            font-family: 'Inter', sans-serif;
        }

        /* ===== Форма ===== */
        .prikaz-form {
            display: flex;
            flex-direction: column;
            gap: 16px;
            margin: 8px 0 16px;
        }
        .prikaz-field {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        .prikaz-field__label {
            font-family: 'Inter', sans-serif;
            font-size: 0.68rem;
            text-transform: uppercase;
            letter-spacing: 0.18em;
            color: #6b5d3f;
            font-weight: 500;
        }
        .prikaz-field input {
            padding: 11px 13px;
            border: 1px solid #d4c8a8;
            background: #fefcf6;
            font-family: 'Inter', sans-serif;
            font-size: 0.95rem;
            color: #1a1a1a;
            transition: border-color 0.15s, box-shadow 0.15s, background-color 0.15s;
        }
        .prikaz-field input:focus {
            outline: none;
            border-color: #8b6914;
            background: #ffffff;
            box-shadow: 0 0 0 3px rgba(139, 105, 20, 0.12);
        }

        /* ===== Кнопка «Войти» ===== */
        .prikaz-btn {
            margin-top: 6px;
            padding: 13px;
            background: #8b6914;
            color: #fff;
            border: none;
            font-family: 'Cormorant Garamond', serif;
            font-size: 1.15rem;
            font-weight: 500;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            cursor: pointer;
            transition: background-color 0.2s, box-shadow 0.2s;
        }
        .prikaz-btn:hover {
            background: #a67c00;
            box-shadow: 0 2px 8px rgba(139, 105, 20, 0.25);
        }
        .prikaz-btn:active {
            background: #6b5010;
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.15);
        }
        .prikaz-btn:focus-visible {
            outline: 2px solid #8b1a1a;
            outline-offset: 2px;
        }

        /* ===== Подпись ===== */
        .prikaz-note {
            text-align: center;
            font-family: 'Cormorant Garamond', serif;
            font-size: 0.85rem;
            font-style: italic;
            color: #6b5d3f;
            margin: 0 0 18px;
        }

        /* ===== Роли в системе ===== */
        .prikaz-roles {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 6px 12px;
            padding-top: 16px;
            border-top: 1px solid #e8dcc0;
            font-family: 'Cormorant Garamond', serif;
            font-size: 0.85rem;
            font-variant: small-caps;
            letter-spacing: 0.06em;
            color: #8b7a5c;
        }
        .prikaz-roles span[aria-hidden="true"] {
            color: #b8944e;
            font-weight: 600;
        }

        /* ===== Мобильный ===== */
        @media (max-width: 560px) {
            .prikaz-auth-body { padding: 16px; }
            .prikaz-card { padding: 36px 24px 28px; }

            .prikaz-title-block { gap: 8px; }
            .prikaz-dropcap { font-size: 4rem; }
            .prikaz-title-rest { font-size: 2.2rem; }
            .prikaz-curl { font-size: 1.8rem; }

            .prikaz-acronym {
                grid-template-columns: 1fr;
                gap: 6px;
                padding: 0;
            }
            .prikaz-acronym__letter { font-size: 1.5rem; }
            .prikaz-acronym__word { font-size: 1rem; }
        }
    </style>
</head>
<body class="prikaz-auth-body">
    <div class="prikaz-card">
        <div class="prikaz-ornament-top" aria-hidden="true">✦</div>

        <div class="prikaz-title-block">
            <span class="prikaz-curl prikaz-curl--left" aria-hidden="true">❧</span>
            <span class="prikaz-dropcap" aria-hidden="true">П</span>
            <h1 class="prikaz-title-rest" aria-label="Приказ">риказ</h1>
            <span class="prikaz-curl prikaz-curl--right" aria-hidden="true">❧</span>
        </div>
        <p class="prikaz-subtitle">управление проектами и&nbsp;аттестацией</p>

        <div class="prikaz-divider" aria-hidden="true"><span>❖</span></div>

        <ul class="prikaz-acronym" aria-label="Расшифровка названия">
            <li><span class="prikaz-acronym__letter">П</span><span class="prikaz-acronym__word">роекты</span></li>
            <li><span class="prikaz-acronym__letter">Р</span><span class="prikaz-acronym__word">егламенты</span></li>
            <li><span class="prikaz-acronym__letter">И</span><span class="prikaz-acronym__word">нструменты</span></li>
            <li><span class="prikaz-acronym__letter">К</span><span class="prikaz-acronym__word">онтроль</span></li>
            <li><span class="prikaz-acronym__letter">А</span><span class="prikaz-acronym__word">ттестация</span></li>
            <li><span class="prikaz-acronym__letter">З</span><span class="prikaz-acronym__word">ащита</span></li>
        </ul>

        <div class="prikaz-divider" aria-hidden="true"><span>❖</span></div>

        <?php if ($flash !== null): ?>
            <div class="prikaz-auth-error"><?= e($flash['message']) ?></div>
        <?php endif; ?>

        <form method="post" action="index.php?page=login&action=login" class="prikaz-form">
            <?= csrf_field() ?>
            <label class="prikaz-field">
                <span class="prikaz-field__label">Логин</span>
                <input type="text" name="login" required autofocus autocomplete="username">
            </label>
            <label class="prikaz-field">
                <span class="prikaz-field__label">Пароль</span>
                <input type="password" name="password" required autocomplete="current-password">
            </label>
            <button type="submit" class="prikaz-btn">Войти</button>
        </form>

        <p class="prikaz-note">Регистрация&nbsp;— только через администратора</p>

        <div class="prikaz-roles" aria-label="Роли в системе">
            <span>Администратор</span>
            <span aria-hidden="true">·</span>
            <span>Преподаватель</span>
            <span aria-hidden="true">·</span>
            <span>Студент</span>
            <span aria-hidden="true">·</span>
            <span>Родитель</span>
        </div>
    </div>
</body>
</html>