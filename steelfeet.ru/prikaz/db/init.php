<?php
declare(strict_types=1);

/**
 * Инициализация и миграция БД Приказа.
 * Работает и по CLI, и по HTTP.
 */

$isCli  = (PHP_SAPI === 'cli');
$isPost = (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST');

// =============================================================================
// Поиск config.php
// =============================================================================
$configCandidates = [
    getenv('PRIKAZ_CONFIG') ?: '',
    dirname(__DIR__, 3) . '/prikaz-data/config.php',
    dirname(__DIR__, 2) . '/../prikaz-data/config.php',
    dirname(__DIR__, 2) . '/prikaz-data/config.php',
    dirname(__DIR__, 4) . '/prikaz-data/config.php',
    dirname(__DIR__, 3) . '/../prikaz-data/config.php',
];

$diagnostics = [];
$configPath = '';
foreach ($configCandidates as $cand) {
    if ($cand === '') {
        $diagnostics[] = ['path' => '(env PRIKAZ_CONFIG не задана)', 'exists' => false, 'size' => 0];
        continue;
    }
    $real = realpath($cand) ?: $cand;
    $exists = is_file($real);
    $size = $exists ? filesize($real) : 0;
    $diagnostics[] = ['path' => $real, 'exists' => $exists, 'size' => $size];
    if ($exists && $configPath === '') {
        $configPath = $real;
    }
}

// =============================================================================
// Функции вывода
// =============================================================================
function init_head(string $title): void
{
    ?><!DOCTYPE html>
<html lang="ru"><head><meta charset="utf-8">
<title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></title>
<style>
    body{font-family:-apple-system,Inter,sans-serif;max-width:820px;margin:40px auto;padding:0 16px;color:#1a1a1a;line-height:1.55;}
    h1{font-size:1.4rem;font-weight:600;margin-bottom:8px;}
    h2{font-size:1.05rem;font-weight:500;margin:24px 0 8px;border-bottom:1px solid #ddd;padding-bottom:4px;}
    .box{border-left:3px solid #8b6914;background:#faf8f3;padding:12px 16px;margin:16px 0;}
    .box--danger{border-left-color:#dc2626;background:#fef2f2;}
    .box--success{border-left-color:#16a34a;background:#f0fdf4;}
    code{background:#f3f3f3;padding:1px 6px;border-radius:3px;font-family:ui-monospace,Menlo,monospace;font-size:0.9em;}
    pre{background:#f3f3f3;padding:10px 14px;border-radius:3px;font-family:ui-monospace,Menlo,monospace;font-size:0.82rem;overflow-x:auto;}
    .summary{width:100%;border-collapse:collapse;font-size:0.85rem;margin-top:12px;}
    .summary td,.summary th{border:1px solid #e5e5e5;padding:6px 10px;text-align:left;vertical-align:top;}
    .summary th{background:#f5f5f5;font-weight:600;}
    .summary tr.is-found td{background:#f0fdf4;}
    .summary tr.is-missing td{color:#9ca3af;}
    .btn{display:inline-block;padding:10px 20px;background:#8b6914;color:#fff;text-decoration:none;border:none;cursor:pointer;font-size:0.9rem;font-weight:500;margin-right:8px;}
    .btn:hover{background:#a67c00;}
    .btn--ghost{background:transparent;color:#4a4a4a;border:1px solid #ccc;}
    .btn--ghost:hover{background:#f3f3f3;}
    .muted{color:#777;font-size:0.82rem;}
    .big{font-size:1.6rem;font-weight:600;color:#16a34a;margin-bottom:16px;}
    .err{color:#b91c1c;font-weight:500;}
    .ok{color:#16a34a;font-weight:600;}
    .bad{color:#dc2626;font-weight:600;}
    ul.keys{margin:8px 0 0 20px;padding:0;}
    ul.keys li{padding:2px 0;font-family:ui-monospace,Menlo,monospace;font-size:0.85rem;}
</style>
</head><body>
<?php
}

function init_foot(bool $isCli): void
{
    if ($isCli) return;
    ?></body></html><?php
}

function init_print_config_diagnostics(array $diagnostics, bool $isCli): void
{
    if ($isCli) {
        echo "Поиск config.php:\n";
        foreach ($diagnostics as $d) {
            $mark = $d['exists'] ? '✓ найден' : '✗ нет';
            printf("  [%s] %s%s\n", $mark, $d['path'], $d['exists'] ? ' (' . $d['size'] . ' байт)' : '');
        }
        return;
    }
    echo '<h2>Поиск config.php</h2>';
    echo '<table class="summary"><thead><tr><th style="width:70px;">Статус</th><th>Путь</th><th style="width:90px;">Размер</th></tr></thead><tbody>';
    foreach ($diagnostics as $d) {
        $cls = $d['exists'] ? 'is-found' : 'is-missing';
        $status = $d['exists'] ? '<span class="ok">✓ найден</span>' : '<span class="bad">✗ нет</span>';
        $size = $d['exists'] ? $d['size'] . ' б' : '—';
        echo '<tr class="' . $cls . '"><td>' . $status . '</td><td><code>'
            . htmlspecialchars($d['path'], ENT_QUOTES, 'UTF-8')
            . '</code></td><td>' . $size . '</td></tr>';
    }
    echo '</tbody></table>';
}

function init_print_config_keys(array $cfg, bool $isCli): void
{
    if ($isCli) {
        echo "Ключи в config.php: " . implode(', ', array_keys($cfg)) . "\n";
        return;
    }
    echo '<h2>Ключи, которые есть в config.php</h2>';
    echo '<ul class="keys">';
    foreach (array_keys($cfg) as $k) {
        $v = $cfg[$k];
        if (is_string($v) && in_array($k, ['admin_password'], true)) {
            $v = '(скрыто, длина ' . strlen($v) . ')';
        } elseif (is_scalar($v)) {
            $v = (string) $v;
        } else {
            $v = gettype($v);
        }
        echo '<li><strong>' . htmlspecialchars($k, ENT_QUOTES, 'UTF-8')
            . '</strong> = ' . htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8') . '</li>';
    }
    echo '</ul>';
}

function init_fail(string $message, bool $isCli, int $code = 1, ?callable $extra = null): void
{
    if ($isCli) {
        fwrite(STDERR, $message . "\n");
        if ($extra !== null) $extra();
        exit($code);
    }
    http_response_code(500);
    init_head('Ошибка инициализации');
    echo '<h1 class="err">Ошибка инициализации</h1>';
    echo '<div class="box box--danger"><pre style="white-space:pre-wrap;margin:0;font-family:inherit;">'
        . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</pre></div>';
    if ($extra !== null) $extra();
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
// Если config.php не найден
// =============================================================================
if ($configPath === '') {
    init_fail(
        "config.php не найден ни по одному из путей.\n\n"
        . "Положите файл в один из путей выше или задайте переменную окружения\n"
        . "PRIKAZ_CONFIG=/полный/путь/config.php.\n\n"
        . "Если файл существует — проверьте права на директорию:\n"
        . "PHP-процесс должен иметь доступ на чтение (r-x) ко всем родительским папкам.",
        $isCli,
        1,
        function () use ($diagnostics, $isCli) {
            init_print_config_diagnostics($diagnostics, $isCli);
        }
    );
}

$cfg = require $configPath;

if (!is_array($cfg)) {
    init_fail(
        "Файл config.php найден, но не возвращает массив.\n\n"
        . "Что вернул файл: " . gettype($cfg) . "\n"
        . "Найденный файл: {$configPath}",
        $isCli,
        1,
        function () use ($diagnostics, $isCli) {
            init_print_config_diagnostics($diagnostics, $isCli);
        }
    );
}

$requiredKeys = ['admin_login', 'admin_password', 'db_path'];
$missingKeys = [];
foreach ($requiredKeys as $k) {
    if (!array_key_exists($k, $cfg)) {
        $missingKeys[] = $k;
    }
}

if ($missingKeys !== []) {
    init_fail(
        "В config.php нет обязательных ключей: " . implode(', ', $missingKeys) . "\n\n"
        . "Прочитан файл: {$configPath}\n\n"
        . "Проверьте, что в config.php есть строки:\n"
        . "  'admin_login'    => 'admin',\n"
        . "  'admin_password' => 'ваш_пароль',\n"
        . "  'admin_fullname' => 'Администратор',\n"
        . "  'db_path'        => __DIR__ . '/prikaz.sqlite',\n\n"
        . "Возможные причины:\n"
        . "  1) Файл config.php не тот, который вы редактировали — сверьте путь выше.\n"
        . "  2) Опечатка в имени ключа.\n"
        . "  3) PHP закэшировал старую версию (opcache). Перезапустите PHP-FPM.",
        $isCli,
        1,
        function () use ($cfg, $diagnostics, $isCli) {
            init_print_config_diagnostics($diagnostics, $isCli);
            init_print_config_keys($cfg, $isCli);
        }
    );
}

$adminLogin    = trim((string) $cfg['admin_login']);
$adminPassword = (string) $cfg['admin_password'];
$adminFullname = trim((string) ($cfg['admin_fullname'] ?? 'Администратор'));
if ($adminFullname === '') $adminFullname = 'Администратор';

$argvList = $_SERVER['argv'] ?? [];
$reset = in_array('--reset', $argvList, true);
foreach ($argvList as $a) {
    if (str_starts_with($a, '--admin='))    $adminLogin    = trim(substr($a, 8));
    if (str_starts_with($a, '--password=')) $adminPassword = substr($a, 11);
    if (str_starts_with($a, '--fullname=')) $adminFullname = trim(substr($a, 11));
}

if ($adminLogin === '') {
    init_fail(
        "Ключ 'admin_login' в config.php пустой.\n\nПрочитан файл: {$configPath}",
        $isCli,
        1,
        function () use ($cfg, $diagnostics, $isCli) {
            init_print_config_diagnostics($diagnostics, $isCli);
            init_print_config_keys($cfg, $isCli);
        }
    );
}
if ($adminPassword === '') {
    init_fail(
        "Ключ 'admin_password' в config.php пустой.\n\nПрочитан файл: {$configPath}",
        $isCli,
        1,
        function () use ($cfg, $diagnostics, $isCli) {
            init_print_config_diagnostics($diagnostics, $isCli);
            init_print_config_keys($cfg, $isCli);
        }
    );
}

$minLen = (int) ($cfg['password_min'] ?? 8);
if (strlen($adminPassword) < $minLen) {
    init_fail(
        "Пароль администратора короче {$minLen} символов (password_min в config.php).\n"
        . "Длина текущего пароля: " . strlen($adminPassword),
        $isCli
    );
}

$dbPath  = (string) $cfg['db_path'];
$dataDir = dirname($dbPath);

$resetRequested = $isCli
    ? $reset
    : (isset($_GET['reset']) && $_GET['reset'] === '1');

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
        . htmlspecialchars($adminLogin, ENT_QUOTES, 'UTF-8') . '</code>.</p>';
    init_foot($isCli);
    exit;
}

if (!is_dir($dataDir)) {
    if (!@mkdir($dataDir, 0775, true) && !is_dir($dataDir)) {
        init_fail("Не удалось создать директорию для БД: {$dataDir}", $isCli);
    }
}
if (!is_writable($dataDir)) {
    init_fail(
        "Нет прав на запись в директорию БД: {$dataDir}\n"
        . "Проверьте: chmod 775 " . escapeshellarg($dataDir),
        $isCli
    );
}

if ($resetRequested && is_file($dbPath)) {
    if (!@unlink($dbPath)) {
        init_fail("Не удалось удалить старую БД: {$dbPath}", $isCli);
    }
    foreach ([$dbPath . '-wal', $dbPath . '-shm'] as $f) {
        if (is_file($f)) @unlink($f);
    }
    if ($isCli) echo "→ Удалён старый файл: {$dbPath}\n";
}

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
    echo '<p class="muted">Прочитан config: <code>' . htmlspecialchars($configPath, ENT_QUOTES, 'UTF-8') . '</code></p>';
    echo '<p class="muted">Файл БД: <code>' . htmlspecialchars($dbPath, ENT_QUOTES, 'UTF-8') . '</code></p>';
    echo '<h2>Ход инициализации</h2>';
} else {
    echo "→ Config: {$configPath}\n";
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

CREATE TABLE IF NOT EXISTS academic_years (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    name        TEXT NOT NULL UNIQUE,
    start_date  TEXT NOT NULL,
    end_date    TEXT NOT NULL,
    is_current  INTEGER NOT NULL DEFAULT 0,
    created_at  TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at  TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE UNIQUE INDEX IF NOT EXISTS uq_academic_years_current
ON academic_years(is_current) WHERE is_current = 1;

CREATE TABLE IF NOT EXISTS groups (
    id               INTEGER PRIMARY KEY AUTOINCREMENT,
    academic_year_id INTEGER NOT NULL REFERENCES academic_years(id) ON DELETE CASCADE,
    name             TEXT NOT NULL,
    curator          TEXT,
    notes            TEXT,
    created_at       TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at       TEXT NOT NULL DEFAULT (datetime('now')),
    UNIQUE (academic_year_id, name)
);

CREATE INDEX IF NOT EXISTS idx_groups_year ON groups(academic_year_id);

CREATE TABLE IF NOT EXISTS subjects (
    id                INTEGER PRIMARY KEY AUTOINCREMENT,
    academic_year_id  INTEGER NOT NULL REFERENCES academic_years(id) ON DELETE CASCADE,
    group_id          INTEGER NOT NULL REFERENCES groups(id) ON DELETE CASCADE,
    short_name        TEXT NOT NULL,
    full_name         TEXT NOT NULL,
    hours             INTEGER NOT NULL DEFAULT 0,
    semester_practice TEXT NOT NULL DEFAULT '',
    created_at        TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at        TEXT NOT NULL DEFAULT (datetime('now')),
    UNIQUE (academic_year_id, group_id, short_name, semester_practice)
);

CREATE INDEX IF NOT EXISTS idx_subjects_year  ON subjects(academic_year_id);
CREATE INDEX IF NOT EXISTS idx_subjects_group ON subjects(group_id);

CREATE TABLE IF NOT EXISTS curriculum (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    subject_id INTEGER NOT NULL REFERENCES subjects(id) ON DELETE CASCADE,
    ord        INTEGER NOT NULL,
    topic      TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at TEXT NOT NULL DEFAULT (datetime('now')),
    UNIQUE (subject_id, ord)
);

CREATE INDEX IF NOT EXISTS idx_curriculum_subject ON curriculum(subject_id);

CREATE TABLE IF NOT EXISTS schedule_entries (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    group_id    INTEGER NOT NULL REFERENCES groups(id) ON DELETE CASCADE,
    weekday     INTEGER NOT NULL CHECK(weekday BETWEEN 1 AND 6),
    lesson_num  INTEGER NOT NULL CHECK(lesson_num BETWEEN 1 AND 9),
    position    INTEGER NOT NULL DEFAULT 1,
    subject_id  INTEGER NOT NULL REFERENCES subjects(id) ON DELETE CASCADE,
    lesson_type TEXT NOT NULL DEFAULT 'group',
    created_at  TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at  TEXT NOT NULL DEFAULT (datetime('now')),
    UNIQUE (group_id, weekday, lesson_num, position)
);

CREATE INDEX IF NOT EXISTS idx_schedule_group    ON schedule_entries(group_id);
CREATE INDEX IF NOT EXISTS idx_schedule_weekday  ON schedule_entries(weekday);
CREATE INDEX IF NOT EXISTS idx_schedule_lesson   ON schedule_entries(lesson_num);
SQL);

init_out('✓ Схема применена', $isCli);

// =============================================================================
// Seed
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

$ayCount = (int) $pdo->query('SELECT COUNT(*) FROM academic_years')->fetchColumn();
if ($ayCount === 0) {
    $seedYears = [
        ['2024/2025', '2024-09-01', '2025-06-30', 0],
        ['2025/2026', '2025-09-01', '2026-06-30', 1],
    ];
    $stmt = $pdo->prepare(
        'INSERT OR IGNORE INTO academic_years (name, start_date, end_date, is_current)
         VALUES (:n, :s, :e, :c)'
    );
    foreach ($seedYears as [$n, $s, $e, $c]) {
        $stmt->execute([':n' => $n, ':s' => $s, ':e' => $e, ':c' => $c]);
    }
    init_out('✓ Учебные годы (сид): ' . count($seedYears), $isCli);
} else {
    init_out('→ Учебные годы: ' . $ayCount . ' (сид пропущен)', $isCli);
}

$groupsCount = (int) $pdo->query('SELECT COUNT(*) FROM groups')->fetchColumn();
if ($groupsCount === 0) {
    $yearsByCode = [];
    foreach ($pdo->query('SELECT id, name FROM academic_years')->fetchAll() as $y) {
        $yearsByCode[$y['name']] = (int) $y['id'];
    }
    $seedGroups = [
        ['2024/2025', '45', null],
        ['2024/2025', '35', null],
        ['2025/2026', '46', null],
        ['2025/2026', '36', null],
    ];
    $stmt = $pdo->prepare(
        'INSERT OR IGNORE INTO groups (academic_year_id, name) VALUES (:y, :n)'
    );
    $added = 0;
    foreach ($seedGroups as [$yearName, $groupName, $curator]) {
        if (!isset($yearsByCode[$yearName])) continue;
        $stmt->execute([':y' => $yearsByCode[$yearName], ':n' => $groupName]);
        $added += $stmt->rowCount();
    }
    init_out('✓ Группы (сид): ' . $added, $isCli);
} else {
    init_out('→ Группы: ' . $groupsCount . ' (сид пропущен)', $isCli);
}

$stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE login = :l');
$stmt->execute([':l' => $adminLogin]);
$adminCreated = false;
if ((int) $stmt->fetchColumn() === 0) {
    $pdo->prepare('INSERT INTO users (login, full_name, password_hash, is_active) VALUES (:l, :n, :h, 1)')
        ->execute([
            ':l' => $adminLogin,
            ':n' => $adminFullname,
            ':h' => password_hash($adminPassword, PASSWORD_DEFAULT),
        ]);
    $adminId = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO user_roles (user_id, role_id) VALUES (:u, 1)')
        ->execute([':u' => $adminId]);
    $adminCreated = true;
    init_out('✓ Администратор создан: <code>' . htmlspecialchars($adminLogin, ENT_QUOTES, 'UTF-8') . '</code>', $isCli);
} else {
    init_out('→ Администратор <code>' . htmlspecialchars($adminLogin, ENT_QUOTES, 'UTF-8') . '</code> уже существует', $isCli);
}

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
    ['prikaz-groups', 'Группы и учебный год',
     "Группы принадлежат учебному году. Каждый год — свой набор групп.\n\nГруппа «46» в 2024/2025 и «46» в 2025/2026 — разные сущности. У каждой своя история и свой состав.\n\nПри удалении учебного года все его группы удаляются. Студенты и оценки остаются, но привязка к группе теряется.",
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
    'project_history', 'parent_visits', 'audit_log', 'help_articles',
    'help_feedback', 'academic_years', 'groups', 'subjects', 'curriculum', 'schedule_entries',
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
    echo "  Админ: {$adminLogin}\n";
    echo "  ⚠ Смените пароль после первого входа!\n";
    echo "  ⚠ Удалите или закройте init.php после инициализации!\n";
    exit(0);
}

?>
<div class="box box--success big">✓ Готово</div>

<p class="muted">
    Прочитан config: <code><?= htmlspecialchars($configPath, ENT_QUOTES, 'UTF-8') ?></code>
</p>

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
        Логин: <code><?= htmlspecialchars($adminLogin, ENT_QUOTES, 'UTF-8') ?></code>
        <?php if ($adminCreated): ?><br><span class="muted">(создан только что)</span><?php endif; ?>
    </p>
    <p class="muted" style="margin:8px 0 0;">
        Пароль — <code>admin_password</code> в <code>config.php</code>.
    </p>
</div>

<p style="margin-top:24px;">
    <a href="../index.php?page=admin" class="btn">Перейти в админку</a>
    <a href="../index.php?page=login" class="btn btn--ghost">Ко входу</a>
</p>

<p class="muted" style="margin-top:32px;">
    Схема применяется всегда — новые таблицы добавляются к существующей БД без потери данных.
</p>

<?php
init_foot($isCli);