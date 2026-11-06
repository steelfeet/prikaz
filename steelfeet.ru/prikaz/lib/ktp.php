<?php
declare(strict_types=1);

/**
 * КТП — Календарно-Тематическое Планирование.
 *
 * Одна тема = один блок. Блок = все занятия одного вида (групповые или
 * подгрупповые) в один день семестра. Праздники пропускаются.
 *
 *   - Групповой блок: все group-пары дня. Занимает столько номеров п/п,
 *     сколько group-пар в день. Tariff = число group-пар.
 *   - Подгрупповой блок: все пары sub1 и sub2 дня. Слоты блока = max(пар
 *     sub1, пар sub2) — то есть уроки одной подгруппы. Занимает столько
 *     номеров п/п, сколько слотов. Tariff = пар sub1 + пар sub2.
 *
 * В день может быть до двух блоков — групповой и подгрупповой. Порядок
 * внутри дня — по минимальному lesson_num пары блока.
 *
 * Нумерация п/п — сквозная по всем блокам семестра. Тема i = блок i.
 */

/**
 * Список блоков предмета по семестру.
 *
 * @return list<array{num_start:int,num_end:int,date:string,kind:string,tariff:int}>
 */
function ktp_lesson_blocks(int $subjectId, ?PDO $pdo = null): array
{
    if ($pdo === null) $pdo = db();

    $stmt = $pdo->prepare(
        'SELECT s.semester_id, ay.start_date, ay.end_date
         FROM subjects s
         JOIN semesters sm      ON sm.id = s.semester_id
         JOIN academic_years ay ON ay.id = sm.academic_year_id
         WHERE s.id = :id'
    );
    $stmt->execute([':id' => $subjectId]);
    $subj = $stmt->fetch();
    if (!$subj) return [];

    $semesterId = (int) $subj['semester_id'];
    $startDate  = (string) $subj['start_date'];
    $endDate    = (string) $subj['end_date'];

    $stmt = $pdo->prepare(
        'SELECT weekday, lesson_num, lesson_type
         FROM schedule_entries
         WHERE subject_id = :s
         ORDER BY weekday, lesson_num'
    );
    $stmt->execute([':s' => $subjectId]);
    $rows = $stmt->fetchAll();
    if ($rows === []) return [];

    $groupPairs = [];
    $sub1Pairs  = [];
    $sub2Pairs  = [];
    foreach ($rows as $r) {
        $wd = (int) $r['weekday'];
        $ln = (int) $r['lesson_num'];
        $lt = (string) $r['lesson_type'];
        if ($lt === 'group') {
            $groupPairs[$wd][$ln] = true;
        } elseif ($lt === 'sub1') {
            $sub1Pairs[$wd][$ln] = true;
        } elseif ($lt === 'sub2') {
            $sub2Pairs[$wd][$ln] = true;
        }
    }
    foreach ([&$groupPairs, &$sub1Pairs, &$sub2Pairs] as &$arr) {
        foreach ($arr as $wd => $_) {
            ksort($arr[$wd]);
            $arr[$wd] = array_keys($arr[$wd]);
        }
    }
    unset($arr);

    $holidaySet = [];
    $stmt = $pdo->prepare('SELECT date FROM holidays WHERE semester_id = :sm');
    $stmt->execute([':sm' => $semesterId]);
    foreach ($stmt->fetchAll() as $h) {
        $holidaySet[(string) $h['date']] = true;
    }

    $startTs = strtotime($startDate);
    $endTs   = strtotime($endDate);
    if ($startTs === false || $endTs === false || $endTs < $startTs) return [];

    $blocks = [];
    $counter = 0;

    for ($ts = $startTs; $ts <= $endTs; $ts += 86400) {
        $date = date('Y-m-d', $ts);
        if (isset($holidaySet[$date])) continue;
        $wd = (int) date('N', $ts);

        $dayBlocks = [];

        if (!empty($groupPairs[$wd])) {
            $count = count($groupPairs[$wd]);
            $dayBlocks[] = [
                'kind'   => 'group',
                'min_ln' => (int) $groupPairs[$wd][0],
                'slots'  => $count,
                'tariff' => $count,
                'date'   => $date,
            ];
        }

        $s1 = $sub1Pairs[$wd] ?? [];
        $s2 = $sub2Pairs[$wd] ?? [];
        if ($s1 !== [] || $s2 !== []) {
            $slots  = max(count($s1), count($s2));
            $tariff = count($s1) + count($s2);
            $minLn  = PHP_INT_MAX;
            if ($s1 !== []) $minLn = min($minLn, (int) $s1[0]);
            if ($s2 !== []) $minLn = min($minLn, (int) $s2[0]);
            if ($minLn === PHP_INT_MAX) $minLn = 1;

            $dayBlocks[] = [
                'kind'   => 'sub',
                'min_ln' => $minLn,
                'slots'  => $slots,
                'tariff' => $tariff,
                'date'   => $date,
            ];
        }

        usort($dayBlocks, fn($a, $b) => $a['min_ln'] <=> $b['min_ln']);

        foreach ($dayBlocks as $b) {
            $slots = (int) $b['slots'];
            if ($slots <= 0) continue;
            $numStart = $counter + 1;
            $numEnd   = $counter + $slots;
            $blocks[] = [
                'num_start' => $numStart,
                'num_end'   => $numEnd,
                'date'      => (string) $b['date'],
                'kind'      => (string) $b['kind'],
                'tariff'    => (int) $b['tariff'],
            ];
            $counter = $numEnd;
        }
    }
    return $blocks;
}

/**
 * Привязывает темы КТП к блокам: тема i → блок i.
 *
 * @return list<array{
 *   id:int, topic:string, placed:bool,
 *   num_start:int, num_end:int, date:string,
 *   kind:string, kind_label:string, tariff:int
 * }>
 */
function ktp_layout(int $subjectId, ?PDO $pdo = null, ?array $blocks = null): array
{
    if ($pdo === null) $pdo = db();
    if ($blocks === null) $blocks = ktp_lesson_blocks($subjectId, $pdo);

    $stmt = $pdo->prepare(
        'SELECT id, ord, topic FROM ktp_entries WHERE subject_id = :s ORDER BY ord, id'
    );
    $stmt->execute([':s' => $subjectId]);
    $entries = $stmt->fetchAll();
    if ($entries === []) return [];

    $totalBlocks = count($blocks);
    $result = [];

    foreach ($entries as $i => $e) {
        if ($i >= $totalBlocks) {
            $result[] = [
                'id'         => (int) $e['id'],
                'topic'      => (string) $e['topic'],
                'placed'     => false,
                'num_start'  => 0,
                'num_end'    => 0,
                'date'       => '',
                'kind'       => '',
                'kind_label' => '',
                'tariff'     => 0,
            ];
            continue;
        }
        $b = $blocks[$i];
        $result[] = [
            'id'         => (int) $e['id'],
            'topic'      => (string) $e['topic'],
            'placed'     => true,
            'num_start'  => (int) $b['num_start'],
            'num_end'    => (int) $b['num_end'],
            'date'       => (string) $b['date'],
            'kind'       => (string) $b['kind'],
            'kind_label' => ktp_kind_label((string) $b['kind']),
            'tariff'     => (int) $b['tariff'],
        ];
    }
    return $result;
}

/**
 * Тарификационные часы по всем блокам семестра (не зависит от КТП).
 */
function ktp_tariff_total(?array $blocks): int
{
    return (int) array_sum(array_column($blocks, 'tariff'));
}

/**
 * Часы «В КТП»: сумма tariff по блокам, занятым темами.
 */
function ktp_hours_from_layout(array $layout): int
{
    $sum = 0;
    foreach ($layout as $row) {
        if (!empty($row['placed'])) $sum += (int) $row['tariff'];
    }
    return $sum;
}

/**
 * Сгенерировать КТП заново: тема i → блок i.
 */
function ktp_generate(int $subjectId, ?PDO $pdo = null): void
{
    if ($pdo === null) $pdo = db();

    $stmt = $pdo->prepare('SELECT ord, topic FROM curriculum WHERE subject_id = :s ORDER BY ord');
    $stmt->execute([':s' => $subjectId]);
    $topics = $stmt->fetchAll();

    $pdo->beginTransaction();
    try {
        $pdo->prepare('DELETE FROM ktp_entries WHERE subject_id = :s')->execute([':s' => $subjectId]);
        if ($topics !== []) {
            $ins = $pdo->prepare(
                'INSERT INTO ktp_entries (subject_id, ord, topic, weight) VALUES (:s, :o, :t, 1)'
            );
            foreach ($topics as $i => $t) {
                $ins->execute([
                    ':s' => $subjectId,
                    ':o' => $i + 1,
                    ':t' => (string) $t['topic'],
                ]);
            }
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

/**
 * Дублировать текущую тему на следующий блок.
 * Вставляет новую запись с тем же названием weight=1 сразу после текущей.
 */
function ktp_add_more(int $entryId, ?PDO $pdo = null): void
{
    if ($pdo === null) $pdo = db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT subject_id, ord, topic FROM ktp_entries WHERE id = :id');
        $stmt->execute([':id' => $entryId]);
        $row = $stmt->fetch();
        if (!$row) { $pdo->rollBack(); return; }

        $subjectId = (int) $row['subject_id'];
        $ord       = (int) $row['ord'];
        $topic     = (string) $row['topic'];
        $now       = date('Y-m-d H:i:s');

        $pdo->prepare(
            'UPDATE ktp_entries SET ord = ord + 1000000
             WHERE subject_id = :s AND ord > :o'
        )->execute([':s' => $subjectId, ':o' => $ord]);

        $pdo->prepare(
            'UPDATE ktp_entries SET ord = ord - 1000000 + 1, updated_at = :u
             WHERE subject_id = :s AND ord > :o'
        )->execute([':u' => $now, ':s' => $subjectId, ':o' => $ord + 1000000]);

        $pdo->prepare(
            'INSERT INTO ktp_entries (subject_id, ord, topic, weight)
             VALUES (:s, :o, :t, 1)'
        )->execute([':s' => $subjectId, ':o' => $ord + 1, ':t' => $topic]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

/**
 * Удалить тему из КТП и переиндексировать оставшиеся.
 */
function ktp_delete(int $entryId, ?PDO $pdo = null): void
{
    if ($pdo === null) $pdo = db();
    $stmt = $pdo->prepare('SELECT subject_id FROM ktp_entries WHERE id = :id');
    $stmt->execute([':id' => $entryId]);
    $subjectId = (int) $stmt->fetchColumn();
    if ($subjectId <= 0) return;
    $pdo->prepare('DELETE FROM ktp_entries WHERE id = :id')->execute([':id' => $entryId]);
    ktp_reindex($subjectId, $pdo);
}

/**
 * Переиндексировать ord по порядку id (после удаления).
 */
function ktp_reindex(int $subjectId, ?PDO $pdo = null): void
{
    if ($pdo === null) $pdo = db();
    $stmt = $pdo->prepare('SELECT id FROM ktp_entries WHERE subject_id = :s ORDER BY ord, id');
    $stmt->execute([':s' => $subjectId]);
    $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if ($ids === []) return;
    $upd = $pdo->prepare('UPDATE ktp_entries SET ord = :o, updated_at = :u WHERE id = :id');
    $now = date('Y-m-d H:i:s');
    foreach ($ids as $i => $id) {
        $upd->execute([':o' => $i + 1, ':u' => $now, ':id' => (int) $id]);
    }
}

/**
 * Вид занятия по kind.
 */
function ktp_kind_label(string $kind): string
{
    if ($kind === 'group') return 'Лекция';
    if ($kind === 'sub' || $kind === 'sub1' || $kind === 'sub2') return 'Практикум';
    if ($kind === 'mixed') return 'Лекция, Практикум';
    return '—';
}

/**
 * Рендерит HTML-блок КТП для одного предмета.
 */
function ktp_render_block(int $subjectId): string
{
    $pdo = db();

    $stmt = $pdo->prepare(
        'SELECT s.id, s.short_name, s.full_name, s.hours,
                g.name AS group_name,
                ay.name AS year_name
         FROM subjects s
         JOIN groups g          ON g.id  = s.group_id
         JOIN semesters sm      ON sm.id = s.semester_id
         JOIN academic_years ay ON ay.id = sm.academic_year_id
         WHERE s.id = :id'
    );
    $stmt->execute([':id' => $subjectId]);
    $row = $stmt->fetch();
    if (!$row) return '';

    $stmt = $pdo->prepare('SELECT id, ord, topic FROM curriculum WHERE subject_id = :s ORDER BY ord');
    $stmt->execute([':s' => $subjectId]);
    $topics = $stmt->fetchAll();

    $stmt = $pdo->prepare('SELECT id, ord, topic FROM ktp_entries WHERE subject_id = :s ORDER BY ord, id');
    $stmt->execute([':s' => $subjectId]);
    $ktp = $stmt->fetchAll();

    $blocks     = ktp_lesson_blocks($subjectId, $pdo);
    $ktpLayout  = $ktp !== [] ? ktp_layout($subjectId, $pdo, $blocks) : [];
    $ktpHours   = ktp_hours_from_layout($ktpLayout);
    $ktpTariff  = ktp_tariff_total($blocks);
    $blocksCount = count($blocks);

    $s = [
        'id'              => (int) $row['id'],
        'short_name'      => (string) $row['short_name'],
        'full_name'       => (string) $row['full_name'],
        'hours'           => (int) $row['hours'],
        'group_name'      => (string) $row['group_name'],
        'year_name'       => (string) $row['year_name'],
        'topics'          => $topics,
        'ktp'             => $ktp,
        'ktp_layout'      => $ktpLayout,
        'ktp_hours'       => $ktpHours,
        'ktp_tariff'      => $ktpTariff,
        'ktp_blocks_count' => $blocksCount,
    ];

    ob_start();
    require __DIR__ . '/views/admin/_ktp_block.php';
    return (string) ob_get_clean();
}