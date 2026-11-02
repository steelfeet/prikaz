<?php
declare(strict_types=1);

return [
    'app_name'         => 'Приказ',
    'db_path'          => __DIR__ . '/prikaz.sqlite',
    'storage_path'     => __DIR__ . '/storage',
    'session_name'     => 'prikaz_session',
    'session_lifetime' => 86400 * 7,
    'password_min'     => 8,
    'csrf_key'         => 'prikaz_csrf_token',
];