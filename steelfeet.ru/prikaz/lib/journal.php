<?php
declare(strict_types=1);

/**
 * Журнал (КТП-based).
 *
 * Привязан к предмету (subjects) и типу занятия (group / sub).
 *
 * Модель:
 *   - Урок = слот КТП. Если тема занимает 2 урока в один день, это ДВА урока
 *     и ДВЕ строки в правой вкладке «Темы журнала», даже если тема одна.
 *   - Столбец журнала = ДАТА. На одну дату — одна колонка в сетке, одна
 *     оценка на студента, одно примечание к уроку. Даже если уроков в этот
 *     день два.
 *   - Пагинация строго по УРОКАМ: 35 уроков на страницу для group, 17 для
 *     sub — как строк в бумажном журнале. Из уроков страницы собираются
 *     даты. Если 35-й урок — первый из пары, второй урок этой пары уходит
 *     на следующую страницу, и дата появляется на обеих страницах.
 *     Оценка и примечание к уроку — одни, потому что привязаны к дате.
 *
 * Оценки и примечания к оценкам — journal_marks (subject_id, student_id,
 * date, kind, weight). Примечания к уроку и ДЗ (общие на дату) —
 * journal_lesson_notes (subject_id, date, kind).
 *
 * Вес оценки (weight):
 *   1   — автоматическая проверка (новости, тесты)
 *   3   — самостоятельная, домашняя
 *   5   — контрольная, важное задание
 *   10  — весомая оценка преподавателя (по умолчанию)
 *   В расчёте среднего участвуют только числовые оценки (2–5). `н`, `ну`,
 *   `·` в средневзвешенный не идут (только посещаемость). Минус (`4-`) —
 *   только отображение, на взвешенный не влияет.
 */

function journal_kind_label(string $kind): string
{
    return $kind === 'sub' ? 'Практика' : 'Групповое';
}

function journal_kinds(): array
{
    return ['group', 'sub'];
}

/**
 * Все уроки (слоты) КТП предмета этого вида.
 *
 * Каждый блок КТП разворачивается на slots строк. Если тема занимает 2 урока,
 * в выдаче — две записи с одинаковой датой и темой.
 *
 * @return list<array{date:string,topic:string,kind:string,kind_label:string}>
 */
function journal_lessons(int $subjectId, string $kind, ?PDO $pdo = null): array
{
    if ($pdo === null) $pdo = db();
    $layout = ktp_layout($subjectId, $pdo);
    $out = [];
    foreach ($layout as $row) {
        if (empty($row['placed'])) continue;
        $k = (string) ($row['kind'] ?? '');
        if ($kind === 'group') {
            if ($k !== 'group' && $k !== 'mixed') continue;
        } elseif ($kind === 'sub') {
            if (!str_contains($k, 'sub') && $k !== 'mixed') continue;
        } else {
            continue;
        }
        $slots = max(1, (int) ($row['slots'] ?? 1));
        for ($s = 0; $s < $slots; $s++) {
            $out[] = [
                'date'       => (string) $row['date'],
                'topic'      => (string) $row['topic'],
                'kind'       => $k,
                'kind_label' => journal_kind_label($kind),
            ];
        }
    }
    usort($out, fn($a, $b) => strcmp($a['date'], $b['date']));
    return $out;
}

function journal_page_size(string $kind): int
{
    return $kind === 'sub' ? 17 : 35;
}

/**
 * Уникальные даты из списка уроков, с числом уроков и списком тем на дату.
 *
 * @param list<array{date:string,topic:string}> $lessons
 * @return list<array{date:string,lessons_count:int,topics:list<string>}>
 */
function journal_dates(array $lessons): array
{
    $byDate = [];
    foreach ($lessons as $l) {
        $d = (string) $l['date'];
        if (!isset($byDate[$d])) {
            $byDate[$d] = ['date' => $d, 'lessons_count' => 0, 'topics' => []];
        }
        $byDate[$d]['lessons_count']++;
        $byDate[$d]['topics'][] = (string) $l['topic'];
    }
    ksort($byDate);
    return array_values($byDate);
}

/**
 * Студенты группы предмета.
 */
function journal_students(int $subjectId, ?PDO $pdo = null): array
{
    if ($pdo === null) $pdo = db();
    $stmt = $pdo->prepare(
        'SELECT s.id, s.full_name, s.last_name, s.first_name, s.middle_name, s.group_id
         FROM students s
         JOIN subjects sub ON sub.group_id = s.group_id
         WHERE sub.id = :id AND s.is_active = 1
         ORDER BY s.last_name, s.first_name, s.id'
    );
    $stmt->execute([':id' => $subjectId]);
    return $stmt->fetchAll();
}

/**
 * Оценки по предмету: [student_id][kind][date] = ['mark'=>, 'note'=>, 'weight'=>].
 */
function journal_marks_map(int $subjectId, ?PDO $pdo = null): array
{
    if ($pdo === null) $pdo = db();
    $stmt = $pdo->prepare(
        'SELECT student_id, kind, date, mark, note, weight
         FROM journal_marks WHERE subject_id = :s'
    );
    $stmt->execute([':s' => $subjectId]);
    $map = [];
    foreach ($stmt->fetchAll() as $r) {
        $sid = (int) $r['student_id'];
        $k   = (string) $r['kind'];
        $d   = (string) $r['date'];
        $map[$sid][$k][$d] = [
            'mark'   => $r['mark'] !== null ? (string) $r['mark'] : '',
            'note'   => $r['note'] !== null ? (string) $r['note'] : '',
            'weight' => max(1, (int) ($r['weight'] ?? 10)),
        ];
    }
    return $map;
}

/**
 * Примечания к урокам: [kind][date] = ['note'=>, 'homework'=>].
 */
function journal_lesson_notes_map(int $subjectId, ?PDO $pdo = null): array
{
    if ($pdo === null) $pdo = db();
    $stmt = $pdo->prepare(
        'SELECT kind, date, note, homework FROM journal_lesson_notes WHERE subject_id = :s'
    );
    $stmt->execute([':s' => $subjectId]);
    $map = [];
    foreach ($stmt->fetchAll() as $r) {
        $k = (string) $r['kind'];
        $d = (string) $r['date'];
        $map[$k][$d] = [
            'note'     => $r['note'] !== null ? (string) $r['note'] : '',
            'homework' => $r['homework'] !== null ? (string) $r['homework'] : '',
        ];
    }
    return $map;
}

/**
 * Сохранить оценку с весом. Если mark и note оба пустые — строка удаляется.
 */
function journal_mark_apply(int $subjectId, int $studentId, string $date, string $kind,
                            string $mark, string $note, int $weight, int $userId, ?PDO $pdo = null): void
{
    if ($pdo === null) $pdo = db();

    if ($mark === '' && $note === '') {
        $pdo->prepare(
            'DELETE FROM journal_marks
             WHERE subject_id = :s AND student_id = :st AND date = :d AND kind = :k'
        )->execute([':s' => $subjectId, ':st' => $studentId, ':d' => $date, ':k' => $kind]);
        return;
    }

    $weight = max(1, $weight);
    $now = date('Y-m-d H:i:s');
    $pdo->prepare(
        'INSERT INTO journal_marks (subject_id, student_id, date, kind, mark, note, weight, updated_by, updated_at)
         VALUES (:s, :st, :d, :k, :m, :n, :w, :u, :t)
         ON CONFLICT(subject_id, student_id, date, kind)
         DO UPDATE SET mark = excluded.mark, note = excluded.note, weight = excluded.weight,
                       updated_by = excluded.updated_by, updated_at = excluded.updated_at'
    )->execute([
        ':s'  => $subjectId,
        ':st' => $studentId,
        ':d'  => $date,
        ':k'  => $kind,
        ':m'  => $mark !== '' ? $mark : null,
        ':n'  => $note !== '' ? $note : null,
        ':w'  => $weight,
        ':u'  => $userId,
        ':t'  => $now,
    ]);
}

/**
 * Сохранить примечание к уроку (общее). Пусто и note, и homework → удалить.
 */
function journal_lesson_note_apply(int $subjectId, string $date, string $kind,
                                   string $note, string $homework, int $userId, ?PDO $pdo = null): void
{
    if ($pdo === null) $pdo = db();
    if ($note === '' && $homework === '') {
        $pdo->prepare(
            'DELETE FROM journal_lesson_notes WHERE subject_id = :s AND date = :d AND kind = :k'
        )->execute([':s' => $subjectId, ':d' => $date, ':k' => $kind]);
        return;
    }
    $now = date('Y-m-d H:i:s');
    $pdo->prepare(
        'INSERT INTO journal_lesson_notes (subject_id, date, kind, note, homework, updated_by, updated_at)
         VALUES (:s, :d, :k, :n, :h, :u, :t)
         ON CONFLICT(subject_id, date, kind)
         DO UPDATE SET note = excluded.note, homework = excluded.homework,
                       updated_by = excluded.updated_by, updated_at = excluded.updated_at'
    )->execute([
        ':s' => $subjectId,
        ':d' => $date,
        ':k' => $kind,
        ':n' => $note !== '' ? $note : null,
        ':h' => $homework !== '' ? $homework : null,
        ':u' => $userId,
        ':t' => $now,
    ]);
}

function journal_valid_mark(string $m): bool
{
    return in_array($m, ['2', '3', '3-', '4', '4-', '5', '5-', 'н', 'ну'], true);
}

/**
 * Рендер сетки журнала.
 *
 * Пагинация строго по урокам: pageSize (35 для group, 17 для sub) уроков
 * на страницу. Из уроков страницы собираются уникальные даты — это столбцы
 * сетки. Если 35-й урок — первый из пары, его пара уходит на следующую
 * страницу, и дата появляется на обеих. Оценка и примечание к уроку
 * привязаны к дате, поэтому в ячейке на обеих страницах — одно значение.
 *
 * На последней странице добавляются будущие даты — до 3 будущих уроков
 * (дата целиком, не разрывается).
 */
function journal_render_grid(int $subjectId, string $kind, int $page, array $currentUser): string
{
    $pdo = db();

    $stmt = $pdo->prepare(
        'SELECT sub.id, sub.short_name, sub.full_name, sub.hours,
                g.name AS group_name, g.id AS group_id,
                sm.name AS semester_name,
                ay.name AS year_name, ay.id AS year_id
         FROM subjects sub
         JOIN groups g           ON g.id = sub.group_id
         JOIN semesters sm       ON sm.id = sub.semester_id
         JOIN academic_years ay  ON ay.id = sm.academic_year_id
         WHERE sub.id = :id'
    );
    $stmt->execute([':id' => $subjectId]);
    $subj = $stmt->fetch();
    if (!$subj) return '<div class="uni-empty">Предмет не найден.</div>';

    $allLessons = journal_lessons($subjectId, $kind, $pdo);
    if ($allLessons === []) {
        return '<div class="uni-empty">Нет уроков: в КТП нет тем этого вида, либо КТП не сгенерировано.</div>';
    }

    $today = date('Y-m-d');
    $pastLessons   = [];
    $futureLessons = [];
    foreach ($allLessons as $l) {
        if (strcmp($l['date'], $today) <= 0) $pastLessons[] = $l;
        else $futureLessons[] = $l;
    }

    $pageSize        = journal_page_size($kind);
    $totalPast       = count($pastLessons);
    $totalPages      = max(1, (int) ceil($totalPast / $pageSize));
    if ($page < 0 || $page >= $totalPages) $page = $totalPages - 1;

    $pageLessons = array_slice($pastLessons, $page * $pageSize, $pageSize);
    $columns     = journal_dates($pageLessons);
    $futureFrom  = count($columns);

    if ($page === $totalPages - 1 && $futureLessons !== []) {
        $futureDates = journal_dates($futureLessons);
        $fCount = 0;
        foreach ($futureDates as $fd) {
            $columns[] = $fd;
            $fCount += max(1, (int) $fd['lessons_count']);
            if ($fCount >= 3) break;
        }
    }

    $students    = journal_students($subjectId, $pdo);
    $marks       = journal_marks_map($subjectId, $pdo);
    $lessonNotes = journal_lesson_notes_map($subjectId, $pdo);

    $isTeacher = in_array('teacher', $currentUser['roles'] ?? [], true)
              || in_array('admin',   $currentUser['roles'] ?? [], true);

    $isStudentOnly = !$isTeacher && in_array('student', $currentUser['roles'] ?? [], true);
    $myStudentId = 0;
    if ($isStudentOnly) {
        $stmt = $pdo->prepare('SELECT id FROM students WHERE user_id = :u LIMIT 1');
        $stmt->execute([':u' => (int) $currentUser['id']]);
        $myStudentId = (int) $stmt->fetchColumn();
        $students = array_values(array_filter($students, fn($s) => (int) $s['id'] === $myStudentId));
    }

    $totalPagesOut = $totalPages;
    $startIdx      = $totalPast === 0 ? 0 : $page * $pageSize + 1;
    $endIdx        = min(($page + 1) * $pageSize, $totalPast);

    $defaultWeight = (int) (prikaz_config()['default_grade_weight'] ?? 10);

    ob_start();
    require __DIR__ . '/views/_journal_grid.php';
    return (string) ob_get_clean();
}

/**
 * Агрегаты по журналу.
 *
 * Поддерживает два источника:
 *   1. Старая модель (grades/lessons): $marks — [student_id][lesson_ord] => mark.
 *   2. Новая модель (journal_marks): $marks — [student_id][kind][date] => ['mark'=>, 'weight'=>].
 *
 * В обоих случаях возвращает:
 *   total   — всего числовых оценок 5/4/3/2
 *   sum     — сумма баллов (без веса)
 *   count   — количество числовых оценок
 *   raw_avg — обычный средний
 *   weighted_sum   — Σ(оценка × вес)
 *   weight_total   — Σ(вес)
 *   weighted_avg   — взвешенный средний (null если оценок нет)
 *   is_preliminary — true, если Σ(вес) < min_weight_for_final
 *   recommended    — 2..5 по взвешенному (null если оценок нет)
 *   dist    — [5=>n, 4=>n, 3=>n, 2=>n]
 *   lessons — [key => ['sum','count','avg','dist']]
 */
function aggregate_journal(array $marks): array
{
    $minWeight = (int) (prikaz_config()['min_weight_for_final'] ?? 10);

    $total = 0; $sum = 0; $count = 0;
    $wSum = 0; $wTotal = 0;
    $dist = [5 => 0, 4 => 0, 3 => 0, 2 => 0];
    $lessons = [];

    foreach ($marks as $studentId => $row) {
        if (!is_array($row)) continue;
        foreach ($row as $key => $value) {
            $mark   = '';
            $weight = 10;

            if (is_array($value)) {
                // Новая модель
                $mark   = (string) ($value['mark']   ?? '');
                $weight = max(1, (int) ($value['weight'] ?? 10));
            } elseif (is_scalar($value)) {
                // Старая модель — без веса
                $mark   = (string) $value;
                $weight = 1;
            }

            if (!preg_match('/^([2-5])(?:\s*[-−—])?$/u', $mark, $mm)) continue;
            $g = (int) $mm[1];

            $total++;
            $sum += $g; $count++;
            $wSum += $g * $weight; $wTotal += $weight;
            $dist[$g]++;

            if (!isset($lessons[$key])) {
                $lessons[$key] = ['sum' => 0, 'count' => 0, 'w_sum' => 0, 'w_total' => 0,
                                  'dist' => [5 => 0, 4 => 0, 3 => 0, 2 => 0]];
            }
            $lessons[$key]['sum'] += $g;
            $lessons[$key]['count']++;
            $lessons[$key]['w_sum'] += $g * $weight;
            $lessons[$key]['w_total'] += $weight;
            $lessons[$key]['dist'][$g]++;
        }
    }

    $rawAvg      = $count > 0 ? round($sum / $count, 2) : null;
    $weightedAvg = $wTotal > 0 ? round($wSum / $wTotal, 2) : null;

    $recommended = null;
    if ($weightedAvg !== null) {
        if ($weightedAvg >= 4.5)      $recommended = 5;
        elseif ($weightedAvg >= 3.5)  $recommended = 4;
        elseif ($weightedAvg >= 2.5)  $recommended = 3;
        else                          $recommended = 2;
    }

    foreach ($lessons as $key => &$l) {
        $l['avg'] = $l['count'] > 0 ? round($l['sum'] / $l['count'], 2) : null;
        $l['weighted_avg'] = $l['w_total'] > 0 ? round($l['w_sum'] / $l['w_total'], 2) : null;
    }
    unset($l);

    return [
        'total'          => $total,
        'sum'            => $sum,
        'count'          => $count,
        'raw_avg'        => $rawAvg,
        'weighted_sum'   => $wSum,
        'weight_total'   => $wTotal,
        'weighted_avg'   => $weightedAvg,
        'is_preliminary' => $wTotal < $minWeight,
        'recommended'    => $recommended,
        'dist'           => $dist,
        'lessons'        => $lessons,
    ];
}

/**
 * Хелпер: страница по урокам (pageSize уроков), из которых собираются даты.
 *
 * @return array{page:int,totalPages:int,totalPast:int,pageLessons:list<array>,columns:list<array>,futureFrom:int,startIdx:int,endIdx:int}
 */
function journal_slice_page(array $allLessons, int $pageSize, int $page): array
{
    $today = date('Y-m-d');
    $past = []; $future = [];
    foreach ($allLessons as $l) {
        if (strcmp($l['date'], $today) <= 0) $past[] = $l;
        else $future[] = $l;
    }
    $totalPast  = count($past);
    $totalPages = max(1, (int) ceil($totalPast / $pageSize));
    if ($page < 0 || $page >= $totalPages) $page = $totalPages - 1;

    $pageLessons = array_slice($past, $page * $pageSize, $pageSize);
    $columns     = journal_dates($pageLessons);
    $futureFrom  = count($columns);

    if ($page === $totalPages - 1 && $future !== []) {
        $futureDates = journal_dates($future);
        $cnt = 0;
        foreach ($futureDates as $fd) {
            $columns[] = $fd;
            $cnt += max(1, (int) $fd['lessons_count']);
            if ($cnt >= 3) break;
        }
    }

    return [
        'page'        => $page,
        'totalPages'  => $totalPages,
        'totalPast'   => $totalPast,
        'pageLessons' => $pageLessons,
        'columns'     => $columns,
        'futureFrom'  => $futureFrom,
        'startIdx'    => $totalPast === 0 ? 0 : $page * $pageSize + 1,
        'endIdx'      => min(($page + 1) * $pageSize, $totalPast),
    ];
}

/**
 * Рендер журнала для студента.
 *
 * $kind ∈ {'all', 'group', 'sub'}. Для 'all' уроки обоих видов объединяются.
 *
 * Сортировка уроков и страниц — ASC (старые сначала), как у учителя.
 * Только список «Темы журнала» выводится в обратном порядке — DESC, свежие
 * сверху.
 *
 * Оценки и примечания к оценкам — только свои. Примечания к урокам и ДЗ —
 * общие.
 *
 * В $dateInfo[$d] добавлены 'weights' — параллельный 'marks' массив весов,
 * чтобы _journal_student.php мог показать бейдж веса.
 */
function journal_render_student(int $subjectId, string $kind, int $page, array $currentUser): string
{
    $pdo = db();

    $stmt = $pdo->prepare(
        'SELECT sub.id, sub.short_name, sub.full_name, sub.hours,
                g.name AS group_name, g.id AS group_id,
                sm.name AS semester_name,
                ay.name AS year_name, ay.id AS year_id
         FROM subjects sub
         JOIN groups g           ON g.id = sub.group_id
         JOIN semesters sm       ON sm.id = sub.semester_id
         JOIN academic_years ay  ON ay.id = sm.academic_year_id
         WHERE sub.id = :id'
    );
    $stmt->execute([':id' => $subjectId]);
    $subj = $stmt->fetch();
    if (!$subj) return '<div class="uni-empty">Предмет не найден.</div>';

    if (!in_array($kind, ['all', 'group', 'sub'], true)) $kind = 'all';
    $kinds = ($kind === 'all') ? ['group', 'sub'] : [$kind];

    $allLessons = [];
    foreach ($kinds as $k) {
        foreach (journal_lessons($subjectId, $k, $pdo) as $l) {
            $l['kind_src'] = $k;
            $allLessons[] = $l;
        }
    }
    if ($allLessons === []) {
        return '<div class="uni-empty">Нет уроков: в КТП нет тем этого вида, либо КТП не сгенерировано.</div>';
    }

    usort($allLessons, fn($a, $b) => strcmp($a['date'], $b['date']));

    $pageSize     = ($kind === 'sub') ? 17 : 35;
    $totalLessons = count($allLessons);
    $totalPages   = max(1, (int) ceil($totalLessons / $pageSize));
    if ($page < 0 || $page >= $totalPages) $page = 0;
    $pageLessons  = array_slice($allLessons, $page * $pageSize, $pageSize);

    $dateToLessons = [];
    foreach ($pageLessons as $l) {
        $dateToLessons[$l['date']][] = $l;
    }
    $columns = [];
    foreach ($dateToLessons as $date => $lessonsOfDate) {
        $topics = [];
        foreach ($lessonsOfDate as $l) {
            if (!in_array($l['topic'], $topics, true)) $topics[] = (string) $l['topic'];
        }
        $columns[] = [
            'date'          => (string) $date,
            'lessons_count' => count($lessonsOfDate),
            'topics'        => $topics,
        ];
    }

    $stmt = $pdo->prepare('SELECT id, full_name FROM students WHERE user_id = :u LIMIT 1');
    $stmt->execute([':u' => (int) $currentUser['id']]);
    $myStudent = $stmt->fetch() ?: null;
    if (!$myStudent) return '<div class="uni-empty">Студент не найден.</div>';
    $myStudentId   = (int) $myStudent['id'];
    $myStudentName = (string) $myStudent['full_name'];

    $marksMap = journal_marks_map($subjectId, $pdo);
    $myMarksByKind = [];
    foreach ($kinds as $k) {
        $myMarksByKind[$k] = $marksMap[$myStudentId][$k] ?? [];
    }

    $lessonNotesMap = journal_lesson_notes_map($subjectId, $pdo);

    $dateInfo = [];
    foreach ($columns as $col) {
        $d = $col['date'];

        $marksList   = [];
        $weightsList = [];
        $markNote    = '';
        foreach ($kinds as $k) {
            $m = $myMarksByKind[$k][$d] ?? null;
            if (!$m) continue;
            $mv = (string) ($m['mark'] ?? '');
            $mn = (string) ($m['note'] ?? '');
            $mw = max(1, (int) ($m['weight'] ?? 10));
            if ($mv !== '') {
                $marksList[]   = $mv;
                $weightsList[] = $mw;
            }
            if ($markNote === '' && $mn !== '') $markNote = $mn;
        }

        $lessonNote = '';
        $homework   = '';
        foreach ($kinds as $k) {
            $ln = $lessonNotesMap[$k][$d] ?? null;
            if (!$ln) continue;
            $n = (string) ($ln['note'] ?? '');
            $h = (string) ($ln['homework'] ?? '');
            if ($lessonNote === '' && $n !== '') $lessonNote = $n;
            if ($homework   === '' && $h !== '') $homework   = $h;
        }

        $dateInfo[$d] = [
            'marks'        => $marksList,
            'weights'      => $weightsList,
            'mark_display' => implode('/', $marksList),
            'mark_note'    => $markNote,
            'lesson_note'  => $lessonNote,
            'homework'     => $homework,
        ];
    }

    $columnsDesc = array_reverse($columns);

    $totalPagesOut = $totalPages;
    $startIdx      = $page * $pageSize + 1;
    $endIdx        = min(($page + 1) * $pageSize, $totalLessons);

    ob_start();
    require __DIR__ . '/views/_journal_student.php';
    return (string) ob_get_clean();
}