<?php
declare(strict_types=1);

/**
 * ============================================================================
 * check.php — проверка PR «Новости IT» для типа проекта «Новости ИТ и ИБ».
 * ============================================================================
 *
 * Запускается из lib/project_types.php::project_type_check().
 * На вход получает $ctx (см. ниже) и ДОЛЖЕН вернуть массив:
 *
 *   return [
 *       'ok'       => true|false,     // прошла ли проверка хотя бы по одному PR
 *       'errors'   => [ '...' ],      // критичные (конфиг, API, отклонённые PR)
 *       'warnings' => [ '...' ],      // некритичные (нет PR, нет repo_url и т.п.)
 *       'details'  => [ '...' ],      // сводка по каждому PR
 *   ];
 *
 * $ctx:
 *   $ctx['type']     = project_types row
 *   $ctx['gogs']     = ['base_url' => ..., 'token' => ..., 'owner' => ...]
 *   $ctx['projects'] = [{id,title,repo_url}, ...] — неархивные проекты этого типа
 *   $ctx['apply']    = bool — если true, check.php может применять действия
 *                            (merge/close/comment). Если false — dry-run.
 *
 * Логика:
 *   1. Для каждого проекта получаем открытые PR.
 *   2. Определяем «наш» ли PR: в названии или в коммитах есть «Новости IT»/«Новости ИТ».
 *   3. Если не наш — молча пропускаем.
 *   4. Если наш — прогоняем 10 проверок. При $ctx['apply']=true:
 *        - прошёл → squash merge + комментарий «Принято. ДД.ММ.ГГГГ ЧЧ:ММ.»;
 *        - не прошёл → комментарий со списком нарушений + закрытие PR.
 *
 * Если `apply=false`, ничего в Gogs не меняется — только отчёт.
 * ============================================================================
 */

@set_time_limit(120);

$type     = is_array($ctx['type']     ?? null) ? $ctx['type']     : [];
$gogs     = is_array($ctx['gogs']     ?? null) ? $ctx['gogs']     : [];
$projects = is_array($ctx['projects'] ?? null) ? $ctx['projects'] : [];
$apply    = !empty($ctx['apply']);

$errors   = [];
$warnings = [];
$details  = [];

// ============================================================================
// 1. Конфигурация
// ============================================================================

$gogsBase  = rtrim((string) ($gogs['base_url'] ?? ''), '/');
$gogsToken = (string) ($gogs['token'] ?? '');
$gogsOwner = (string) ($gogs['owner'] ?? '');

if ($gogsBase === '')  $errors[] = 'Не настроен gogs.base_url в config.php.';
if ($gogsToken === '') $errors[] = 'Не настроен gogs.token в config.php.';
if ($gogsOwner === '') $errors[] = 'Не настроен владелец Gogs (ни в типе, ни в config.php).';

if ($errors !== []) {
    return ['ok' => false, 'errors' => $errors, 'warnings' => $warnings, 'details' => $details];
}

if ($projects === []) {
    $warnings[] = 'У типа «' . (string) ($type['name'] ?? '') . '» нет проектов. Проверять нечего.';
    return ['ok' => true, 'errors' => [], 'warnings' => $warnings, 'details' => $details];
}

// ============================================================================
// 2. HTTP-хелперы для Gogs
// ============================================================================

/**
 * GET-запрос. Если $raw = true — возвращает тело как строку (для raw-файлов).
 * Иначе — декодирует JSON и ожидает массив.
 */
$gogsGet = static function (string $path, bool $raw = false) use ($gogsBase, $gogsToken) {
    $ch = curl_init($gogsBase . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: token ' . $gogsToken,
            'Accept: application/json',
        ],
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_FOLLOWLOCATION => true,
    ]);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($body === false) {
        return ['ok' => false, 'code' => 0, 'body' => null, 'error' => $err ?: 'curl error'];
    }
    if ($code < 200 || $code >= 300) {
        return ['ok' => false, 'code' => $code, 'body' => null, 'error' => 'HTTP ' . $code];
    }
    if ($raw) {
        return ['ok' => true, 'code' => $code, 'body' => $body, 'error' => null];
    }
    $decoded = json_decode($body, true);
    if (!is_array($decoded)) {
        return ['ok' => false, 'code' => $code, 'body' => null, 'error' => 'Ответ не JSON-массив'];
    }
    return ['ok' => true, 'code' => $code, 'body' => $decoded, 'error' => null];
};

/**
 * POST/PATCH-запрос с JSON-телом.
 */
$gogsSend = static function (string $method, string $path, array $payload) use ($gogsBase, $gogsToken) {
    $ch = curl_init($gogsBase . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER     => [
            'Authorization: token ' . $gogsToken,
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        CURLOPT_TIMEOUT        => 25,
    ]);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($body === false) {
        return ['ok' => false, 'code' => 0, 'error' => $err ?: 'curl error'];
    }
    if ($code < 200 || $code >= 300) {
        return ['ok' => false, 'code' => $code, 'error' => 'HTTP ' . $code];
    }
    return ['ok' => true, 'code' => $code, 'error' => null];
};

// ============================================================================
// 3. Хелперы предметной области
// ============================================================================

/** Извлекает owner/repo из repo_url. Поддерживает https://, ssh://, owner/repo. */
$parseRepo = static function (string $repoUrl): ?array {
    $repoUrl = trim($repoUrl);
    if ($repoUrl === '') return null;

    // SSH: git@host:owner/repo.git
    if (preg_match('#^[\w.-]+@[\w.-]+:([^/]+)/([^/]+?)(?:\.git)?$#', $repoUrl, $m)) {
        return ['owner' => $m[1], 'repo' => $m[2]];
    }

    // HTTP(S) или относительный путь
    $path = parse_url($repoUrl, PHP_URL_PATH);
    if (!is_string($path) || $path === '') {
        $path = $repoUrl; // на случай "owner/repo"
    }
    $path = trim($path, '/');
    $path = preg_replace('/\.git$/i', '', $path);
    $parts = explode('/', $path);
    if (count($parts) < 2) return null;
    return ['owner' => $parts[0], 'repo' => $parts[1]];
};

/** Проверка: имя файла — 4–8 латинских букв (без .md). */
$isLatinName = static function (string $name): bool {
    return (bool) preg_match('/^[A-Za-z]{4,8}$/', $name);
};

/** Эвристика: имя файла похоже на фамилию в латинской транслитерации. */
$looksLikeSurname = static function (string $name): bool {
    $lower = strtolower($name);
    $surnameEndings = ['ov', 'ev', 'ova', 'eva', 'sky', 'skaya', 'enko', 'ich', 'ovich', 'evich'];
    foreach ($surnameEndings as $end) {
        if (str_ends_with($lower, $end) && strlen($lower) > strlen($end) + 1) {
            return true;
        }
    }
    return false;
};

/** Индекс первой строки с URL. */
$findUrlLine = static function (array $lines): ?int {
    foreach ($lines as $i => $line) {
        if (preg_match('#https?://[^\s)\]]+#i', $line)) return $i;
    }
    return null;
};

/** Строка — дата ДД.ММ.ГГГГ. */
$isDateDDMMYYYY = static function (string $s): bool {
    return (bool) preg_match('/^\s*(\d{2})\.(\d{2})\.(\d{4})\s*$/', $s, $m)
        && checkdate((int) $m[2], (int) $m[1], (int) $m[3]);
};

/** Парсит дату ДД.ММ.ГГГГ → DateTimeImmutable или null. */
$parseDateDDMMYYYY = static function (string $s): ?DateTimeImmutable {
    if (!preg_match('/^\s*(\d{2})\.(\d{2})\.(\d{4})\s*$/', $s, $m)) return null;
    $d = (int) $m[1]; $mo = (int) $m[2]; $y = (int) $m[3];
    if (!checkdate($mo, $d, $y)) return null;
    try {
        return new DateTimeImmutable(sprintf('%04d-%02d-%02d', $y, $mo, $d));
    } catch (Throwable $e) {
        return null;
    }
};

/** Убирает заголовки, URL, строку с датой и пустые строки. */
$stripMeta = static function (string $text): string {
    $lines = preg_split('/\R/u', $text) ?: [];
    $out = [];
    foreach ($lines as $line) {
        $trim = trim($line);
        if ($trim === '') continue;
        if (str_starts_with($trim, '#')) continue;
        if (preg_match('#^https?://#i', $trim)) continue;
        if (preg_match('/^\d{2}\.\d{2}\.\d{4}$/', $trim)) continue;
        $out[] = $line;
    }
    return implode("\n", $out);
};

/** mb_strlen с фоллбэком. */
$mbLen = static function (string $s): int {
    return function_exists('mb_strlen') ? mb_strlen($s, 'UTF-8') : strlen($s);
};

/** mb_strtolower с фоллбэком. */
$mbLower = static function (string $s): string {
    return function_exists('mb_strtolower') ? mb_strtolower($s, 'UTF-8') : strtolower($s);
};

// ============================================================================
// 4. Основной цикл
// ============================================================================

$totalChecked = 0;   // «наши» PR, которые реально проверялись
$totalPassed  = 0;
$totalFailed  = 0;
$totalSkipped = 0;   // PR не «наши» (нет «Новости IT» в названии/коммитах)
$actionsTaken = [];  // что было сделано (при apply=true)

foreach ($projects as $p) {
    $pid   = (int) ($p['id'] ?? 0);
    $title = (string) ($p['title'] ?? '');
    $repo  = (string) ($p['repo_url'] ?? '');

    if ($repo === '') {
        $warnings[] = 'Проект #' . $pid . ' «' . $title . '»: не указан repo_url, пропуск.';
        continue;
    }

    $parsed = $parseRepo($repo);
    if ($parsed === null) {
        $errors[] = 'Проект #' . $pid . ': не удалось разобрать repo_url «' . $repo . '».';
        continue;
    }
    $repoOwner = $parsed['owner'];
    $repoName  = $parsed['repo'];

    // Открытые PR в репозитории проекта
    $res = $gogsGet(
        '/api/v1/repos/' . rawurlencode($repoOwner) . '/' . rawurlencode($repoName)
        . '/pulls?state=open&limit=50'
    );
    if (!$res['ok']) {
        $errors[] = 'Проект #' . $pid . ' «' . $title . '»: не удалось получить список PR — ' . $res['error'];
        continue;
    }
    $prs = is_array($res['body']) ? $res['body'] : [];
    if ($prs === []) {
        $details[] = 'Проект #' . $pid . ' «' . $title . '»: нет открытых PR.';
        continue;
    }

    foreach ($prs as $pr) {
        $prNum   = (int) ($pr['number'] ?? 0);
        $prTitle = (string) ($pr['title'] ?? '');
        $prHead  = (string) ($pr['head']['ref'] ?? '');
        $prUpd   = (string) ($pr['updated_at'] ?? ($pr['created_at'] ?? ''));
        $prefix  = 'PR #' . $prNum . ' «' . $prTitle . '» (' . $repoOwner . '/' . $repoName . '):';

        if ($prNum <= 0) {
            $warnings[] = 'Проект #' . $pid . ': PR без номера, пропуск.';
            continue;
        }

        // --- «Наш» ли PR? ---
        $hasInTitle = (bool) preg_match('/новости\s*(it|ит)/iu', $prTitle);
        $hasInCommits = false;
        if (!$hasInTitle) {
            $commitsRes = $gogsGet(
                '/api/v1/repos/' . rawurlencode($repoOwner) . '/' . rawurlencode($repoName)
                . '/pulls/' . $prNum . '/commits'
            );
            if ($commitsRes['ok']) {
                foreach ((array) $commitsRes['body'] as $c) {
                    $msg = (string) ($c['commit']['message'] ?? '');
                    if (preg_match('/новости\s*(it|ит)/iu', $msg)) {
                        $hasInCommits = true;
                        break;
                    }
                }
            }
        }
        if (!$hasInTitle && !$hasInCommits) {
            $totalSkipped++;
            $details[] = $prefix . ' не наш PR (в названии и коммитах нет «Новости IT») — пропущен.';
            continue;
        }

        $totalChecked++;
        $checkErrors = [];

        // --- Файлы в PR ---
        $filesRes = $gogsGet(
            '/api/v1/repos/' . rawurlencode($repoOwner) . '/' . rawurlencode($repoName)
            . '/pulls/' . $prNum . '/files'
        );
        if (!$filesRes['ok']) {
            $totalFailed++;
            $errors[] = $prefix . ' не удалось получить файлы — ' . $filesRes['error'];
            continue;
        }
        $files = is_array($filesRes['body']) ? $filesRes['body'] : [];
        $added = array_values(array_filter($files, fn($f) => ($f['status'] ?? '') === 'added'));
        $other = array_values(array_filter($files, fn($f) => ($f['status'] ?? '') !== 'added'));

        if (count($added) !== 1) {
            $checkErrors[] = 'В PR должен быть ровно один новый .md-файл в Новости/ (сейчас новых: ' . count($added) . ').';
        }
        if ($other !== []) {
            $checkErrors[] = 'Нельзя менять/удалять существующие файлы (изменено/удалено: ' . count($other) . ').';
        }

        $addedFile = $added[0] ?? null;
        if ($addedFile === null) {
            // Дальше проверять нечего — фиксируем результат
            goto finish_pr;
        }

        $filename = (string) ($addedFile['filename'] ?? '');
        $basename = basename($filename);

        // --- Путь: Новости/<уч.год>/<номер_группы>/<имя>.md ---
        if (!preg_match('#^Новости/(\d{2,4}-\d{2,4})/([^/]+)/([A-Za-z]{4,8})\.md$#u', $filename, $m)) {
            $checkErrors[] = 'Путь должен быть Новости/<уч.год>/<номер_группы>/<4–8 латинских букв>.md (сейчас: ' . $filename . ').';
        } else {
            $fileBaseName = $m[3];
            if ($looksLikeSurname($fileBaseName)) {
                $checkErrors[] = 'Имя файла похоже на фамилию («' . $basename . '»). Используйте ник или имя+инициал.';
            }
        }

        // --- Содержимое ---
        $rawRes = $gogsGet(
            '/api/v1/repos/' . rawurlencode($repoOwner) . '/' . rawurlencode($repoName)
            . '/raw/' . rawurlencode($filename) . '?ref=' . rawurlencode($prHead),
            true
        );
        if (!$rawRes['ok']) {
            $totalFailed++;
            $errors[] = $prefix . ' не удалось получить содержимое файла — ' . $rawRes['error'];
            continue;
        }
        $text = (string) $rawRes['body'];
        $text = preg_replace('/^\xEF\xBB\xBF/', '', $text); // BOM
        $lines = preg_split('/\R/u', $text) ?: [];
        $lowerText = $mbLower($text);

        // --- 1. Заголовок H1 ---
        if (!preg_match('/^\s*#\s+\S/u', $text)) {
            $checkErrors[] = 'Нет заголовка первого уровня (# Тема новости).';
        }

        // --- 2. Источник: URL + дата на следующей строке ---
        $sourceDate = null;
        $urlIdx = $findUrlLine($lines);
        if ($urlIdx === null) {
            $checkErrors[] = 'Нет ссылки на источник (https://…).';
        } else {
            $next = $lines[$urlIdx + 1] ?? '';
            if (!$isDateDDMMYYYY($next)) {
                $checkErrors[] = 'Дата публикации ДД.ММ.ГГГГ должна быть на следующей строке после ссылки.';
            } else {
                $sourceDate = $parseDateDDMMYYYY($next);
                if ($sourceDate === null) {
                    $checkErrors[] = 'Дата публикации «' . trim($next) . '» — некорректная.';
                }
            }
        }

        // --- 3. Объём комментария ≥ 500 символов ---
        $bodyLen = $mbLen($stripMeta($text));
        if ($bodyLen < 500) {
            $checkErrors[] = 'Комментарий короче 500 символов (сейчас ' . $bodyLen . ').';
        }

        // --- 4. Личное суждение от первого лица ---
        $judgementMarkers = ['я считаю', 'мне кажется', 'по-моему', 'на мой взгляд', 'я думаю'];
        $hasJudgement = false;
        foreach ($judgementMarkers as $marker) {
            if (str_contains($lowerText, $marker)) { $hasJudgement = true; break; }
        }
        if (!$hasJudgement) {
            $checkErrors[] = 'Нет суждения от первого лица (одно из: «я считаю», «мне кажется», «по-моему», «на мой взгляд», «я думаю»).';
        }

        // --- 5. Запрещённые клише ---
        $cliches = ['в современном мире', 'данная новость актуальна', 'нельзя не отметить'];
        $foundCliches = [];
        foreach ($cliches as $c) {
            if (str_contains($lowerText, $c)) $foundCliches[] = $c;
        }
        $paras = preg_split('/\n\s*\n/u', trim($text)) ?: [];
        $lastPara = $mbLower((string) (end($paras) ?: ''));
        if (str_contains($lastPara, 'таким образом')) {
            $foundCliches[] = 'таким образом (в последнем абзаце)';
        }
        if ($foundCliches !== []) {
            $checkErrors[] = 'Найдены клише: ' . implode(', ', $foundCliches) . '.';
        }

        // --- 6. Персональные данные ---
        $pii = [];
        if (preg_match('/\b[А-ЯЁ][а-яё]+\s+[А-ЯЁ][а-яё]+\s+[А-ЯЁ][а-яё]+(вич|вна)\b/u', $text)) {
            $pii[] = 'ФИО с отчеством';
        }
        if (preg_match('/[\w.+-]+@[\w-]+\.[\w.]+/u', $text)) {
            $pii[] = 'e-mail';
        }
        if (preg_match('/(\+7|8)[\s\-]?\(?\d{3}\)?[\s\-]?\d{3}[\s\-]?\d{2}[\s\-]?\d{2}/', $text)) {
            $pii[] = 'телефон';
        }
        if ($pii !== []) {
            $checkErrors[] = 'Персональные данные: ' . implode(', ', $pii) . '. Оставьте только имя и первую букву фамилии.';
        }

        // --- 7. Свежесть новости (не старше 14 дней от даты PR) ---
        if ($sourceDate !== null && $prUpd !== '') {
            try {
                $prDate = new DateTimeImmutable($prUpd);
                $days = (int) floor(($prDate->getTimestamp() - $sourceDate->getTimestamp()) / 86400);
                if ($days > 14) {
                    $checkErrors[] = 'Новость старше двух недель (опубликована ' . $sourceDate->format('d.m.Y') . ', разница ' . $days . ' дн.).';
                }
            } catch (Throwable $e) {
                $warnings[] = $prefix . ' не удалось распарсить дату PR.';
            }
        }

        finish_pr:
        if ($checkErrors === []) {
            $totalPassed++;

            if ($apply) {
                // Принять: squash merge + комментарий
                $mergeRes = $gogsSend(
                    'POST',
                    '/api/v1/repos/' . rawurlencode($repoOwner) . '/' . rawurlencode($repoName)
                    . '/pulls/' . $prNum . '/merge',
                    ['Do' => 'squash', 'MergeTitleField' => $prTitle]
                );
                if ($mergeRes['ok']) {
                    $gogsSend(
                        'POST',
                        '/api/v1/repos/' . rawurlencode($repoOwner) . '/' . rawurlencode($repoName)
                        . '/issues/' . $prNum . '/comments',
                        ['body' => '✅ Принято. ' . date('d.m.Y H:i') . '.']
                    );
                    $actionsTaken[] = $prefix . ' принят (squash merge).';
                    $details[] = $prefix . ' ✓ всё в порядке, принят.';
                } else {
                    $warnings[] = $prefix . ' прошёл проверки, но merge не удался: ' . $mergeRes['error'];
                    $details[] = $prefix . ' ✓ проверки пройдены, но merge не удался.';
                }
            } else {
                $details[] = $prefix . ' ✓ всё в порядке (dry-run, не применено).';
            }
        } else {
            $totalFailed++;

            if ($apply) {
                // Отклонить: комментарий + закрыть PR
                $commentBody = "❌ Не принято.\n\nНарушения:\n" . implode("\n", array_map(
                    static fn(string $e): string => '- ' . $e,
                    $checkErrors
                ));
                $gogsSend(
                    'POST',
                    '/api/v1/repos/' . rawurlencode($repoOwner) . '/' . rawurlencode($repoName)
                    . '/issues/' . $prNum . '/comments',
                    ['body' => $commentBody]
                );
                $gogsSend(
                    'PATCH',
                    '/api/v1/repos/' . rawurlencode($repoOwner) . '/' . rawurlencode($repoName)
                    . '/pulls/' . $prNum,
                    ['state' => 'closed']
                );
                $actionsTaken[] = $prefix . ' отклонён (комментарий + закрыт).';
            }

            foreach ($checkErrors as $e) {
                $errors[] = $prefix . ' ' . $e;
            }
            $details[] = $prefix . ' ✗ не прошёл: ' . implode('; ', $checkErrors) . '.';
        }
    }
}

// ============================================================================
// 5. Итог
// ============================================================================

$mode = $apply ? 'apply (действия применены)' : 'dry-run (ничего не изменено)';
$details[] = 'Итого: проверено PR ' . $totalChecked
    . ', прошло ' . $totalPassed
    . ', отклонено ' . $totalFailed
    . ', пропущено (не наши) ' . $totalSkipped
    . ' [' . $mode . '].';
if ($actionsTaken !== []) {
    $details[] = 'Действия: ' . implode(' ', $actionsTaken);
}

return [
    'ok'       => $errors === [],
    'errors'   => $errors,
    'warnings' => $warnings,
    'details'  => $details,
];