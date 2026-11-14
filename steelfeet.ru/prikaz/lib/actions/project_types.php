<?php
declare(strict_types=1);

// ===== ТИП ПРОЕКТА: сохранение =====
if ($action === 'admin_project_type_save') {
    if (!in_array('admin', $currentUser['roles'], true)) { http_response_code(403); exit('Доступ запрещён'); }

    $id          = (int) ($_POST['id'] ?? 0);
    $code        = trim((string) ($_POST['code'] ?? ''));
    $name        = trim((string) ($_POST['name'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    $color       = trim((string) ($_POST['color'] ?? '#8b6914'));
    $icon        = trim((string) ($_POST['icon'] ?? '📌'));
    $gogsOwner   = trim((string) ($_POST['gogs_owner'] ?? ''));
    $gogsPrefix  = trim((string) ($_POST['gogs_repo_prefix'] ?? ''));

    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
        $color = '#8b6914';
    }

    $backUrl = 'index.php?page=admin&section=project_types';

    if ($name === '') {
        flash_set('danger', 'Название типа обязательно.');
        redirect($backUrl);
    }

    try {
        $newId = project_type_save([
            'id'             => $id,
            'code'           => $code,
            'name'           => $name,
            'description'    => $description,
            'color'          => $color,
            'icon'           => $icon,
            'gogs_owner'     => $gogsOwner,
            'gogs_repo_prefix' => $gogsPrefix,
        ], (int) $currentUser['id'], $pdo);

        audit_log((int) $currentUser['id'],
            $id > 0 ? 'project_type_update' : 'project_type_create',
            'project_type', $newId);

        flash_set('success', $id > 0 ? 'Тип сохранён.' : 'Тип создан. Папка projects/' . $code . '/ сгенерирована из шаблона.');
    } catch (Throwable $e) {
        flash_set('danger', 'Ошибка: ' . $e->getMessage());
    }
    redirect($backUrl);
}

// ===== ТИП ПРОЕКТА: удаление =====
if ($action === 'admin_project_type_delete') {
    if (!in_array('admin', $currentUser['roles'], true)) { http_response_code(403); exit('Доступ запрещён'); }
    $id = (int) ($_POST['id'] ?? 0);
    $backUrl = 'index.php?page=admin&section=project_types';
    try {
        project_type_delete($id, $pdo);
        audit_log((int) $currentUser['id'], 'project_type_delete', 'project_type', $id);
        flash_set('success', 'Тип удалён. Папка projects/ оставлена на месте.');
    } catch (Throwable $e) {
        flash_set('danger', 'Ошибка: ' . $e->getMessage());
    }
    redirect($backUrl);
}

// ===== ТИП ПРОЕКТА: пересоздать папку (принудительно) =====
if ($action === 'admin_project_type_regen_folder') {
    if (!in_array('admin', $currentUser['roles'], true)) { http_response_code(403); exit('Доступ запрещён'); }
    $id = (int) ($_POST['id'] ?? 0);
    $backUrl = 'index.php?page=admin&section=project_types';
    try {
        project_type_render_files($id, $pdo, true);
        audit_log((int) $currentUser['id'], 'project_type_regen', 'project_type', $id);
        flash_set('success', 'Файлы типа перезаписаны из шаблона.');
    } catch (Throwable $e) {
        flash_set('danger', 'Ошибка: ' . $e->getMessage());
    }
    redirect($backUrl);
}

// ===== ТИП ПРОЕКТА: проверка =====
if ($action === 'admin_project_type_check') {
    if (!in_array('admin', $currentUser['roles'], true)) { http_response_code(403); exit('Доступ запрещён'); }
    $id     = (int) ($_POST['id'] ?? 0);
    $apply  = ((string) ($_POST['apply'] ?? '0')) === '1';
    $backUrl = 'index.php?page=admin&section=project_types';

    try {
        $res = project_type_check($id, (int) $currentUser['id'], $pdo, $apply);
        audit_log((int) $currentUser['id'], 'project_type_check', 'project_type', $id,
            'ok=' . ($res['ok'] ? '1' : '0')
            . ' apply=' . ($apply ? '1' : '0')
            . ' errors=' . count($res['errors'])
            . ' warnings=' . count($res['warnings']));

        if ($res['ok']) {
            $msg = $apply ? 'Проверка пройдена, действия применены.' : 'Проверка пройдена (dry-run).';
            if ($res['details'] !== []) $msg .= ' ' . implode(' ', array_map('strval', $res['details']));
            flash_set('success', $msg);
        } else {
            $msg = $apply ? 'Проверка не пройдена, действия применены.' : 'Проверка не пройдена (dry-run).';
            if ($res['errors'] !== []) {
                $msg .= ' ' . implode(' ', array_map('strval', $res['errors']));
            }
            flash_set('danger', $msg);
        }
    } catch (Throwable $e) {
        flash_set('danger', 'Ошибка: ' . $e->getMessage());
    }
    redirect($backUrl . '#type-' . $id);
}

// ===== ФАЗА ТИПА: сохранение (создание/обновление) =====
if ($action === 'admin_project_type_phase_save') {
    if (!in_array('admin', $currentUser['roles'], true)) { http_response_code(403); exit('Доступ запрещён'); }

    $id          = (int) ($_POST['id'] ?? 0);
    $typeId      = (int) ($_POST['project_type_id'] ?? 0);
    $ord         = (int) ($_POST['ord'] ?? 0);
    $name        = trim((string) ($_POST['name'] ?? ''));
    $color       = trim((string) ($_POST['color'] ?? '#8b6914'));
    $description = trim((string) ($_POST['description'] ?? ''));

    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
        $color = '#8b6914';
    }

    $backUrl = 'index.php?page=admin&section=project_types';
    if ($typeId > 0) $backUrl .= '#type-' . $typeId;

    if ($typeId <= 0 || $name === '' || $ord <= 0) {
        flash_set('danger', 'Фаза: нужны тип, номер > 0 и название.');
        redirect($backUrl);
    }

    try {
        if ($id > 0) {
            $pdo->prepare(
                'UPDATE project_type_phases SET ord = :o, name = :n, color = :c, description = :d
                 WHERE id = :id'
            )->execute([
                ':o' => $ord, ':n' => $name, ':c' => $color,
                ':d' => $description !== '' ? $description : null, ':id' => $id,
            ]);
            audit_log((int) $currentUser['id'], 'project_type_phase_update', 'project_type_phase', $id);
            flash_set('success', 'Фаза сохранена.');
        } else {
            $pdo->prepare(
                'INSERT INTO project_type_phases (project_type_id, ord, name, color, description)
                 VALUES (:t, :o, :n, :c, :d)'
            )->execute([
                ':t' => $typeId, ':o' => $ord, ':n' => $name, ':c' => $color,
                ':d' => $description !== '' ? $description : null,
            ]);
            audit_log((int) $currentUser['id'], 'project_type_phase_create', 'project_type_phase', (int) $pdo->lastInsertId());
            flash_set('success', 'Фаза добавлена.');
        }
    } catch (PDOException $e) {
        if (str_contains($e->getMessage(), 'UNIQUE')) {
            flash_set('danger', 'У типа уже есть фаза с номером ' . $ord . '.');
        } else {
            flash_set('danger', 'Ошибка: ' . $e->getMessage());
        }
    }
    redirect($backUrl);
}

// ===== ФАЗА ТИПА: удаление =====
if ($action === 'admin_project_type_phase_delete') {
    if (!in_array('admin', $currentUser['roles'], true)) { http_response_code(403); exit('Доступ запрещён'); }
    $id     = (int) ($_POST['id'] ?? 0);
    $typeId = (int) ($_POST['project_type_id'] ?? 0);
    $backUrl = 'index.php?page=admin&section=project_types';
    if ($typeId > 0) $backUrl .= '#type-' . $typeId;

    try {
        $pdo->prepare('DELETE FROM project_type_phases WHERE id = :id')->execute([':id' => $id]);
        audit_log((int) $currentUser['id'], 'project_type_phase_delete', 'project_type_phase', $id);
        flash_set('success', 'Фаза удалена.');
    } catch (Throwable $e) {
        flash_set('danger', 'Ошибка: ' . $e->getMessage());
    }
    redirect($backUrl);
}