<?php
declare(strict_types=1);

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