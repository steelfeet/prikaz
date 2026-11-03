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
}