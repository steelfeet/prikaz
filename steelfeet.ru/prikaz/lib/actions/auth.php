<?php
declare(strict_types=1);

if ($action === 'logout') {
    auth_logout();
    redirect('index.php?page=login');
}

if ($action === 'change_own_password') {
    $current = (string) ($_POST['current_password'] ?? '');
    $new     = (string) ($_POST['new_password'] ?? '');
    $repeat  = (string) ($_POST['repeat_password'] ?? '');
    $email   = trim((string) ($_POST['email'] ?? ''));
    $vkUrl   = trim((string) ($_POST['vk_url'] ?? ''));
    $tg      = ltrim(trim((string) ($_POST['telegram'] ?? '')), '@');

    $stmt = $pdo->prepare('SELECT password_hash, email FROM users WHERE id = :id');
    $stmt->execute([':id' => (int) $currentUser['id']]);
    $row = $stmt->fetch();
    if (!$row) {
        flash_set('danger', 'Пользователь не найден.');
        redirect('index.php?page=login');
    }

    if (!password_verify($current, (string) $row['password_hash'])) {
        flash_set('danger', 'Текущий пароль неверен.');
        redirect('index.php?page=profile');
    }

    $newEmail = $email !== '' ? $email : (string) ($row['email'] ?? '');
    if ($newEmail !== '' && !filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
        flash_set('danger', 'Некорректный email.');
        redirect('index.php?page=profile');
    }

    $changePassword = ($new !== '');

    if ($changePassword) {
        if (strlen($new) < PRIKAZ_PASSWORD_MIN) {
            flash_set('danger', 'Пароль не короче ' . PRIKAZ_PASSWORD_MIN . ' символов.');
            redirect('index.php?page=profile');
        }
        if ($new !== $repeat) {
            flash_set('danger', 'Пароли не совпадают.');
            redirect('index.php?page=profile');
        }
    }

    $isStudent = in_array('student', $currentUser['roles'], true);
    $hasStudentRow = false;
    if ($isStudent) {
        $stmt = $pdo->prepare('SELECT id FROM students WHERE user_id = :u LIMIT 1');
        $stmt->execute([':u' => (int) $currentUser['id']]);
        $hasStudentRow = $stmt->fetchColumn() !== false;
    }

    $pdo->beginTransaction();
    try {
        if ($changePassword) {
            $pdo->prepare(
                'UPDATE users SET email = :e, password_hash = :h, updated_at = :t WHERE id = :id'
            )->execute([
                ':e'  => $newEmail !== '' ? $newEmail : null,
                ':h'  => password_hash($new, PASSWORD_DEFAULT),
                ':t'  => date('Y-m-d H:i:s'),
                ':id' => (int) $currentUser['id'],
            ]);
        } else {
            $pdo->prepare(
                'UPDATE users SET email = :e, updated_at = :t WHERE id = :id'
            )->execute([
                ':e'  => $newEmail !== '' ? $newEmail : null,
                ':t'  => date('Y-m-d H:i:s'),
                ':id' => (int) $currentUser['id'],
            ]);
        }

        if ($hasStudentRow) {
            $pdo->prepare(
                'UPDATE students SET vk_url = :vk, telegram = :tg WHERE user_id = :u'
            )->execute([
                ':vk' => $vkUrl !== '' ? $vkUrl : null,
                ':tg' => $tg    !== '' ? $tg    : null,
                ':u'  => (int) $currentUser['id'],
            ]);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        flash_set('danger', 'Ошибка: ' . $e->getMessage());
        redirect('index.php?page=profile');
    }

    audit_log((int) $currentUser['id'], 'profile_update', 'user', (int) $currentUser['id'],
        'email_changed=' . ($newEmail !== '' ? '1' : '0')
        . ' password_changed=' . ($changePassword ? '1' : '0')
        . ' vk_changed=' . ($hasStudentRow ? '1' : '0')
        . ' tg_changed=' . ($hasStudentRow ? '1' : '0'));

    flash_set('success', 'Профиль сохранён.');
    redirect('index.php?page=profile');
}