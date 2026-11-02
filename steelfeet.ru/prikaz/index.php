<?php
declare(strict_types=1);

/**
 * Точка входа Приказа. Единственный PHP, доступный по HTTP.
 * Папка сайта: /prikaz/. Конфиг и БД — вне сайта, на уровень выше.
 */

$configCandidates = [
    getenv('PRIKAZ_CONFIG') ?: '',
    dirname(__DIR__, 2) . '/prikaz-data/config.php',  // ../.. от /prikaz/ = корень сайта, ещё на уровень выше = prikaz-data
    dirname(__DIR__, 1) . '/../prikaz-data/config.php',
];

$configPath = '';
foreach ($configCandidates as $p) {
    if ($p !== '' && is_file($p)) { $configPath = $p; break; }
}

if ($configPath === '') {
    http_response_code(500);
    error_log('PRIKAZ: config.php не найден. Искали: ' . implode(', ', $configCandidates));
    exit('Конфигурация Приказа не найдена. Проверьте путь к prikaz-data/config.php или env PRIKAZ_CONFIG.');
}

$GLOBALS['PRIKAZ_CONFIG']   = require $configPath;
$GLOBALS['PRIKAZ_APP_ROOT'] = __DIR__;

require __DIR__ . '/lib/bootstrap.php';

$page   = $_GET['page']   ?? 'dashboard';
$action = $_GET['action'] ?? '';

if ($page === 'login') {
    if (auth_user() !== null) redirect('index.php');

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'login') {
        csrf_verify();
        $user = auth_attempt(
            (string) ($_POST['login'] ?? ''),
            (string) ($_POST['password'] ?? '')
        );
        if ($user === null) {
            flash_set('danger', 'Неверный логин или пароль.');
            redirect('index.php?page=login');
        }
        redirect('index.php');
    }

    require PRIKAZ_ROOT . '/lib/views/auth.php';
    exit;
}

$currentUser = auth_require();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require PRIKAZ_ROOT . '/lib/actions.php';
    exit;
}

$availableRoles = $currentUser['roles'];
$viewRole = $_GET['role'] ?? '';
if (!in_array($viewRole, $availableRoles, true)) {
    $viewRole = $availableRoles[0] ?? '';
}
if ($viewRole === '') {
    http_response_code(403);
    exit('У вашей учётной записи нет ролей. Обратитесь к администратору.');
}

if ($page === 'dashboard') {
    $map = ['admin' => 'admin', 'teacher' => 'teacher', 'student' => 'student', 'parent' => 'parent'];
    redirect('index.php?page=' . ($map[$viewRole] ?? 'profile'));
}

if ($page === 'profile') {
    require PRIKAZ_ROOT . '/lib/views/profile.php';
    exit;
}

require PRIKAZ_ROOT . '/lib/data.php';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(PRIKAZ_APP_NAME) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@300;400;500;600&family=Inter:wght@300;400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(asset_url('styles.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset_url('prikaz.css')) ?>">
</head>
<body class="role-<?= e($viewRole) ?>">
    <div class="dashboard">
        <?php require PRIKAZ_ROOT . '/lib/views/topbar.php'; ?>
        <?php require PRIKAZ_ROOT . '/lib/views/nav.php'; ?>
        <main class="content">
            <?php
            switch ($page) {
                case 'teacher': require PRIKAZ_ROOT . '/lib/views/teacher.php'; break;
                case 'student': require PRIKAZ_ROOT . '/lib/views/student.php'; break;
                case 'parent':  require PRIKAZ_ROOT . '/lib/views/parent.php';  break;
                case 'admin':   require PRIKAZ_ROOT . '/lib/views/admin.php';   break;
                default: echo '<div class="uni-empty">Страница не найдена.</div>';
            }
            ?>
        </main>
    </div>

    <?php require PRIKAZ_ROOT . '/lib/views/footer.php'; ?>

    <script src="<?= e(asset_url('common.js')) ?>"></script>
    <?php if ($page === 'teacher' && ($_GET['tab'] ?? 'journal') === 'journal'): ?>
        <script src="<?= e(asset_url('teacher.js')) ?>"></script>
    <?php endif; ?>
</body>
</html>