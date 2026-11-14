<?php
declare(strict_types=1);

/**
 * ============================================================================
 * check.php — точка входа CI/CD-проверки для одного типа проекта.
 * ============================================================================
 *
 * Этот файл автоматически копируется из projects/_templates/check.php
 * в projects/<code>/check.php при создании типа проекта.
 * Отредактировать его можно прямо здесь — тогда проверки для этого типа
 * будут отличаться от остальных.
 *
 * Файл вызывается из lib/project_types.php::project_type_check().
 * На вход получает переменную $ctx (см. ниже). Должен ВЕРНУТЬ массив.
 *
 * Возвращаемая структура (обязательна):
 *
 *   return [
 *       'ok'       => true|false,   // прошла ли проверка
 *       'errors'   => [ '...' ],    // критические ошибки (проект не проходит)
 *       'warnings' => [ '...' ],    // некритичные замечания
 *       'details'  => [ '...' ],    // любая полезная сводка для админа
 *   ];
 *
 * Если файл не вернёт массив — проверка автоматически упадёт с ошибкой
 * «check.php не вернул массив».
 *
 * ----------------------------------------------------------------------------
 * Что уже лежит в $ctx
 * ----------------------------------------------------------------------------
 *
 *   $ctx['type'] = [
 *       'id'             => int,
 *       'code'           => 'original',
 *       'name'           => 'Оригинал',
 *       'description'    => '...',
 *       'color'          => '#16a34a',
 *       'icon'           => '💻',
 *       'gogs_owner'     => 'kolledzh',       // пусто, если не задан
 *       'gogs_repo_prefix'=> 'proj-',         // пусто, если не задан
 *   ];
 *
 *   $ctx['gogs'] = [
 *       'base_url' => 'https://gogs.example', // из config.php
 *       'token'    => '...',                  // personal access token, из config.php
 *       'owner'    => 'kolledzh',             // $type['gogs_owner'] ?: config
 *   ];
 *
 *   $ctx['projects'] = [
 *       ['id' => 1, 'title' => '...', 'repo_url' => 'https://gogs.example/owner/repo'],
 *       ...
 *   ];  // только проекты этого типа, неархивные
 *
 *   $ctx['dir'] = '/полный/путь/к/projects/<code>/check.php';
 *
 * ----------------------------------------------------------------------------
 * Как это отдать в чат ИИ
 * ----------------------------------------------------------------------------
 *
 * Скопируй весь файл (вместе с этим комментарием) и добавь:
 *
 *   «Это заглушка CI/CD-проверки для типа проекта в школьной LMS на PHP+SQLite.
 *    В $ctx — доступ к Gogs (owner/repo/base_url/token) и список проектов типа.
 *    Нужно реализовать проверки:
 *      1. ...
 *      2. ...
 *      3. ...
 *    Формат ответа — массив ['ok','errors','warnings','details'] (см. шапку).»
 *
 * Остальное ИИ поймёт из структуры $ctx и сигнатуры ответа.
 *
 * ----------------------------------------------------------------------------
 * Gogs API — что может понадобиться
 * ----------------------------------------------------------------------------
 *
 * Personal access token: Settings → Applications → Generate New Token.
 * Scopes: repo (read). URL без слэша на конце.
 *
 *   1. Найти открытые PR в репозитории:
 *      GET {base}/api/v1/repos/{owner}/{repo}/pulls?state=open&limit=100
 *      Заголовок: Authorization: token {gogs.token}
 *      Возвращает: [{ number, title, user: {login}, head: {ref}, base: {ref}, ... }]
 *
 *   2. Файлы в PR (изменённые):
 *      GET {base}/api/v1/repos/{owner}/{repo}/pulls/{number}/files
 *      Возвращает: [{ filename, status, additions, deletions }]
 *
 *   3. Содержимое файла на ветке (raw):
 *      GET {base}/api/v1/repos/{owner}/{repo}/raw/{path}?ref={branch}
 *      Возвращает: текст файла.
 *
 *   4. Список файлов ветки (если нужно искать spec.md):
 *      GET {base}/api/v1/repos/{owner}/{repo}/contents/{path}?ref={branch}
 *
 * В PHP проще всего через cURL (curl_init + curl_setopt + curl_exec).
 * Помни: file_get_contents с токеном в заголовке — небезопасно, лучше cURL
 * с CURLOPT_HTTPHEADER и CURLOPT_TIMEOUT.
 *
 * ----------------------------------------------------------------------------
 * Заглушка (что сейчас делает этот файл)
 * ----------------------------------------------------------------------------
 *
 * Проверяет только базовую настройку:
 *   - есть ли проекты этого типа (если нет — предупреждение);
 *   - задан ли gogs.base_url и gogs.token (если нет — ошибка);
 *   - у каждого проекта есть repo_url (если нет — ошибка).
 *
 * Реальные проверки (PR, изменённые файлы, spec.md, уникальность) — TODO.
 * Оставь заглушку как есть или перепиши под конкретный тип проекта.
 */

// ---------------------------------------------------------------------------
// Базовые проверки
// ---------------------------------------------------------------------------
$errors   = [];
$warnings = [];
$details  = [];

$type     = is_array($ctx['type']     ?? null) ? $ctx['type']     : [];
$gogs     = is_array($ctx['gogs']     ?? null) ? $ctx['gogs']     : [];
$projects = is_array($ctx['projects'] ?? null) ? $ctx['projects'] : [];

$typeName = (string) ($type['name'] ?? '');
$typeCode = (string) ($type['code'] ?? '');

if ($projects === []) {
    $warnings[] = 'У типа «' . $typeName . '» нет проектов. Проверять нечего.';
}

if (empty($gogs['base_url'])) {
    $errors[] = 'Не настроен gogs.base_url в config.php — реальные проверки CI/CD невозможны.';
}
if (empty($gogs['token'])) {
    $errors[] = 'Не настроен gogs.token в config.php — запросы к Gogs будут отклонены.';
}
if (empty($gogs['owner'])) {
    $errors[] = 'Не настроен владелец Gogs (ни в типе, ни в config.php).';
}

foreach ($projects as $p) {
    $pid = (int) ($p['id'] ?? 0);
    $title = (string) ($p['title'] ?? '');
    $repo  = (string) ($p['repo_url'] ?? '');

    if ($repo === '') {
        $errors[] = 'Проект #' . $pid . ' «' . $title . '»: не указан repo_url.';
        continue;
    }
    if (empty($gogs['token'])) {
        // без токена проверять смысла нет — уже добавили ошибку выше
        continue;
    }

    // TODO 1. Проверить наличие открытого PR:
    //   GET {gogs.base_url}/api/v1/repos/{gogs.owner}/{repo}/pulls?state=open
    //   если пусто — $errors[] = 'Проект #' . $pid . ': нет открытого PR.';
    //
    // TODO 2. Проверить изменённые файлы в PR:
    //   GET .../pulls/{number}/files
    //   сравнить с config.json (allowed_extensions, changed_files_min).
    //
    // TODO 3. Проверить spec.md:
    //   GET .../raw/spec.md?ref={branch}
    //   минимум N слов, формат Markdown, ключевые разделы.
    //
    // TODO 4. Уникальность spec.md:
    //   md5 от нормализованного текста; если уже есть у другого проекта — $errors.
    //
    // TODO 5. Собрать $details: количество проверенных проектов, PR, файлов.

    $details[] = 'Проект #' . $pid . ' «' . $title . '»: repo_url задан, проверка заглушки.';
}

// ---------------------------------------------------------------------------
// Итог
// ---------------------------------------------------------------------------
$ok = ($errors === []);

return [
    'ok'       => $ok,
    'errors'   => $errors,
    'warnings' => $warnings,
    'details'  => $details,
];