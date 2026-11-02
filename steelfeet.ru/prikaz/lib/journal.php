<?php
declare(strict_types=1);

/**
 * Сводная агрегация журнала: сумма, средний, посещаемость,
 * распределение оценок 5/4/3/2.
 */
function aggregate_journal(array $marksByStudent): array
{
    $dist    = [5 => 0, 4 => 0, 3 => 0, 2 => 0];
    $absent  = 0;
    $present = 0;
    $cells   = 0;
    $sum     = 0;
    $count   = 0;

    foreach ($marksByStudent as $marks) {
        foreach ((array) $marks as $m) {
            $cells++;
            if ($m === 'н') {
                $absent++;
            } elseif ($m === '5' || $m === '4' || $m === '3' || $m === '2') {
                $g = (int) $m;
                $sum += $g;
                $count++;
                $dist[$g] = ($dist[$g] ?? 0) + 1;
                $present++;
            } elseif ($m === '·') {
                $present++;
            }
        }
    }

    return [
        'dist'       => $dist,
        'absent'     => $absent,
        'present'    => $present,
        'cells'      => $cells,
        'gradeSum'   => $sum,
        'gradeCount' => $count,
        'avg'        => $count > 0 ? round($sum / $count, 2) : null,
        'attendance' => ($absent + $present) > 0
            ? round($present / ($absent + $present) * 100, 1)
            : null,
    ];
}