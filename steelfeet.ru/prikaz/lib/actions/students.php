<?php
declare(strict_types=1);

// ===== СТУДЕНТ: создание =====
if ($action === 'admin_student_create') {
    if (!in_array('admin', $currentUser['roles'], true)) { http_response_code(403); exit('Доступ запрещён'); }

    $login    = trim((string) ($_POST['login'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $email    = trim((string) ($_POST['email'] ?? ''));
    $last     = trim((string) ($_POST['last_name'] ?? ''));
    $first    = trim((string) ($_POST['first_name'] ?? ''));
    $middle   = trim((string) ($_POST['middle_name'] ?? ''));
    $vk       = trim((string) ($_POST['vk_url'] ?? ''));
    $tg       = trim((string) ($_POST['telegram'] ?? ''));
    $groupId  = (int) ($_POST['group_id'] ?? 0);

    $backUrl = 'index.php?page=admin&section=students';

    $err = student_validate_login($login);
    if ($err !== null) { flash_set('danger', $err); redirect($backUrl); }
    if ($last === '' || $first === '') {
        flash_set('danger', 'Фамилия и имя обязательны.');
        redirect($backUrl);
    }
    if (strlen($password) < PRIKAZ_PASSWORD_MIN) {
        flash_set('danger', 'Пароль не короче ' . PRIKAZ_PASSWORD_MIN . ' символов.');
        redirect($backUrl);
    }
    $err = student_validate_email($email);
    if ($err !== null) { flash_set('danger', $err); redirect($backUrl); }

    try {
        $id = students_create([
            'login'       => $login,
            'password'    => $password,
            'email'       => $email,
            'last_name'   => $last,
            'first_name'  => $first,
            'middle_name' => $middle,
            'vk_url'      => $vk,
            'telegram'    => $tg,
            'group_id'    => $groupId,
        ], (int) $currentUser['id'], $pdo);
        audit_log((int) $currentUser['id'], 'student_create', 'student', $id);
        flash_set('success', 'Студент «' . $login . '» создан.');
    } catch (PDOException $e) {
        if (str_contains($e->getMessage(), 'UNIQUE')) {
            flash_set('danger', 'Логин занят.');
        } else {
            flash_set('danger', 'Ошибка: ' . $e->getMessage());
        }
    }
    redirect($backUrl);
}

// ===== СТУДЕНТ: обновление =====
if ($action === 'admin_student_update') {
    if (!in_array('admin', $currentUser['roles'], true)) { http_response_code(403); exit('Доступ запрещён'); }

    $id       = (int) ($_POST['id'] ?? 0);
    $login    = trim((string) ($_POST['login'] ?? ''));
    $email    = trim((string) ($_POST['email'] ?? ''));
    $last     = trim((string) ($_POST['last_name'] ?? ''));
    $first    = trim((string) ($_POST['first_name'] ?? ''));
    $middle   = trim((string) ($_POST['middle_name'] ?? ''));
    $vk       = trim((string) ($_POST['vk_url'] ?? ''));
    $tg       = trim((string) ($_POST['telegram'] ?? ''));
    $groupId  = (int) ($_POST['group_id'] ?? 0);

    $backUrl = 'index.php?page=admin&section=students';

    if ($id <= 0) {
        flash_set('danger', 'Студент не найден.');
        redirect($backUrl);
    }
    $err = student_validate_login($login);
    if ($err !== null) { flash_set('danger', $err); redirect($backUrl); }
    if ($last === '' || $first === '') {
        flash_set('danger', 'Фамилия и имя обязательны.');
        redirect($backUrl);
    }
    $err = student_validate_email($email);
    if ($err !== null) { flash_set('danger', $err); redirect($backUrl); }

    try {
        students_update($id, [
            'login'       => $login,
            'email'       => $email,
            'last_name'   => $last,
            'first_name'  => $first,
            'middle_name' => $middle,
            'vk_url'      => $vk,
            'telegram'    => $tg,
            'group_id'    => $groupId,
        ], $pdo);
        audit_log((int) $currentUser['id'], 'student_update', 'student', $id);
        flash_set('success', 'Студент сохранён.');
    } catch (PDOException $e) {
        if (str_contains($e->getMessage(), 'UNIQUE')) {
            flash_set('danger', 'Логин занят.');
        } else {
            flash_set('danger', 'Ошибка: ' . $e->getMessage());
        }
    }
    redirect($backUrl);
}

// ===== СТУДЕНТ: удаление =====
if ($action === 'admin_student_delete') {
    if (!in_array('admin', $currentUser['roles'], true)) { http_response_code(403); exit('Доступ запрещён'); }
    $id = (int) ($_POST['id'] ?? 0);
    $backUrl = 'index.php?page=admin&section=students';
    try {
        students_delete($id, $pdo);
        audit_log((int) $currentUser['id'], 'student_delete', 'student', $id);
        flash_set('success', 'Студент удалён.');
    } catch (Throwable $e) {
        flash_set('danger', 'Ошибка: ' . $e->getMessage());
    }
    redirect($backUrl);
}


// ===== СТУДЕНТ: массовое добавление =====
if ($action === 'admin_student_bulk_create') {
    if (!in_array('admin', $currentUser['roles'], true)) { http_response_code(403); exit('Доступ запрещён'); }

    $raw     = (string) ($_POST['students'] ?? '');
    $groupId = (int) ($_POST['group_id'] ?? 0);
    $backUrl = 'index.php?page=admin&section=students#bulk-students';

    if ($groupId <= 0) {
        flash_set('danger', 'Выберите группу — без группы массовое добавление невозможно.');
        redirect($backUrl);
    }

    $stmt = $pdo->prepare(
        'SELECT g.id
         FROM groups g
         JOIN academic_years ay ON ay.id = g.academic_year_id
         WHERE g.id = :id AND ay.is_current = 1'
    );
    $stmt->execute([':id' => $groupId]);
    if ((int) $stmt->fetchColumn() === 0) {
        flash_set('danger', 'Выбранная группа не относится к текущему учебному году.');
        redirect($backUrl);
    }

    $parsed = parse_student_lines($raw);

    if ($parsed['students'] === []) {
        $msg = 'Не найдено ни одной валидной строки.';
        if ($parsed['invalid'] > 0) $msg .= ' Некорректных строк: ' . $parsed['invalid'] . '.';
        flash_set('danger', $msg);
        redirect($backUrl);
    }

    try {
        $res = students_bulk_create($parsed['students'], $groupId, $pdo);
        audit_log((int) $currentUser['id'], 'student_bulk_create', 'user', 0,
            'added=' . $res['added'] . ' skipped_login=' . $res['skipped_login']
            . ' skipped_format=' . $res['skipped_format']
            . ' group_id=' . $groupId . ' default_password=1111');

        $msg = 'Добавлено студентов: ' . $res['added'] . '.';
        $stmt = $pdo->prepare('SELECT name FROM groups WHERE id = :id');
        $stmt->execute([':id' => $groupId]);
        $gname = (string) $stmt->fetchColumn();
        if ($gname !== '') $msg .= ' Группа: ' . $gname . '.';
        if ($res['skipped_login']  > 0) $msg .= ' Пропущено из-за занятого логина: ' . $res['skipped_login'] . '.';
        if ($res['skipped_format'] > 0) $msg .= ' Пропущено из-за неверного формата: ' . $res['skipped_format'] . '.';
        if ($parsed['invalid']     > 0) $msg .= ' Некорректных строк во входе: ' . $parsed['invalid'] . '.';
        $msg .= ' Пароль по умолчанию — 1111, email — <логин>@mail.ru.';
        flash_set('success', $msg);
    } catch (Throwable $e) {
        flash_set('danger', 'Ошибка: ' . $e->getMessage());
    }
    redirect($backUrl);
}


// ===== СТУДЕНТ: личный кабинет =====
if ($action === 'student_profile_save') {
    if (!in_array('student', $currentUser['roles'], true)) { http_response_code(403); exit('Доступ запрещён'); }

    $email   = trim((string) ($_POST['email'] ?? ''));
    $newPwd  = (string) ($_POST['new_password'] ?? '');
    $repeat  = (string) ($_POST['repeat_password'] ?? '');

    $backUrl = 'index.php?page=student';

    if ($newPwd !== '' && $newPwd !== $repeat) {
        flash_set('danger', 'Пароли не совпадают.');
        redirect($backUrl);
    }

    try {
        student_profile_save((int) $currentUser['id'], $email, $newPwd, $pdo);
        audit_log((int) $currentUser['id'], 'student_profile_update', 'user', (int) $currentUser['id']);
        flash_set('success', 'Профиль сохранён.');
    } catch (Throwable $e) {
        flash_set('danger', $e->getMessage());
    }
    redirect($backUrl);
}

// ===== РОДИТЕЛЬ: привязка =====
if ($action === 'admin_parent_link') {
    if (!in_array('admin', $currentUser['roles'], true)) { http_response_code(403); exit('Доступ запрещён'); }
    $parentId  = (int) ($_POST['parent_id'] ?? 0);
    $studentId = (int) ($_POST['student_id'] ?? 0);
    if ($parentId > 0 && $studentId > 0) {
        $pdo->prepare('INSERT OR IGNORE INTO parent_student (parent_id, student_id) VALUES (:p, :s)')
            ->execute([':p' => $parentId, ':s' => $studentId]);
        audit_log((int) $currentUser['id'], 'parent_link', 'parent', $parentId, 'student=' . $studentId);
        flash_set('success', 'Связка добавлена.');
    }
    redirect('index.php?page=admin&section=parents');
}

// ===== РОДИТЕЛЬ: отвязка =====
if ($action === 'admin_parent_unlink') {
    if (!in_array('admin', $currentUser['roles'], true)) { http_response_code(403); exit('Доступ запрещён'); }
    $parentId  = (int) ($_POST['parent_id'] ?? 0);
    $studentId = (int) ($_POST['student_id'] ?? 0);
    $pdo->prepare('DELETE FROM parent_student WHERE parent_id = :p AND student_id = :s')
        ->execute([':p' => $parentId, ':s' => $studentId]);
    audit_log((int) $currentUser['id'], 'parent_unlink', 'parent', $parentId, 'student=' . $studentId);
    flash_set('success', 'Связка удалена.');
    redirect('index.php?page=admin&section=parents');
}