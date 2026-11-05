<?php
declare(strict_types=1);

/**
 * Загрузка данных для рендера. Ожидает $currentUser и $viewRole.
 */

$pdo = db();

$projectPhases = project_phases_meta();
$projectTypes  = project_types_meta();

// ===== Уроки =====
$journalDates   = [];
$journalLessons = [];
$stmt = $pdo->query('SELECT * FROM lessons ORDER BY ord ASC');
foreach ($stmt->fetchAll() as $i => $row) {
    $journalDates[$i]   = $row['date_str'];
    $journalLessons[$i] = [
        'topic'    => $row['topic'],
        'homework' => $row['homework'],
        'id'       => (int) $row['id'],
    ];
}

// ===== Студенты =====
$studentsRows = $pdo->query('SELECT * FROM students WHERE is_active = 1 ORDER BY full_name')->fetchAll();
$studentById  = [];
foreach ($studentsRows as $s) $studentById[(int) $s['id']] = $s;

// ===== Оценки =====
$journalMarks = [];
$journalNotes = [];
$stmt = $pdo->query('SELECT * FROM grades');
foreach ($stmt->fetchAll() as $g) {
    $lessonOrd = null;
    foreach ($journalLessons as $ord => $l) {
        if ((int) $l['id'] === (int) $g['lesson_id']) { $lessonOrd = $ord; break; }
    }
    if ($lessonOrd === null) continue;
    $journalMarks[(int) $g['student_id']][$lessonOrd] = $g['mark'];
    if (!empty($g['note'])) $journalNotes[(int) $g['student_id']][$lessonOrd] = $g['note'];
}

// ===== Проекты =====
$mockProjects = [];
$stmt = $pdo->query('SELECT * FROM projects WHERE is_archived = 0 ORDER BY id');
foreach ($stmt->fetchAll() as $p) {
    $pid = (int) $p['id'];
    $p['history']       = project_history_rows($pid);
    $p['students']      = array_map(fn($s) => (int) $s['id'], project_students_rows($pid));
    $p['current_phase'] = project_current_phase_num($pid);
    $mockProjects[]     = $p;
}
$projectById       = [];
$projectsByStudent = [];
foreach ($mockProjects as $proj) {
    $projectById[(int) $proj['id']] = $proj;
    foreach ($proj['students'] as $sid) {
        $projectsByStudent[(int) $sid][] = (int) $proj['id'];
    }
}

// ===== Агрегаты журнала =====
$journalSummary = aggregate_journal($journalMarks);
$perStudentSummary = [];
foreach ($studentsRows as $st) {
    $sid = (int) $st['id'];
    $row = $journalMarks[$sid] ?? [];
    $sSum = 0; $sCount = 0; $sAbsent = 0; $sPresent = 0;
    $sDist = [5 => 0, 4 => 0, 3 => 0, 2 => 0];
    foreach ($row as $m) {
        if ($m === 'н') {
            $sAbsent++;
        } elseif (in_array($m, ['5', '4', '3', '2'], true)) {
            $g = (int) $m; $sSum += $g; $sCount++;
            $sDist[$g] = ($sDist[$g] ?? 0) + 1; $sPresent++;
        } elseif ($m === '·') {
            $sPresent++;
        }
    }
    $perStudentSummary[$sid] = [
        'student'    => $st,
        'sum'        => $sSum,
        'count'      => $sCount,
        'avg'        => $sCount > 0 ? round($sSum / $sCount, 2) : null,
        'absent'     => $sAbsent,
        'present'    => $sPresent,
        'dist'       => $sDist,
        'attendance' => ($sAbsent + $sPresent) > 0 ? round($sPresent / ($sAbsent + $sPresent) * 100, 1) : null,
    ];
}

// ===== Профиль по роли =====
$studentProfile = null;
$parentProfile  = null;
$parentChild    = null;
$teacherProfile = null;

if (in_array('student', $currentUser['roles'], true)) {
    $stmt = $pdo->prepare('SELECT * FROM students WHERE user_id = :u LIMIT 1');
    $stmt->execute([':u' => (int) $currentUser['id']]);
    $studentProfile = $stmt->fetch() ?: null;
}
if (in_array('parent', $currentUser['roles'], true)) {
    $stmt = $pdo->prepare('SELECT * FROM parents WHERE user_id = :u LIMIT 1');
    $stmt->execute([':u' => (int) $currentUser['id']]);
    $parentProfile = $stmt->fetch() ?: null;
    if ($parentProfile) {
        $stmt = $pdo->prepare(
            'SELECT s.* FROM students s
             JOIN parent_student ps ON ps.student_id = s.id
             WHERE ps.parent_id = :p LIMIT 1'
        );
        $stmt->execute([':p' => (int) $parentProfile['id']]);
        $parentChild = $stmt->fetch() ?: null;
    }
}
if (in_array('teacher', $currentUser['roles'], true)) {
    $stmt = $pdo->prepare('SELECT * FROM teachers WHERE user_id = :u LIMIT 1');
    $stmt->execute([':u' => (int) $currentUser['id']]);
    $teacherProfile = $stmt->fetch() ?: null;
}

// ===== Визит родителя =====
if ($viewRole === 'parent' && $parentProfile) {
    try {
        $stmt = $pdo->prepare('INSERT OR IGNORE INTO parent_visits (parent_id, visit_date) VALUES (:p, :d)');
        $stmt->execute([':p' => (int) $parentProfile['id'], ':d' => date('Y-m-d')]);
    } catch (Throwable $e) {}
}

// ===== Сводка визитов =====
$parentVisitsMap = [];
try {
    $rows = $pdo->query(
        'SELECT ps.student_id, COUNT(pv.id) AS cnt, MAX(pv.visited_at) AS last
         FROM parent_visits pv
         JOIN parent_student ps ON ps.parent_id = pv.parent_id
         GROUP BY ps.student_id'
    )->fetchAll();
    foreach ($rows as $row) {
        $parentVisitsMap[(int) $row['student_id']] = [
            'count' => (int) $row['cnt'],
            'last'  => $row['last'],
        ];
    }
} catch (Throwable $e) {}

// ===== Help =====
$helpArticles = [];
try {
    $stmt = $pdo->prepare(
        "SELECT * FROM help_articles WHERE role_visibility IN ('all', :r) ORDER BY id"
    );
    $stmt->execute([':r' => $viewRole]);
    $helpArticles = $stmt->fetchAll();
} catch (Throwable $e) {}

// ===== Данные админки =====
$adminUsers = $adminStudents = $adminParents = $adminLessons = $adminProjects = $adminParentLinks = $adminAudit = [];
$adminAcademicYears = [];
$adminCurrentYear = null;
$adminGroups = [];
$adminGroupsFilterYear = 0;
$defaultGroupYearId = 0;
$adminSubjects = [];
$adminSubjectsFilterYear = 0;
$adminSubjectsFilterGroup = 0;
$adminSubjectsFilterSemester = 0;
$adminAllGroups = [];
$adminSemesters = [];
$adminAllSemesters = [];
$defaultSubjectGroupId = 0;
$defaultSubjectSemesterId = 0;
$adminCurrentYearSubjectCount = 0;
$adminCurriculum = [];
$adminScheduleGroupId = 0;
$adminScheduleEntries = [];
$adminScheduleSubjects = [];
$adminScheduleGroup = null;

if ($viewRole === 'admin') {
    $adminUsers = $pdo->query(
        "SELECT u.*, GROUP_CONCAT(r.code, ',') AS roles
         FROM users u
         LEFT JOIN user_roles ur ON ur.user_id = u.id
         LEFT JOIN roles r ON r.id = ur.role_id
         GROUP BY u.id ORDER BY u.id"
    )->fetchAll();

    $adminStudents = $pdo->query('SELECT * FROM students ORDER BY full_name')->fetchAll();
    $adminParents  = $pdo->query('SELECT * FROM parents ORDER BY full_name')->fetchAll();
    $adminLessons  = $pdo->query('SELECT * FROM lessons ORDER BY ord')->fetchAll();
    $adminProjects = $mockProjects;
    $adminParentLinks = $pdo->query('SELECT * FROM parent_student')->fetchAll();
    $adminAudit = $pdo->query(
        'SELECT a.*, u.login
         FROM audit_log a
         LEFT JOIN users u ON u.id = a.user_id
         ORDER BY a.id DESC
         LIMIT 100'
    )->fetchAll();

    $adminAcademicYears = $pdo->query(
        'SELECT * FROM academic_years ORDER BY start_date DESC'
    )->fetchAll();

    foreach ($adminAcademicYears as $ay) {
        if ((int) $ay['is_current'] === 1) {
            $adminCurrentYear = $ay;
            break;
        }
    }

    // ===== Группы =====
    // По умолчанию (без group_year в URL) — текущий учебный год.
    // Явное group_year=0 — «Все».
    if (isset($_GET['group_year'])) {
        $adminGroupsFilterYear = (int) $_GET['group_year'];
    } elseif ($adminCurrentYear !== null) {
        $adminGroupsFilterYear = (int) $adminCurrentYear['id'];
    } elseif ($adminAcademicYears !== []) {
        $adminGroupsFilterYear = (int) $adminAcademicYears[0]['id'];
    } else {
        $adminGroupsFilterYear = 0;
    }

    $sql = 'SELECT g.*,
                   ay.name AS year_name,
                   ay.is_current AS year_is_current,
                   ay.start_date AS year_start
            FROM groups g
            JOIN academic_years ay ON ay.id = g.academic_year_id';
    $params = [];
    if ($adminGroupsFilterYear > 0) {
        $sql .= ' WHERE g.academic_year_id = :y';
        $params[':y'] = $adminGroupsFilterYear;
    }
    $sql .= ' ORDER BY ay.start_date DESC, g.name';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $adminGroups = $stmt->fetchAll();

    // Значение по умолчанию для селекта «Учебный год» в форме создания:
    //   — если включён фильтр, то отфильтрованный год;
    //   — иначе текущий;
    //   — иначе самый свежий из существующих.
    if ($adminGroupsFilterYear > 0) {
        $defaultGroupYearId = $adminGroupsFilterYear;
    } elseif ($adminCurrentYear !== null) {
        $defaultGroupYearId = (int) $adminCurrentYear['id'];
    } elseif ($adminAcademicYears !== []) {
        $defaultGroupYearId = (int) $adminAcademicYears[0]['id'];
    }

    // ===== Семестры =====
    $adminAllSemesters = $pdo->query(
        'SELECT sm.*, ay.name AS year_name, ay.is_current AS year_is_current
         FROM semesters sm
         JOIN academic_years ay ON ay.id = sm.academic_year_id
         ORDER BY ay.start_date DESC, sm.ord, sm.name'
    )->fetchAll();

    // ===== Предметы =====
    // Год берётся через семестр: предмет относится к семестру, а не к году напрямую.
    // subject_year=0 — «все годы», subject_semester=0 — «все семестры».
    if (isset($_GET['subject_year'])) {
        $adminSubjectsFilterYear = (int) $_GET['subject_year'];
    } elseif ($adminCurrentYear !== null) {
        $adminSubjectsFilterYear = (int) $adminCurrentYear['id'];
    } elseif ($adminAcademicYears !== []) {
        $adminSubjectsFilterYear = (int) $adminAcademicYears[0]['id'];
    } else {
        $adminSubjectsFilterYear = 0;
    }
    $adminSubjectsFilterGroup   = isset($_GET['subject_group'])   ? (int) $_GET['subject_group']   : 0;
    $adminSubjectsFilterSemester = isset($_GET['subject_semester']) ? (int) $_GET['subject_semester'] : 0;

    $sql = 'SELECT s.*,
                   sm.name        AS semester_name,
                   sm.is_practice AS semester_is_practice,
                   sm.ord         AS semester_ord,
                   ay.name        AS year_name,
                   ay.is_current  AS year_is_current,
                   g.name         AS group_name
            FROM subjects s
            JOIN semesters sm     ON sm.id = s.semester_id
            JOIN academic_years ay ON ay.id = sm.academic_year_id
            JOIN groups g          ON g.id = s.group_id';
    $params = [];
    $where  = [];
    if ($adminSubjectsFilterYear > 0) {
        $where[] = 'sm.academic_year_id = :y';
        $params[':y'] = $adminSubjectsFilterYear;
    }
    if ($adminSubjectsFilterGroup > 0) {
        $where[] = 's.group_id = :g';
        $params[':g'] = $adminSubjectsFilterGroup;
    }
    if ($adminSubjectsFilterSemester > 0) {
        $where[] = 's.semester_id = :sm';
        $params[':sm'] = $adminSubjectsFilterSemester;
    }
    if ($where !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY ay.start_date DESC, sm.ord, sm.name, g.name, s.short_name';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $adminSubjects = $stmt->fetchAll();

    // Все группы (с названием года) — для селектов «Группа» в фильтрах и формах.
    $adminAllGroups = $pdo->query(
        'SELECT g.id, g.name, g.academic_year_id, ay.name AS year_name
         FROM groups g
         JOIN academic_years ay ON ay.id = g.academic_year_id
         ORDER BY ay.start_date DESC, g.name'
    )->fetchAll();

    // Семестры выбранного года — для селекта и фильтра «Семестр».
    foreach ($adminAllSemesters as $sm) {
        if ($adminSubjectsFilterYear === 0 || (int) $sm['academic_year_id'] === $adminSubjectsFilterYear) {
            $adminSemesters[] = $sm;
        }
    }

    $defaultSubjectGroupId    = $adminSubjectsFilterGroup;
    $defaultSubjectSemesterId = $adminSubjectsFilterSemester;
    if ($defaultSubjectSemesterId === 0 && $adminSemesters !== []) {
        $defaultSubjectSemesterId = (int) $adminSemesters[0]['id'];
    }

    if ($adminCurrentYear !== null) {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM subjects s
             JOIN semesters sm ON sm.id = s.semester_id
             WHERE sm.academic_year_id = :y'
        );
        $stmt->execute([':y' => (int) $adminCurrentYear['id']]);
        $adminCurrentYearSubjectCount = (int) $stmt->fetchColumn();
    }

    // ===== Учебный план: предметы года + их темы =====
    $adminCurriculum = [];
    if ($adminCurrentYear !== null) {
        $stmt = $pdo->prepare(
            'SELECT s.id, s.short_name, s.full_name, s.hours,
                    sm.name AS semester_name, sm.is_practice AS semester_is_practice,
                    g.name AS group_name
             FROM subjects s
             JOIN semesters sm ON sm.id = s.semester_id
             JOIN groups g     ON g.id = s.group_id
             WHERE sm.academic_year_id = :y
             ORDER BY sm.ord, sm.name, g.name, s.short_name'
        );
        $stmt->execute([':y' => (int) $adminCurrentYear['id']]);
        $subjectsRows = $stmt->fetchAll();

        $topicStmt = $pdo->prepare(
            'SELECT id, ord, topic FROM curriculum WHERE subject_id = :sid ORDER BY ord'
        );
        foreach ($subjectsRows as &$subj) {
            $topicStmt->execute([':sid' => (int) $subj['id']]);
            $subj['topics'] = $topicStmt->fetchAll();
        }
        unset($subj);
        $adminCurriculum = $subjectsRows;
    }

    // ===== Расписание =====
    // Выбор группы: из URL или первая группа текущего года (или любая).
    $adminScheduleGroupId = isset($_GET['schedule_group']) ? (int) $_GET['schedule_group'] : 0;

    $defaultScheduleGroupId = 0;
    if ($adminScheduleGroupId > 0) {
        $stmt = $pdo->prepare(
            'SELECT g.id FROM groups g WHERE g.id = :g LIMIT 1'
        );
        $stmt->execute([':g' => $adminScheduleGroupId]);
        if ((int) $stmt->fetchColumn() === 0) {
            $adminScheduleGroupId = 0;
        }
    }
    if ($adminScheduleGroupId === 0) {
        // Первая группа текущего года, иначе первая вообще.
        if ($adminCurrentYear !== null) {
            $stmt = $pdo->prepare(
                'SELECT g.id FROM groups g WHERE g.academic_year_id = :y ORDER BY g.name LIMIT 1'
            );
            $stmt->execute([':y' => (int) $adminCurrentYear['id']]);
            $defaultScheduleGroupId = (int) $stmt->fetchColumn();
        }
        if ($defaultScheduleGroupId === 0 && $adminAllGroups !== []) {
            $defaultScheduleGroupId = (int) $adminAllGroups[0]['id'];
        }
        $adminScheduleGroupId = $defaultScheduleGroupId;
    }

    if ($adminScheduleGroupId > 0) {
        // Группа (для заголовка).
        $stmt = $pdo->prepare(
            'SELECT g.*, ay.name AS year_name
             FROM groups g JOIN academic_years ay ON ay.id = g.academic_year_id
             WHERE g.id = :g'
        );
        $stmt->execute([':g' => $adminScheduleGroupId]);
        $adminScheduleGroup = $stmt->fetch() ?: null;

        // Предметы группы (для селектов).
        $stmt = $pdo->prepare(
            'SELECT s.id, s.short_name, s.full_name,
                    sm.name AS semester_name, sm.is_practice AS semester_is_practice
             FROM subjects s
             JOIN semesters sm ON sm.id = s.semester_id
             WHERE s.group_id = :g
             ORDER BY sm.ord, sm.name, s.short_name'
        );
        $stmt->execute([':g' => $adminScheduleGroupId]);
        $adminScheduleSubjects = $stmt->fetchAll();

        // Записи расписания группы.
        $stmt = $pdo->prepare(
            'SELECT se.id, se.weekday, se.lesson_num, se.position,
                    se.subject_id, se.lesson_type,
                    s.short_name AS subject_short_name
             FROM schedule_entries se
             JOIN subjects s ON s.id = se.subject_id
             WHERE se.group_id = :g
             ORDER BY se.lesson_num, se.weekday, se.position'
        );
        $stmt->execute([':g' => $adminScheduleGroupId]);
        $adminScheduleEntries = $stmt->fetchAll();
    }
}