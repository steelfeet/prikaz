<?php
declare(strict_types=1);

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function asset_url(string $path): string
{
    $script = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
    $base = rtrim(str_replace('\\', '/', dirname($script)), '/');
    return ($base === '' ? '' : $base) . '/assets/' . ltrim($path, '/');
}

function redirect(string $url): never
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
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

// =============================================================================
// Конфиг (config.php)
// =============================================================================

/**
 * Возвращает config.php.
 *
 * Сначала пробует $GLOBALS['PRIKAZ_CONFIG'] (то, что прочитал index.php),
 * но только если там реально есть admin_login. Иначе читает файл напрямую
 * по нескольким путям (PRIKAZ_CONFIG_PATH, рядом с БД, рядом с корнем,
 * env). Кэширует в static, чтобы не читать файл больше одного раза.
 */
function prikaz_config(): array
{
    static $cfg = null;
    if ($cfg !== null) return $cfg;

    // 1. Из index.php через bootstrap
    if (isset($GLOBALS['PRIKAZ_CONFIG'])
        && is_array($GLOBALS['PRIKAZ_CONFIG'])
        && !empty($GLOBALS['PRIKAZ_CONFIG']['admin_login'])) {
        $cfg = $GLOBALS['PRIKAZ_CONFIG'];
        return $cfg;
    }

    // 2. Читаем файл напрямую
    $candidates = [];
    if (defined('PRIKAZ_CONFIG_PATH') && PRIKAZ_CONFIG_PATH !== '') {
        $candidates[] = PRIKAZ_CONFIG_PATH;
    }
    if (defined('PRIKAZ_DB_PATH') && PRIKAZ_DB_PATH !== '') {
        $candidates[] = dirname(PRIKAZ_DB_PATH) . '/config.php';
    }
    if (defined('PRIKAZ_ROOT') && PRIKAZ_ROOT !== '') {
        $candidates[] = dirname(PRIKAZ_ROOT) . '/prikaz-data/config.php';
        $candidates[] = dirname(PRIKAZ_ROOT, 2) . '/prikaz-data/config.php';
        $candidates[] = dirname(PRIKAZ_ROOT, 3) . '/prikaz-data/config.php';
    }
    $env = getenv('PRIKAZ_CONFIG');
    if ($env) $candidates[] = $env;
    $candidates[] = '/var/www/u3469397/data/www/prikaz-data/config.php';
    $candidates[] = '/var/www/u3469397/data/prikaz-data/config.php';

    foreach ($candidates as $path) {
        if ($path === '' || !is_file($path)) continue;
        $data = @include $path;
        if (is_array($data) && !empty($data['admin_login'])) {
            $cfg = $data;
            return $cfg;
        }
    }

    // 3. Ничего не нашли — вернём пустой массив
    $cfg = [];
    return $cfg;
}

/**
 * Логин — это логин администратора из config.php?
 * Регистронезависимо (users.login — COLLATE NOCASE).
 */
function prikaz_is_admin_login(string $login): bool
{
    $cfg = prikaz_config();
    $adminLogin = (string) ($cfg['admin_login'] ?? '');
    if ($adminLogin === '') return false;
    return strcasecmp($adminLogin, $login) === 0;
}

/**
 * Пароль совпадает с admin_password из config.php?
 */
function prikaz_check_admin_password(string $password): bool
{
    $cfg = prikaz_config();
    $expected = (string) ($cfg['admin_password'] ?? '');
    if ($expected === '') return false;
    return hash_equals($expected, $password);
}

// =============================================================================
// JSON-ответы для AJAX-обработчиков
// =============================================================================

function wants_json(): bool
{
    if (($_POST['ajax'] ?? '') === '1') return true;
    if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest') return true;
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    return str_contains($accept, 'application/json');
}

function json_response(array $data): never
{
    $junk = '';
    while (ob_get_level() > 0) {
        $junk .= (string) ob_get_clean();
    }
    if ($junk !== '') {
        @error_log('[Prikaz json_response] garbage in buffer: ' . substr($junk, 0, 500));
    }
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// =============================================================================
// Текстовые вставки
// =============================================================================

function text_sanitize_utf8(string $raw): array
{
    $raw = str_replace("\xEF\xBB\xBF", '', $raw);
    if ($raw === '' || preg_match('//u', $raw)) {
        return ['text' => $raw, 'repaired' => false];
    }
    $fixed = @iconv('UTF-8', 'UTF-8//IGNORE', $raw);
    if (is_string($fixed) && $fixed !== '' && preg_match('//u', $fixed)) {
        return ['text' => $fixed, 'repaired' => true];
    }
    if (function_exists('mb_convert_encoding')) {
        $fixed = @mb_convert_encoding($raw, 'UTF-8', 'UTF-8');
        if (is_string($fixed) && preg_match('//u', $fixed)) {
            return ['text' => $fixed, 'repaired' => true];
        }
    }
    return ['text' => '', 'repaired' => true];
}

function normalize_topic_text(string $topic): string
{
    if (!preg_match('//u', $topic)) {
        $topic = text_sanitize_utf8($topic)['text'];
        if ($topic === '') return '';
    }
    $clean = preg_replace('/[\p{Cc}\p{Cf}]/u', ' ', $topic);
    if (!is_string($clean)) return trim($topic);
    $collapsed = preg_replace('/\s+/u', ' ', $clean);
    if (!is_string($collapsed)) return trim($clean);
    return trim($collapsed);
}

function topic_key(string $topic): string
{
    $normalized = normalize_topic_text($topic);
    return function_exists('mb_strtolower')
        ? mb_strtolower($normalized, 'UTF-8')
        : $normalized;
}

function parse_topic_lines(string $raw): array
{
    $san = text_sanitize_utf8($raw);
    if ($san['text'] === '') {
        return ['topics' => [], 'empty' => 0, 'invalid' => $raw !== '' ? 1 : 0];
    }
    $lines = preg_split('/\R/u', $san['text']);
    if (!is_array($lines)) {
        return ['topics' => [], 'empty' => 0, 'invalid' => 1];
    }
    $topics  = [];
    $empty   = 0;
    $invalid = 0;
    foreach ($lines as $line) {
        $topic = normalize_topic_text($line);
        if ($topic === '') { $empty++; continue; }
        $topics[] = $topic;
    }
    return ['topics' => $topics, 'empty' => $empty, 'invalid' => $invalid];
}

function semester_label(string $name, int|string $isPractice): string
{
    $label = trim($name);
    if ((int) $isPractice === 1 && mb_stripos($label, 'практик') === false) {
        $label .= ' · практика';
    }
    return $label;
}