<?php
declare(strict_types=1);

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

                if ($subjectId <= 0) continue;

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