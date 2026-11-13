<?php
declare(strict_types=1);

/**
 * Студенты: ФИО, группа, логин/пароль/email (в users), VK, Telegram.
 *
 * В users: login, password_hash, email, full_name (склеенное ФИО).
 * В students: full_name (для совместимости), last_name, first_name,
 * middle_name, group_id, group_name (для совместимости), alias (= login),
 * vk_url, telegram.
 */

function student_split_full_name(string $fullName): array
{
    $parts = preg_split('/\s+/u', trim($fullName), -1, PREG_SPLIT_NO_EMPTY);
    $last   = $parts[0] ?? '';
    $first  = $parts[1] ?? '';
    $middle = count($parts) > 2 ? implode(' ', array_slice($parts, 2)) : '';
    return ['last_name' => $last, 'first_name' => $first, 'middle_name' => $middle];
}

function student_join_full_name(string $last, string $first, string $middle = ''): string
{
    $fio = trim($last . ' ' . $first);
    if ($middle !== '') $fio .= ' ' . $middle;
    return trim($fio);
}

function student_validate_login(string $login): ?string
{
    if (!preg_match('/^[a-zA-Z0-9_.-]{3,64}$/', $login)) {
        return 'Логин: 3–64 символа, латиница, цифры, точка, дефис, подчёркивание.';
    }
    return null;
}

function student_validate_email(string $email): ?string
{
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return 'Некорректный email.';
    }
    return null;
}

function students_group_options(?PDO $pdo = null): array
{
    if ($pdo === null) $pdo = db();
    return $pdo->query(
        'SELECT g.id, g.name, g.academic_year_id, ay.name AS year_name, ay.is_current
         FROM groups g
         JOIN academic_years ay ON ay.id = g.academic_year_id
         ORDER BY ay.start_date DESC, g.name'
    )->fetchAll();
}

/**
 * Парсер массового добавления.
 *
 * Одна непустая строка = один студент: «Фамилия Имя Отчество, логин».
 * Пустые строки — разделители, пропускаются. Отчество опционально.
 *
 * @return array{students: list<array>, empty:int, invalid:int}
 */
function parse_student_lines(string $raw): array
{
    $san = text_sanitize_utf8($raw);
    if ($san['text'] === '') {
        return ['students' => [], 'empty' => 0, 'invalid' => $raw !== '' ? 1 : 0];
    }
    $lines = preg_split('/\R/u', $san['text']);
    if (!is_array($lines)) {
        return ['students' => [], 'empty' => 0, 'invalid' => 1];
    }

    $students = [];
    $empty = 0;
    $invalid = 0;

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') { $empty++; continue; }

        $parts = preg_split('/\s*,\s*/', $line, 2);
        if (!is_array($parts) || count($parts) < 2) { $invalid++; continue; }

        $fullNameRaw = trim((string) $parts[0]);
        $login       = trim((string) $parts[1]);

        if ($fullNameRaw === '' || $login === '') { $invalid++; continue; }

        $names = student_split_full_name($fullNameRaw);
        if ($names['last_name'] === '' || $names['first_name'] === '') {
            $invalid++;
            continue;
        }
        if (student_validate_login($login) !== null) {
            $invalid++;
            continue;
        }

        $students[] = [
            'last_name'   => $names['last_name'],
            'first_name'  => $names['first_name'],
            'middle_name' => $names['middle_name'],
            'full_name'   => student_join_full_name(
                $names['last_name'], $names['first_name'], $names['middle_name']
            ),
            'login'       => $login,
        ];
    }

    return ['students' => $students, 'empty' => $empty, 'invalid' => $invalid];
}

/**
 * Создание студента (users + user_roles + students) в транзакции.
 */
function students_create(array $data, int $createdBy, ?PDO $pdo = null): int
{
    if ($pdo === null) $pdo = db();

    $login    = trim((string) $data['login']);
    $password = (string) ($data['password'] ?? '');
    $email    = trim((string) ($data['email'] ?? ''));
    $last     = trim((string) ($data['last_name'] ?? ''));
    $first    = trim((string) ($data['first_name'] ?? ''));
    $middle   = trim((string) ($data['middle_name'] ?? ''));
    $vk       = trim((string) ($data['vk_url'] ?? ''));
    $tg       = ltrim(trim((string) ($data['telegram'] ?? '')), '@');
    $groupId  = (int) ($data['group_id'] ?? 0);
    $fullName = student_join_full_name($last, $first, $middle);

    $gname = '';
    if ($groupId > 0) {
        $stmt = $pdo->prepare('SELECT name FROM groups WHERE id = :id');
        $stmt->execute([':id' => $groupId]);
        $gname = (string) $stmt->fetchColumn();
    }

    $pdo->beginTransaction();
    try {
        $pdo->prepare(
            'INSERT INTO users (login, full_name, email, password_hash, is_active)
             VALUES (:l, :n, :e, :h, 1)'
        )->execute([
            ':l' => $login,
            ':n' => $fullName,
            ':e' => $email,
            ':h' => password_hash($password, PASSWORD_DEFAULT),
        ]);
        $userId = (int) $pdo->lastInsertId();

        $stmt = $pdo->prepare('SELECT id FROM roles WHERE code = :c');
        $stmt->execute([':c' => 'student']);
        $roleId = (int) $stmt->fetchColumn();
        $pdo->prepare('INSERT INTO user_roles (user_id, role_id) VALUES (:u, :r)')
            ->execute([':u' => $userId, ':r' => $roleId]);

        $pdo->prepare(
            'INSERT INTO students (user_id, full_name, last_name, first_name, middle_name,
                                   group_id, group_name, alias, vk_url, telegram)
             VALUES (:u, :fn, :ln, :fi, :mn, :g, :gn, :a, :vk, :tg)'
        )->execute([
            ':u'  => $userId,
            ':fn' => $fullName,
            ':ln' => $last,
            ':fi' => $first,
            ':mn' => $middle !== '' ? $middle : null,
            ':g'  => $groupId > 0 ? $groupId : null,
            ':gn' => $gname,
            ':a'  => $login,
            ':vk' => $vk !== '' ? $vk : null,
            ':tg' => $tg !== '' ? $tg : null,
        ]);
        $studentId = (int) $pdo->lastInsertId();

        $pdo->commit();
        return $studentId;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

/**
 * Обновление студента.
 * Если передан непустой $data['password'] — меняет и пароль.
 */
function students_update(int $studentId, array $data, ?PDO $pdo = null): void
{
    if ($pdo === null) $pdo = db();

    $login    = trim((string) $data['login']);
    $email    = trim((string) ($data['email'] ?? ''));
    $last     = trim((string) ($data['last_name'] ?? ''));
    $first    = trim((string) ($data['first_name'] ?? ''));
    $middle   = trim((string) ($data['middle_name'] ?? ''));
    $vk       = trim((string) ($data['vk_url'] ?? ''));
    $tg       = ltrim(trim((string) ($data['telegram'] ?? '')), '@');
    $groupId  = (int) ($data['group_id'] ?? 0);
    $password = (string) ($data['password'] ?? '');
    $fullName = student_join_full_name($last, $first, $middle);

    if ($password !== '' && strlen($password) < PRIKAZ_PASSWORD_MIN) {
        throw new InvalidArgumentException('Пароль не короче ' . PRIKAZ_PASSWORD_MIN . ' символов.');
    }

    $stmt = $pdo->prepare('SELECT user_id FROM students WHERE id = :id');
    $stmt->execute([':id' => $studentId]);
    $userId = (int) $stmt->fetchColumn();
    if ($userId <= 0) throw new RuntimeException('Студент не найден.');

    $gname = '';
    if ($groupId > 0) {
        $stmt = $pdo->prepare('SELECT name FROM groups WHERE id = :id');
        $stmt->execute([':id' => $groupId]);
        $gname = (string) $stmt->fetchColumn();
    }

    $pdo->beginTransaction();
    try {
        if ($password !== '') {
            $pdo->prepare(
                'UPDATE users
                 SET login = :l, full_name = :n, email = :e,
                     password_hash = :h, updated_at = :t
                 WHERE id = :id'
            )->execute([
                ':l'  => $login,
                ':n'  => $fullName,
                ':e'  => $email,
                ':h'  => password_hash($password, PASSWORD_DEFAULT),
                ':t'  => date('Y-m-d H:i:s'),
                ':id' => $userId,
            ]);
        } else {
            $pdo->prepare(
                'UPDATE users SET login = :l, full_name = :n, email = :e, updated_at = :t
                 WHERE id = :id'
            )->execute([
                ':l'  => $login,
                ':n'  => $fullName,
                ':e'  => $email,
                ':t'  => date('Y-m-d H:i:s'),
                ':id' => $userId,
            ]);
        }

        $pdo->prepare(
            'UPDATE students SET full_name = :fn, last_name = :ln, first_name = :fi,
                                 middle_name = :mn, group_id = :g, group_name = :gn,
                                 alias = :a, vk_url = :vk, telegram = :tg
             WHERE id = :id'
        )->execute([
            ':fn' => $fullName,
            ':ln' => $last,
            ':fi' => $first,
            ':mn' => $middle !== '' ? $middle : null,
            ':g'  => $groupId > 0 ? $groupId : null,
            ':gn' => $gname,
            ':a'  => $login,
            ':vk' => $vk !== '' ? $vk : null,
            ':tg' => $tg !== '' ? $tg : null,
            ':id' => $studentId,
        ]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}
/**
 * Удаление студента. Каскад по user_id сносит students, grades,
 * project_students, parent_student.
 */
function students_delete(int $studentId, ?PDO $pdo = null): void
{
    if ($pdo === null) $pdo = db();
    $stmt = $pdo->prepare('SELECT user_id FROM students WHERE id = :id');
    $stmt->execute([':id' => $studentId]);
    $userId = (int) $stmt->fetchColumn();
    if ($userId <= 0) return;
    $pdo->prepare('DELETE FROM users WHERE id = :id')->execute([':id' => $userId]);
}

/**
 * Массовое создание студентов.
 *
 * Пароль по умолчанию — «1111». Email — «<login>@mail.ru».
 * Если $groupId > 0 — все созданные привязываются к этой группе.
 *
 * @return array{added:int, skipped_login:int, skipped_format:int}
 */
function students_bulk_create(array $parsedStudents, int $groupId = 0, ?PDO $pdo = null): array
{
    if ($pdo === null) $pdo = db();

    $defaultPassword = '1111';
    $added = 0;
    $skippedLogin = 0;
    $skippedFormat = 0;
    $seenLogins = [];

    $gname = '';
    if ($groupId > 0) {
        $gstmt = $pdo->prepare('SELECT name FROM groups WHERE id = :id');
        $gstmt->execute([':id' => $groupId]);
        $gname = (string) $gstmt->fetchColumn();
    }

    $pdo->beginTransaction();
    try {
        $checkStmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE login = :l');
        $roleStmt  = $pdo->prepare('SELECT id FROM roles WHERE code = :c');
        $roleStmt->execute([':c' => 'student']);
        $roleId = (int) $roleStmt->fetchColumn();

        foreach ($parsedStudents as $s) {
            $login = (string) $s['login'];
            if (student_validate_login($login) !== null) { $skippedFormat++; continue; }
            if (isset($seenLogins[$login])) { $skippedLogin++; continue; }
            $checkStmt->execute([':l' => $login]);
            if ((int) $checkStmt->fetchColumn() > 0) { $skippedLogin++; continue; }
            $seenLogins[$login] = true;

            $email    = $login . '@mail.ru';
            $fullName = (string) $s['full_name'];
            $last     = (string) $s['last_name'];
            $first    = (string) $s['first_name'];
            $middle   = (string) $s['middle_name'];

            $pdo->prepare(
                'INSERT INTO users (login, full_name, email, password_hash, is_active)
                 VALUES (:l, :n, :e, :h, 1)'
            )->execute([
                ':l' => $login,
                ':n' => $fullName,
                ':e' => $email,
                ':h' => password_hash($defaultPassword, PASSWORD_DEFAULT),
            ]);
            $userId = (int) $pdo->lastInsertId();

            $pdo->prepare('INSERT INTO user_roles (user_id, role_id) VALUES (:u, :r)')
                ->execute([':u' => $userId, ':r' => $roleId]);

            $pdo->prepare(
                'INSERT INTO students (user_id, full_name, last_name, first_name,
                                       middle_name, group_id, group_name, alias)
                 VALUES (:u, :fn, :ln, :fi, :mn, :g, :gn, :a)'
            )->execute([
                ':u'  => $userId,
                ':fn' => $fullName,
                ':ln' => $last,
                ':fi' => $first,
                ':mn' => $middle !== '' ? $middle : null,
                ':g'  => $groupId > 0 ? $groupId : null,
                ':gn' => $gname,
                ':a'  => $login,
            ]);

            $added++;
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }

    return [
        'added'          => $added,
        'skipped_login'  => $skippedLogin,
        'skipped_format' => $skippedFormat,
    ];
}


/**
 * Личный кабинет: email и (опционально) пароль.
 */
function student_profile_save(int $userId, string $email, string $newPassword, ?PDO $pdo = null): void
{
    if ($pdo === null) $pdo = db();

    $email = trim($email);
    $err = student_validate_email($email);
    if ($err !== null) throw new InvalidArgumentException($err);

    if ($newPassword !== '') {
        if (strlen($newPassword) < PRIKAZ_PASSWORD_MIN) {
            throw new InvalidArgumentException('Пароль не короче ' . PRIKAZ_PASSWORD_MIN . ' символов.');
        }
        $pdo->prepare(
            'UPDATE users SET email = :e, password_hash = :h, updated_at = :t WHERE id = :id'
        )->execute([
            ':e'  => $email,
            ':h'  => password_hash($newPassword, PASSWORD_DEFAULT),
            ':t'  => date('Y-m-d H:i:s'),
            ':id' => $userId,
        ]);
    } else {
        $pdo->prepare('UPDATE users SET email = :e, updated_at = :t WHERE id = :id')
            ->execute([
                ':e'  => $email,
                ':t'  => date('Y-m-d H:i:s'),
                ':id' => $userId,
            ]);
    }
}