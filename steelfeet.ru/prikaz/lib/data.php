<?php
declare(strict_types=1);

/**
 * Загрузка справочников, mock-данных, агрегатов дашборда и метаданных документов.
 * Подключается из index.php после auth+actions. Ожидает $pdo и $currentUser.
 */

// ===== Справочники из БД =====
$universities = $pdo->query('SELECT id, name FROM universities ORDER BY id')->fetchAll();
$products     = $pdo->query('SELECT id, name FROM it_products ORDER BY id')->fetchAll();
$phases       = $pdo->query('SELECT id, code, num, name, color FROM interaction_phases ORDER BY num')->fetchAll();
$interactions = $pdo->query('SELECT id, university_id, product_id, phase_id FROM interactions')->fetchAll();

$phaseMap = [];
$interactionIdMap = [];
foreach ($interactions as $row) {
    $phaseMap[(int) $row['university_id']][(int) $row['product_id']] =
        $row['phase_id'] !== null ? (int) $row['phase_id'] : null;
    $interactionIdMap[(int) $row['university_id']][(int) $row['product_id']] = (int) $row['id'];
}

$phaseById = [];
$phaseByNum = [];
foreach ($phases as &$p) {
    $p['requires_confirmation'] = phase_requires_confirmation((int) $p['num']);
    $phaseById[(int) $p['id']] = $p;
    $phaseByNum[(int) $p['num']] = $p;
}
unset($p);

$phaseMeta = [];
foreach ($phases as $p) {
    $phaseMeta[] = [
        'id'                    => (int) $p['id'],
        'num'                   => (int) $p['num'],
        'name'                  => $p['name'],
        'color'                 => $p['color'],
        'requires_confirmation' => (bool) $p['requires_confirmation'],
    ];
}

// ===== Декорация справочников =====
$universityMarks = [
    1  => ['code' => 'К(П)ФУ', 'crest' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/8/85/Seal_of_KFU.svg/60px-Seal_of_KFU.svg.png'],
    2  => ['code' => 'МФТИ',   'crest' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/2/2c/Mipt_logo.svg/60px-Mipt_logo.svg.png'],
    3  => ['code' => 'МИФИ',   'crest' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/e/e5/MEI_logo.svg/60px-MEI_logo.svg.png'],
    4  => ['code' => 'СПбГУ',  'crest' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/3/3a/Seal_of_Saint_Petersburg_State_University.svg/60px-Seal_of_Saint_Petersburg_State_University.svg.png'],
    5  => ['code' => 'НГУ',    'crest' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/1/1c/Novosibirsk_State_University_logo.svg/60px-Novosibirsk_State_University_logo.svg.png'],
    6  => ['code' => 'УрФУ',   'crest' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/9/96/Ural_Federal_University_Logo.svg/60px-Ural_Federal_University_Logo.svg.png'],
    7  => ['code' => 'ЮФУ',    'crest' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/9/98/Southern_Federal_University_logo.svg/60px-Southern_Federal_University_logo.svg.png'],
    8  => ['code' => 'ДВФУ',   'crest' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/1/11/Far_Eastern_Federal_University_Logo.svg/60px-Far_Eastern_Federal_University_Logo.svg.png'],
    9  => ['code' => 'ТПУ',    'crest' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/1/1e/TPU_logo.svg/60px-TPU_logo.svg.png'],
    10 => ['code' => 'КНИТУ-КАИ', 'crest' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/8/83/Kazan_National_Research_Technical_University_logo.svg/60px-Kazan_National_Research_Technical_University_logo.svg.png'],
];

$productShortNames = [
    'Курсы по использованию ИИ в обучении'               => 'ИИ в обучении',
    'Основы Data Science для преподавателей'             => 'Data Science',
    'Кибергигиена и информационная безопасность'         => 'Инфобез',
    'Облачные технологии: практика применения'           => 'Облака',
    'Разработка на Python: базовый курс'                 => 'Python',
    'Машинное обучение в задачах телекома'               => 'ML в телекоме',
    'Цифровая трансформация образования'                 => 'Цифр. трансформация',
    'Программирование для школьников'                    => 'Прогиб для школ',
    'Аналитика данных: визуализация и отчётность'        => 'Аналитика данных',
    'IoT: умные устройства и сети'                       => 'IoT',
    'ИТ-программа «Большие данные и ИИ»'                 => 'БД и ИИ',
    'ИТ-программа «Информационная безопасность»'         => 'Инфобез (прогр.)',
    'ИТ-программа «Облачные вычисления и DevOps»'        => 'Cloud & DevOps',
    'ИТ-программа «Сети связи и телеком-системы»'        => 'Сети и телеком',
    'ИТ-программа «Цифровые платформы и лоу-код разработка»' => 'Цифр. платформы',
];

foreach ($universities as &$university) {
    $mark = $universityMarks[(int) $university['id']] ?? null;
    $university['short'] = $mark !== null ? $mark['code'] : $university['name'];
    $university['crest'] = $mark !== null ? $mark['crest'] : null;
    $university['initial'] = uni_initial($university['name']);
}
unset($university);

foreach ($products as &$product) {
    $product['short'] = $productShortNames[$product['name']] ?? $product['name'];
}
unset($product);

// ===== Workflow =====
$workflowSteps = [
    ['num' => 1,  'name' => 'Поиск контактов ответственного в вузе',   'side' => 'internal'],
    ['num' => 2,  'name' => 'Коммуникация и уточнение актуальности программ', 'side' => 'both'],
    ['num' => 3,  'name' => 'Организация встречи',                     'side' => 'both'],
    ['num' => 4,  'name' => 'Обмен документами',                       'side' => 'both'],
    ['num' => 5,  'name' => 'Корректировка документов',                'side' => 'both'],
    ['num' => 6,  'name' => 'Подписание документов',                   'side' => 'both'],
    ['num' => 7,  'name' => 'Передача материалов, лицензий, документации', 'side' => 'external'],
    ['num' => 8,  'name' => 'Сопровождение внедрения',                 'side' => 'both'],
    ['num' => 9,  'name' => 'Обучение преподавателей',                 'side' => 'both'],
    ['num' => 10, 'name' => 'Актуализация учебной программы',          'side' => 'both'],
    ['num' => 11, 'name' => 'Ведение занятий',                         'side' => 'external'],
    ['num' => 12, 'name' => 'Актуализация документации',               'side' => 'both'],
    ['num' => 13, 'name' => 'Повышение квалификации преподавателей',   'side' => 'both'],
    ['num' => 14, 'name' => 'Контроль исполнения',                     'side' => 'internal'],
];

$stepStates = [
    'completed'        => ['label' => 'Завершён',        'color' => '#16a34a', 'fill' => 100],
    'in_progress'      => ['label' => 'В работе',        'color' => '#2563eb', 'fill' => 60],
    'waiting_internal' => ['label' => 'Ждёт ИТ Школу',   'color' => '#8b6914', 'fill' => 40],
    'waiting_external' => ['label' => 'Ждёт вуз',        'color' => '#d97706', 'fill' => 40],
    'needs_revision'   => ['label' => 'Требует доработки', 'color' => '#dc2626', 'fill' => 25],
    'pending'          => ['label' => 'Ожидает',         'color' => '#d1d5db', 'fill' => 0],
];

$currentStates = ['in_progress', 'waiting_external', 'needs_revision'];

$sideLabels = [
    'internal' => 'Внутренний контур — ИТ Школа РТК',
    'external' => 'Внешний контур — вуз',
    'both'     => 'Совместный шаг — ИТ Школа РТК + вуз',
];

// ===== Текущий проект =====
$projectOptions = $pdo->query(
    'SELECT i.id, i.phase_id, i.university_id, i.product_id,
            u.name AS university_name, p.name AS product_name
     FROM interactions i
     JOIN universities u ON u.id = i.university_id
     JOIN it_products  p ON p.id = i.product_id
     ORDER BY i.id'
)->fetchAll();

$shortByUniversityId = [];
foreach ($universities as $university) $shortByUniversityId[(int) $university['id']] = $university['short'];
$shortByProductId = [];
foreach ($products as $product) $shortByProductId[(int) $product['id']] = $product['short'];

foreach ($projectOptions as &$opt) {
    $opt['title'] = $opt['university_name'] . ' · ' . $opt['product_name'];
    $opt['short'] =
        ($shortByUniversityId[(int) $opt['university_id']] ?? $opt['university_name'])
        . ' · ' . ($shortByProductId[(int) $opt['product_id']] ?? $opt['product_name']);
    $opt['phase_num'] = ($opt['phase_id'] !== null && isset($phaseById[(int) $opt['phase_id']]))
        ? (int) $phaseById[(int) $opt['phase_id']]['num'] : 0;
    $opt['phase'] = ($opt['phase_id'] !== null) ? ($phaseById[(int) $opt['phase_id']] ?? null) : null;
}
unset($opt);

$currentProjectId = 0;
foreach ($projectOptions as $opt) {
    if ((int) $opt['id'] === (int) ($_GET['project'] ?? 0)) { $currentProjectId = (int) $opt['id']; break; }
}
if ($currentProjectId === 0 && $projectOptions !== []) $currentProjectId = (int) $projectOptions[0]['id'];

$currentProject = ['id' => $currentProjectId, 'title' => 'нет активных проектов', 'short' => '—', 'phase' => null];
$currentPhaseNum = 0;
foreach ($projectOptions as $opt) {
    if ((int) $opt['id'] === $currentProjectId) {
        $currentProject['title'] = $opt['university_name'] . ' · ' . $opt['product_name'];
        $currentProject['short'] = $opt['short'];
        $currentProject['phase'] = $opt['phase'];
        $currentPhaseNum = (int) $opt['phase_num'];
        break;
    }
}

$pendingChange = $_SESSION['pending_phase_change'] ?? null;
$pendingByProject = [];
if ($pendingChange !== null) $pendingByProject[(int) $pendingChange['project_id']] = $pendingChange;

$workflow = build_project_workflow($workflowSteps, $sideLabels, $stepStates, $currentStates, $currentPhaseNum, $currentProjectId);

// ===== Фазы и типы проектов =====
$studentPhases = [
    ['code' => 'topic',   'num' => 1, 'name' => 'Выбор темы',             'color' => '#8b6914'],
    ['code' => 'work',    'num' => 2, 'name' => 'Работа над проектом',     'color' => '#2563eb'],
    ['code' => 'predef',  'num' => 3, 'name' => 'Предварительная защита',  'color' => '#d97706'],
    ['code' => 'defense', 'num' => 4, 'name' => 'Защита проекта',          'color' => '#16a34a'],
];
$studentPhaseByNum = [];
foreach ($studentPhases as $sp) $studentPhaseByNum[(int) $sp['num']] = $sp;

$projectTypes = [
    'code'        => ['label' => 'Код-проект',   'color' => '#16a34a', 'icon' => '💻'],
    'research'    => ['label' => 'Исследование', 'color' => '#7c3aed', 'icon' => '🔬'],
    'interactive' => ['label' => 'Интерактив',   'color' => '#2563eb', 'icon' => '🎮'],
    'report'      => ['label' => 'Доклад',       'color' => '#8b6914', 'icon' => '📄'],
    'design'      => ['label' => 'Дизайн',       'color' => '#d97706', 'icon' => '🎨'],
];

// ===== Mock-студенты и проекты =====
$mockStudents = [
    ['id' => 1, 'name' => 'Иванов Иван Иванович',       'group' => 'ИУ7-41Б'],
    ['id' => 2, 'name' => 'Петрова Анна Сергеевна',     'group' => 'ИУ7-41Б'],
    ['id' => 3, 'name' => 'Сидоров Пётр Алексеевич',    'group' => 'ИУ7-42Б'],
    ['id' => 4, 'name' => 'Кузнецова Мария Дмитриевна', 'group' => 'ИУ7-42Б'],
    ['id' => 5, 'name' => 'Смирнов Олег Викторович',    'group' => 'ИУ7-43Б'],
    ['id' => 6, 'name' => 'Волкова Ольга Игоревна',     'group' => 'ИУ7-43Б'],
    ['id' => 7, 'name' => 'Никитин Артём Павлович',     'group' => 'ИУ7-44Б'],
    ['id' => 8, 'name' => 'Морозова Дарья Ивановна',    'group' => 'ИУ7-44Б'],
    ['id' => 9, 'name' => 'Федотов Кирилл Андреевич',   'group' => 'ИУ7-45Б'],
    ['id' => 10,'name' => 'Романова Елизавета Олеговна','group' => 'ИУ7-45Б'],
];
$studentById = [];
foreach ($mockStudents as $s) $studentById[(int) $s['id']] = $s;

$mockProjects = [
    ['id' => 1, 'title' => 'ML-модель для телекома', 'type' => 'code', 'students' => [1, 2, 4], 'history' => [
        ['num' => 1, 'date' => '02.09', 'note' => 'Выбор темы и постановка задачи'],
        ['num' => 2, 'date' => '09.09', 'note' => 'Начало работы, сбор данных'],
        ['num' => 3, 'date' => '23.09', 'note' => 'Предзащита: замечания по методологии'],
        ['num' => 2, 'date' => '30.09', 'note' => 'Возврат: доработка методологии'],
        ['num' => 3, 'date' => '07.10', 'note' => 'Повторная предзащита — принято'],
    ], 'git' => [
        ['hash' => 'a3f9c12', 'date' => '20.09 14:23', 'author' => 'Иванов И.И.',    'message' => 'Добавлен раздел «Введение»'],
        ['hash' => 'b7e2a45', 'date' => '19.09 18:07', 'author' => 'Петрова А.С.',   'message' => 'Постановка задачи и цели работы'],
        ['hash' => 'c1d4f88', 'date' => '19.09 11:52', 'author' => 'Преподаватель',  'message' => 'Ревью: рекомендации по структуре'],
        ['hash' => 'd92b8e1', 'date' => '18.09 20:31', 'author' => 'Кузнецова М.Д.', 'message' => 'Инициализация репозитория и README'],
    ]],
    ['id' => 2, 'title' => 'Аналитика оттока клиентов', 'type' => 'research', 'students' => [3], 'history' => [
        ['num' => 1, 'date' => '02.09', 'note' => 'Выбор темы: аналитика оттока'],
        ['num' => 2, 'date' => '09.09', 'note' => 'Сбор и обработка данных'],
        ['num' => 3, 'date' => '23.09', 'note' => 'Предзащита — хорошо'],
    ], 'git' => [
        ['hash' => 'f1a2b3c', 'date' => '15.09 10:14', 'author' => 'Сидоров П.А.', 'message' => 'Обзор литературы'],
        ['hash' => 'g4h5i6j', 'date' => '12.09 16:40', 'author' => 'Сидоров П.А.', 'message' => 'Первичный анализ датасета'],
    ]],
    ['id' => 3, 'title' => 'Чат-бот поддержки на LLM', 'type' => 'code', 'students' => [5, 6], 'history' => [
        ['num' => 1, 'date' => '02.09', 'note' => 'Выбор темы: чат-бот на LLM'],
        ['num' => 2, 'date' => '09.09', 'note' => 'Прототип и интеграция с API'],
        ['num' => 3, 'date' => '23.09', 'note' => 'Предзащита'],
        ['num' => 4, 'date' => '30.09', 'note' => 'Защита проекта — отлично'],
    ], 'git' => [
        ['hash' => 'k1l2m3n', 'date' => '22.09 11:08', 'author' => 'Смирнов О.В.',  'message' => 'MVP чат-бота'],
        ['hash' => 'o4p5q6r', 'date' => '21.09 17:32', 'author' => 'Волкова О.И.',  'message' => 'Интеграция с LLM API'],
    ]],
    ['id' => 4, 'title' => 'Дашборд качества связи', 'type' => 'interactive', 'students' => [7], 'history' => [
        ['num' => 1, 'date' => '02.09', 'note' => 'Выбор темы: дашборд качества'],
        ['num' => 2, 'date' => '09.09', 'note' => 'Прототип и визуализация'],
        ['num' => 3, 'date' => '23.09', 'note' => 'Предзащита — принято'],
    ], 'git' => [
        ['hash' => 's7t8u9v', 'date' => '20.09 09:15', 'author' => 'Никитин А.П.', 'message' => 'Прототип дашборда'],
    ]],
    ['id' => 5, 'title' => 'Классификация обращений', 'type' => 'code', 'students' => [8, 9], 'history' => [
        ['num' => 1, 'date' => '02.09', 'note' => 'Выбор темы'],
        ['num' => 2, 'date' => '09.09', 'note' => 'Начало работы'],
        ['num' => 3, 'date' => '23.09', 'note' => 'Предзащита'],
        ['num' => 2, 'date' => '30.09', 'note' => 'Возврат: доработка признаков'],
    ], 'git' => [
        ['hash' => 'w1x2y3z', 'date' => '19.09 14:52', 'author' => 'Морозова Д.И.', 'message' => 'Базовая модель классификации'],
    ]],
    ['id' => 6, 'title' => 'Рекомендации тарифов', 'type' => 'report', 'students' => [10, 1], 'history' => [
        ['num' => 1, 'date' => '05.09', 'note' => 'Выбор темы: рекомендации тарифов'],
    ], 'git' => []],
    ['id' => 7, 'title' => 'Юзабилити-аудит личного кабинета', 'type' => 'design', 'students' => [3, 5], 'history' => [
        ['num' => 1, 'date' => '06.09', 'note' => 'Согласование темы'],
        ['num' => 2, 'date' => '13.09', 'note' => 'Сбор метрик и интервью'],
    ], 'git' => []],
];

$projectById = [];
$projectsByStudent = [];
foreach ($mockProjects as $proj) {
    $projectById[(int) $proj['id']] = $proj;
    foreach ($proj['students'] as $sid) {
        $projectsByStudent[(int) $sid][] = (int) $proj['id'];
    }
}

// ===== Журнал =====
$journalDates = ['03.09', '05.09', '10.09', '12.09', '17.09', '19.09', '24.09', '26.09', '01.10', '03.10'];

$journalLessons = [
    0 => ['topic' => 'Введение в машинное обучение',       'homework' => 'Прочитать главу 1, ответить на вопросы'],
    1 => ['topic' => 'Линейная регрессия',                 'homework' => 'Реализовать линейную регрессию с нуля'],
    2 => ['topic' => 'Логистическая регрессия',            'homework' => 'Разобрать примеры, выполнить задание 2'],
    3 => ['topic' => 'Деревья решений',                    'homework' => 'Построить дерево на датасете Iris'],
    4 => ['topic' => 'Ансамбли: Random Forest',            'homework' => 'Сравнить Random Forest и одиночное дерево'],
    5 => ['topic' => 'Градиентный бустинг',                'homework' => 'Применить XGBoost на учебном датасете'],
    6 => ['topic' => 'Кластеризация: K-means',             'homework' => 'Кластеризовать клиентов по RFM'],
    7 => ['topic' => 'PCA и снижение размерности',         'homework' => 'Применить PCA к датасету, визуализировать'],
    8 => ['topic' => 'Метрики качества',                   'homework' => 'Рассчитать precision/recall на своём проекте'],
    9 => ['topic' => 'Семинар: разбор проектов',           'homework' => 'Подготовить отчёт по проекту'],
];

$journalMarks = [
    1  => ['5', '·', '4', '·', '5', '·', '4', 'н', '5', '·'],
    2  => ['·', '5', '·', '5', '4', '·', '5', '5', '·', '4'],
    3  => ['4', '4', '·', '·', 'н', '4', '5', '5', '5', '5'],
    4  => ['5', '·', '5', '5', '·', '·', '4', '4', 'н', '5'],
    5  => ['·', '·', 'н', '4', '·', '4', '·', '·', '4', '4'],
    6  => ['4', '5', '5', '·', '5', '5', '·', '4', '5', '·'],
    7  => ['5', '·', '·', '4', '5', 'н', '5', '5', '·', '5'],
    8  => ['·', '4', '4', '·', '·', '5', '4', 'н', '·', '4'],
    9  => ['3', '·', 'н', '·', '4', '·', '·', '4', '4', '·'],
    10 => ['4', '5', '·', '5', '5', '4', '5', '5', '·', '5'],
];

$journalNotes = [
    1  => [1 => 'Просил разобрать подробнее линейную регрессию', 8 => 'Сильно вырос, хвалить'],
    3  => [4 => 'Не был по уважительной причине, отработает',     5 => 'Отвечал на семинаре'],
    5  => [2 => 'Похвалить за активность',                        3 => 'Пропуск — предупредил заранее'],
    7  => [5 => 'Нужна помощь с бустингом, назначить консультацию'],
    8  => [7 => 'Спросить, почему не сдал задание'],
];

$journalSummary = aggregate_journal($journalMarks);
$avgGrade     = $journalSummary['avg'];
$totalAbsent  = $journalSummary['absent'];
$totalPresent = $journalSummary['present'];
$attendance   = $journalSummary['attendance'];

$perStudentSummary = [];
foreach ($mockStudents as $st) {
    $sid = (int) $st['id'];
    $marks = $journalMarks[$sid] ?? [];
    $sSum = 0; $sCount = 0; $sAbsent = 0; $sPresent = 0;
    $sDist = [5 => 0, 4 => 0, 3 => 0, 2 => 0];
    foreach ($marks as $m) {
        if ($m === 'н') {
            $sAbsent++;
        } elseif ($m === '5' || $m === '4' || $m === '3' || $m === '2') {
            $g = (int) $m;
            $sSum += $g; $sCount++;
            $sDist[$g] = ($sDist[$g] ?? 0) + 1;
            $sPresent++;
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

// ===== Активность родителей =====
$parentVisitsMock = [
    1  => ['count' => 14, 'last' => '20.09', 'trend' => '+3', 'unread_bad' => 0],
    2  => ['count' => 8,  'last' => '18.09', 'trend' => '+1', 'unread_bad' => 0],
    3  => ['count' => 19, 'last' => '21.09', 'trend' => '+5', 'unread_bad' => 1],
    4  => ['count' => 6,  'last' => '15.09', 'trend' => '-2', 'unread_bad' => 1],
    5  => ['count' => 3,  'last' => '10.09', 'trend' => '0',  'unread_bad' => 2],
    6  => ['count' => 11, 'last' => '19.09', 'trend' => '+2', 'unread_bad' => 0],
    7  => ['count' => 16, 'last' => '20.09', 'trend' => '+4', 'unread_bad' => 0],
    8  => ['count' => 4,  'last' => '12.09', 'trend' => '-1', 'unread_bad' => 2],
    9  => ['count' => 2,  'last' => '06.09', 'trend' => '0',  'unread_bad' => 1],
    10 => ['count' => 13, 'last' => '21.09', 'trend' => '+3', 'unread_bad' => 0],
];

// ===== Дашборд =====
$dashPhaseCounts = [];
foreach ($phases as $p) $dashPhaseCounts[(int) $p['id']] = 0;
$dashUniCounts = [];
foreach ($universities as $u) $dashUniCounts[(int) $u['id']] = 0;
$dashUniMaxPhaseNum = [];
$dashUniPhaseCount = [];
$totalInteractions = 0;
$activeInteractions = 0;
$sumPhase = 0;

foreach ($interactions as $row) {
    $totalInteractions++;
    $pid = $row['phase_id'] !== null ? (int) $row['phase_id'] : 0;
    $uid = (int) $row['university_id'];
    if ($pid > 0 && isset($dashPhaseCounts[$pid])) $dashPhaseCounts[$pid]++;
    if (isset($dashUniCounts[$uid])) $dashUniCounts[$uid]++;
    $num = ($pid > 0 && isset($phaseById[$pid])) ? (int) $phaseById[$pid]['num'] : 0;
    $sumPhase += $num;
    if ($num > 0 && $num < 14) $activeInteractions++;
    if (!isset($dashUniMaxPhaseNum[$uid]) || $num > $dashUniMaxPhaseNum[$uid]) $dashUniMaxPhaseNum[$uid] = $num;
    if ($pid > 0) {
        if (!isset($dashUniPhaseCount[$uid])) $dashUniPhaseCount[$uid] = [];
        $dashUniPhaseCount[$uid][$pid] = ($dashUniPhaseCount[$uid][$pid] ?? 0) + 1;
    }
}

$avgPhase = $totalInteractions > 0 ? round($sumPhase / $totalInteractions, 1) : 0;
$chartPhases = array_values(array_filter($phases, function ($p) { return (int) $p['num'] > 0; }));

$topUniversities = [];
foreach ($universities as $u) {
    $uid = (int) $u['id'];
    $topUniversities[] = ['id' => $uid, 'short' => $u['short'], 'full' => $u['name'], 'count' => $dashUniCounts[$uid] ?? 0];
}
usort($topUniversities, function ($a, $b) { return $b['count'] - $a['count']; });
$topUniversities = array_slice($topUniversities, 0, 8);

$monthsRu = ['Янв','Фев','Мар','Апр','Май','Июн','Июл','Авг','Сен','Окт','Ноя','Дек'];
$nowMonth = (int) date('n');
$chartNewProjectsByMonth = [];
$monthly = [];
for ($i = 11; $i >= 0; $i--) {
    $m = $nowMonth - $i;
    while ($m <= 0) $m += 12;
    $seed = ($i + 3) * 41;
    $byUniPhase = [];
    foreach ($universities as $u) {
        $uid = (int) $u['id'];
        $byUniPhase[(string) $uid] = [];
        foreach ($chartPhases as $p) {
            $pid = (int) $p['id'];
            $base = ($seed * 7 + $uid * 11 + $pid * 13) % 4;
            $recency = (12 - $i);
            $val = $base + (int) ($recency / 4);
            if ($val > 0) $byUniPhase[(string) $uid][(string) $pid] = $val;
        }
    }
    $chartNewProjectsByMonth[] = 1 + ($seed % 4);
    $monthly[] = ['label' => $monthsRu[$m - 1], 'byUniPhase' => $byUniPhase];
}

$mapViewWidth  = 1000;
$mapViewHeight = 500;
$lngMin = 19.0;  $lngMax = 180.0;
$latMin = 41.0;  $latMax = 82.0;

$universityCoords = [
    1  => [55.7906, 49.1221], 2  => [55.9297, 37.5213], 3  => [55.6497, 37.6642],
    4  => [59.8822, 29.8258], 5  => [54.8473, 83.0930], 6  => [56.8439, 60.6526],
    7  => [47.2225, 39.7188], 8  => [43.1155, 131.8855], 9 => [56.4653, 84.9508], 10 => [55.8337, 49.1254],
];

$mapPoints = [];
foreach ($universities as $u) {
    $uid = (int) $u['id'];
    if (!isset($universityCoords[$uid])) continue;
    $maxNum   = $dashUniMaxPhaseNum[$uid] ?? 0;
    $maxPhase = $maxNum > 0 ? ($phaseByNum[$maxNum] ?? null) : null;
    $color    = $maxPhase !== null ? $maxPhase['color'] : '#9ca3af';
    [$sx, $sy] = project_point($universityCoords[$uid][0], $universityCoords[$uid][1], $mapViewWidth, $mapViewHeight, $lngMin, $lngMax, $latMin, $latMax);
    $mapPoints[] = [
        'id'       => $uid, 'name' => $u['name'], 'short' => $u['short'],
        'x'        => $sx, 'y' => $sy, 'color' => $color,
        'phase'    => $maxPhase !== null ? $maxPhase['name'] : 'Не начато',
        'projects' => $dashUniCounts[$uid] ?? 0,
    ];
}

$dashPayload = [
    'phases' => array_map(function ($p) {
        return ['id' => (int) $p['id'], 'num' => (int) $p['num'], 'name' => $p['name'], 'color' => $p['color']];
    }, $chartPhases),
    'universities' => array_map(function ($u) {
        return ['id' => $u['id'], 'short' => $u['short'], 'name' => $u['full']];
    }, $topUniversities),
    'allUniversities' => array_map(function ($u) {
        return ['id' => (int) $u['id'], 'short' => $u['short'], 'name' => $u['name']];
    }, $universities),
    'mapPoints'   => $mapPoints,
    'monthly'     => $monthly,
    'newProjects' => $chartNewProjectsByMonth,
];

// ===== Уведомления и подпись =====
$universityNotification = $_SESSION['university_notification'] ?? null;
$managerNotification = $_SESSION['manager_notification'] ?? null;
if ($managerNotification !== null) unset($_SESSION['manager_notification']);

$managerLabel = $currentUser !== null ? ($currentUser['full_name'] ?: $currentUser['login']) : 'Менеджер';

// ===== Документы: счётчик «на мне» для бейджа в nav =====
$docsInboxCount = 0;
$docsByInteraction = [];
$docSideForNav = doc_side_for_role($viewRole);
if ($docSideForNav !== null) {
    try {
        $docsInboxCount = doc_count_inbox($pdo, $docSideForNav);
        $stmt = $pdo->query(
            "SELECT interaction_id, COUNT(*) AS cnt
             FROM documents
             WHERE status NOT IN ('archived', 'expired')
             GROUP BY interaction_id"
        );
        foreach ($stmt->fetchAll() as $row) {
            $docsByInteraction[(string) (int) $row['interaction_id']] = (int) $row['cnt'];
        }
    } catch (Throwable $e) {
        // таблица ещё не создана — тихо игнорируем
    }
}