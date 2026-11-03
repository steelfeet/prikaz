<?php
declare(strict_types=1);

/**
 * POST-обработчики Приказа. Ожидает $currentUser и $action.
 */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
csrf_verify();
$pdo = db();

// ===== Logout =====
if ($action === 'logout') {
    auth_logout();
    redirect('index.php?page=login');
}

// ===== Смена своего пароля =====
if ($action === 'change_own_password') {
    $current = (string) ($_POST['current_password'] ?? '');
    $new     = (string) ($_POST['new_password'] ?? '');
    $repeat  = (string) ($_POST['repeat_password'] ?? '');
    if (strlen($new) < PRIKAZ_PASSWORD_MIN) {
        flash_set('danger', 'Пароль не короче ' . PRIKAZ_PASSWORD_MIN . ' символов.');
        redirect('index.php?page=profile');
    }
    if ($new !== $repeat) {
        flash_set('danger', 'Пароли не совпадают.');
        redirect('index.php?page=profile');
    }
    $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = :id');
    $stmt->execute([':id' => (int) $currentUser['id']]);
    $hash = (string) $stmt->fetchColumn();
    if (!password_verify($current, $hash)) {
        flash_set('danger', 'Текущий пароль неверен.');
        redirect('index.php?page=profile');
    }
    $pdo->prepare('UPDATE users SET password_hash = :h, updated_at = :t WHERE id = :id')
        ->execute([':h' => password_hash($new, PASSWORD_DEFAULT), ':t' => date('Y-m-d H:i:s'), ':id' => (int) $currentUser['id']]);
    audit_log((int) $currentUser['id'], 'password_change', 'user', (int) $currentUser['id']);
    flash_set('success', 'Пароль изменён.');
    redirect('index.php?page=profile');
}

// ===== Оценка =====
if ($action === 'grade_set') {
    if (!in_array('teacher', $currentUser['roles'], true) && !in_array('admin', $currentUser['roles'], true)) {
        http_response_code(403); exit('Доступ запрещён');
    }
    $studentId = (int) ($_POST['student_id'] ?? 0);
    $lessonId  = (int) ($_POST['lesson_id'] ?? 0);
    $mark      = (string) ($_POST['mark'] ?? '');
    $note      = trim((string) ($_POST['note'] ?? ''));
    if (!valid_mark($mark) || $studentId <= 0 || $lessonId <= 0) {
        flash_set('danger', 'Неверные данные.');
        redirect('index.php?page=teacher&tab=journal');
    }
    $stmt = $pdo->prepare('SELECT id FROM grades WHERE student_id = :s AND lesson_id = :l');
    $stmt->execute([':s' => $studentId, ':l' => $lessonId]);
    $existing = $stmt->fetchColumn();
    if ($existing !== false) {
        $pdo->prepare('UPDATE grades SET mark = :m, note = :n, updated_by = :u, updated_at = :t WHERE id = :id')
            ->execute([':m' => $mark, ':n' => $note !== '' ? $note : null, ':u' => (int) $currentUser['id'], ':t' => date('Y-m-d H:i:s'), ':id' => (int) $existing]);
        audit_log((int) $currentUser['id'], 'grade_update', 'grade', (int) $existing);
    } else {
        $pdo->prepare('INSERT INTO grades (lesson_id, student_id, mark, note, updated_by) VALUES (:l, :s, :m, :n, :u)')
            ->execute([':l' => $lessonId, ':s' => $studentId, ':m' => $mark, ':n' => $note !== '' ? $note : null, ':u' => (int) $currentUser['id']]);
        audit_log((int) $currentUser['id'], 'grade_create', 'grade', (int) $pdo->lastInsertId());
    }
    flash_set('success', 'Оценка сохранена.');
    redirect('index.php?page=teacher&tab=journal');
}

// ===== Смена фазы проекта =====
if ($action === 'project_move_phase') {
    if (!in_array('teacher', $currentUser['roles'], true) && !in_array('admin', $currentUser['roles'], true)) {
        http_response_code(403); exit('Доступ запрещён');
    }
    $projectId = (int) ($_POST['project_id'] ?? 0);
    $phaseNum  = (int) ($_POST['phase_num'] ?? 0);
    $note      = trim((string) ($_POST['note'] ?? ''));
    try {
        project_change_phase($projectId, $phaseNum, $note, (int) $currentUser['id']);
        audit_log((int) $currentUser['id'], 'project_phase_change', 'project', $projectId);
        flash_set('success', 'Фаза изменена.');
    } catch (Throwable $e) {
        flash_set('danger', $e->getMessage());
    }
    redirect('index.php?page=teacher&tab=projects');
}

// ===== УЧЕБНЫЙ ГОД: сохранение (создание / редактирование) =====
if ($action === 'admin_academic_year_save') {
    if (!in_array('admin', $currentUser['roles'], true)) { http_response_code(403); exit('Доступ запрещён'); }

    $id        = (int) ($_POST['id'] ?? 0);
    $name      = trim((string) ($_POST['name'] ?? ''));
    $startDate = trim((string) ($_POST['start_date'] ?? ''));
    $endDate   = trim((string) ($_POST['end_date'] ?? ''));
    $isCurrent = (isset($_POST['is_current']) && (string) $_POST['is_current'] === '1') ? 1 : 0;

    if ($name === '' || $startDate === '' || $endDate === '') {
        flash_set('danger', 'Заполните название и обе даты.');
        redirect('index.php?page=admin&section=academic_years');
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate) ||
        !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
        flash_set('danger', 'Даты должны быть в формате YYYY-MM-DD.');
        redirect('index.php?page=admin&section=academic_years');
    }
    if (strtotime($endDate) <= strtotime($startDate)) {
        flash_set('danger', 'Дата окончания должна быть позже даты начала.');
        redirect('index.php?page=admin&section=academic_years');
    }

    try {
        $pdo->beginTransaction();
        if ($isCurrent === 1) {
            $pdo->exec('UPDATE academic_years SET is_current = 0');
        }
        if ($id > 0) {
            $pdo->prepare(
                'UPDATE academic_years
                 SET name = :n, start_date = :s, end_date = :e, is_current = :c, updated_at = :t
                 WHERE id = :id'
            )->execute([
                ':n' => $name, ':s' => $startDate, ':e' => $endDate,
                ':c' => $isCurrent, ':t' => date('Y-m-d H:i:s'), ':id' => $id,
            ]);
            audit_log((int) $currentUser['id'], 'academic_year_update', 'academic_year', $id);
        } else {
            $pdo->prepare(
                'INSERT INTO academic_years (name, start_date, end_date, is_current)
                 VALUES (:n, :s, :e, :c)'
            )->execute([':n' => $name, ':s' => $startDate, ':e' => $endDate, ':c' => $isCurrent]);
            $newId = (int) $pdo->lastInsertId();
            audit_log((int) $currentUser['id'], 'academic_year_create', 'academic_year', $newId);
        }
        $pdo->commit();
        flash_set('success', 'Учебный год сохранён.');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        flash_set('danger', 'Ошибка: ' . $e->getMessage());
    }
    redirect('index.php?page=admin&section=academic_years');
}

// ===== УЧЕБНЫЙ ГОД: удаление =====
if ($action === 'admin_academic_year_delete') {
    if (!in_array('admin', $currentUser['roles'], true)) { http_response_code(403); exit('Доступ запрещён'); }
    $id = (int) ($_POST['id'] ?? 0);
    try {
        $stmt = $pdo->prepare('SELECT is_current FROM academic_years WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $isCurrent = (int) $stmt->fetchColumn();
        if ($isCurrent === 1) {
            flash_set('danger', 'Нельзя удалить текущий учебный год. Сначала назначьте текущим другой.');
            redirect('index.php?page=admin&section=academic_years');
        }
        $pdo->prepare('DELETE FROM academic_years WHERE id = :id')->execute([':id' => $id]);
        audit_log((int) $currentUser['id'], 'academic_year_delete', 'academic_year', $id);
        flash_set('success', 'Учебный год удалён.');
    } catch (Throwable $e) {
        flash_set('danger', 'Ошибка: ' . $e->getMessage());
    }
    redirect('index.php?page=admin&section=academic_years');
}

// ===== УЧЕБНЫЙ ГОД: сделать текущим =====
if ($action === 'admin_academic_year_set_current') {
    if (!in_array('admin', $currentUser['roles'], true)) { http_response_code(403); exit('Доступ запрещён'); }
    $id = (int) ($_POST['id'] ?? 0);
    try {
        $pdo->beginTransaction();
        $pdo->exec('UPDATE academic_years SET is_current = 0');
        $pdo->prepare('UPDATE academic_years SET is_current = 1, updated_at = :t WHERE id = :id')
            ->execute([':t' => date('Y-m-d H:i:s'), ':id' => $id]);
        $pdo->commit();
        audit_log((int) $currentUser['id'], 'academic_year_set_current', 'academic_year', $id);
        flash_set('success', 'Текущий учебный год обновлён.');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        flash_set('danger', 'Ошибка: ' . $e->getMessage());
    }
    redirect('index.php?page=admin&section=academic_years');
}

// ===== Пользователь: создание =====
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
            ->execute([':l' => $login, ':n' => $fullName, ':e' => $email !== '' ? $email : null, ':h' => password_hash($password, PASSWORD_DEFAULT)]);
        $userId = (int) $pdo->lastInsertId();
        $stmt = $pdo->prepare('SELECT id FROM roles WHERE code = :c');
        $stmt->execute([':c' => $roleCode]);
        $roleId = (int) $stmt->fetchColumn();
        $pdo->prepare('INSERT INTO user_roles (user_id, role_id) VALUES (:u, :r)')->execute([':u' => $userId, ':r' => $roleId]);
        if ($roleCode === 'teacher') {
            $pdo->prepare('INSERT INTO teachers (user_id, full_name) VALUES (:u, :n)')->execute([':u' => $userId, ':n' => $fullName]);
        } elseif ($roleCode === 'student') {
            $pdo->prepare('INSERT INTO students (user_id, full_name, group_name, alias) VALUES (:u, :n, :g, :a)')
                ->execute([':u' => $userId, ':n' => $fullName, ':g' => $group, ':a' => $alias !== '' ? $alias : null]);
        } elseif ($roleCode === 'parent') {
            $pdo->prepare('INSERT INTO parents (user_id, full_name) VALUES (:u, :n)')->execute([':u' => $userId, ':n' => $fullName]);
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

if ($action === 'admin_user_reset_password') {
    if (!in_array('admin', $currentUser['roles'], true)) { http_response_code(403); exit('Доступ запрещён'); }
    $userId   = (int) ($_POST['user_id'] ?? 0);
    $password = (string) ($_POST['password'] ?? '');
    if (strlen($password) < PRIKAZ_PASSWORD_MIN) {
        flash_set('danger', 'Пароль не короче ' . PRIKAZ_PASSWORD_MIN . ' символов.');
        redirect('index.php?page=admin&section=users');
    }
    $pdo->prepare('UPDATE users SET password_hash = :h, updated_at = :t WHERE id = :id')
        ->execute([':h' => password_hash($password, PASSWORD_DEFAULT), ':t' => date('Y-m-d H:i:s'), ':id' => $userId]);
    audit_log((int) $currentUser['id'], 'user_reset_password', 'user', $userId);
    flash_set('success', 'Пароль сброшен.');
    redirect('index.php?page=admin&section=users');
}

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

if ($action === 'admin_lesson_save') {
    if (!in_array('admin', $currentUser['roles'], true)) { http_response_code(403); exit('Доступ запрещён'); }
    $id       = (int) ($_POST['id'] ?? 0);
    $ord      = (int) ($_POST['ord'] ?? 0);
    $date     = trim((string) ($_POST['date_str'] ?? ''));
    $topic    = trim((string) ($_POST['topic'] ?? ''));
    $homework = trim((string) ($_POST['homework'] ?? ''));
    if ($ord <= 0 || $date === '' || $topic === '') {
        flash_set('danger', 'Заполните номер, дату и тему.');
        redirect('index.php?page=admin&section=lessons');
    }
    try {
        if ($id > 0) {
            $pdo->prepare('UPDATE lessons SET ord = :o, date_str = :d, topic = :t, homework = :h WHERE id = :id')
                ->execute([':o' => $ord, ':d' => $date, ':t' => $topic, ':h' => $homework, ':id' => $id]);
            audit_log((int) $currentUser['id'], 'lesson_update', 'lesson', $id);
        } else {
            $pdo->prepare('INSERT INTO lessons (ord, date_str, topic, homework) VALUES (:o, :d, :t, :h)')
                ->execute([':o' => $ord, ':d' => $date, ':t' => $topic, ':h' => $homework]);
            audit_log((int) $currentUser['id'], 'lesson_create', 'lesson', (int) $pdo->lastInsertId());
        }
        flash_set('success', 'Урок сохранён.');
    } catch (Throwable $e) {
        flash_set('danger', 'Ошибка: ' . $e->getMessage());
    }
    redirect('index.php?page=admin&section=lessons');
}

if ($action === 'admin_lesson_delete') {
    if (!in_array('admin', $currentUser['roles'], true)) { http_response_code(403); exit('Доступ запрещён'); }
    $id = (int) ($_POST['id'] ?? 0);
    $pdo->prepare('DELETE FROM lessons WHERE id = :id')->execute([':id' => $id]);
    audit_log((int) $currentUser['id'], 'lesson_delete', 'lesson', $id);
    flash_set('success', 'Урок удалён вместе с оценками.');
    redirect('index.php?page=admin&section=lessons');
}

if ($action === 'admin_project_create') {
    if (!in_array('admin', $currentUser['roles'], true)) { http_response_code(403); exit('Доступ запрещён'); }
    $title     = trim((string) ($_POST['title'] ?? ''));
    $type      = (string) ($_POST['type'] ?? 'original');
    $parentId  = (int) ($_POST['parent_id'] ?? 0);
    $repoUrl   = trim((string) ($_POST['repo_url'] ?? ''));
    $students  = $_POST['students'] ?? [];
    if (!is_array($students)) $students = [];
    if ($title === '' || !isset(project_types_meta()[$type])) {
        flash_set('danger', 'Заполните название и тип.');
        redirect('index.php?page=admin&section=projects');
    }
    try {
        $pdo->beginTransaction();
        $pdo->prepare('INSERT INTO projects (title, type, parent_id, repo_url, created_by) VALUES (:t, :ty, :p, :r, :u)')
            ->execute([':t' => $title, ':ty' => $type, ':p' => $parentId > 0 ? $parentId : null, ':r' => $repoUrl !== '' ? $repoUrl : null, ':u' => (int) $currentUser['id']]);
        $pid = (int) $pdo->lastInsertId();
        foreach ($students as $sid) {
            $sid = (int) $sid;
            if ($sid > 0) {
                $pdo->prepare('INSERT OR IGNORE INTO project_students (project_id, student_id) VALUES (:p, :s)')->execute([':p' => $pid, ':s' => $sid]);
            }
        }
        $phases = project_phases_meta();
        $pdo->prepare('INSERT INTO project_history (project_id, phase_id, moved_by) VALUES (:p, :ph, :u)')
            ->execute([':p' => $pid, ':ph' => $phases[1]['id'], ':u' => (int) $currentUser['id']]);
        $pdo->commit();
        audit_log((int) $currentUser['id'], 'project_create', 'project', $pid);
        flash_set('success', 'Проект создан.');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        flash_set('danger', 'Ошибка: ' . $e->getMessage());
    }
    redirect('index.php?page=admin&section=projects');
}

if ($action === 'help_feedback') {
    $articleId = (int) ($_POST['article_id'] ?? 0);
    $body      = trim((string) ($_POST['body'] ?? ''));
    if ($articleId > 0 && $body !== '') {
        $pdo->prepare('INSERT INTO help_feedback (article_id, user_id, body) VALUES (:a, :u, :b)')
            ->execute([':a' => $articleId, ':u' => (int) $currentUser['id'], ':b' => mb_substr($body, 0, 500)]);
    }
    redirect($_SERVER['HTTP_REFERER'] ?? 'index.php');
}

redirect('index.php');