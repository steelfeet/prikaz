<?php
declare(strict_types=1);

$__cfg = $GLOBALS['PRIKAZ_CONFIG'] ?? null;
if (!is_array($__cfg)) {
    http_response_code(500);
    exit('Bootstrap: config не загружен.');
}

define('PRIKAZ_ROOT',         $GLOBALS['PRIKAZ_APP_ROOT'] ?? dirname(__DIR__));
define('PRIKAZ_CONFIG_PATH',  (string) ($GLOBALS['PRIKAZ_CONFIG_PATH'] ?? ''));
define('PRIKAZ_APP_NAME',     $__cfg['app_name']);
define('PRIKAZ_DB_PATH',      $__cfg['db_path']);
define('PRIKAZ_STORAGE',      $__cfg['storage_path']);
define('PRIKAZ_PASSWORD_MIN', (int) $__cfg['password_min']);
define('PRIKAZ_CSRF_KEY',     $__cfg['csrf_key']);
$__session_name = (string) ($__cfg['session_name'] ?? 'prikaz_session');

unset($__cfg);

if (!is_file(PRIKAZ_DB_PATH)) {
    http_response_code(500);
    error_log('PRIKAZ: БД не найдена: ' . PRIKAZ_DB_PATH);
    exit('База данных не инициализирована. Запустите: php prikaz/db/init.php');
}

if (session_status() === PHP_SESSION_NONE) {
    session_name($__session_name);
    session_set_cookie_params([
        'lifetime' => 86400 * 7,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']),
    ]);
    session_start();
}
unset($__session_name);

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    $pdo = new PDO('sqlite:' . PRIKAZ_DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA busy_timeout = 5000');
    $pdo->exec('PRAGMA journal_mode = WAL');
    return $pdo;
}

function csrf_token(): string
{
    if (empty($_SESSION[PRIKAZ_CSRF_KEY])) {
        $_SESSION[PRIKAZ_CSRF_KEY] = bin2hex(random_bytes(32));
    }
    return $_SESSION[PRIKAZ_CSRF_KEY];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="'
        . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function csrf_verify(): void
{
    $token = $_POST['_csrf'] ?? '';
    if (!is_string($token) || $token === '' || !hash_equals(csrf_token(), $token)) {
        http_response_code(419);
        exit('CSRF-токен недействителен. Обновите страницу.');
    }
}

function get_user_roles(int $userId): array
{
    $stmt = db()->prepare(
        'SELECT r.code FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = :id ORDER BY r.id'
    );
    $stmt->execute([':id' => $userId]);
    return array_column($stmt->fetchAll(), 'code');
}

/**
 * Аутентификация.
 * Пароль администратора (login = admin_login из config.php) — по config.php.
 * Все остальные — по users.password_hash.
 */
function auth_attempt(string $login, string $password): ?array
{
    $pdo   = db();
    $login = trim($login);

    $logPath = dirname(PRIKAZ_DB_PATH) . '/diag.log';
    $log = function (string $msg) use ($logPath, $login): void {
        @file_put_contents(
            $logPath,
            '[' . date('Y-m-d H:i:s') . '] login=' . $login . ' ' . $msg . PHP_EOL,
            FILE_APPEND
        );
    };

    $stmt = $pdo->prepare('SELECT * FROM users WHERE login = :l AND is_active = 1 LIMIT 1');
    $stmt->execute([':l' => $login]);
    $user = $stmt->fetch();
    if (!$user) {
        $log('user not found or inactive → return null');
        return null;
    }

    $cfg       = prikaz_config();
    $cfgAdmin  = (string) ($cfg['admin_login'] ?? '');
    $cfgPassLn = strlen((string) ($cfg['admin_password'] ?? ''));
    $isAdmin   = prikaz_is_admin_login($login);

    $log('cfg.admin_login="' . $cfgAdmin . '" cfg.pass_len=' . $cfgPassLn
        . ' cfg.keys=' . implode(',', array_keys($cfg))
        . ' is_admin=' . ($isAdmin ? '1' : '0'));

    if ($isAdmin) {
        if (!prikaz_check_admin_password($password)) {
            $log('admin password mismatch, input_len=' . strlen($password));
            return null;
        }
        $log('admin password OK');
    } else {
        if (!password_verify($password, (string) $user['password_hash'])) {
            $log('password_verify failed (non-admin)');
            return null;
        }
        $log('password_verify OK (non-admin)');
        if (password_needs_rehash((string) $user['password_hash'], PASSWORD_DEFAULT)) {
            $pdo->prepare('UPDATE users SET password_hash = :h WHERE id = :id')
                ->execute([':h' => password_hash($password, PASSWORD_DEFAULT), ':id' => (int) $user['id']]);
        }
    }

    session_regenerate_id(true);
    $_SESSION['user_id']        = (int) $user['id'];
    $_SESSION['user_login']     = $user['login'];
    $_SESSION['user_full_name'] = $user['full_name'];
    $_SESSION['user_roles']     = get_user_roles((int) $user['id']);

    $pdo->prepare('UPDATE users SET last_login_at = :t WHERE id = :id')
        ->execute([':t' => date('Y-m-d H:i:s'), ':id' => (int) $user['id']]);
    audit_log((int) $user['id'], 'login', 'user', (int) $user['id']);
    $log('auth OK');
    return $user;
}

function auth_logout(): void
{
    if (!empty($_SESSION['user_id'])) {
        audit_log((int) $_SESSION['user_id'], 'logout', 'user', (int) $_SESSION['user_id']);
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function auth_user(): ?array
{
    if (empty($_SESSION['user_id'])) return null;
    $stmt = db()->prepare('SELECT * FROM users WHERE id = :id AND is_active = 1 LIMIT 1');
    $stmt->execute([':id' => (int) $_SESSION['user_id']]);
    $user = $stmt->fetch();
    if (!$user) return null;
    $user['roles'] = get_user_roles((int) $user['id']);
    return $user;
}

function auth_require(): array
{
    $u = auth_user();
    if ($u === null) { header('Location: index.php?page=login'); exit; }
    return $u;
}

function auth_require_role(string ...$roles): array
{
    $u = auth_require();
    foreach ($roles as $r) {
        if (in_array($r, $u['roles'], true)) return $u;
    }
    http_response_code(403);
    exit('Доступ запрещён.');
}

function audit_log(int $userId, string $action, ?string $targetType = null, ?int $targetId = null, ?string $meta = null): void
{
    try {
        db()->prepare(
            'INSERT INTO audit_log (user_id, action, target_type, target_id, meta, ip)
             VALUES (:u, :a, :tt, :ti, :m, :ip)'
        )->execute([
            ':u'  => $userId,
            ':a'  => $action,
            ':tt' => $targetType,
            ':ti' => $targetId,
            ':m'  => $meta,
            ':ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    } catch (Throwable $e) {}
}

require_once __DIR__ . '/utils.php';
require_once __DIR__ . '/journal.php';
require_once __DIR__ . '/projects.php';
require_once __DIR__ . '/schedule.php';
require_once __DIR__ . '/ktp.php';
require_once __DIR__ . '/curriculum.php';
require PRIKAZ_ROOT . '/lib/students.php';
