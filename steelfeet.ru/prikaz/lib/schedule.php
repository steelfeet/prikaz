<?php
declare(strict_types=1);

/**
 * Справочник расписания: дни недели, номера уроков и их продолжительность,
 * типы занятий.
 *
 * Дней — шесть (без воскресенья), уроков — девять.
 * Значения справочные и в БД не хранятся.
 */

/** Дни недели: ключ — номер дня (1 = понедельник), значение — подпись. */
function schedule_days(): array
{
    return [
        1 => 'Понедельник',
        2 => 'Вторник',
        3 => 'Среда',
        4 => 'Четверг',
        5 => 'Пятница',
        6 => 'Суббота',
    ];
}

/** Уроки: ключ — номер урока, значение — строка продолжительности. */
function schedule_lesson_times(): array
{
    return [
        1 => '8-30 – 9-15',
        2 => '9-20 – 10-05',
        3 => '10-15 – 11-00',
        4 => '11-05 – 11-50',
        5 => '12-00 – 12-45',
        6 => '12-50 – 13-35',
        7 => '13-45 – 14-30',
        8 => '14-35 – 15-20',
        9 => '15-25 – 16-10',
    ];
}

/** Типы занятий: ключ — код, значение — подпись. */
function schedule_lesson_types(): array
{
    return [
        'group' => 'Групповое',
        'sub1'  => '1 подгруппа',
        'sub2'  => '2 подгруппа',
    ];
}

/** Максимально занятий в одной ячейке (день + урок). */
function schedule_max_per_cell(): int
{
    return 3;
}

function schedule_day_label(int $day): string
{
    return schedule_days()[$day] ?? (string) $day;
}

function schedule_lesson_type_label(string $type): string
{
    return schedule_lesson_types()[$type] ?? $type;
}

/**
 * Собирает строки расписания в структуру «урок → день → список занятий».
 *
 * @param array $rows строки из schedule_entries с short_name/group_name
 * @return array<int, array<int, list<array>>>
 */
function schedule_grid(array $rows): array
{
    $grid = [];
    foreach ($rows as $row) {
        $lesson = (int) $row['lesson_num'];
        $day    = (int) $row['weekday'];
        $grid[$lesson][$day][] = $row;
    }
    // Сортировка занятий внутри ячейки по исходной позиции.
    foreach ($grid as $lesson => $days) {
        foreach ($days as $day => $cells) {
            usort($grid[$lesson][$day], fn($a, $b) => (int) $a['position'] <=> (int) $b['position']);
        }
    }
    return $grid;
}
