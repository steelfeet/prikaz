<?php
declare(strict_types=1);

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