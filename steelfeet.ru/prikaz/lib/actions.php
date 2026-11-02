<?php
declare(strict_types=1);

/**
 * POST-обработчики: workflow (move/confirm/reject), документы, help feedback,
 * скачивание файлов. Подключается из index.php до data.php.
 */

// ===== Переход фазы workflow =====
if ($action === 'move_phase' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $projectId     = (int) ($input['project_id'] ?? 0);
    $targetPhaseId = (int) ($input['target_phase_id'] ?? 0);

    $stmt = $pdo->prepare('SELECT * FROM interactions WHERE id = :id');
    $stmt->execute([':id' => $projectId]);
    $interaction = $stmt->fetch();
    if (!$interaction) { echo json_encode(['ok' => false, 'error' => 'Проект не найден']); exit; }

    $stmt = $pdo->prepare('SELECT * FROM interaction_phases WHERE id = :id');
    $stmt->execute([':id' => $targetPhaseId]);
    $targetPhase = $stmt->fetch();
    if (!$targetPhase) { echo json_encode(['ok' => false, 'error' => 'Фаза не найдена']); exit; }

    $currentPhaseId = $interaction['phase_id'] !== null ? (int) $interaction['phase_id'] : 0;
    $currentPhase = null;
    if ($currentPhaseId > 0) {
        $stmt = $pdo->prepare('SELECT * FROM interaction_phases WHERE id = :id');
        $stmt->execute([':id' => $currentPhaseId]);
        $currentPhase = $stmt->fetch();
    }
    $curNum = $currentPhase ? (int) $currentPhase['num'] : 0;
    $tgtNum = (int) $targetPhase['num'];

    if (abs($tgtNum - $curNum) !== 1) {
        echo json_encode(['ok' => false, 'error' => 'Перемещать можно только на соседнюю фазу']);
        exit;
    }

    if (phase_requires_confirmation($tgtNum)) {
        $_SESSION['pending_phase_change'] = [
            'project_id'        => $projectId,
            'from_phase_id'     => $currentPhaseId,
            'target_phase_id'   => $targetPhaseId,
            'target_phase_num'  => $tgtNum,
            'target_phase_name' => $targetPhase['name'],
            'created_at'        => time(),
        ];
        $_SESSION['university_notification'] = [
            'type'       => 'pending',
            'message'    => 'ИТ Школа РТК предлагает перевести проект в фазу «'
                          . $targetPhase['name'] . '». Требуется ваше подтверждение.',
            'created_at' => time(),
        ];
        echo json_encode(['ok' => true, 'pending' => true]);
    } else {
        $stmt = $pdo->prepare('UPDATE interactions SET phase_id = :pid WHERE id = :id');
        $stmt->execute([':pid' => $targetPhaseId, ':id' => $projectId]);
        unset($_SESSION['pending_phase_change']);
        $_SESSION['university_notification'] = [
            'type'       => 'phase_changed',
            'message'    => 'Проект переведён в фазу «' . $targetPhase['name'] . '».',
            'created_at' => time(),
        ];
        echo json_encode(['ok' => true, 'pending' => false]);
    }
    exit;
}

if (($action === 'confirm_phase' || $action === 'reject_phase') && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_SESSION['pending_phase_change'])) {
        $pending = $_SESSION['pending_phase_change'];
        if ($action === 'confirm_phase') {
            $stmt = $pdo->prepare('UPDATE interactions SET phase_id = :pid WHERE id = :id');
            $stmt->execute([':pid' => $pending['target_phase_id'], ':id' => $pending['project_id']]);
            $_SESSION['manager_notification'] = [
                'type'       => 'confirmed',
                'message'    => 'Вуз подтвердил перевод в фазу «' . $pending['target_phase_name'] . '».',
                'created_at' => time(),
            ];
        } else {
            $_SESSION['manager_notification'] = [
                'type'       => 'rejected',
                'message'    => 'Вуз отклонил перевод в фазу «' . $pending['target_phase_name'] . '».',
                'created_at' => time(),
            ];
        }
        unset($_SESSION['pending_phase_change'], $_SESSION['university_notification']);
    }
    header('Location: index.php?role=university');
    exit;
}

// ===== Документы: смена статуса =====
if ($action === 'doc_transition' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $docId = (int) ($_POST['document_id'] ?? 0);
    $docAction = (string) ($_POST['doc_action'] ?? '');
    $doc = doc_fetch_card($pdo, $docId);
    if (!$doc) { header('Location: index.php?page=documents'); exit; }

    $side = doc_side_for_role($viewRole);
    if ($side === null) { header('Location: index.php'); exit; }

    $signatureCount = count(doc_signatures($pdo, $docId));
    $available = doc_available_actions($doc['status'], $side, $signatureCount, $doc['type']);
    $allowed = false;
    foreach ($available as $a) { if ($a['action'] === $docAction) { $allowed = true; break; } }
    if (!$allowed) {
        $_SESSION['doc_flash'] = ['type' => 'danger', 'message' => 'Это действие недоступно в текущем статусе.'];
        header('Location: index.php?page=document&id=' . $docId . '&role=' . $viewRole);
        exit;
    }

    try {
        [$newStatus, $newOwner] = doc_next_state($doc['status'], $side, $docAction, $doc['type'], $signatureCount);
        $stmt = $pdo->prepare('UPDATE documents SET status = :s, owner_side = :o WHERE id = :id');
        $stmt->execute([':s' => $newStatus, ':o' => $newOwner, ':id' => $docId]);

        $stmt = $pdo->prepare(
            'INSERT INTO document_events (document_id, actor_user_id, actor_side, action, from_status, to_status, comment)
             VALUES (:d, :u, :s, :a, :from, :to, :c)'
        );
        $stmt->execute([
            ':d'    => $docId,
            ':u'    => (int) $currentUser['id'],
            ':s'    => $side,
            ':a'    => $docAction,
            ':from' => $doc['status'],
            ':to'   => $newStatus,
            ':c'    => null,
        ]);

        $_SESSION['doc_flash'] = ['type' => 'success', 'message' => 'Документ обновлён.'];
    } catch (Throwable $e) {
        $_SESSION['doc_flash'] = ['type' => 'danger', 'message' => 'Ошибка: ' . $e->getMessage()];
    }

    header('Location: index.php?page=document&id=' . $docId . '&role=' . $viewRole);
    exit;
}

// ===== Документы: подпись =====
if ($action === 'doc_sign' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $docId = (int) ($_POST['document_id'] ?? 0);
    $doc = doc_fetch_card($pdo, $docId);
    if (!$doc) { header('Location: index.php?page=documents'); exit; }

    $side = doc_side_for_role($viewRole);
    if ($side === null) { header('Location: index.php'); exit; }

    $signatureCount = count(doc_signatures($pdo, $docId));
    $available = doc_available_actions($doc['status'], $side, $signatureCount, $doc['type']);
    $isSign = false;
    foreach ($available as $a) { if (!empty($a['is_sign'])) { $isSign = true; break; } }
    if (!$isSign) {
        $_SESSION['doc_flash'] = ['type' => 'danger', 'message' => 'Подпись сейчас недоступна.'];
        header('Location: index.php?page=document&id=' . $docId . '&role=' . $viewRole);
        exit;
    }

    // Уже подписал?
    foreach (doc_signatures($pdo, $docId) as $s) {
        if ((int) $s['user_id'] === (int) $currentUser['id']) {
            $_SESSION['doc_flash'] = ['type' => 'danger', 'message' => 'Вы уже подписали этот документ.'];
            header('Location: index.php?page=document&id=' . $docId . '&role=' . $viewRole);
            exit;
        }
    }

    // Берём последнюю версию
    $stmt = $pdo->prepare('SELECT * FROM document_versions WHERE document_id = :id ORDER BY version DESC LIMIT 1');
    $stmt->execute([':id' => $docId]);
    $lastVer = $stmt->fetch();
    if (!$lastVer) {
        $_SESSION['doc_flash'] = ['type' => 'danger', 'message' => 'Нельзя подписать: нет ни одной версии.'];
        header('Location: index.php?page=document&id=' . $docId . '&role=' . $viewRole);
        exit;
    }

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO document_signatures (document_id, version_id, user_id, side, file_hash)
             VALUES (:d, :v, :u, :s, :h)'
        );
        $stmt->execute([
            ':d' => $docId,
            ':v' => (int) $lastVer['id'],
            ':u' => (int) $currentUser['id'],
            ':s' => $side,
            ':h' => $lastVer['file_hash'],
        ]);

        // Проверяем, стала ли подпись второй
        $cnt = (int) $pdo->query("SELECT COUNT(*) FROM document_signatures WHERE document_id = " . (int) $docId)->fetchColumn();
        $newStatus = $doc['status'];
        $newOwner  = $doc['owner_side'];
        if ($cnt >= 2) {
            $newStatus = 'active';
            $newOwner  = 'internal';
        } else {
            $newOwner = $side === 'internal' ? 'external' : 'internal';
        }
        $stmt = $pdo->prepare('UPDATE documents SET status = :s, owner_side = :o WHERE id = :id');
        $stmt->execute([':s' => $newStatus, ':o' => $newOwner, ':id' => $docId]);

        $stmt = $pdo->prepare(
            'INSERT INTO document_events (document_id, version_id, actor_user_id, actor_side, action, from_status, to_status)
             VALUES (:d, :v, :u, :s, :a, :from, :to)'
        );
        $stmt->execute([
            ':d'    => $docId,
            ':v'    => (int) $lastVer['id'],
            ':u'    => (int) $currentUser['id'],
            ':s'    => $side,
            ':a'    => 'sign',
            ':from' => $doc['status'],
            ':to'   => $newStatus,
        ]);

        $_SESSION['doc_flash'] = ['type' => 'success', 'message' => 'Подпись применена.'];
    } catch (Throwable $e) {
        $_SESSION['doc_flash'] = ['type' => 'danger', 'message' => 'Ошибка: ' . $e->getMessage()];
    }
    header('Location: index.php?page=document&id=' . $docId . '&role=' . $viewRole);
    exit;
}

// ===== Документы: загрузка новой версии =====
if ($action === 'doc_upload_version' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $docId = (int) ($_POST['document_id'] ?? 0);
    $comment = trim((string) ($_POST['comment'] ?? ''));
    $doc = doc_fetch_card($pdo, $docId);
    if (!$doc) { header('Location: index.php?page=documents'); exit; }

    $side = doc_side_for_role($viewRole);
    if ($side === null) { header('Location: index.php'); exit; }

    if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['doc_flash'] = ['type' => 'danger', 'message' => 'Ошибка загрузки файла.'];
        header('Location: index.php?page=document&id=' . $docId . '&role=' . $viewRole);
        exit;
    }

    try {
        $file = $_FILES['file'];
        $allowed = ['pdf','png','jpeg','jpg','zip','gzip','rar','doc','docx','xls','xlsx','gz'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed, true)) {
            throw new RuntimeException('Недопустимое расширение файла.');
        }
        if ($file['size'] > 20 * 1024 * 1024) {
            throw new RuntimeException('Файл больше 20 МБ.');
        }

        $sha = hash_file('sha256', $file['tmp_name']);

        // Номер новой версии
        $stmt = $pdo->prepare('SELECT COALESCE(MAX(version), 0) + 1 FROM document_versions WHERE document_id = :id');
        $stmt->execute([':id' => $docId]);
        $newVersion = (int) $stmt->fetchColumn();

        $dir = __DIR__ . '/../storage/documents/' . $docId;
        if (!is_dir($dir)) mkdir($dir, 0775, true);
        $shortHash = substr($sha, 0, 8);
        $fileName = "v{$newVersion}_{$shortHash}.{$ext}";
        $target = $dir . '/' . $fileName;
        if (!move_uploaded_file($file['tmp_name'], $target)) {
            throw new RuntimeException('Не удалось сохранить файл на диск.');
        }

        $stmt = $pdo->prepare(
            'INSERT INTO document_versions (document_id, version, file_path, file_name, file_size, file_hash, uploaded_by, comment)
             VALUES (:d, :v, :p, :n, :s, :h, :u, :c)'
        );
        $stmt->execute([
            ':d' => $docId,
            ':v' => $newVersion,
            ':p' => 'storage/documents/' . $docId . '/' . $fileName,
            ':n' => $file['name'],
            ':s' => (int) $file['size'],
            ':h' => $sha,
            ':u' => (int) $currentUser['id'],
            ':c' => $comment !== '' ? $comment : null,
        ]);
        $versionId = (int) $pdo->lastInsertId();

        $stmt = $pdo->prepare('UPDATE documents SET current_version_id = :v WHERE id = :id');
        $stmt->execute([':v' => $versionId, ':id' => $docId]);

        $stmt = $pdo->prepare(
            'INSERT INTO document_events (document_id, version_id, actor_user_id, actor_side, action, comment)
             VALUES (:d, :v, :u, :s, :a, :c)'
        );
        $stmt->execute([
            ':d' => $docId,
            ':v' => $versionId,
            ':u' => (int) $currentUser['id'],
            ':s' => $side,
            ':a' => 'uploaded',
            ':c' => $comment !== '' ? $comment : null,
        ]);

        $_SESSION['doc_flash'] = ['type' => 'success', 'message' => 'Версия v' . $newVersion . ' загружена.'];
    } catch (Throwable $e) {
        $_SESSION['doc_flash'] = ['type' => 'danger', 'message' => $e->getMessage()];
    }
    header('Location: index.php?page=document&id=' . $docId . '&role=' . $viewRole);
    exit;
}

// ===== Документы: комментарий =====
if ($action === 'doc_comment' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $docId = (int) ($_POST['document_id'] ?? 0);
    $comment = trim((string) ($_POST['comment'] ?? ''));
    $doc = doc_fetch_card($pdo, $docId);
    if (!$doc) { header('Location: index.php?page=documents'); exit; }
    $side = doc_side_for_role($viewRole);
    if ($side === null) { header('Location: index.php'); exit; }
    if ($comment !== '') {
        $stmt = $pdo->prepare(
            'INSERT INTO document_events (document_id, actor_user_id, actor_side, action, comment)
             VALUES (:d, :u, :s, :a, :c)'
        );
        $stmt->execute([
            ':d' => $docId,
            ':u' => (int) $currentUser['id'],
            ':s' => $side,
            ':a' => 'comment',
            ':c' => $comment,
        ]);
    }
    header('Location: index.php?page=document&id=' . $docId . '&role=' . $viewRole);
    exit;
}

// ===== Скачивание файла =====
if ($action === 'doc_download' && isset($_GET['id'], $_GET['v'])) {
    $docId = (int) $_GET['id'];
    $verNum = (int) $_GET['v'];
    $doc = doc_fetch_card($pdo, $docId);
    if (!$doc) { http_response_code(404); exit('Документ не найден'); }
    $side = doc_side_for_role($viewRole);
    if ($side === null) { http_response_code(403); exit('Доступ запрещён'); }

    $stmt = $pdo->prepare('SELECT * FROM document_versions WHERE document_id = :d AND version = :v LIMIT 1');
    $stmt->execute([':d' => $docId, ':v' => $verNum]);
    $ver = $stmt->fetch();
    if (!$ver) { http_response_code(404); exit('Версия не найдена'); }

    $absolute = __DIR__ . '/../' . $ver['file_path'];
    if (!is_file($absolute)) { http_response_code(404); exit('Файл отсутствует на диске'); }

    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . addslashes($ver['file_name']) . '"');
    header('Content-Length: ' . filesize($absolute));
    readfile($absolute);
    exit;
}

// ===== Help feedback =====
if ($action === 'help_feedback' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $articleId = (int) ($_POST['article_id'] ?? 0);
    $body = trim((string) ($_POST['body'] ?? ''));
    if ($articleId > 0 && $body !== '') {
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO help_feedback (article_id, user_id, body) VALUES (:a, :u, :b)'
            );
            $stmt->execute([
                ':a' => $articleId,
                ':u' => (int) $currentUser['id'],
                ':b' => mb_substr($body, 0, 500),
            ]);
        } catch (Throwable $e) {
            // тихо
        }
    }
    $back = $_SERVER['HTTP_REFERER'] ?? 'index.php';
    header('Location: ' . $back);
    exit;
}