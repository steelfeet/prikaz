<?php
declare(strict_types=1);

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

// ===== ТЕМЫ: сохранение (создание / редактирование) =====
if ($action === 'admin_curriculum_save') {
    if (!in_array('admin', $currentUser['roles'], true)) { http_response_code(403); exit('Доступ запрещён'); }

    $id      = (int) ($_POST['id'] ?? 0);
    $subject = (int) ($_POST['subject_id'] ?? 0);
    $ord     = (int) ($_POST['ord'] ?? 0);
    $topic   = trim((string) ($_POST['topic'] ?? ''));

    $backUrl = 'index.php?page=admin&section=subjects';
    if ($subject > 0) {
        $stmt = $pdo->prepare(
            'SELECT sm.academic_year_id FROM subjects s
             JOIN semesters sm ON sm.id = s.semester_id
             WHERE s.id = :id'
        );
        $stmt->execute([':id' => $subject]);
        $yearId = (int) $stmt->fetchColumn();
        if ($yearId > 0) $backUrl .= '&subject_year=' . $yearId;
    }

    if ($subject <= 0) {
        if (wants_json()) json_response(['ok' => false, 'error' => 'Предмет не указан.']);
        flash_set('danger', 'Предмет не указан.');
        redirect($backUrl);
    }
    if ($topic === '') {
        if (wants_json()) json_response(['ok' => false, 'error' => 'Тема не может быть пустой.']);
        flash_set('danger', 'Тема не может быть пустой.');
        redirect($backUrl);
    }
    if ($ord <= 0) {
        if (wants_json()) json_response(['ok' => false, 'error' => 'Порядковый номер должен быть больше 0.']);
        flash_set('danger', 'Порядковый номер должен быть больше 0.');
        redirect($backUrl);
    }

    try {
        if ($id > 0) {
            $pdo->prepare(
                'UPDATE curriculum SET ord = :o, topic = :t, updated_at = :u WHERE id = :id'
            )->execute([':o' => $ord, ':t' => $topic, ':u' => date('Y-m-d H:i:s'), ':id' => $id]);
            audit_log((int) $currentUser['id'], 'curriculum_update', 'curriculum', $id);

            if (wants_json()) {
                json_response([
                    'ok'         => true,
                    'html'       => curriculum_render_block($subject),
                    'subject_id' => $subject,
                ]);
            }
            flash_set('success', 'Тема сохранена.');
        } else {
            $pdo->prepare(
                'INSERT INTO curriculum (subject_id, ord, topic) VALUES (:s, :o, :t)'
            )->execute([':s' => $subject, ':o' => $ord, ':t' => $topic]);
            $newId = (int) $pdo->lastInsertId();
            audit_log((int) $currentUser['id'], 'curriculum_create', 'curriculum', $newId);

            if (wants_json()) {
                json_response([
                    'ok'         => true,
                    'html'       => curriculum_render_block($subject),
                    'subject_id' => $subject,
                ]);
            }
            flash_set('success', 'Тема добавлена.');
        }
    } catch (PDOException $e) {
        $err = str_contains($e->getMessage(), 'UNIQUE')
            ? 'Тема с таким порядковым номером уже есть у этого предмета.'
            : 'Ошибка: ' . $e->getMessage();
        if (wants_json()) json_response(['ok' => false, 'error' => $err]);
        flash_set('danger', $err);
    }
    redirect($backUrl);
}

// ===== ТЕМЫ: вставить тему ниже выбранной =====
if ($action === 'admin_curriculum_insert_below') {
    if (!in_array('admin', $currentUser['roles'], true)) { http_response_code(403); exit('Доступ запрещён'); }

    $id    = (int) ($_POST['id'] ?? 0);
    $topic = trim((string) ($_POST['topic'] ?? ''));

    $backUrl = 'index.php?page=admin&section=subjects';
    $subjectId = 0;
    try {
        $stmt = $pdo->prepare('SELECT subject_id FROM curriculum WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $subjectId = (int) $stmt->fetchColumn();
        if ($subjectId > 0) {
            $stmt = $pdo->prepare(
                'SELECT sm.academic_year_id FROM subjects s
                 JOIN semesters sm ON sm.id = s.semester_id
                 WHERE s.id = :id'
            );
            $stmt->execute([':id' => $subjectId]);
            $yearId = (int) $stmt->fetchColumn();
            if ($yearId > 0) $backUrl .= '&subject_year=' . $yearId;
        }
    } catch (Throwable $e) {
        if (wants_json()) json_response(['ok' => false, 'error' => $e->getMessage()]);
        flash_set('danger', $e->getMessage());
        redirect($backUrl);
    }

    if ($subjectId <= 0) {
        if (wants_json()) json_response(['ok' => false, 'error' => 'Тема не найдена.']);
        flash_set('danger', 'Тема не найдена.');
        redirect($backUrl);
    }
    if ($topic === '') {
        if (wants_json()) json_response(['ok' => false, 'error' => 'Тема не может быть пустой.']);
        flash_set('danger', 'Тема не может быть пустой.');
        redirect($backUrl);
    }

    try {
        $newId = curriculum_insert_below($id, $topic, $pdo);
        audit_log((int) $currentUser['id'], 'curriculum_insert_below', 'curriculum', $newId);

        if (wants_json()) {
            json_response([
                'ok'         => true,
                'html'       => curriculum_render_block($subjectId),
                'subject_id' => $subjectId,
            ]);
        }
        flash_set('success', 'Тема добавлена ниже.');
    } catch (Throwable $e) {
        if (wants_json()) json_response(['ok' => false, 'error' => $e->getMessage()]);
        flash_set('danger', 'Ошибка: ' . $e->getMessage());
    }
    redirect($backUrl);
}

// ===== ТЕМЫ: удаление =====
if ($action === 'admin_curriculum_delete') {
    if (!in_array('admin', $currentUser['roles'], true)) { http_response_code(403); exit('Доступ запрещён'); }
    $id = (int) ($_POST['id'] ?? 0);

    $backUrl = 'index.php?page=admin&section=subjects';
    $subjectId = 0;
    try {
        $stmt = $pdo->prepare(
            'SELECT c.subject_id, sm.academic_year_id
             FROM curriculum c
             JOIN subjects s ON s.id = c.subject_id
             JOIN semesters sm ON sm.id = s.semester_id
             WHERE c.id = :id'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        if ($row) {
            $subjectId = (int) $row['subject_id'];
            $yearId    = (int) $row['academic_year_id'];
            if ($yearId > 0) $backUrl .= '&subject_year=' . $yearId;
        }

        $pdo->prepare('DELETE FROM curriculum WHERE id = :id')->execute([':id' => $id]);
        audit_log((int) $currentUser['id'], 'curriculum_delete', 'curriculum', $id);

        if (wants_json()) {
            $html = $subjectId > 0 ? curriculum_render_block($subjectId) : '';
            json_response(['ok' => true, 'html' => $html, 'subject_id' => $subjectId]);
        }
        flash_set('success', 'Тема удалена.');
    } catch (Throwable $e) {
        if (wants_json()) json_response(['ok' => false, 'error' => $e->getMessage()]);
        flash_set('danger', 'Ошибка: ' . $e->getMessage());
    }
    redirect($backUrl);
}

// ===== ТЕМЫ: массовое добавление =====
if ($action === 'admin_curriculum_bulk_add') {
    if (!in_array('admin', $currentUser['roles'], true)) { http_response_code(403); exit('Доступ запрещён'); }

    $subjectId = (int) ($_POST['subject_id'] ?? 0);
    $rawTopics = (string) ($_POST['topics'] ?? '');

    $backUrl = 'index.php?page=admin&section=subjects';
    if ($subjectId > 0) {
        $stmt = $pdo->prepare(
            'SELECT sm.academic_year_id FROM subjects s
             JOIN semesters sm ON sm.id = s.semester_id
             WHERE s.id = :id'
        );
        $stmt->execute([':id' => $subjectId]);
        $yearId = (int) $stmt->fetchColumn();
        if ($yearId > 0) $backUrl .= '&subject_year=' . $yearId;
    }

    if ($subjectId <= 0) {
        if (wants_json()) json_response(['ok' => false, 'error' => 'Предмет не указан.']);
        flash_set('danger', 'Предмет не указан.');
        redirect($backUrl);
    }
    $stmt = $pdo->prepare('SELECT id, short_name FROM subjects WHERE id = :id');
    $stmt->execute([':id' => $subjectId]);
    $subject = $stmt->fetch();
    if (!$subject) {
        if (wants_json()) json_response(['ok' => false, 'error' => 'Предмет не найден.']);
        flash_set('danger', 'Предмет не найден.');
        redirect($backUrl);
    }

    $parsed = parse_topic_lines($rawTopics);

    if ($parsed['invalid'] > 0) {
        $err = 'Не удалось разобрать вставленный текст: возможны символы другой кодировки. '
            . 'Скопируйте текст как обычный текст (Ctrl+Shift+V) и попробуйте снова.';
        if (wants_json()) json_response(['ok' => false, 'error' => $err]);
        flash_set('danger', $err);
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
            $err = 'Все непустые строки — дубликаты (повтор в списке: ' . $dupBatch
                . ', уже есть в плане: ' . $dupExist . '). Новых тем не добавлено.';
        } elseif ($emptyLines > 0) {
            $err = 'Все строки пустые или состоят только из пробелов и невидимых символов. Новых тем не добавлено.';
        } else {
            $err = 'Список пуст: нечего добавлять.';
        }
        if (wants_json()) json_response(['ok' => false, 'error' => $err]);
        flash_set('danger', $err);
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

        if (wants_json()) {
            json_response([
                'ok'         => true,
                'html'       => curriculum_render_block($subjectId),
                'subject_id' => $subjectId,
            ]);
        }

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
        if (wants_json()) json_response(['ok' => false, 'error' => $e->getMessage()]);
        flash_set('danger', 'Ошибка: ' . $e->getMessage());
    }
    redirect($backUrl);
}

// ===== КТП: генерация =====
if ($action === 'admin_ktp_generate') {
    if (!in_array('admin', $currentUser['roles'], true)) { http_response_code(403); exit('Доступ запрещён'); }

    $subjectId = (int) ($_POST['subject_id'] ?? 0);

    $backUrl = 'index.php?page=admin&section=subjects';
    if ($subjectId > 0) {
        $stmt = $pdo->prepare(
            'SELECT sm.academic_year_id FROM subjects s
             JOIN semesters sm ON sm.id = s.semester_id
             WHERE s.id = :id'
        );
        $stmt->execute([':id' => $subjectId]);
        $yearId = (int) $stmt->fetchColumn();
        if ($yearId > 0) $backUrl .= '&subject_year=' . $yearId;
        $backUrl .= '#ktp-' . $subjectId;
    }

    if ($subjectId <= 0) {
        if (wants_json()) json_response(['ok' => false, 'error' => 'Предмет не указан.']);
        flash_set('danger', 'Предмет не указан.');
        redirect($backUrl);
    }

    try {
        ktp_generate($subjectId, $pdo);
        audit_log((int) $currentUser['id'], 'ktp_generate', 'subject', $subjectId);

        if (wants_json()) {
            json_response(['ok' => true, 'html' => ktp_render_block($subjectId), 'subject_id' => $subjectId]);
        }
        flash_set('success', 'КТП сгенерировано.');
    } catch (Throwable $e) {
        if (wants_json()) json_response(['ok' => false, 'error' => $e->getMessage()]);
        flash_set('danger', 'Ошибка: ' . $e->getMessage());
    }
    redirect($backUrl);
}

// ===== КТП: дублировать тему на следующий блок =====
if ($action === 'admin_ktp_add_more') {
    if (!in_array('admin', $currentUser['roles'], true)) { http_response_code(403); exit('Доступ запрещён'); }

    $entryId = (int) ($_POST['id'] ?? 0);

    $backUrl = 'index.php?page=admin&section=subjects';
    $subjectId = 0;
    try {
        $stmt = $pdo->prepare('SELECT subject_id FROM ktp_entries WHERE id = :id');
        $stmt->execute([':id' => $entryId]);
        $subjectId = (int) $stmt->fetchColumn();
        if ($subjectId > 0) {
            $stmt = $pdo->prepare(
                'SELECT sm.academic_year_id FROM subjects s
                 JOIN semesters sm ON sm.id = s.semester_id
                 WHERE s.id = :id'
            );
            $stmt->execute([':id' => $subjectId]);
            $yearId = (int) $stmt->fetchColumn();
            if ($yearId > 0) $backUrl .= '&subject_year=' . $yearId;
            $backUrl .= '#ktp-' . $subjectId;
        }
        ktp_add_more($entryId, $pdo);
        audit_log((int) $currentUser['id'], 'ktp_add_more', 'ktp_entry', $entryId);

        if (wants_json()) {
            $html = $subjectId > 0 ? ktp_render_block($subjectId) : '';
            json_response(['ok' => true, 'html' => $html, 'subject_id' => $subjectId]);
        }
        flash_set('success', 'Тема продублирована на следующий блок.');
    } catch (Throwable $e) {
        if (wants_json()) json_response(['ok' => false, 'error' => $e->getMessage()]);
        flash_set('danger', 'Ошибка: ' . $e->getMessage());
    }
    redirect($backUrl);
}

// ===== КТП: удалить тему =====
if ($action === 'admin_ktp_delete') {
    if (!in_array('admin', $currentUser['roles'], true)) { http_response_code(403); exit('Доступ запрещён'); }

    $entryId = (int) ($_POST['id'] ?? 0);

    $backUrl = 'index.php?page=admin&section=subjects';
    $subjectId = 0;
    try {
        $stmt = $pdo->prepare('SELECT subject_id FROM ktp_entries WHERE id = :id');
        $stmt->execute([':id' => $entryId]);
        $subjectId = (int) $stmt->fetchColumn();
        if ($subjectId > 0) {
            $stmt = $pdo->prepare(
                'SELECT sm.academic_year_id FROM subjects s
                 JOIN semesters sm ON sm.id = s.semester_id
                 WHERE s.id = :id'
            );
            $stmt->execute([':id' => $subjectId]);
            $yearId = (int) $stmt->fetchColumn();
            if ($yearId > 0) $backUrl .= '&subject_year=' . $yearId;
            $backUrl .= '#ktp-' . $subjectId;
        }
        ktp_delete($entryId, $pdo);
        audit_log((int) $currentUser['id'], 'ktp_delete', 'ktp_entry', $entryId);

        if (wants_json()) {
            $html = $subjectId > 0 ? ktp_render_block($subjectId) : '';
            json_response(['ok' => true, 'html' => $html, 'subject_id' => $subjectId]);
        }
        flash_set('success', 'Тема удалена из КТП.');
    } catch (Throwable $e) {
        if (wants_json()) json_response(['ok' => false, 'error' => $e->getMessage()]);
        flash_set('danger', 'Ошибка: ' . $e->getMessage());
    }
    redirect($backUrl);
}