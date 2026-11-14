<?php
declare(strict_types=1);

// ===== ОЦЕНКА: поставить / изменить (старая модель lessons/grades) =====
if ($action === 'grade_set') {
    if (!in_array('teacher', $currentUser['roles'], true) && !in_array('admin', $currentUser['roles'], true)) {
        http_response_code(403); exit('Доступ запрещён');
    }
    $studentId = (int) ($_POST['student_id'] ?? 0);
    $lessonId  = (int) ($_POST['lesson_id'] ?? 0);
    $mark      = (string) ($_POST['mark'] ?? '');
    $note      = trim((string) ($_POST['note'] ?? ''));
    $weight    = (int) ($_POST['weight'] ?? 0);
    if (!valid_mark($mark) || $studentId <= 0 || $lessonId <= 0) {
        flash_set('danger', 'Неверные данные.');
        redirect('index.php?page=teacher&tab=journal');
    }
    if ($weight <= 0 || $weight > 100) {
        $weight = (int) (prikaz_config()['default_grade_weight'] ?? 10);
    }
    $stmt = $pdo->prepare('SELECT id FROM grades WHERE student_id = :s AND lesson_id = :l');
    $stmt->execute([':s' => $studentId, ':l' => $lessonId]);
    $existing = $stmt->fetchColumn();
    if ($existing !== false) {
        $pdo->prepare('UPDATE grades SET mark = :m, note = :n, weight = :w, updated_by = :u, updated_at = :t WHERE id = :id')
            ->execute([
                ':m'  => $mark,
                ':n'  => $note !== '' ? $note : null,
                ':w'  => $weight,
                ':u'  => (int) $currentUser['id'],
                ':t'  => date('Y-m-d H:i:s'),
                ':id' => (int) $existing,
            ]);
        audit_log((int) $currentUser['id'], 'grade_update', 'grade', (int) $existing);
    } else {
        $pdo->prepare('INSERT INTO grades (lesson_id, student_id, mark, note, weight, updated_by) VALUES (:l, :s, :m, :n, :w, :u)')
            ->execute([
                ':l' => $lessonId,
                ':s' => $studentId,
                ':m' => $mark,
                ':n' => $note !== '' ? $note : null,
                ':w' => $weight,
                ':u' => (int) $currentUser['id'],
            ]);
        audit_log((int) $currentUser['id'], 'grade_create', 'grade', (int) $pdo->lastInsertId());
    }
    flash_set('success', 'Оценка сохранена.');
    redirect('index.php?page=teacher&tab=journal');
}

// ===== ЖУРНАЛ: сохранить оценку (новая модель, AJAX) =====
if ($action === 'journal_mark_set') {
    if (!in_array('teacher', $currentUser['roles'], true) && !in_array('admin', $currentUser['roles'], true)) {
        if (wants_json()) json_response(['ok' => false, 'error' => 'Доступ запрещён.']);
        http_response_code(403); exit('Доступ запрещён');
    }

    $subjectId = (int) ($_POST['subject_id'] ?? 0);
    $studentId = (int) ($_POST['student_id'] ?? 0);
    $date      = trim((string) ($_POST['date'] ?? ''));
    $kind      = (string) ($_POST['kind'] ?? 'group');
    $mark      = trim((string) ($_POST['mark'] ?? ''));
    $note      = trim((string) ($_POST['note'] ?? ''));
    $weight    = (int) ($_POST['weight'] ?? 0);
    $page      = (int) ($_POST['page'] ?? -1);

    if (!in_array($kind, journal_kinds(), true)) {
        if (wants_json()) json_response(['ok' => false, 'error' => 'Неверный тип занятия.']);
        flash_set('danger', 'Неверный тип занятия.');
        redirect('index.php?page=journal');
    }
    if ($subjectId <= 0 || $studentId <= 0 || $date === '') {
        if (wants_json()) json_response(['ok' => false, 'error' => 'Не хватает данных.']);
        flash_set('danger', 'Не хватает данных.');
        redirect('index.php?page=journal');
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        if (wants_json()) json_response(['ok' => false, 'error' => 'Неверная дата.']);
        flash_set('danger', 'Неверная дата.');
        redirect('index.php?page=journal');
    }
    if ($mark !== '' && !journal_valid_mark($mark)) {
        if (wants_json()) json_response(['ok' => false, 'error' => 'Неверная оценка.']);
        flash_set('danger', 'Неверная оценка.');
        redirect('index.php?page=journal');
    }
    if ($weight <= 0 || $weight > 100) {
        $weight = (int) (prikaz_config()['default_grade_weight'] ?? 10);
    }

    try {
        journal_mark_apply($subjectId, $studentId, $date, $kind, $mark, $note, $weight, (int) $currentUser['id'], $pdo);
        audit_log((int) $currentUser['id'], 'journal_mark_set', 'journal_mark', $studentId,
            'subject=' . $subjectId . ' date=' . $date . ' kind=' . $kind . ' mark=' . $mark . ' weight=' . $weight);

        if (wants_json()) {
            json_response([
                'ok'         => true,
                'html'       => journal_render_grid($subjectId, $kind, $page, $currentUser),
                'subject_id' => $subjectId,
            ]);
        }
        flash_set('success', 'Оценка сохранена.');
    } catch (Throwable $e) {
        if (wants_json()) json_response(['ok' => false, 'error' => $e->getMessage()]);
        flash_set('danger', 'Ошибка: ' . $e->getMessage());
    }
    redirect('index.php?page=journal&subject=' . $subjectId . '&kind=' . $kind . '&p=' . $page);
}

// ===== ЖУРНАЛ: сохранить примечание к уроку и ДЗ (AJAX) =====
if ($action === 'journal_lesson_note_set') {
    if (!in_array('teacher', $currentUser['roles'], true) && !in_array('admin', $currentUser['roles'], true)) {
        if (wants_json()) json_response(['ok' => false, 'error' => 'Доступ запрещён.']);
        http_response_code(403); exit('Доступ запрещён');
    }

    $subjectId = (int) ($_POST['subject_id'] ?? 0);
    $date      = trim((string) ($_POST['date'] ?? ''));
    $kind      = (string) ($_POST['kind'] ?? 'group');
    $note      = trim((string) ($_POST['note'] ?? ''));
    $homework  = trim((string) ($_POST['homework'] ?? ''));
    $page      = (int) ($_POST['page'] ?? -1);

    if (!in_array($kind, journal_kinds(), true)) {
        if (wants_json()) json_response(['ok' => false, 'error' => 'Неверный тип занятия.']);
        flash_set('danger', 'Неверный тип занятия.');
        redirect('index.php?page=journal');
    }
    if ($subjectId <= 0 || $date === '') {
        if (wants_json()) json_response(['ok' => false, 'error' => 'Не хватает данных.']);
        flash_set('danger', 'Не хватает данных.');
        redirect('index.php?page=journal');
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        if (wants_json()) json_response(['ok' => false, 'error' => 'Неверная дата.']);
        flash_set('danger', 'Неверная дата.');
        redirect('index.php?page=journal');
    }

    try {
        journal_lesson_note_apply($subjectId, $date, $kind, $note, $homework, (int) $currentUser['id'], $pdo);
        audit_log((int) $currentUser['id'], 'journal_lesson_note_set', 'journal_lesson_note', $subjectId,
            'date=' . $date . ' kind=' . $kind);

        if (wants_json()) {
            json_response([
                'ok'         => true,
                'html'       => journal_render_grid($subjectId, $kind, $page, $currentUser),
                'subject_id' => $subjectId,
            ]);
        }
        flash_set('success', 'Примечание к уроку сохранено.');
    } catch (Throwable $e) {
        if (wants_json()) json_response(['ok' => false, 'error' => $e->getMessage()]);
        flash_set('danger', 'Ошибка: ' . $e->getMessage());
    }
    redirect('index.php?page=journal&subject=' . $subjectId . '&kind=' . $kind . '&p=' . $page);
}