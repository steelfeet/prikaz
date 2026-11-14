<?php
declare(strict_types=1);

/**
 * Домен проектов: фазы, типы, история, смена фазы.
 *
 * Типы проектов теперь в БД (project_types), фазы — в project_type_phases.
 * Старые глобальные project_phases и project_history.phase_id оставлены как
 * fallback для существующих проектов (у которых project_type_id IS NULL).
 */

/**
 * Глобальные фазы проекта из project_phases (fallback для старых проектов).
 */
function project_phases_meta(): array
{
    static $m = null;
    if ($m !== null) return $m;

    $m = [];
    try {
        $rows = db()->query('SELECT * FROM project_phases ORDER BY num')->fetchAll();
        foreach ($rows as $p) {
            $m[(int) $p['num']] = [
                'id'    => (int) $p['id'],
                'code'  => $p['code'],
                'num'   => (int) $p['num'],
                'name'  => $p['name'],
                'color' => $p['color'],
            ];
        }
    } catch (Throwable $e) {
        $m = [];
    }
    return $m;
}

/**
 * Типы проектов — из БД. Формат совместим с прежним (code => [label,color,icon]).
 * Если таблицы ещё нет — fallback на статический список.
 */
function project_types_meta(): array
{
    static $m = null;
    if ($m !== null) return $m;

    $m = [];
    try {
        $rows = db()->query('SELECT * FROM project_types ORDER BY code')->fetchAll();
        foreach ($rows as $t) {
            $m[(string) $t['code']] = [
                'id'    => (int) $t['id'],
                'label' => (string) $t['name'],
                'color' => (string) ($t['color'] ?? '#8b6914'),
                'icon'  => (string) ($t['icon'] ?? '📌'),
            ];
        }
    } catch (Throwable $e) {
        $m = [];
    }

    if ($m === []) {
        // Fallback, если таблицы нет.
        $m = [
            'original'    => ['id' => 0, 'label' => 'Оригинал',     'color' => '#16a34a', 'icon' => '💻'],
            'fork'        => ['id' => 0, 'label' => 'Форк',         'color' => '#7c3aed', 'icon' => '🔀'],
            'research'    => ['id' => 0, 'label' => 'Исследование', 'color' => '#2563eb', 'icon' => '🔬'],
            'interactive' => ['id' => 0, 'label' => 'Интерактив',   'color' => '#0d9488', 'icon' => '🎮'],
            'report'      => ['id' => 0, 'label' => 'Доклад',       'color' => '#8b6914', 'icon' => '📄'],
        ];
    }
    return $m;
}

/**
 * Фазы конкретного проекта (в порядке ord).
 *
 * Для проектов с project_type_id — свои фазы; иначе — глобальные.
 *
 * @return list<array{num:int,id:int,code?:string,name:string,color:string}>
 */
function project_phases_for(int $projectId, ?PDO $pdo = null): array
{
    if ($pdo === null) $pdo = db();

    $stmt = $pdo->prepare('SELECT project_type_id FROM projects WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $projectId]);
    $typeId = (int) $stmt->fetchColumn();

    if ($typeId > 0) {
        $stmt = $pdo->prepare(
            'SELECT id, ord AS num, name, color FROM project_type_phases
             WHERE project_type_id = :t ORDER BY ord'
        );
        $stmt->execute([':t' => $typeId]);
        return $stmt->fetchAll();
    }

    // Fallback: глобальные
    $out = [];
    foreach (project_phases_meta() as $num => $p) {
        $out[] = ['num' => $num, 'id' => $p['id'], 'code' => $p['code'], 'name' => $p['name'], 'color' => $p['color']];
    }
    return $out;
}

/**
 * Текущая фаза — номер (1..N) последней записи в истории.
 */
function project_current_phase_num(int $projectId): int
{
    $stmt = db()->prepare(
        'SELECT COALESCE(pph.num, ptph.ord) AS num
         FROM project_history ph
         LEFT JOIN project_phases pph       ON pph.id  = ph.phase_id
         LEFT JOIN project_type_phases ptph ON ptph.id = ph.type_phase_id
         WHERE ph.project_id = :id
         ORDER BY ph.moved_at DESC, ph.id DESC
         LIMIT 1'
    );
    $stmt->execute([':id' => $projectId]);
    $num = $stmt->fetchColumn();
    return $num !== false ? (int) $num : 0;
}

/**
 * История переходов — от старых к новым.
 */
function project_history_rows(int $projectId): array
{
    $stmt = db()->prepare(
        'SELECT ph.id, ph.note, ph.moved_at,
                COALESCE(pph.num, ptph.ord)   AS num,
                COALESCE(pph.name, ptph.name) AS name,
                COALESCE(pph.color, ptph.color) AS color
         FROM project_history ph
         LEFT JOIN project_phases pph       ON pph.id  = ph.phase_id
         LEFT JOIN project_type_phases ptph ON ptph.id = ph.type_phase_id
         WHERE ph.project_id = :id
         ORDER BY ph.moved_at ASC, ph.id ASC'
    );
    $stmt->execute([':id' => $projectId]);
    return $stmt->fetchAll();
}

/**
 * Студенты — участники проекта.
 */
function project_students_rows(int $projectId): array
{
    $stmt = db()->prepare(
        'SELECT s.* FROM students s
         JOIN project_students ps ON ps.student_id = s.id
         WHERE ps.project_id = :id
         ORDER BY s.full_name'
    );
    $stmt->execute([':id' => $projectId]);
    return $stmt->fetchAll();
}

/**
 * Сменить фазу. Для проекта с project_type_id пишет type_phase_id;
 * иначе — phase_id (глобальная).
 */
function project_change_phase(int $projectId, int $phaseNum, ?string $note, int $byUserId): void
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT project_type_id FROM projects WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $projectId]);
    $typeId = (int) $stmt->fetchColumn();

    if ($typeId > 0) {
        $stmt = $pdo->prepare(
            'SELECT id FROM project_type_phases WHERE project_type_id = :t AND ord = :o LIMIT 1'
        );
        $stmt->execute([':t' => $typeId, ':o' => $phaseNum]);
        $phaseId = (int) $stmt->fetchColumn();
        if ($phaseId <= 0) throw new RuntimeException('Фаза не найдена.');

        $pdo->prepare(
            'INSERT INTO project_history (project_id, type_phase_id, note, moved_by)
             VALUES (:p, :ph, :n, :u)'
        )->execute([
            ':p'  => $projectId,
            ':ph' => $phaseId,
            ':n'  => $note !== null && $note !== '' ? $note : null,
            ':u'  => $byUserId,
        ]);
        return;
    }

    $phases = project_phases_meta();
    if (!isset($phases[$phaseNum])) throw new RuntimeException('Фаза не найдена.');

    $pdo->prepare(
        'INSERT INTO project_history (project_id, phase_id, note, moved_by)
         VALUES (:p, :ph, :n, :u)'
    )->execute([
        ':p'  => $projectId,
        ':ph' => $phases[$phaseNum]['id'],
        ':n'  => $note !== null && $note !== '' ? $note : null,
        ':u'  => $byUserId,
    ]);
}