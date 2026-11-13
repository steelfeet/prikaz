<?php
declare(strict_types=1);

// ===== ПОЛЬЗОВАТЕЛЬ: создание =====
if ($action === 'admin_user_create') {
    if (!in_array('admin', $currentUser['roles'], true)) { http_response_code(403); exit('Доступ запрещён'); }
    $login    = trim((string) ($_POST['login'] ?? ''));
    $fullName = trim((string) ($_POST['full_name'] ?? ''));
    $roleCode = (string) ($_POST['role'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    $email    = trim((string) ($_POST['email'] ?? ''));
    $group    = trim((string) ($_POST['group_name'] ?? ''));
    $alias    = trim((string) ($_POST['alias'] ?? ''));
    if ($login === '' || $fullName === '' || $password === '') {
        flash_set('danger', 'Логин, ФИО и пароль обязательны.');
        redirect('index.php?page=admin&section=users');
    }
    if (strlen($password) < PRIKAZ_PASSWORD_MIN) {
        flash_set('danger', 'Пароль не короче ' . PRIKAZ_PASSWORD_MIN . ' символов.');
        redirect('index.php?page=admin&section=users');
    }
    if (!in_array($roleCode, ['admin', 'teacher', 'student', 'parent'], true)) {
        flash_set('danger', 'Неверная роль.');
        redirect('index.php?page=admin&section=users');
    }
    if ($roleCode === 'student' && $group === '') {
        flash_set('danger', 'Для студента укажите группу.');
        redirect('index.php?page=admin&section=users');
    }
    try {
        $pdo->beginTransaction();
        $pdo->prepare('INSERT INTO users (login, full_name, email, password_hash, is_active) VALUES (:l, :n, :e, :h, 1)')
            ->execute([
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
        } elseif ($roleCode === 'student') {
            $pdo->prepare('INSERT INTO students (user_id, full_name, group_name, alias) VALUES (:u, :n, :g, :a)')
                ->execute([
                    ':u' => $userId,
                    ':n' => $fullName,
                    ':g' => $group,
                    ':a' => $alias !== '' ? $alias : null,
                ]);
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
    redirect('index.php?page=admin&section=users');
}

// ===== ПОЛЬЗОВАТЕЛЬ: блокировка =====
if ($action === 'admin_user_toggle') {
    if (!in_array('admin', $currentUser['roles'], true)) { http_response_code(403); exit('Доступ запрещён'); }
    $userId = (int) ($_POST['user_id'] ?? 0);
    if ($userId === (int) $currentUser['id']) {
        flash_set('danger', 'Нельзя деактивировать самого себя.');
        redirect('index.php?page=admin&section=users');
    }
    $pdo->prepare('UPDATE users SET is_active = 1 - is_active, updated_at = :t WHERE id = :id')
        ->execute([':t' => date('Y-m-d H:i:s'), ':id' => $userId]);
    audit_log((int) $currentUser['id'], 'user_toggle_active', 'user', $userId);
    flash_set('success', 'Статус изменён.');
    redirect('index.php?page=admin&section=users');
}

// ===== ПОЛЬЗОВАТЕЛЬ: сброс пароля =====
if ($action === 'admin_user_reset_password') {
    if (!in_array('admin', $currentUser['roles'], true)) { http_response_code(403); exit('Доступ запрещён'); }
    $userId   = (int) ($_POST['user_id'] ?? 0);
    $password = (string) ($_POST['password'] ?? '');
    if (strlen($password) < PRIKAZ_PASSWORD_MIN) {
        flash_set('danger', 'Пароль не короче ' . PRIKAZ_PASSWORD_MIN . ' символов.');
        redirect('index.php?page=admin&section=users');
    }
    $pdo->prepare('UPDATE users SET password_hash = :h, updated_at = :t WHERE id = :id')
        ->execute([
            ':h'  => password_hash($password, PASSWORD_DEFAULT),
            ':t'  => date('Y-m-d H:i:s'),
            ':id' => $userId,
        ]);
    audit_log((int) $currentUser['id'], 'user_reset_password', 'user', $userId);
    flash_set('success', 'Пароль сброшен.');
    redirect('index.php?page=admin&section=users');
}