<?php
declare(strict_types=1);

/**
 * Типы проектов: CRUD, фазы типа, файлы CI/CD, запуск проверки.
 *
 * Папка `projects/` (в корне Приказа) закрыта .htaccess от прямого доступа.
 * `_templates/check.php` — эталон; при создании типа копируется в
 * `projects/<code>/check.php`. При редактировании типа файл НЕ перезаписывается,
 * чтобы админ мог его доработать вручную. Отдельная кнопка «Пересоздать папку»
 * делает принудительную перезапись.
 */

function project_types_dir(): string
{
    return PRIKAZ_ROOT . '/projects';
}

function project_types_templates_dir(): string
{
    return project_types_dir() . '/_templates';
}

function project_type_dir(string $code): string
{
    return project_types_dir() . '/' . $code;
}

/**
 * Все типы проектов.
 *
 * @return list<array>
 */
function project_types_all(?PDO $pdo = null): array
{
    if ($pdo === null) $pdo = db();
    try {
        return $pdo->query(
            'SELECT * FROM project_types ORDER BY code'
        )->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

function project_type_by_id(int $id, ?PDO $pdo = null): ?array
{
    if ($pdo === null) $pdo = db();
    $stmt = $pdo->prepare('SELECT * FROM project_types WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function project_type_by_code(string $code, ?PDO $pdo = null): ?array
{
    if ($pdo === null) $pdo = db();
    $stmt = $pdo->prepare('SELECT * FROM project_types WHERE code = :c LIMIT 1');
    $stmt->execute([':c' => $code]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Фазы типа, отсортированные по ord.
 *
 * @return list<array{id:int,ord:int,name:string,color:string,description:?string}>
 */
function project_type_phases(int $typeId, ?PDO $pdo = null): array
{
    if ($pdo === null) $pdo = db();
    $stmt = $pdo->prepare(
        'SELECT id, ord, name, color, description
         FROM project_type_phases WHERE project_type_id = :t ORDER BY ord, id'
    );
    $stmt->execute([':t' => $typeId]);
    return $stmt->fetchAll();
}

/**
 * Последние проверки по типу.
 */
function project_type_checks_recent(int $typeId, int $limit = 5, ?PDO $pdo = null): array
{
    if ($pdo === null) $pdo = db();
    $stmt = $pdo->prepare(
        'SELECT c.*, u.login AS checked_login
         FROM project_type_checks c
         LEFT JOIN users u ON u.id = c.checked_by
         WHERE c.project_type_id = :t
         ORDER BY c.id DESC LIMIT :lim'
    );
    $stmt->bindValue(':t', $typeId, PDO::PARAM_INT);
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function project_type_validate_code(string $code): ?string
{
    if (!preg_match('/^[a-z][a-z0-9_-]{1,31}$/', $code)) {
        return 'Код: латиница в нижнем регистре, цифры, _ и -, начинается с буквы, 2–32 символа.';
    }
    $dir = project_type_dir($code);
    // Не считаем существующую папку ошибкой — она может быть от текущего типа при обновлении.
    return null;
}

/**
 * Создание/обновление типа.
 *
 * При создании — генерируется папка `projects/<code>/` из `_templates/`.
 * При обновлении — файлы не трогаются (можно пересоздать отдельной кнопкой).
 */
function project_type_save(array $data, int $byUserId, ?PDO $pdo = null): int
{
    if ($pdo === null) $pdo = db();

    $id          = (int) ($data['id'] ?? 0);
    $code        = trim((string) ($data['code'] ?? ''));
    $name        = trim((string) ($data['name'] ?? ''));
    $description = trim((string) ($data['description'] ?? ''));
    $color       = trim((string) ($data['color'] ?? '#8b6914'));
    $icon        = trim((string) ($data['icon'] ?? '📌'));
    $owner       = trim((string) ($data['gogs_owner'] ?? ''));
    $repoPrefix  = trim((string) ($data['gogs_repo_prefix'] ?? ''));

    if ($name === '') throw new InvalidArgumentException('Название типа обязательно.');
    $err = project_type_validate_code($code);
    if ($err !== null) throw new InvalidArgumentException($err);

    $now = date('Y-m-d H:i:s');

    if ($id > 0) {
        $pdo->prepare(
            'UPDATE project_types SET code = :c, name = :n, description = :d, color = :col,
                icon = :i, gogs_owner = :o, gogs_repo_prefix = :rp, updated_at = :t
             WHERE id = :id'
        )->execute([
            ':c' => $code, ':n' => $name, ':d' => $description !== '' ? $description : null,
            ':col' => $color, ':i' => $icon,
            ':o' => $owner !== '' ? $owner : null,
            ':rp' => $repoPrefix !== '' ? $repoPrefix : null,
            ':t' => $now, ':id' => $id,
        ]);
        return $id;
    }

    $pdo->prepare(
        'INSERT INTO project_types (code, name, description, color, icon, gogs_owner, gogs_repo_prefix)
         VALUES (:c, :n, :d, :col, :i, :o, :rp)'
    )->execute([
        ':c' => $code, ':n' => $name, ':d' => $description !== '' ? $description : null,
        ':col' => $color, ':i' => $icon,
        ':o' => $owner !== '' ? $owner : null,
        ':rp' => $repoPrefix !== '' ? $repoPrefix : null,
    ]);
    $newId = (int) $pdo->lastInsertId();

    // Папка + check.php из _templates (при создании).
    try {
        project_type_render_files($newId, $pdo, true);
    } catch (Throwable $e) {
        // не роняем создание, но сообщаем наверх — если хочешь, потом обработать
        @error_log('[project_type_save] render files failed: ' . $e->getMessage());
    }

    return $newId;
}

function project_type_delete(int $id, ?PDO $pdo = null): void
{
    if ($pdo === null) $pdo = db();
    $type = project_type_by_id($id, $pdo);
    if (!$type) return;

    // У типа есть проекты? Не разрешаем удалять.
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM projects WHERE project_type_id = :t');
    $stmt->execute([':t' => $id]);
    if ((int) $stmt->fetchColumn() > 0) {
        throw new RuntimeException('К типу привязаны проекты. Сначала удалите их или смените тип.');
    }

    $pdo->prepare('DELETE FROM project_types WHERE id = :id')->execute([':id' => $id]);

    // Папку не удаляем — вдруг админ туда что-то вручную положил. Просто оставляем.
}

/**
 * Пересоздать файлы типа из _templates. Если $overwrite = false, существующий
 * check.php не трогается. Если $overwrite = true, перезаписывается.
 */
function project_type_render_files(int $typeId, ?PDO $pdo = null, bool $overwrite = false): void
{
    if ($pdo === null) $pdo = db();
    $type = project_type_by_id($typeId, $pdo);
    if (!$type) throw new RuntimeException('Тип не найден.');

    $dir      = project_type_dir((string) $type['code']);
    $tplDir   = project_types_templates_dir();
    $tplFile  = $tplDir . '/check.php';

    if (!is_file($tplFile)) {
        throw new RuntimeException('Шаблон не найден: ' . $tplFile);
    }
    if (!is_dir($dir)) {
        if (!@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('Не удалось создать папку: ' . $dir);
        }
    }
    $target = $dir . '/check.php';
    if ($overwrite || !is_file($target)) {
        if (!@copy($tplFile, $target)) {
            throw new RuntimeException('Не удалось скопировать шаблон в ' . $target);
        }
    }
}

/**
 * Запуск проверки: include projects/<code>/check.php с $ctx.
 *
 * Если проверка падает или возвращает неверный формат — возвращаем ok=false
 * с текстом ошибки.
 *
 * @return array{ok:bool, errors:list<string>, warnings:list<string>, details:list<string>}
 */
function project_type_check(int $typeId, int $byUserId, ?PDO $pdo = null): array
{
    if ($pdo === null) $pdo = db();

    $type = project_type_by_id($typeId, $pdo);
    if (!$type) {
        return ['ok' => false, 'errors' => ['Тип не найден.'], 'warnings' => [], 'details' => []];
    }

    $dir  = project_type_dir((string) $type['code']);
    $file = $dir . '/check.php';

    if (!is_file($file)) {
        // Попробуем создать из шаблона в первый раз.
        try {
            project_type_render_files($typeId, $pdo, false);
        } catch (Throwable $e) {
            return ['ok' => false, 'errors' => ['check.php отсутствует и не удалось создать из шаблона: ' . $e->getMessage()], 'warnings' => [], 'details' => []];
        }
        if (!is_file($file)) {
            return ['ok' => false, 'errors' => ['check.php не найден: ' . $file], 'warnings' => [], 'details' => []];
        }
    }

    $cfg = prikaz_config();

    $ctx = [
        'type'     => $type,
        'gogs'     => [
            'base_url' => (string) ($cfg['gogs_base_url'] ?? ''),
            'token'    => (string) ($cfg['gogs_token'] ?? ''),
            'owner'    => (string) ($type['gogs_owner'] ?: ($cfg['gogs_owner'] ?? '')),
        ],
        'projects' => [],
        'dir'      => $dir,
    ];

    // Проекты этого типа (заготовка: реальную проверку пишет админ).
    try {
        $stmt = $pdo->prepare(
            'SELECT p.id, p.title, p.repo_url
             FROM projects p
             WHERE p.project_type_id = :t AND p.is_archived = 0
             ORDER BY p.id'
        );
        $stmt->execute([':t' => $typeId]);
        $ctx['projects'] = $stmt->fetchAll();
    } catch (Throwable $e) {
        $ctx['projects'] = [];
    }

    $result = null;
    try {
        $returned = (static function (string $__file, array $__ctx) {
            return include $__file;
        })($file, $ctx);
        if (is_array($returned)) {
            $result = $returned;
        }
    } catch (Throwable $e) {
        $result = ['ok' => false, 'errors' => ['Исключение в check.php: ' . $e->getMessage()], 'warnings' => [], 'details' => []];
    }

    if (!is_array($result)) {
        $result = ['ok' => false, 'errors' => ['check.php не вернул массив.'], 'warnings' => [], 'details' => []];
    }

    $ok       = (bool) ($result['ok']       ?? false);
    $errors   = (array) ($result['errors']   ?? []);
    $warnings = (array) ($result['warnings'] ?? []);
    $details  = (array) ($result['details']  ?? []);

    // Сохраняем результат.
    try {
        $pdo->prepare(
            'INSERT INTO project_type_checks (project_type_id, checked_by, ok, errors, warnings, details)
             VALUES (:t, :u, :o, :e, :w, :d)'
        )->execute([
            ':t' => $typeId,
            ':u' => $byUserId,
            ':o' => $ok ? 1 : 0,
            ':e' => $errors   !== [] ? json_encode($errors,   JSON_UNESCAPED_UNICODE) : null,
            ':w' => $warnings !== [] ? json_encode($warnings, JSON_UNESCAPED_UNICODE) : null,
            ':d' => $details  !== [] ? json_encode($details,  JSON_UNESCAPED_UNICODE) : null,
        ]);
    } catch (Throwable $e) {
        // не критично
    }

    return ['ok' => $ok, 'errors' => $errors, 'warnings' => $warnings, 'details' => $details];
}