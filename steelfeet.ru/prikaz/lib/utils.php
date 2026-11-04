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
    // UTF-8 BOM ломает первую строку: его не видно, но он попадает в начало текста.
    $raw = str_replace("\xEF\xBB\xBF", '', $raw);

    // Быстрая проверка валидности UTF-8: пустой паттерн + модификатор u.
    if ($raw === '' || preg_match('//u', $raw)) {
        return ['text' => $raw, 'repaired' => false];
    }

    // Пытаемся «выкинуть» невалидные байты.
    $fixed = @iconv('UTF-8', 'UTF-8//IGNORE', $raw);
    if (is_string($fixed) && $fixed !== '' && preg_match('//u', $fixed)) {
        return ['text' => $fixed, 'repaired' => true];
    }

    // Фоллбэк: mb_convert_encoding — тоже убирает невалидные байты.
    if (function_exists('mb_convert_encoding')) {
        $fixed = @mb_convert_encoding($raw, 'UTF-8', 'UTF-8');
        if (is_string($fixed) && preg_match('//u', $fixed)) {
            return ['text' => $fixed, 'repaired' => true];
        }
    }

    // Ничего не вышло — возвращаем пустую строку, caller сам решит.
    return ['text' => '', 'repaired' => true];
}

/**
 * Нормализует текст темы: убирает управляющие и форматные символы, схлопывает
 * пробелы (включая NBSP) в один, обрезает края.
 *
 * Используется и для новых тем, и для уже сохранённых — чтобы сравнение
 * дубликатов было корректным независимо от того, откуда текст попал в систему.
 */
function normalize_topic_text(string $topic): string
{
    // Если строка не прошла проверку кодировки — сначала выкинем невалидные байты.
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
 * Аккуратно относится к «мусору», который приходит при вставке из документов:
 * невалидные байты UTF-8, zero-width и управляющие символы, разделители
 * U+2028/U+2029 (LINE/PARAGRAPH SEPARATOR), которые не режутся обычным \R.
 *
 * Пустые строки и строки без печатных символов отбрасываются.
 *
 * @return array{topics: string[], empty: int, invalid: int}
 */
function parse_topic_lines(string $raw): array
{
    $san = text_sanitize_utf8($raw);

    if ($san['text'] === '') {
        return ['topics' => [], 'empty' => 0, 'invalid' => $raw !== '' ? 1 : 0];
    }

    // \R с модификатором /u режет ВСЕ Unicode-разрывы строк,
    // включая U+2028 (LINE SEPARATOR) и U+2029 (PARAGRAPH SEPARATOR),
    // которые часто встречаются при копировании из PDF и Word.
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
            // Пустая строка или строка только из пробелов/невидимых символов.
            $empty++;
            continue;
        }
        $topics[] = $topic;
    }

    return ['topics' => $topics, 'empty' => $empty, 'invalid' => $invalid];
}