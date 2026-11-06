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
$adminSemestersByYear = [];
$defaultSubjectGroupId = 0;
$defaultSubjectSemesterId = 0;
$adminCurrentYearSubjectCount = 0;

// Расписание
$adminScheduleYearId = 0;
$adminScheduleSemesters = [];
$adminScheduleSemesterId = 0;
$adminScheduleSemesterName = '';
$adminScheduleSubjects = [];
$adminScheduleEntries = [];

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

    $holidaysBySemester = [];
    try {
        $rows = $pdo->query(
            'SELECT h.* FROM holidays h ORDER BY h.date ASC'
        )->fetchAll();
        foreach ($rows as $h) {
            $holidaysBySemester[(int) $h['semester_id']][] = $h;
        }
    } catch (Throwable $e) {}

    foreach ($adminAllSemesters as $sm) {
        $yearId  = (int) $sm['academic_year_id'];
        $smId    = (int) $sm['id'];
        $sm['holidays'] = $holidaysBySemester[$smId] ?? [];
        $adminSemestersByYear[$yearId][] = $sm;
    }

    // ===== Предметы =====
    if (isset($_GET['subject_year'])) {
        $adminSubjectsFilterYear = (int) $_GET['subject_year'];
    } elseif ($adminCurrentYear !== null) {
        $adminSubjectsFilterYear = (int) $adminCurrentYear['id'];
    } elseif ($adminAcademicYears !== []) {
        $adminSubjectsFilterYear = (int) $adminAcademicYears[0]['id'];
    } else {
        $adminSubjectsFilterYear = 0;
    }
    $adminSubjectsFilterGroup    = isset($_GET['subject_group'])    ? (int) $_GET['subject_group']    : 0;
    $adminSubjectsFilterSemester = isset($_GET['subject_semester']) ? (int) $_GET['subject_semester'] : 0;

    $sql = 'SELECT s.*,
                   sm.name        AS semester_name,
                   sm.is_practice AS semester_is_practice,
                   sm.ord         AS semester_ord,
                   ay.id          AS academic_year_id,
                   ay.name        AS year_name,
                   ay.is_current  AS year_is_current,
                   g.name         AS group_name
            FROM subjects s
            JOIN semesters sm      ON sm.id = s.semester_id
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

    // ===== Темы и КТП для каждого предмета =====
    if ($adminSubjects !== []) {
        $subjectIds   = array_map(fn($s) => (int) $s['id'], $adminSubjects);
        $placeholders = implode(',', array_fill(0, count($subjectIds), '?'));

        $stmt = $pdo->prepare(
            "SELECT id, subject_id, ord, topic
             FROM curriculum
             WHERE subject_id IN ($placeholders)
             ORDER BY subject_id, ord"
        );
        $stmt->execute($subjectIds);
        $topicsBySubject = [];
        foreach ($stmt->fetchAll() as $t) {
            $topicsBySubject[(int) $t['subject_id']][] = $t;
        }

        $stmt = $pdo->prepare(
            "SELECT id, subject_id, ord, topic
             FROM ktp_entries
             WHERE subject_id IN ($placeholders)
             ORDER BY subject_id, ord, id"
        );
        $stmt->execute($subjectIds);
        $ktpBySubject = [];
        foreach ($stmt->fetchAll() as $k) {
            $ktpBySubject[(int) $k['subject_id']][] = $k;
        }

        foreach ($adminSubjects as &$subj) {
            $sid = (int) $subj['id'];
            $subj['topics'] = $topicsBySubject[$sid] ?? [];
            $subj['ktp']    = $ktpBySubject[$sid] ?? [];

            $blocks = ktp_lesson_blocks($sid, $pdo);
            $subj['ktp_blocks_count'] = count($blocks);
            $subj['ktp_tariff']       = ktp_tariff_total($blocks);

            if ($subj['ktp'] !== []) {
                $subj['ktp_layout'] = ktp_layout($sid, $pdo, $blocks);
                $subj['ktp_hours']  = ktp_hours_from_layout($subj['ktp_layout']);
            } else {
                $subj['ktp_layout'] = [];
                $subj['ktp_hours']  = 0;
            }
        }
        unset($subj);
    }

    $adminAllGroups = $pdo->query(
        'SELECT g.id, g.name, g.academic_year_id, ay.name AS year_name
         FROM groups g
         JOIN academic_years ay ON ay.id = g.academic_year_id
         ORDER BY ay.start_date DESC, g.name'
    )->fetchAll();

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

    // ===== Расписание: год + семестр, все группы =====
    $adminScheduleYearId = (int) ($_GET['schedule_year'] ?? 0);
    if ($adminScheduleYearId <= 0) {
        if ($adminCurrentYear !== null) {
            $adminScheduleYearId = (int) $adminCurrentYear['id'];
        } elseif ($adminAcademicYears !== []) {
            $adminScheduleYearId = (int) $adminAcademicYears[0]['id'];
        }
    }

    foreach ($adminAllSemesters as $sm) {
        if ((int) $sm['academic_year_id'] === $adminScheduleYearId) {
            $adminScheduleSemesters[] = $sm;
        }
    }

    $adminScheduleSemesterId = (int) ($_GET['schedule_semester'] ?? 0);
    $semValid = false;
    foreach ($adminScheduleSemesters as $sm) {
        if ((int) $sm['id'] === $adminScheduleSemesterId) { $semValid = true; break; }
    }
    if (!$semValid) {
        $adminScheduleSemesterId = $adminScheduleSemesters !== []
            ? (int) $adminScheduleSemesters[0]['id']
            : 0;
    }

    foreach ($adminScheduleSemesters as $sm) {
        if ((int) $sm['id'] === $adminScheduleSemesterId) {
            $adminScheduleSemesterName = (string) $sm['name'];
            break;
        }
    }

    if ($adminScheduleSemesterId > 0) {
        $stmt = $pdo->prepare(
            'SELECT s.id, s.short_name, s.full_name,
                    sm.name AS semester_name, sm.is_practice AS semester_is_practice,
                    g.id AS group_id, g.name AS group_name
             FROM subjects s
             JOIN semesters sm ON sm.id = s.semester_id
             JOIN groups g     ON g.id = s.group_id
             WHERE s.semester_id = :sm
             ORDER BY g.name, s.short_name'
        );
        $stmt->execute([':sm' => $adminScheduleSemesterId]);
        $adminScheduleSubjects = $stmt->fetchAll();
    }

    if ($adminScheduleSemesterId > 0) {
        $stmt = $pdo->prepare(
            'SELECT se.id, se.weekday, se.lesson_num, se.position,
                    se.subject_id, se.lesson_type,
                    s.short_name AS subject_short_name,
                    s.group_id,
                    g.name AS group_name
             FROM schedule_entries se
             JOIN subjects s ON s.id = se.subject_id
             JOIN groups g   ON g.id = s.group_id
             WHERE s.semester_id = :sm
             ORDER BY se.lesson_num, se.weekday, se.position'
        );
        $stmt->execute([':sm' => $adminScheduleSemesterId]);
        $adminScheduleEntries = $stmt->fetchAll();
    }
}