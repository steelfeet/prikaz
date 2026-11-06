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

// =============================================================================
// JSON-ответы для AJAX-обработчиков
// =============================================================================

/**
 * Клиент просит JSON-ответ (fetch с ajax=1 или Accept: application/json).
 */
function wants_json(): bool
{
    if (($_POST['ajax'] ?? '') === '1') return true;
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    return str_contains($accept, 'application/json');
}

/**
 * Отдать JSON-ответ и завершить запрос.
 */
function json_response(array $data): never
{
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// =============================================================================
// Надёжная обработка текстовых вставок (массовое добавление тем)
// =============================================================================

/**
 * Проверяет и, при необходимости, «чинит» кодировку строки.
 *
 * При копировании из PDF, Word и других источников текст может содержать
 * невалидные байты UTF-8. Эта функция возвращает нормализованную строку
 * и флаг, было ли исправление.
 *
 * @return array{text: string, repaired: bool}
 */
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

/**
 * Нормализует текст темы: убирает управляющие и форматные символы, схлопывает
 * пробелы (включая NBSP) в один, обрезает края.
 */
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

/**
 * Регистронезависимый ключ темы для дедупликации.
 */
function topic_key(string $topic): string
{
    $normalized = normalize_topic_text($topic);
    return function_exists('mb_strtolower')
        ? mb_strtolower($normalized, 'UTF-8')
        : $normalized;
}

/**
 * Разбивает многострочный текст на список тем (одна строка — одна тема).
 *
 * @return array{topics: string[], empty: int, invalid: int}
 */
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
        if ($topic === '') {
            $empty++;
            continue;
        }
        $topics[] = $topic;
    }

    return ['topics' => $topics, 'empty' => $empty, 'invalid' => $invalid];
}

/**
 * Возвращает читаемую метку семестра: «1 семестр», «производственная практика».
 */
function semester_label(string $name, int|string $isPractice): string
{
    $label = trim($name);
    if ((int) $isPractice === 1 && mb_stripos($label, 'практик') === false) {
        $label .= ' · практика';
    }
    return $label;
}