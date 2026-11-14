<?php
declare(strict_types=1);

/**
 * Действия раздела «Пользователи».
 *
 * Здесь — только преподаватели, родители и администраторы.
 * Студенты управляются через раздел «Студенты» (lib/actions/students.php):
 * у них есть личное дело (`students.user_id`), группа, Gogs-логин
 * и личный кабинет. Поэтому:
 *   - admin_user_create с ролью student — отказ;
 *   - admin_user_toggle / admin_user_reset_password для user_id,
 *     который является студентом, — отказ (даже если POST пришёл напрямую).
 */

// Guard: целевой user_id — студент? Тогда отказ и редирект в «Пользователи».
$__guardNotStudent = static function (PDO $pdo, int $userId, string $backUrl): void {
    if ($userId <= 0) return;

    // 1. Есть ли запись в students (значит, у пользователя личное дело студента).
    $stmt = $pdo->prepare('SELECT 1 FROM students WHERE user_id = :u LIMIT 1');
    $stmt->execute([':u' => $userId]);
    if ((int) $stmt->fetchColumn() === 1) {
        flash_set('danger', 'Студенты управляются в разделе «Студенты».');
        redirect($backUrl);
    }

    // 2. Есть ли роль student (на случай, если students-записи нет,
    //    но роль почему-то осталась).
    $stmt = $pdo->prepare(
        "SELECT 1
         FROM user_roles ur
         JOIN roles r ON r.id = ur.role_id
         WHERE ur.user_id = :u AND r.code = 'student' LIMIT 1"
    );
    $stmt->execute([':u' => $userId]);
    if ((int) $stmt->fetchColumn() === 1) {
        flash_set('danger', 'Студенты управляются в разделе «Студенты».');
        redirect($backUrl);
    }
};

// ===== ПОЛЬЗОВАТЕЛЬ: создание =====
if ($action === 'admin_user_create') {
    if (!in_array('admin', $currentUser['roles'], true)) { http_response_code(403); exit('Доступ запрещён'); }

    $backUrl  = 'index.php?page=admin&section=users';
    $login    = trim((string) ($_POST['login'] ?? ''));
    $fullName = trim((string) ($_POST['full_name'] ?? ''));
    $roleCode = (string) ($_POST['role'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    $email    = trim((string) ($_POST['email'] ?? ''));

    if ($login === '' || $fullName === '' || $password === '') {
        flash_set('danger', 'Логин, ФИО и пароль обязательны.');
        redirect($backUrl);
    }
    if (strlen($password) < PRIKAZ_PASSWORD_MIN) {
        flash_set('danger', 'Пароль не короче ' . PRIKAZ_PASSWORD_MIN . ' символов.');
        redirect($backUrl);
    }
    if ($roleCode === 'student') {
        flash_set('danger', 'Студенты создаются в разделе «Студенты» — там появляется личное дело, группа и связка с Gogs.');
        redirect($backUrl);
    }
    if (!in_array($roleCode, ['admin', 'teacher', 'parent'], true)) {
        flash_set('danger', 'Неверная роль.');
        redirect($backUrl);
    }

    try {
        $pdo->beginTransaction();
        $pdo->prepare(
            'INSERT INTO users (login, full_name, email, password_hash, is_active)
             VALUES (:l, :n, :e, :h, 1)'
        )->execute([
            ':l' => $login,
            ':n' => $fullName,
            ':e' => $email !== '' ? $email : null,
            ':h' => password_hash($password, PASSWORD_DEFAULT),
        ]);
        $userId = (int) $pdo->lastInsertId();

        $stmt = $pdo->prepare('SELECT id FROM roles WHERE code = :c');
        $stmt->execute([':c' => $roleCode]);
        $roleId = (int) $stmt->fetchColumn();
        $pdo->prepare('INSERT INTO user_roles (user_id, role_id) VALUES (:u, :r)')
            ->execute([':u' => $userId, ':r' => $roleId]);

        if ($roleCode === 'teacher') {
            $pdo->prepare('INSERT INTO teachers (user_id, full_name) VALUES (:u, :n)')
                ->execute([':u' => $userId, ':n' => $fullName]);
        } elseif ($roleCode === 'parent') {
            $pdo->prepare('INSERT INTO parents (user_id, full_name) VALUES (:u, :n)')
                ->execute([':u' => $userId, ':n' => $fullName]);
        }

        $pdo->commit();
        audit_log((int) $currentUser['id'], 'user_create', 'user', $userId);
        flash_set('success', "Пользователь «{$login}» создан.");
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        flash_set('danger', 'Не удалось создать: ' . $e->getMessage());
    }
    redirect($backUrl);
}

// ===== ПОЛЬЗОВАТЕЛЬ: блокировка =====
if ($action === 'admin_user_toggle') {
    if (!in_array('admin', $currentUser['roles'], true)) { http_response_code(403); exit('Доступ запрещён'); }

    $backUrl = 'index.php?page=admin&section=users';
    $userId  = (int) ($_POST['user_id'] ?? 0);

    if ($userId === (int) $currentUser['id']) {
        flash_set('danger', 'Нельзя деактивировать самого себя.');
        redirect($backUrl);
    }
    $__guardNotStudent($pdo, $userId, $backUrl);

    $pdo->prepare('UPDATE users SET is_active = 1 - is_active, updated_at = :t WHERE id = :id')
        ->execute([':t' => date('Y-m-d H:i:s'), ':id' => $userId]);
    audit_log((int) $currentUser['id'], 'user_toggle_active', 'user', $userId);
    flash_set('success', 'Статус изменён.');
    redirect($backUrl);
}

// ===== ПОЛЬЗОВАТЕЛЬ: сброс пароля =====
if ($action === 'admin_user_reset_password') {
    if (!in_array('admin', $currentUser['roles'], true)) { http_response_code(403); exit('Доступ запрещён'); }

    $backUrl  = 'index.php?page=admin&section=users';
    $userId   = (int) ($_POST['user_id'] ?? 0);
    $password = (string) ($_POST['password'] ?? '');

    $__guardNotStudent($pdo, $userId, $backUrl);

    if (strlen($password) < PRIKAZ_PASSWORD_MIN) {
        flash_set('danger', 'Пароль не короче ' . PRIKAZ_PASSWORD_MIN . ' символов.');
        redirect($backUrl);
    }
    $pdo->prepare('UPDATE users SET password_hash = :h, updated_at = :t WHERE id = :id')
        ->execute([
            ':h'  => password_hash($password, PASSWORD_DEFAULT),
            ':t'  => date('Y-m-d H:i:s'),
            ':id' => $userId,
        ]);
    audit_log((int) $currentUser['id'], 'user_reset_password', 'user', $userId);
    flash_set('success', 'Пароль сброшен.');
    redirect($backUrl);
}