<?php
declare(strict_types=1);

/**
 * Домен проектов: фазы, типы, история, смена фазы.
 * Используется в data.php (чтение), actions.php (смена), views/teacher.php.
 */

/**
 * Фазы проекта из БД, кэш на время запроса.
 * Возвращает массив: num => [id, code, num, name, color]
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
        // таблицы ещё нет — вернём пустой массив, UI покажет пустую сетку
        $m = [];
    }

    return $m;
}

/**
 * Типы проектов — статический справочник, не в БД.
 * original / fork / research / interactive / report.
 */
function project_types_meta(): array
{
    return [
        'original'    => ['label' => 'Оригинал',     'color' => '#16a34a', 'icon' => '💻'],
        'fork'        => ['label' => 'Форк',         'color' => '#7c3aed', 'icon' => '🔀'],
        'research'    => ['label' => 'Исследование', 'color' => '#2563eb', 'icon' => '🔬'],
        'interactive' => ['label' => 'Интерактив',   'color' => '#0d9488', 'icon' => '🎮'],
        'report'      => ['label' => 'Доклад',       'color' => '#8b6914', 'icon' => '📄'],
    ];
}

/**
 * Текущая фаза проекта — номер (1..4).
 * Если истории нет — 0.
 */
function project_current_phase_num(int $projectId): int
{
    $stmt = db()->prepare(
        'SELECT pph.num
         FROM project_history ph
         JOIN project_phases pph ON pph.id = ph.phase_id
         WHERE ph.project_id = :id
         ORDER BY ph.moved_at DESC, ph.id DESC
         LIMIT 1'
    );
    $stmt->execute([':id' => $projectId]);
    $num = $stmt->fetchColumn();
    return $num !== false ? (int) $num : 0;
}

/**
 * История переходов по фазам — от старых к новым.
 */
function project_history_rows(int $projectId): array
{
    $stmt = db()->prepare(
        'SELECT ph.id, ph.note, ph.moved_at,
                pph.num, pph.name, pph.color
         FROM project_history ph
         JOIN project_phases pph ON pph.id = ph.phase_id
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
 * Сменить фазу проекта.
 * Добавляет запись в project_history, не трогает сам project (текущая
 * фаза всегда вычисляется по последней записи истории).
 */
function project_change_phase(int $projectId, int $phaseNum, ?string $note, int $byUserId): void
{
    $phases = project_phases_meta();
    if (!isset($phases[$phaseNum])) {
        throw new RuntimeException('Фаза не найдена.');
    }

    db()->prepare(
        'INSERT INTO project_history (project_id, phase_id, note, moved_by)
         VALUES (:p, :ph, :n, :u)'
    )->execute([
        ':p'  => $projectId,
        ':ph' => $phases[$phaseNum]['id'],
        ':n'  => $note !== null && $note !== '' ? $note : null,
        ':u'  => $byUserId,
    ]);
}