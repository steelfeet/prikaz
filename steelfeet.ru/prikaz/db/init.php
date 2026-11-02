<?php
declare(strict_types=1);

/**
 * Инициализация БД Приказа.
 *
 * Работает и по CLI, и по HTTP.
 *
 * CLI:
 *   php prikaz/db/init.php
 *   php prikaz/db/init.php --reset
 *   PRIKAZ_CONFIG=/path/to/config.php php prikaz/db/init.php
 *
 * HTTP:
 *   GET  /prikaz/db/init.php              — создать, если БД ещё нет
 *   GET  /prikaz/db/init.php?reset=1      — страница подтверждения сброса
 *   POST /prikaz/db/init.php?reset=1      — сброс + создание
 *
 * Защита от случайного сноса:
 *   — без ?reset=1 файл ничего не удаляет;
 *   — сброс через HTTP выполняется только методом POST;
 *   — если БД уже инициализирована и нет запроса на сброс, файл выходит
 *     с сообщением, не трогая данные.
 *
 * ⚠ Пароль администратора хранится константой ниже. После первого входа
 *   смените его через раздел «Профиль». Для продакшена удалите этот файл
 *   с сервера или закройте доступ к нему в .htaccess.
 */

// =============================================================================
// Пароль администратора — СМЕНИТЬ ПЕРЕД ДЕПЛОЕМ
// =============================================================================
const ADMIN_LOGIN    = 'admin';
const ADMIN_PASSWORD = 'Admin#2026!PriKaz';
const ADMIN_FULLNAME = 'Администратор';

// =============================================================================
// Окружение
// =============================================================================
$isCli  = (PHP_SAPI === 'cli');
$isPost = (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST');

// =============================================================================
// Загрузка конфига (config.php лежит вне сайта, в prikaz-data/)
// =============================================================================
$configCandidates = [
    getenv('PRIKAZ_CONFIG') ?: '',
    dirname(__DIR__, 3) . '/prikaz-data/config.php',
    dirname(__DIR__, 2) . '/../prikaz-data/config.php',
    dirname(__DIR__, 2) . '/prikaz-data/config.php',
];
$configPath = '';
foreach ($configCandidates as $cand) {
    if ($cand !== '' && is_file($cand)) { $configPath = $cand; break; }
}

if ($configPath === '') {
    $msg = "config.php не найден.\nИскали:\n  " . implode("\n  ", array_filter($configCandidates))
         . "\nЗадайте переменную окружения PRIKAZ_CONFIG=/полный/путь/config.php";
    init_fail($msg, $isCli);
}
$cfg = require $configPath;

// =============================================================================
// Вспомогательные функции вывода
// =============================================================================
function init_head(string $title): void
{
    ?><!DOCTYPE html>
<html lang="ru"><head><meta charset="utf-8">
<title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></title>
<style>
    body{font-family:-apple-system,Inter,sans-serif;max-width:760px;margin:40px auto;padding:0 16px;color:#1a1a1a;line-height:1.55;}
    h1{font-size:1.4rem;font-weight:600;margin-bottom:8px;}
    h2{font-size:1.05rem;font-weight:500;margin:24px 0 8px;border-bottom:1px solid #ddd;padding-bottom:4px;}
    .box{border-left:3px solid #8b6914;background:#faf8f3;padding:12px 16px;margin:16px 0;}
    .box--danger{border-left-color:#dc2626;background:#fef2f2;}
    .box--success{border-left-color:#16a34a;background:#f0fdf4;}
    code{background:#f3f3f3;padding:1px 6px;border-radius:3px;font-family:ui-monospace,Menlo,monospace;font-size:0.9em;}
    .summary{width:100%;border-collapse:collapse;font-size:0.85rem;margin-top:12px;}
    .summary td,.summary th{border:1px solid #e5e5e5;padding:4px 10px;text-align:left;}
    .summary th{background:#f5f5f5;font-weight:600;}
    .btn{display:inline-block;padding:10px 20px;background:#8b6914;color:#fff;text-decoration:none;border:none;cursor:pointer;font-size:0.9rem;font-weight:500;margin-right:8px;}
    .btn:hover{background:#a67c00;}
    .btn--ghost{background:transparent;color:#4a4a4a;border:1px solid #ccc;}
    .btn--ghost:hover{background:#f3f3f3;}
    .muted{color:#777;font-size:0.82rem;}
    .big{font-size:1.6rem;font-weight:600;color:#16a34a;margin-bottom:16px;}
    .err{color:#b91c1c;font-weight:500;}
</style>
</head><body>
<?php
}

function init_foot(bool $isCli): void
{
    if ($isCli) return;
    ?></body></html><?php
}

function init_fail(string $message, bool $isCli, int $code = 1): void
{
    if ($isCli) {
        fwrite(STDERR, $message . "\n");
        exit($code);
    }
    http_response_code(500);
    init_head('Ошибка инициализации');
    echo '<h1 class="err">Ошибка инициализации</h1>';
    echo '<div class="box box--danger"><pre style="white-space:pre-wrap;margin:0;font-family:inherit;">'
        . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</pre></div>';
    init_foot($isCli);
    exit;
}

function init_out(string $msg, bool $isCli): void
{
    if ($isCli) {
        echo $msg . "\n";
    } else {
        echo $msg . "<br>\n";
    }
}

// =============================================================================
// Путь к БД
// =============================================================================
$dbPath  = $cfg['db_path'];
$dataDir = dirname($dbPath);

// Если БД уже есть — проверяем, инициализирована ли она
$dbExists = is_file($dbPath);
$dbInitialized = false;
$usersCount = 0;
if ($dbExists) {
    try {
        $checkPdo = new PDO('sqlite:' . $dbPath);
        $checkPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $checkPdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $tables = $checkPdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='users'")->fetchAll();
        if (!empty($tables)) {
            $dbInitialized = true;
            $usersCount = (int) $checkPdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        }
    } catch (Throwable $e) {
        // Не инициализирована или повреждена — попробуем пересоздать
        $dbInitialized = false;
    }
}

// =============================================================================
// Логика сброса
// =============================================================================
$resetRequested = $isCli
    ? in_array('--reset', $_SERVER['argv'] ?? [], true)
    : (isset($_GET['reset']) && $_GET['reset'] === '1');

// HTTP: GET ?reset=1 без POST — показать страницу подтверждения
if (!$isCli && $resetRequested && !$isPost) {
    init_head('Сброс БД Приказа');
    echo '<h1>Сброс базы данных</h1>';
    echo '<div class="box box--danger">';
    echo '<strong>Внимание.</strong> Эта операция удалит текущую БД со всеми пользователями, оценками, проектами и историей. Отменить нельзя.';
    echo '</div>';
    echo '<form method="post" action="init.php?reset=1" style="margin-top:24px;">';
    echo '<button type="submit" class="btn">Да, сбросить и создать заново</button>';
    echo '<a href="init.php" class="btn btn--ghost">Отмена</a>';
    echo '</form>';
    echo '<p class="muted" style="margin-top:24px;">После сброса в БД будет создан администратор: <code>'
        . htmlspecialchars(ADMIN_LOGIN, ENT_QUOTES, 'UTF-8') . '</code>. Пароль — из константы в этом файле.</p>';
    init_foot($isCli);
    exit;
}

// Если БД инициализирована и сброса не запрошено — ничего не делать
if ($dbInitialized && !$resetRequested) {
    if ($isCli) {
        echo "БД уже инициализирована: {$dbPath}\n";
        echo "Пользователей: {$usersCount}\n";
        echo "Для сброса: php prikaz/db/init.php --reset\n";
        exit(0);
    }
    init_head('БД уже инициализирована');
    echo '<h1>БД уже инициализирована</h1>';
    echo '<div class="box box--success">';
    echo '<strong>Файл БД существует и содержит данные.</strong><br>';
    echo 'Путь: <code>' . htmlspecialchars($dbPath, ENT_QUOTES, 'UTF-8') . '</code><br>';
    echo 'Пользователей в системе: <strong>' . (int) $usersCount . '</strong>';
    echo '</div>';
    echo '<p style="margin-top:16px;">';
    echo '<a href="../index.php?page=login" class="btn">Перейти ко входу</a>';
    echo '<a href="init.php?reset=1" class="btn btn--ghost">Сбросить БД</a>';
    echo '</p>';
    echo '<p class="muted" style="margin-top:24px;">';
    echo 'Если вы обновляете версию Приказа и нужны новые таблицы — сначала сделайте бэкап, затем сбросьте. ';
    echo 'После сброса смените пароль администратора через раздел «Профиль».';
    echo '</p>';
    init_foot($isCli);
    exit;
}

// =============================================================================
// Создание директории для БД
// =============================================================================
if (!is_dir($dataDir)) {
    if (!@mkdir($dataDir, 0775, true) && !is_dir($dataDir)) {
        init_fail("Не удалось создать директорию для БД: {$dataDir}", $isCli);
    }
}
if (!is_writable($dataDir)) {
    init_fail("Нет прав на запись в директорию БД: {$dataDir}\n"
        . "Проверьте права: chmod 775 " . escapeshellarg($dataDir)
        . "\nВладелец директории должен совпадать с пользователем веб-сервера.", $isCli);
}

// =============================================================================
// Удаление старой БД (при reset)
// =============================================================================
if ($resetRequested && is_file($dbPath)) {
    if (!@unlink($dbPath)) {
        init_fail("Не удалось удалить старую БД: {$dbPath}", $isCli);
    }
    foreach ([$dbPath . '-wal', $dbPath . '-shm'] as $f) {
        if (is_file($f)) @unlink($f);
    }
    if ($isCli) {
        echo "→ Удалён старый файл: {$dbPath}\n";
    }
}

// =============================================================================
// Открытие БД
// =============================================================================
try {
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA journal_mode = WAL');
} catch (Throwable $e) {
    init_fail('Не удалось открыть БД: ' . $e->getMessage(), $isCli);
}

if (!$isCli) {
    init_head('Инициализация БД Приказа');
    echo '<h1>Инициализация БД Приказа</h1>';
    echo '<p class="muted">Файл: <code>' . htmlspecialchars($dbPath, ENT_QUOTES, 'UTF-8') . '</code></p>';
    echo '<h2>Ход инициализации</h2>';
} else {
    echo "→ БД: {$dbPath}\n\n";
}

// =============================================================================
// Схема
// =============================================================================
$pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS roles (
    id    INTEGER PRIMARY KEY AUTOINCREMENT,
    code  TEXT NOT NULL UNIQUE,
    name  TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS users (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    login         TEXT NOT NULL UNIQUE COLLATE NOCASE,
    full_name     TEXT NOT NULL,
    email         TEXT,
    password_hash TEXT NOT NULL,
    is_active     INTEGER NOT NULL DEFAULT 1,
    created_at    TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at    TEXT NOT NULL DEFAULT (datetime('now')),
    last_login_at TEXT
);

CREATE TABLE IF NOT EXISTS user_roles (
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    role_id INTEGER NOT NULL REFERENCES roles(id) ON DELETE CASCADE,
    PRIMARY KEY (user_id, role_id)
);

CREATE TABLE IF NOT EXISTS teachers (
    id        INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id   INTEGER NOT NULL UNIQUE REFERENCES users(id) ON DELETE CASCADE,
    full_name TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS students (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id    INTEGER NOT NULL UNIQUE REFERENCES users(id) ON DELETE CASCADE,
    full_name  TEXT NOT NULL,
    group_name TEXT NOT NULL,
    alias      TEXT UNIQUE,
    is_active  INTEGER NOT NULL DEFAULT 1
);

CREATE TABLE IF NOT EXISTS parents (
    id        INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id   INTEGER NOT NULL UNIQUE REFERENCES users(id) ON DELETE CASCADE,
    full_name TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS parent_student (
    parent_id  INTEGER NOT NULL REFERENCES parents(id) ON DELETE CASCADE,
    student_id INTEGER NOT NULL REFERENCES students(id) ON DELETE CASCADE,
    PRIMARY KEY (parent_id, student_id)
);

CREATE TABLE IF NOT EXISTS lessons (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    ord        INTEGER NOT NULL UNIQUE,
    date_str   TEXT NOT NULL,
    topic      TEXT NOT NULL,
    homework   TEXT NOT NULL DEFAULT '',
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS grades (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    lesson_id  INTEGER NOT NULL REFERENCES lessons(id) ON DELETE CASCADE,
    student_id INTEGER NOT NULL REFERENCES students(id) ON DELETE CASCADE,
    mark       TEXT NOT NULL,
    note       TEXT,
    updated_by INTEGER REFERENCES users(id),
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at TEXT NOT NULL DEFAULT (datetime('now')),
    UNIQUE (lesson_id, student_id)
);

CREATE INDEX IF NOT EXISTS idx_grades_student ON grades(student_id);
CREATE INDEX IF NOT EXISTS idx_grades_lesson  ON grades(lesson_id);

CREATE TABLE IF NOT EXISTS project_phases (
    id    INTEGER PRIMARY KEY AUTOINCREMENT,
    code  TEXT NOT NULL UNIQUE,
    num   INTEGER NOT NULL UNIQUE,
    name  TEXT NOT NULL,
    color TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS projects (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    title       TEXT NOT NULL,
    type        TEXT NOT NULL,
    parent_id   INTEGER REFERENCES projects(id) ON DELETE SET NULL,
    repo_url    TEXT,
    alias       TEXT,
    created_by  INTEGER REFERENCES users(id),
    created_at  TEXT NOT NULL DEFAULT (datetime('now')),
    is_archived INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS project_students (
    project_id INTEGER NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
    student_id INTEGER NOT NULL REFERENCES students(id) ON DELETE CASCADE,
    PRIMARY KEY (project_id, student_id)
);

CREATE TABLE IF NOT EXISTS project_history (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    project_id INTEGER NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
    phase_id   INTEGER NOT NULL REFERENCES project_phases(id),
    note       TEXT,
    moved_at   TEXT NOT NULL DEFAULT (datetime('now')),
    moved_by   INTEGER REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS parent_visits (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    parent_id  INTEGER NOT NULL REFERENCES parents(id) ON DELETE CASCADE,
    visit_date TEXT NOT NULL,
    visited_at TEXT NOT NULL DEFAULT (datetime('now')),
    UNIQUE (parent_id, visit_date)
);

CREATE INDEX IF NOT EXISTS idx_parent_visits_parent ON parent_visits(parent_id, visit_date);

CREATE TABLE IF NOT EXISTS audit_log (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id     INTEGER REFERENCES users(id),
    action      TEXT NOT NULL,
    target_type TEXT,
    target_id   INTEGER,
    meta        TEXT,
    ip          TEXT,
    created_at  TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE INDEX IF NOT EXISTS idx_audit_user   ON audit_log(user_id);
CREATE INDEX IF NOT EXISTS idx_audit_action ON audit_log(action);

CREATE TABLE IF NOT EXISTS help_articles (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    slug            TEXT NOT NULL UNIQUE,
    title           TEXT NOT NULL,
    body_md         TEXT NOT NULL,
    role_visibility TEXT NOT NULL DEFAULT 'all'
);

CREATE TABLE IF NOT EXISTS help_feedback (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    article_id  INTEGER NOT NULL REFERENCES help_articles(id) ON DELETE CASCADE,
    user_id     INTEGER REFERENCES users(id),
    body        TEXT NOT NULL,
    is_resolved INTEGER NOT NULL DEFAULT 0,
    created_at  TEXT NOT NULL DEFAULT (datetime('now'))
);
SQL);

init_out('✓ Схема создана', $isCli);

// =============================================================================
// Роли
// =============================================================================
$roles = [
    [1, 'admin',   'Администратор'],
    [2, 'teacher', 'Преподаватель'],
    [3, 'student', 'Студент'],
    [4, 'parent',  'Родитель'],
];
$stmt = $pdo->prepare('INSERT OR IGNORE INTO roles (id, code, name) VALUES (:id, :c, :n)');
foreach ($roles as [$id, $code, $name]) $stmt->execute([':id' => $id, ':c' => $code, ':n' => $name]);
init_out('✓ Роли: ' . count($roles), $isCli);

// =============================================================================
// Фазы проектов
// =============================================================================
$phases = [
    [1, 'topic',   1, 'Выбор темы',             '#8b6914'],
    [2, 'work',    2, 'Работа над проектом',    '#2563eb'],
    [3, 'predef',  3, 'Предварительная защита', '#d97706'],
    [4, 'defense', 4, 'Защита проекта',         '#16a34a'],
];
$stmt = $pdo->prepare('INSERT OR IGNORE INTO project_phases (id, code, num, name, color) VALUES (:id, :c, :n, :name, :color)');
foreach ($phases as [$id, $code, $num, $name, $color]) {
    $stmt->execute([':id' => $id, ':c' => $code, ':n' => $num, ':name' => $name, ':color' => $color]);
}
init_out('✓ Фазы проектов: ' . count($phases), $isCli);

// =============================================================================
// Администратор
// =============================================================================
$stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE login = :l');
$stmt->execute([':l' => ADMIN_LOGIN]);
$adminCreated = false;

if ((int) $stmt->fetchColumn() === 0) {
    $pdo->prepare(
        'INSERT INTO users (login, full_name, password_hash, is_active) VALUES (:l, :n, :h, 1)'
    )->execute([
        ':l' => ADMIN_LOGIN,
        ':n' => ADMIN_FULLNAME,
        ':h' => password_hash(ADMIN_PASSWORD, PASSWORD_DEFAULT),
    ]);
    $adminId = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO user_roles (user_id, role_id) VALUES (:u, 1)')
        ->execute([':u' => $adminId]);
    $adminCreated = true;
    init_out('✓ Администратор создан: <code>' . ADMIN_LOGIN . '</code>', $isCli);
} else {
    init_out('→ Администратор <code>' . ADMIN_LOGIN . '</code> уже существует', $isCli);
}

// =============================================================================
// Help-статьи
// =============================================================================
$help = [
    ['prikaz-journal', 'Как работает журнал',
     "Журнал показывает оценки и посещаемость по всем занятиям.\n\nПреподаватель: клик по ячейке — поставить или изменить оценку, оставить заметку.\nСтудент: только свои оценки и заметки.\nРодитель: только оценки своего ребёнка.",
     'all'],
    ['prikaz-projects', 'Как устроены проекты',
     "Проект проходит 4 фазы: Выбор темы → Работа над проектом → Предварительная защита → Защита.\n\nНад одним проектом может работать несколько студентов.\nУ одного студента может быть несколько проектов.\nВозврат к предыдущей фазе — нормальный процесс доработки.",
     'all'],
    ['prikaz-aliases', 'Псевдонимы и персональные данные',
     "В репозиториях (Gogs, GitHub) используются только псевдонимы вида s-<группа>-<номер>.\n\nРасшифровка «псевдоним → ФИО» хранится только в Приказе. Это требование 152-ФЗ.\n\nРаздел «Студенты» в админке показывает соответствие.",
     'admin'],
    ['prikaz-admin', 'Админ: как управлять пользователями',
     "Только администратор может создавать пользователей.\n\n1. Раздел «Пользователи».\n2. «Создать» — логин, ФИО, роль, временный пароль.\n3. Передать пароль пользователю.\n4. Смена пароля — кнопка «Сбросить».",
     'admin'],
];
$stmt = $pdo->prepare('INSERT OR IGNORE INTO help_articles (slug, title, body_md, role_visibility) VALUES (:s, :t, :b, :r)');
foreach ($help as [$slug, $title, $body, $role]) {
    $stmt->execute([':s' => $slug, ':t' => $title, ':b' => $body, ':r' => $role]);
}
init_out('✓ Help-статьи: ' . count($help), $isCli);

// =============================================================================
// Сводка
// =============================================================================
$tables = [
    'roles', 'users', 'user_roles', 'teachers', 'students', 'parents',
    'lessons', 'grades', 'project_phases', 'projects', 'project_students',
    'project_history', 'parent_visits', 'audit_log', 'help_articles', 'help_feedback',
];
$counts = [];
foreach ($tables as $t) {
    try {
        $counts[$t] = (int) $pdo->query("SELECT COUNT(*) FROM {$t}")->fetchColumn();
    } catch (Throwable $e) {
        $counts[$t] = 0;
    }
}

if ($isCli) {
    echo "\nИтог:\n";
    foreach ($counts as $t => $n) {
        printf("  %-22s %d\n", $t, $n);
    }
    echo "\nГотово.\n";
    echo "  Вход: /prikaz/index.php?page=login\n";
    echo "  Админ: " . ADMIN_LOGIN . " / " . ADMIN_PASSWORD . "\n";
    echo "  ⚠ Смените пароль после первого входа!\n";
    echo "  ⚠ Удалите или закройте init.php после инициализации!\n";
    exit(0);
}

// HTTP-результат
?>
<div class="box box--success big">✓ Готово</div>

<h2>Сводка по таблицам</h2>
<table class="summary">
    <thead><tr><th>Таблица</th><th>Записей</th></tr></thead>
    <tbody>
    <?php foreach ($counts as $t => $n): ?>
        <tr><td><code><?= htmlspecialchars($t, ENT_QUOTES, 'UTF-8') ?></code></td><td><?= (int) $n ?></td></tr>
    <?php endforeach; ?>
    </tbody>
</table>

<h2>Учётная запись администратора</h2>
<div class="box">
    <p style="margin:0;">
        Логин: <code><?= htmlspecialchars(ADMIN_LOGIN, ENT_QUOTES, 'UTF-8') ?></code><br>
        Пароль: <code><?= htmlspecialchars(ADMIN_PASSWORD, ENT_QUOTES, 'UTF-8') ?></code>
        <?php if ($adminCreated): ?><br><span class="muted">(пароль только что создан)</span><?php endif; ?>
    </p>
</div>

<div class="box box--danger">
    <strong>Обязательно сделайте два действия прямо сейчас:</strong>
    <ol style="margin:8px 0 0 20px;padding:0;">
        <li>Перейдите ко входу и <strong>смените пароль</strong> администратора через «Профиль».</li>
        <li><strong>Закройте init.php от повторного вызова</strong> — либо удалите файл с сервера,
            либо верните <code>Require all denied</code> в <code>prikaz/db/.htaccess</code>
            (сейчас там стоит исключение для этого файла).</li>
    </ol>
</div>

<p style="margin-top:24px;">
    <a href="../index.php?page=login" class="btn">Перейти ко входу</a>
</p>

<p class="muted" style="margin-top:32px;">
    Дальнейшие шаги: создайте преподавателя, студентов (с группой и псевдонимом), родителей — в разделе «Пользователи» от имени администратора.
</p>

<?php
init_foot($isCli);