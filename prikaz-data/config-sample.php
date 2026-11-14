<?php
declare(strict_types=1);

/**
 * Конфигурация Приказа.
 * Лежит вне webroot — по HTTP не отдаётся.
 *
 * Права на файл: 640 (владелец = пользователь веб-сервера).
 * Пароль администратора ниже — в открытом виде, поэтому файл не должен
 * быть доступен никому, кроме PHP и владельца сайта.
 */

return [
    'app_name'         => 'Приказ',
    'db_path'          => __DIR__ . '/prikaz.sqlite',
    'storage_path'     => __DIR__ . '/storage',
    'session_name'     => 'prikaz_session',
    'session_lifetime' => 86400 * 7,
    'password_min'     => 8,
    'csrf_key'         => 'prikaz_csrf_token',

    // Первый администратор — создаётся при инициализации БД (db/init.php).
    // Сменить пароль сразу после первого входа через «Профиль».
    // Пароль должен быть не короче password_min.
    'admin_login'      => 'admin',
    'admin_password'   => 'Admin',
    'admin_fullname'   => 'Администратор',


    'gogs_base_url' => 'https://gogs.example',   // URL вашего Gogs, БЕЗ слэша на конце
    'gogs_token'    => '',                       // personal access token (Settings → Applications)
    'gogs_owner'    => '',                       // владелец репозиториев по умолчанию

];