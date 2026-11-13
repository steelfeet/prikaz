<?php
declare(strict_types=1);

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