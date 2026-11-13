<?php
declare(strict_types=1);

/**
 * POST-диспетчер. Маршрутизирует $action в lib/actions/<file>.php.
 * Каждый файл — набор обработчиков одной доменной области.
 *
 * ВАЖНО: файл не должен заканчиваться на ?>.
 */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
csrf_verify();
$pdo = db();

// Точные action'ы
$exact = [
    'logout'               => 'auth.php',
    'change_own_password'  => 'auth.php',

    'grade_set'            => 'journal.php',
    'journal_mark_set'     => 'journal.php',
    'journal_lesson_note_set' => 'journal.php',

    'project_move_phase'   => 'projects.php',
    'student_profile_save' => 'students.php',
    'help_feedback'        => 'misc.php',
];

// Префиксы — обрабатывают несколько action'ов одного домена
$prefixes = [
    'admin_academic_year_' => 'academic.php',
    'admin_group_'         => 'academic.php',
    'admin_semester_'      => 'academic.php',
    'admin_holiday_'       => 'academic.php',

    'admin_subject_'       => 'subjects.php',
    'admin_curriculum_'    => 'subjects.php',
    'admin_ktp_'           => 'subjects.php',

    'admin_schedule_'      => 'schedule.php',

    'admin_user_'          => 'users.php',

    'admin_student_'       => 'students.php',
    'admin_parent_'        => 'students.php',

    'admin_lesson_'        => 'lessons.php',
    'admin_project_'       => 'projects.php',

    'journal_'             => 'journal.php',
];

$file = null;

if (isset($exact[$action])) {
    $file = $exact[$action];
} else {
    foreach ($prefixes as $prefix => $f) {
        if (str_starts_with($action, $prefix)) {
            $file = $f;
            break;
        }
    }
}

if ($file !== null) {
    require __DIR__ . '/actions/' . $file;
}

redirect('index.php');