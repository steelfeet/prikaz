<?php
declare(strict_types=1);

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/**
 * Возвращает URL до ассета с учётом вложенности приложения.
 * Работает и в корне домена (/assets/...), и в папке (/prikaz/assets/...).
 */
function asset_url(string $path): string
{
    $script = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
    $base = rtrim(str_replace('\\', '/', dirname($script)), '/');
    return ($base === '' ? '' : $base) . '/assets/' . ltrim($path, '/');
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function flash_set(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function flash_get(): ?array
{
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

function valid_mark(string $m): bool
{
    return in_array($m, ['5', '4', '3', '2', 'н', '·'], true);
}