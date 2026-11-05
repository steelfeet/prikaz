<?php
declare(strict_types=1);

/**
 * POST-обработчики Приказа.
 * Ожидает $currentUser и $action из index.php.
 *
 * ВАЖНО: файл не должен заканчиваться на ?>.
 */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
csrf_verify();
$pdo = db();

// ===== Выход =====
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

// ===== Оценка: поставить / изменить =====
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
            ->execute([
                ':m'  => $mark,
                ':n'  => $note !== '' ? $note : null,
                ':u'  => (int) $currentUser['id'],
                ':t'  => date('Y-m-d H:i:s'),
                ':id' => (int) $existing,
            ]);
        audit_log((int) $currentUser['id'], 'grade_update', 'grade', (int) $existing);
    } else {
        $pdo->prepare('INSERT INTO grades (lesson_id, student_id, mark, note, updated_by) VALUES (:l, :s, :m, :n, :u)')
            ->execute([
                ':l' => $lessonId,
                ':s' => $studentId,
                ':m' => $mark,
                ':n' => $note !== '' ? $note : null,
                ':u' => (int) $currentUser['id'],
            ]);
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

// ===== УЧЕБНЫЙ ГОД: сохранение =====
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

            $semStmt = $pdo->prepare(
                'INSERT INTO semesters (academic_year_id, name, is_practice, ord)
                 VALUES (:y, :n, 0, :o)'
            );
            foreach (['1 семестр' => 1, '2 семестр' => 2] as $semName => $semOrd) {
                $semStmt->execute([':y' => $newId, ':n' => $semName, ':o' => $semOrd]);
            }
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

// ===== ГРУППА: сохранение =====
if ($action === 'admin_group_save') {
    if (!in_array('admin', $currentUser['roles'], true)) { http_response_code(403); exit('Доступ запрещён'); }

    $id             = (int) ($_POST['id'] ?? 0);
    $academicYearId = (int) ($_POST['academic_year_id'] ?? 0);
    $name           = trim((string) ($_POST['name'] ?? ''));
    $curator        = trim((string) ($_POST['curator'] ?? ''));
    $notes          = trim((string) ($_POST['notes'] ?? ''));

    if ($name === '') {
        flash_set('danger', 'Укажите название группы.');
        redirect('index.php?page=admin&section=groups');
    }
    if ($academicYearId <= 0) {
        flash_set('danger', 'Выберите учебный год.');
        redirect('index.php?page=admin&section=groups');
    }
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM academic_years WHERE id = :id');
    $stmt->execute([':id' => $academicYearId]);
    if ((int) $stmt->fetchColumn() === 0) {
        flash_set('danger', 'Учебный год не найден.');
        redirect('index.php?page=admin&section=groups');
    }

    try {
        if ($id > 0) {
            $pdo->prepare(
                'UPDATE groups
                 SET academic_year_id = :y, name = :n, curator = :c, notes = :no, updated_at = :t
                 WHERE id = :id'
            )->execute([
                ':y'  => $academicYearId,
                ':n'  => $name,
                ':c'  => $curator !== '' ? $curator : null,
                ':no' => $notes !== '' ? $notes : null,
                ':t'  => date('Y-m-d H:i:s'),
                ':id' => $id,
            ]);
            audit_log((int) $currentUser['id'], 'group_update', 'group', $id);
            flash_set('success', 'Группа сохранена.');
        } else {
            $pdo->prepare(
                'INSERT INTO groups (academic_year_id, name, curator, notes)
                 VALUES (:y, :n, :c, :no)'
            )->execute([
                ':y'  => $academicYearId,
                ':n'  => $name,
                ':c'  => $curator !== '' ? $curator : null,
                ':no' => $notes !== '' ? $notes : null,
            ]);
            $newId = (int) $pdo->lastInsertId();
            audit_log((int) $currentUser['id'], 'group_create', 'group', $newId);
            flash_set('success', 'Группа создана.');
        }
    } catch (PDOException $e) {
        if (str_contains($e->getMessage(), 'UNIQUE')) {
            flash_set('danger', 'Группа с таким названием уже есть в этом учебном году.');
        } else {
            flash_set('danger', 'Ошибка: ' . $e->getMessage());
        }
    }
    redirect('index.php?page=admin&section=groups');
}

// ===== ГРУППА: удаление =====
if ($action === 'admin_group_delete') {
    if (!in_array('admin', $currentUser['roles'], true)) { http_response_code(403); exit('Доступ запрещён'); }
    $id = (int) ($_POST['id'] ?? 0);
    try {
        $pdo->prepare('DELETE FROM groups WHERE id = :id')->execute([':id' => $id]);
        audit_log((int) $currentUser['id'], 'group_delete', 'group', $id);
        flash_set('success', 'Группа удалена.');
    } catch (Throwable $e) {
        flash_set('danger', 'Ошибка: ' . $e->getMessage());
    }
    redirect('index.php?page=admin&section=groups');
}

// ===== СЕМЕСТР: сохранение =====
if ($action === 'admin_semester_save') {
    if (!in_array('admin', $currentUser['roles'], true)) { http_response_code(403); exit('Доступ запрещён'); }

    $id             = (int) ($_POST['id'] ?? 0);
    $academicYearId = (int) ($_POST['academic_year_id'] ?? 0);
    $name           = trim((string) ($_POST['name'] ?? ''));
    $isPractice     = isset($_POST['is_practice']) ? 1 : 0;
    $ordRaw         = trim((string) ($_POST['ord'] ?? ''));

    $backUrl = 'index.php?page=admin&section=academic_years';

    if ($name === '') {
        flash_set('danger', 'Укажите название семестра.');
        redirect($backUrl);
    }
    if ($academicYearId <= 0) {
        flash_set('danger', 'Выберите учебный год.');
        redirect($backUrl);
    }
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM academic_years WHERE id = :id');
    $stmt->execute([':id' => $academicYearId]);
    if ((int) $stmt->fetchColumn() === 0) {
        flash_set('danger', 'Учебный год не найден.');
        redirect($backUrl);
    }
    if ($ordRaw === '') {
        $ord = 1;
    } elseif (!preg_match('/^\d{1,4}$/', $ordRaw) || (int) $ordRaw < 1) {
        flash_set('danger', 'Порядковый номер — целое число от 1 до 9999.');
        redirect($backUrl);
    } else {
        $ord = (int) $ordRaw;
    }

    try {
        if ($id > 0) {
            $pdo->prepare(
                'UPDATE semesters
                 SET academic_year_id = :y, name = :n, is_practice = :p, ord = :o
                 WHERE id = :id'
            )->execute([
                ':y'  => $academicYearId,
                ':n'  => $name,
                ':p'  => $isPractice,
                ':o'  => $ord,
                ':id' => $id,
            ]);
            audit_log((int) $currentUser['id'], 'semester_update', 'semester', $id);
            flash_set('success', 'Семестр сохранён.');
        } else {
            $pdo->prepare(
                'INSERT INTO semesters (academic_year_id, name, is_practice, ord)
                 VALUES (:y, :n, :p, :o)'
            )->execute([
                ':y' => $academicYearId,
                ':n' => $name,
                ':p' => $isPractice,
                ':o' => $ord,
            ]);
            $newId = (int) $pdo->lastInsertId();
            audit_log((int) $currentUser['id'], 'semester_create', 'semester', $newId);
            flash_set('success', 'Семестр создан.');
        }
    } catch (PDOException $e) {
        if (str_contains($e->getMessage(), 'UNIQUE')) {
            flash_set('danger', 'Семестр с таким названием уже есть в этом учебном году.');
        } else {
            flash_set('danger', 'Ошибка: ' . $e->getMessage());
        }
    }
    redirect($backUrl);
}

// ===== СЕМЕСТР: удаление =====
if ($action === 'admin_semester_delete') {
    if (!in_array('admin', $currentUser['roles'], true)) { http_response_code(403); exit('Доступ запрещён'); }
    $id = (int) ($_POST['id'] ?? 0);
    try {
        $pdo->prepare('DELETE FROM semesters WHERE id = :id')->execute([':id' => $id]);
        audit_log((int) $currentUser['id'], 'semester_delete', 'semester', $id);
        flash_set('success', 'Семестр удалён.');
    } catch (Throwable $e) {
        flash_set('danger', 'Ошибка: ' . $e->getMessage());
    }
    redirect('index.php?page=admin&section=academic_years');
}

// ===== ПРЕДМЕТ: сохранение =====
if ($action === 'admin_subject_save') {
    if (!in_array('admin', $currentUser['roles'], true)) { http_response_code(403); exit('Доступ запрещён'); }

    $id          = (int) ($_POST['id'] ?? 0);
    $semesterId  = (int) ($_POST['semester_id'] ?? 0);
    $groupId     = (int) ($_POST['group_id'] ?? 0);
    $shortName   = trim((string) ($_POST['short_name'] ?? ''));
    $fullName    = trim((string) ($_POST['full_name'] ?? ''));
    $hoursRaw    = trim((string) ($_POST['hours'] ?? ''));

    $backYearId = 0;
    if ($semesterId > 0) {
        $stmt = $pdo->prepare('SELECT academic_year_id FROM semesters WHERE id = :id');
        $stmt->execute([':id' => $semesterId]);
        $backYearId = (int) $stmt->fetchColumn();
    }
    $backUrl = 'index.php?page=admin&section=subjects'
        . ($backYearId > 0 ? '&subject_year=' . $backYearId : '');

    if ($shortName === '') {
        flash_set('danger', 'Укажите краткое название предмета.');
        redirect($backUrl);
    }
    if ($fullName === '') {
        flash_set('danger', 'Укажите полное название предмета.');
        redirect($backUrl);
    }
    if ($groupId <= 0) {
        flash_set('danger', 'Выберите группу.');
        redirect($backUrl);
    }
    if ($hoursRaw === '') {
        $hours = 0;
    } elseif (!preg_match('/^\d{1,6}$/', $hoursRaw)) {
        flash_set('danger', 'Количество часов — целое число от 0 до 999999.');
        redirect($backUrl);
    } else {
        $hours = (int) $hoursRaw;
    }

    $stmt = $pdo->prepare(
        'SELECT sm.id, sm.name AS semester_name, sm.academic_year_id, ay.name AS year_name
         FROM semesters sm
         JOIN academic_years ay ON ay.id = sm.academic_year_id
         WHERE sm.id = :id'
    );
    $stmt->execute([':id' => $semesterId]);
    $semester = $stmt->fetch();
    if (!$semester) {
        flash_set('danger', 'Семестр не найден. Сначала создайте семестр в разделе «Учебный год».');
        redirect($backUrl);
    }

    $stmt = $pdo->prepare(
        'SELECT g.id, g.academic_year_id, g.name AS group_name, ay.name AS year_name
         FROM groups g
         JOIN academic_years ay ON ay.id = g.academic_year_id
         WHERE g.id = :id'
    );
    $stmt->execute([':id' => $groupId]);
    $group = $stmt->fetch();
    if (!$group) {
        flash_set('danger', 'Группа не найдена.');
        redirect($backUrl);
    }
    if ((int) $group['academic_year_id'] !== (int) $semester['academic_year_id']) {
        flash_set('danger', 'Группа «' . $group['group_name'] . '» относится к учебному году «'
            . $group['year_name'] . '», а семестр «' . $semester['semester_name'] . '» — к году «'
            . $semester['year_name'] . '». Выберите группу года этого семестра.');
        redirect($backUrl);
    }

    try {
        if ($id > 0) {
            $pdo->prepare(
                'UPDATE subjects
                 SET semester_id = :sm, group_id = :g, short_name = :sn, full_name = :fn,
                     hours = :h, updated_at = :t
                 WHERE id = :id'
            )->execute([
                ':sm' => $semesterId,
                ':g'  => $groupId,
                ':sn' => $shortName,
                ':fn' => $fullName,
                ':h'  => $hours,
                ':t'  => date('Y-m-d H:i:s'),
                ':id' => $id,
            ]);
            audit_log((int) $currentUser['id'], 'subject_update', 'subject', $id);
            flash_set('success', 'Предмет сохранён.');
        } else {
            $pdo->prepare(
                'INSERT INTO subjects (semester_id, group_id, short_name, full_name, hours)
                 VALUES (:sm, :g, :sn, :fn, :h)'
            )->execute([
                ':sm' => $semesterId,
                ':g'  => $groupId,
                ':sn' => $shortName,
                ':fn' => $fullName,
                ':h'  => $hours,
            ]);
            $newId = (int) $pdo->lastInsertId();
            audit_log((int) $currentUser['id'], 'subject_create', 'subject', $newId);
            flash_set('success', 'Предмет создан.');
        }
    } catch (PDOException $e) {
        if (str_contains($e->getMessage(), 'UNIQUE')) {
            flash_set('danger', 'У этой группы в этом семестре уже есть предмет с таким кратким названием.');
        } else {
            flash_set('danger', 'Ошибка: ' . $e->getMessage());
        }
    }
    redirect($backUrl);
}

// ===== ПРЕДМЕТ: удаление =====
if ($action === 'admin_subject_delete') {
    if (!in_array('admin', $currentUser['roles'], true)) { http_response_code(403); exit('Доступ запрещён'); }
    $id = (int) ($_POST['id'] ?? 0);
    $backUrl = 'index.php?page=admin&section=subjects';
    try {
        $stmt = $pdo->prepare(
            'SELECT sm.academic_year_id FROM subjects s
             JOIN semesters sm ON sm.id = s.semester_id
             WHERE s.id = :id'
        );
        $stmt->execute([':id' => $id]);
        $yearId = (int) $stmt->fetchColumn();
        $pdo->prepare('DELETE FROM subjects WHERE id = :id')->execute([':id' => $id]);
        audit_log((int) $currentUser['id'], 'subject_delete', 'subject', $id);
        flash_set('success', 'Предмет удалён.');
        if ($yearId > 0) $backUrl .= '&subject_year=' . $yearId;
    } catch (Throwable $e) {
        flash_set('danger', 'Ошибка: ' . $e->getMessage());
    }
    redirect($backUrl);
}

// ===== УЧЕБНЫЙ ПЛАН: сохранение темы =====
if ($action === 'admin_curriculum_save') {
    if (!in_array('admin', $currentUser['roles'], true)) { http_response_code(403); exit('Доступ запрещён'); }

    $id      = (int) ($_POST['id'] ?? 0);
    $subject = (int) ($_POST['subject_id'] ?? 0);
    $ord     = (int) ($_POST['ord'] ?? 0);
    $topic   = trim((string) ($_POST['topic'] ?? ''));

    if ($subject <= 0) {
        flash_set('danger', 'Предмет не указан.');
        redirect('index.php?page=admin&section=curriculum');
    }
    if ($topic === '') {
        flash_set('danger', 'Тема не может быть пустой.');
        redirect('index.php?page=admin&section=curriculum');
    }
    if ($ord <= 0) {
        flash_set('danger', 'Порядковый номер должен быть больше 0.');
        redirect('index.php?page=admin&section=curriculum');
    }

    try {
        if ($id > 0) {
            $pdo->prepare(
                'UPDATE curriculum SET ord = :o, topic = :t, updated_at = :u WHERE id = :id'
            )->execute([':o' => $ord, ':t' => $topic, ':u' => date('Y-m-d H:i:s'), ':id' => $id]);
            audit_log((int) $currentUser['id'], 'curriculum_update', 'curriculum', $id);
            flash_set('success', 'Тема сохранена.');
        } else {
            $pdo->prepare(
                'INSERT INTO curriculum (subject_id, ord, topic) VALUES (:s, :o, :t)'
            )->execute([':s' => $subject, ':o' => $ord, ':t' => $topic]);
            $newId = (int) $pdo->lastInsertId();
            audit_log((int) $currentUser['id'], 'curriculum_create', 'curriculum', $newId);
            flash_set('success', 'Тема добавлена.');
        }
    } catch (PDOException $e) {
        if (str_contains($e->getMessage(), 'UNIQUE')) {
            flash_set('danger', 'Тема с таким порядковым номером уже есть у этого предмета.');
        } else {
            flash_set('danger', 'Ошибка: ' . $e->getMessage());
        }
    }
    redirect('index.php?page=admin&section=curriculum');
}

// ===== УЧЕБНЫЙ ПЛАН: удаление темы =====
if ($action === 'admin_curriculum_delete') {
    if (!in_array('admin', $currentUser['roles'], true)) { http_response_code(403); exit('Доступ запрещён'); }
    $id = (int) ($_POST['id'] ?? 0);
    try {
        $pdo->prepare('DELETE FROM curriculum WHERE id = :id')->execute([':id' => $id]);
        audit_log((int) $currentUser['id'], 'curriculum_delete', 'curriculum', $id);
        flash_set('success', 'Тема удалена.');
    } catch (Throwable $e) {
        flash_set('danger', 'Ошибка: ' . $e->getMessage());
    }
    redirect('index.php?page=admin&section=curriculum');
}

// ===== УЧЕБНЫЙ ПЛАН: массовое добавление тем =====
if ($action === 'admin_curriculum_bulk_add') {
    if (!in_array('admin', $currentUser['roles'], true)) { http_response_code(403); exit('Доступ запрещён'); }

    $subjectId = (int) ($_POST['subject_id'] ?? 0);
    $rawTopics = (string) ($_POST['topics'] ?? '');
    $backUrl   = 'index.php?page=admin&section=curriculum';

    if ($subjectId <= 0) {
        flash_set('danger', 'Предмет не указан.');
        redirect($backUrl);
    }
    $stmt = $pdo->prepare('SELECT id, short_name FROM subjects WHERE id = :id');
    $stmt->execute([':id' => $subjectId]);
    $subject = $stmt->fetch();
    if (!$subject) {
        flash_set('danger', 'Предмет не найден.');
        redirect($backUrl);
    }

    $parsed = parse_topic_lines($rawTopics);

    if ($parsed['invalid'] > 0) {
        flash_set('danger', 'Не удалось разобрать вставленный текст: возможны символы другой кодировки. '
            . 'Скопируйте текст как обычный текст (Ctrl+Shift+V) и попробуйте снова.');
        redirect($backUrl);
    }

    $stmt = $pdo->prepare('SELECT topic FROM curriculum WHERE subject_id = :s');
    $stmt->execute([':s' => $subjectId]);
    $existingKeys = [];
    foreach ($stmt->fetchAll() as $row) {
        $existingKeys[topic_key((string) $row['topic'])] = true;
    }

    $seenKeys   = [];
    $toAdd      = [];
    $dupBatch   = 0;
    $dupExist   = 0;
    $emptyLines = $parsed['empty'];

    foreach ($parsed['topics'] as $topic) {
        $key = topic_key($topic);
        if ($key === '') { $emptyLines++; continue; }
        if (isset($seenKeys[$key])) { $dupBatch++; continue; }
        $seenKeys[$key] = true;
        if (isset($existingKeys[$key])) { $dupExist++; continue; }
        $toAdd[] = $topic;
    }

    if ($toAdd === []) {
        $skipped = $dupBatch + $dupExist;
        if ($skipped > 0) {
            flash_set('danger', 'Все непустые строки — дубликаты (повтор в списке: ' . $dupBatch
                . ', уже есть в плане: ' . $dupExist . '). Новых тем не добавлено.');
        } elseif ($emptyLines > 0) {
            flash_set('danger', 'Все строки пустые или состоят только из пробелов и невидимых символов. Новых тем не добавлено.');
        } else {
            flash_set('danger', 'Список пуст: нечего добавлять.');
        }
        redirect($backUrl);
    }

    $stmt = $pdo->prepare('SELECT COALESCE(MAX(ord), 0) FROM curriculum WHERE subject_id = :s');
    $stmt->execute([':s' => $subjectId]);
    $ord = (int) $stmt->fetchColumn();

    try {
        $pdo->beginTransaction();
        $ins = $pdo->prepare('INSERT INTO curriculum (subject_id, ord, topic) VALUES (:s, :o, :t)');
        foreach ($toAdd as $topic) {
            $ord++;
            $ins->execute([':s' => $subjectId, ':o' => $ord, ':t' => $topic]);
        }
        $pdo->commit();
        $skipped = $dupBatch + $dupExist;
        audit_log((int) $currentUser['id'], 'curriculum_bulk_create', 'subject', $subjectId,
            'added=' . count($toAdd) . ' skipped=' . $skipped . ' empty=' . $emptyLines);

        $message = 'Добавлено тем: ' . count($toAdd) . ' (с № ' . ($ord - count($toAdd) + 1) . ' по № ' . $ord . ').';
        if ($skipped > 0) {
            $message .= ' Пропущено дубликатов: ' . $skipped . '.';
        }
        if ($emptyLines > 0) {
            $message .= ' Пустых строк пропущено: ' . $emptyLines . '.';
        }
        flash_set('success', $message);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        flash_set('danger', 'Ошибка: ' . $e->getMessage());
    }
    redirect($backUrl);
}

// ===== РАСПИСАНИЕ: сохранение (перезапись семестра) =====
if ($action === 'admin_schedule_save') {
    if (!in_array('admin', $currentUser['roles'], true)) { http_response_code(403); exit('Доступ запрещён'); }

    $semesterId = (int) ($_POST['semester_id'] ?? 0);
    $yearId     = (int) ($_POST['schedule_year'] ?? 0);

    $backUrl = 'index.php?page=admin&section=schedule';
    if ($yearId > 0)     $backUrl .= '&schedule_year=' . $yearId;
    if ($semesterId > 0) $backUrl .= '&schedule_semester=' . $semesterId;

    if ($semesterId <= 0) {
        flash_set('danger', 'Не выбран семестр.');
        redirect('index.php?page=admin&section=schedule');
    }

    $stmt = $pdo->prepare(
        'SELECT s.id, s.short_name, s.group_id, g.name AS group_name
         FROM subjects s JOIN groups g ON g.id = s.group_id
         WHERE s.semester_id = :sm'
    );
    $stmt->execute([':sm' => $semesterId]);
    $subjectsInSemester = [];
    foreach ($stmt->fetchAll() as $row) {
        $subjectsInSemester[(int) $row['id']] = [
            'group_id'   => (int) $row['group_id'],
            'short_name' => (string) $row['short_name'],
            'group_name' => (string) $row['group_name'],
        ];
    }
    if ($subjectsInSemester === []) {
        flash_set('danger', 'В этом семестре нет предметов — расписание сохранять некуда.');
        redirect($backUrl);
    }

    $rawCells = $_POST['cell'] ?? [];
    if (!is_array($rawCells)) $rawCells = [];

    $validTypes = schedule_lesson_types();
    $maxPerCell = schedule_max_per_cell();

    $toInsert     = [];
    $duplicates   = 0;
    $invalid      = 0;
    $slotPosition = [];

    for ($weekday = 1; $weekday <= 6; $weekday++) {
        if (!isset($rawCells[$weekday]) || !is_array($rawCells[$weekday])) continue;
        for ($lesson = 1; $lesson <= 9; $lesson++) {
            if (!isset($rawCells[$weekday][$lesson]) || !is_array($rawCells[$weekday][$lesson])) continue;

            foreach ($rawCells[$weekday][$lesson] as $cellData) {
                if (!is_array($cellData)) continue;
                $subjectId  = (int) ($cellData['subject_id'] ?? 0);
                $lessonType = trim((string) ($cellData['lesson_type'] ?? ''));

                if ($subjectId <= 0 && $lessonType === '') continue;
                if ($subjectId <= 0) { $invalid++; continue; }
                if (!isset($subjectsInSemester[$subjectId])) { $invalid++; continue; }
                if (!isset($validTypes[$lessonType])) { $invalid++; continue; }

                $groupId = $subjectsInSemester[$subjectId]['group_id'];
                $slotKey = $groupId . ':' . $weekday . ':' . $lesson;

                $pos = ($slotPosition[$slotKey] ?? 0) + 1;
                if ($pos > $maxPerCell) { $duplicates++; continue; }
                $slotPosition[$slotKey] = $pos;

                $toInsert[] = [
                    'group_id'    => $groupId,
                    'weekday'     => $weekday,
                    'lesson_num'  => $lesson,
                    'position'    => $pos,
                    'subject_id'  => $subjectId,
                    'lesson_type' => $lessonType,
                ];
            }
        }
    }

    try {
        $pdo->beginTransaction();

        $pdo->prepare(
            'DELETE FROM schedule_entries
             WHERE subject_id IN (SELECT id FROM subjects WHERE semester_id = :sm)'
        )->execute([':sm' => $semesterId]);

        if ($toInsert !== []) {
            $ins = $pdo->prepare(
                'INSERT INTO schedule_entries (group_id, weekday, lesson_num, position, subject_id, lesson_type)
                 VALUES (:g, :wd, :ln, :pos, :si, :lt)'
            );
            foreach ($toInsert as $row) {
                $ins->execute([
                    ':g'   => $row['group_id'],
                    ':wd'  => $row['weekday'],
                    ':ln'  => $row['lesson_num'],
                    ':pos' => $row['position'],
                    ':si'  => $row['subject_id'],
                    ':lt'  => $row['lesson_type'],
                ]);
            }
        }

        $pdo->commit();
        audit_log((int) $currentUser['id'], 'schedule_save', 'semester', $semesterId,
            'entries=' . count($toInsert) . ' dupes=' . $duplicates . ' invalid=' . $invalid);

        $msg = 'Расписание семестра сохранено (' . count($toInsert) . ' записей).';
        if ($duplicates > 0) $msg .= ' Пропущено дубликатов: ' . $duplicates . '.';
        if ($invalid    > 0) $msg .= ' Пропущено записей с неверным предметом: ' . $invalid . '.';
        flash_set('success', $msg);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        flash_set('danger', 'Ошибка: ' . $e->getMessage());
    }
    redirect($backUrl);
}

// ===== РАСПИСАНИЕ: очистка семестра =====
if ($action === 'admin_schedule_clear') {
    if (!in_array('admin', $currentUser['roles'], true)) { http_response_code(403); exit('Доступ запрещён'); }

    $semesterId = (int) ($_POST['semester_id'] ?? 0);
    $yearId     = (int) ($_POST['schedule_year'] ?? 0);

    $backUrl = 'index.php?page=admin&section=schedule';
    if ($yearId > 0)     $backUrl .= '&schedule_year=' . $yearId;
    if ($semesterId > 0) $backUrl .= '&schedule_semester=' . $semesterId;

    if ($semesterId <= 0) {
        flash_set('danger', 'Семестр не выбран.');
        redirect('index.php?page=admin&section=schedule');
    }

    try {
        $stmt = $pdo->prepare('SELECT name FROM semesters WHERE id = :id');
        $stmt->execute([':id' => $semesterId]);
        $name = (string) $stmt->fetchColumn();

        $pdo->prepare(
            'DELETE FROM schedule_entries
             WHERE subject_id IN (SELECT id FROM subjects WHERE semester_id = :sm)'
        )->execute([':sm' => $semesterId]);
        audit_log((int) $currentUser['id'], 'schedule_clear', 'semester', $semesterId);
        flash_set('success', 'Расписание семестра «' . $name . '» очищено.');
    } catch (Throwable $e) {
        flash_set('danger', 'Ошибка: ' . $e->getMessage());
    }
    redirect($backUrl);
}

// ===== ПРАЗДНИЧНЫЙ ДЕНЬ: добавление (в разделе «Учебный год») =====
if ($action === 'admin_holiday_add') {
    if (!in_array('admin', $currentUser['roles'], true)) { http_response_code(403); exit('Доступ запрещён'); }

    $semesterId = (int) ($_POST['semester_id'] ?? 0);
    $date       = trim((string) ($_POST['date'] ?? ''));
    $name       = trim((string) ($_POST['name'] ?? ''));

    $backUrl = 'index.php?page=admin&section=academic_years'
        . ($semesterId > 0 ? '#sem-' . $semesterId : '');

    if ($semesterId <= 0) {
        flash_set('danger', 'Семестр не выбран.');
        redirect('index.php?page=admin&section=academic_years');
    }
    if ($date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        flash_set('danger', 'Укажите дату в формате ГГГГ-ММ-ДД.');
        redirect($backUrl);
    }

    $stmt = $pdo->prepare(
        'SELECT sm.id, sm.name AS semester_name,
                ay.start_date, ay.end_date, ay.name AS year_name
         FROM semesters sm
         JOIN academic_years ay ON ay.id = sm.academic_year_id
         WHERE sm.id = :id'
    );
    $stmt->execute([':id' => $semesterId]);
    $semester = $stmt->fetch();
    if (!$semester) {
        flash_set('danger', 'Семестр не найден.');
        redirect('index.php?page=admin&section=academic_years');
    }
    if ($date < $semester['start_date'] || $date > $semester['end_date']) {
        flash_set('danger', 'Дата ' . $date . ' вне учебного года «' . $semester['year_name']
            . '» (' . $semester['start_date'] . ' — ' . $semester['end_date'] . ').');
        redirect($backUrl);
    }

    try {
        $stmt = $pdo->prepare(
            'INSERT OR IGNORE INTO holidays (semester_id, date, name)
             VALUES (:sm, :d, :n)'
        );
        $stmt->execute([
            ':sm' => $semesterId,
            ':d'  => $date,
            ':n'  => $name !== '' ? $name : null,
        ]);
        if ($stmt->rowCount() === 0) {
            flash_set('danger', 'Дата ' . $date . ' уже отмечена как праздничная в этом семестре.');
        } else {
            $newId = (int) $pdo->lastInsertId();
            audit_log((int) $currentUser['id'], 'holiday_add', 'holiday', $newId,
                'semester=' . $semesterId . ' date=' . $date);
            flash_set('success', 'Праздничный день ' . $date . ' добавлен в семестр «'
                . $semester['semester_name'] . '».');
        }
    } catch (Throwable $e) {
        flash_set('danger', 'Ошибка: ' . $e->getMessage());
    }
    redirect($backUrl);
}

// ===== ПРАЗДНИЧНЫЙ ДЕНЬ: удаление =====
if ($action === 'admin_holiday_delete') {
    if (!in_array('admin', $currentUser['roles'], true)) { http_response_code(403); exit('Доступ запрещён'); }

    $id         = (int) ($_POST['id'] ?? 0);
    $semesterId = (int) ($_POST['semester_id'] ?? 0);

    $backUrl = 'index.php?page=admin&section=academic_years'
        . ($semesterId > 0 ? '#sem-' . $semesterId : '');

    if ($id > 0) {
        try {
            $pdo->prepare('DELETE FROM holidays WHERE id = :id')->execute([':id' => $id]);
            audit_log((int) $currentUser['id'], 'holiday_delete', 'holiday', $id);
            flash_set('success', 'Праздничный день удалён.');
        } catch (Throwable $e) {
            flash_set('danger', 'Ошибка: ' . $e->getMessage());
        }
    }
    redirect($backUrl);
}

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

// ===== УРОК: сохранение =====
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

// ===== УРОК: удаление =====
if ($action === 'admin_lesson_delete') {
    if (!in_array('admin', $currentUser['roles'], true)) { http_response_code(403); exit('Доступ запрещён'); }
    $id = (int) ($_POST['id'] ?? 0);
    $pdo->prepare('DELETE FROM lessons WHERE id = :id')->execute([':id' => $id]);
    audit_log((int) $currentUser['id'], 'lesson_delete', 'lesson', $id);
    flash_set('success', 'Урок удалён вместе с оценками.');
    redirect('index.php?page=admin&section=lessons');
}

// ===== ПРОЕКТ: создание =====
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
            ->execute([
                ':t'  => $title,
                ':ty' => $type,
                ':p'  => $parentId > 0 ? $parentId : null,
                ':r'  => $repoUrl !== '' ? $repoUrl : null,
                ':u'  => (int) $currentUser['id'],
            ]);
        $pid = (int) $pdo->lastInsertId();
        foreach ($students as $sid) {
            $sid = (int) $sid;
            if ($sid > 0) {
                $pdo->prepare('INSERT OR IGNORE INTO project_students (project_id, student_id) VALUES (:p, :s)')
                    ->execute([':p' => $pid, ':s' => $sid]);
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

// ===== HELP: feedback =====
if ($action === 'help_feedback') {
    $articleId = (int) ($_POST['article_id'] ?? 0);
    $body      = trim((string) ($_POST['body'] ?? ''));
    if ($articleId > 0 && $body !== '') {
        $pdo->prepare('INSERT INTO help_feedback (article_id, user_id, body) VALUES (:a, :u, :b)')
            ->execute([
                ':a' => $articleId,
                ':u' => (int) $currentUser['id'],
                ':b' => mb_substr($body, 0, 500),
            ]);
    }
    redirect($_SERVER['HTTP_REFERER'] ?? 'index.php');
}

redirect('index.php');