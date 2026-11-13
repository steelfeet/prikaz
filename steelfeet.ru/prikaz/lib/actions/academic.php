<?php
declare(strict_types=1);

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

// ===== ПРАЗДНИЧНЫЙ ДЕНЬ: добавление =====
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