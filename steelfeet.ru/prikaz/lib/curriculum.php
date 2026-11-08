<?php
declare(strict_types=1);

/**
 * Темы предмета (curriculum).
 *
 * Рендер блока тем для страницы «Предметы» и для AJAX-ответов.
 * Вызывается и при полной загрузке страницы (require из subjects.php),
 * и в обработчиках admin_curriculum_* при AJAX (data.html).
 */

/**
 * Собирает массив для partial _topics_block.php.
 *
 * @return array{id:int, short_name:string, topics:list<array{id:int,ord:int,topic:string}>}|null
 */
function curriculum_block_data(int $subjectId, ?PDO $pdo = null): ?array
{
    if ($pdo === null) $pdo = db();

    $stmt = $pdo->prepare('SELECT id, short_name FROM subjects WHERE id = :id');
    $stmt->execute([':id' => $subjectId]);
    $subj = $stmt->fetch();
    if (!$subj) return null;

    $stmt = $pdo->prepare(
        'SELECT id, ord, topic FROM curriculum WHERE subject_id = :s ORDER BY ord'
    );
    $stmt->execute([':s' => $subjectId]);
    $topics = $stmt->fetchAll();

    return [
        'id'         => (int) $subj['id'],
        'short_name' => (string) $subj['short_name'],
        'topics'     => $topics,
    ];
}

/**
 * Рендерит HTML-блок тем для одного предмета.
 */
function curriculum_render_block(int $subjectId): string
{
    $s = curriculum_block_data($subjectId);
    if ($s === null) return '';

    ob_start();
    require __DIR__ . '/views/admin/_topics_block.php';
    return (string) ob_get_clean();
}

/**
 * Вставить новую тему сразу после указанной (по ord).
 *
 * Сдвигает все ord > указанного на +1. Двухфазный сдвиг через «дальнюю
 * зону» — иначе UNIQUE (subject_id, ord) ругается, пока старые значения
 * ещё заняты.
 */
function curriculum_insert_below(int $afterId, string $topic, ?PDO $pdo = null): int
{
    if ($pdo === null) $pdo = db();

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT subject_id, ord FROM curriculum WHERE id = :id');
        $stmt->execute([':id' => $afterId]);
        $row = $stmt->fetch();
        if (!$row) { $pdo->rollBack(); throw new RuntimeException('Тема не найдена.'); }

        $subjectId = (int) $row['subject_id'];
        $ord       = (int) $row['ord'];
        $now       = date('Y-m-d H:i:s');

        $pdo->prepare(
            'UPDATE curriculum SET ord = ord + 1000000
             WHERE subject_id = :s AND ord > :o'
        )->execute([':s' => $subjectId, ':o' => $ord]);

        $pdo->prepare(
            'UPDATE curriculum SET ord = ord - 1000000 + 1, updated_at = :u
             WHERE subject_id = :s AND ord > :o'
        )->execute([':u' => $now, ':s' => $subjectId, ':o' => $ord + 1000000]);

        $pdo->prepare(
            'INSERT INTO curriculum (subject_id, ord, topic)
             VALUES (:s, :o, :t)'
        )->execute([':s' => $subjectId, ':o' => $ord + 1, ':t' => $topic]);

        $newId = (int) $pdo->lastInsertId();
        $pdo->commit();
        return $newId;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}