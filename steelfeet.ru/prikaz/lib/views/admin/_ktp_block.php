<?php
declare(strict_types=1);

/**
 * Partial: блок КТП для одного предмета.
 *
 * Ожидает $s — массив с полями:
 *   id, short_name, full_name, hours, group_name, year_name,
 *   topics, ktp, ktp_layout, ktp_tariff, ktp_slots, ktp_blocks_count
 *
 * Дата в таблице склеивается через rowspan для всех блоков одного дня.
 */

$sTopics     = $s['topics']     ?? [];
$sKtp        = $s['ktp']        ?? [];
$sKtpLayout  = $s['ktp_layout'] ?? [];
$sKtpTariff  = (int) ($s['ktp_tariff'] ?? 0);
$sKtpSlots   = (int) ($s['ktp_slots']  ?? 0);
$sPlanHours  = (int) $s['hours'];
$sHoursMatch = ($sPlanHours > 0 && $sKtpTariff === $sPlanHours);

$renderRows = [];
foreach ($sKtpLayout as $row) {
    if (!empty($row['placed'])) $renderRows[] = $row;
}

$dateSpans = [];
$totalRows = count($renderRows);
$i = 0;
while ($i < $totalRows) {
    $date  = $renderRows[$i]['date'];
    $start = $i;
    while ($i < $totalRows && $renderRows[$i]['date'] === $date) $i++;
    $dateSpans[$start] = $i - $start;
}

$unplaced = 0;
foreach ($sKtpLayout as $row) if (empty($row['placed'])) $unplaced++;
?>
<details class="pzk-subject-ktp" id="ktp-<?= (int) $s['id'] ?>">
    <summary class="pzk-subject-topics__summary">
        <?php if ($sKtp !== []): ?>
            📅 КТП (<?= $sKtpTariff ?> ч.) — развернуть
        <?php else: ?>
            📅 КТП — не сгенерировано
        <?php endif; ?>
    </summary>
    <div class="pzk-subject-topics__body">
        <?php if ($sKtp === []): ?>
            <p class="pzk-form__hint" style="margin:0 0 8px;">
                КТП ещё не сгенерировано. Темы для генерации берутся из списка тем
                предмета; уроки — из расписания семестра; праздники пропускаются.
                <?php if ($sTopics === []): ?>
                    Сначала добавьте темы — без них КТП будет пустым.
                <?php endif; ?>
            </p>
            <form method="post" action="index.php?action=admin_ktp_generate" style="margin:0;">
                <?= csrf_field() ?>
                <input type="hidden" name="subject_id" value="<?= (int) $s['id'] ?>">
                <button type="submit" class="pzk-btn pzk-btn--sm pzk-btn--primary"
                        <?= $sTopics === [] ? 'disabled' : '' ?>>
                    Сгенерировать КТП
                </button>
            </form>
        <?php else: ?>
            <div class="pzk-ktp__head <?= $sHoursMatch ? 'pzk-ktp__head--match' : '' ?>">
                <span class="pzk-ktp__title">📅 КТП</span>
                <span class="pzk-ktp__hours">
                    Часов по плану: <strong><?= $sPlanHours ?></strong>
                    / По тарификации: <strong><?= $sKtpTariff ?></strong>
                    / В КТП: <strong><?= $sKtpSlots ?></strong>
                    <?php if ($sHoursMatch): ?>
                        <span class="pzk-ktp__match-tag">✓ совпадает</span>
                    <?php endif; ?>
                </span>
                <div style="margin-left:auto; display:flex; gap:6px; flex-wrap:wrap;">
                    <form method="post" action="index.php?action=admin_ktp_generate" style="margin:0;"
                          title="Пересобрать КТП по актуальному расписанию. Ручные правки будут потеряны."
                          onsubmit="return confirm('Перегенерировать КТП? Если расписание изменилось — часы и уроки пересчитаются заново. Все ручные правки (удаления и «ещё одно») будут потеряны.');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="subject_id" value="<?= (int) $s['id'] ?>">
                        <button type="submit" class="pzk-btn pzk-btn--sm">Перегенерировать</button>
                    </form>
                </div>
            </div>

            <?php if ($renderRows === []): ?>
                <p class="pzk-form__hint" style="margin:6px 0;">
                    КТП пусто.
                </p>
            <?php else: ?>
                <div class="pzk-ktp-table-wrap">
                    <table class="pzk-ktp-table">
                        <thead>
                            <tr>
                                <th style="width:110px;">Номер п/п</th>
                                <th style="width:130px;">Дата</th>
                                <th>Тема урока</th>
                                <th style="width:150px;">Вид занятий</th>
                                <th style="width:190px;">Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($renderRows as $i => $row): ?>
                                <tr>
                                    <td>
                                        <?php if ($row['num_start'] === $row['num_end']): ?>
                                            <?= (int) $row['num_start'] ?>
                                        <?php else: ?>
                                            <?= (int) $row['num_start'] ?>-<?= (int) $row['num_end'] ?>
                                        <?php endif; ?>
                                    </td>
                                    <?php if (isset($dateSpans[$i])): ?>
                                        <?php $span = $dateSpans[$i]; ?>
                                        <td <?= $span > 1 ? 'rowspan="' . $span . '"' : '' ?>
                                            style="vertical-align:middle; white-space:nowrap;">
                                            <?= e(date('d.m.Y', strtotime($row['date']))) ?>
                                        </td>
                                    <?php endif; ?>
                                    <td><?= e($row['topic']) ?></td>
                                    <td><?= e($row['kind_label']) ?></td>
                                    <td class="pzk-table__actions">
                                        <form method="post" action="index.php?action=admin_ktp_add_more" style="margin:0;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                            <button type="submit" class="pzk-btn pzk-btn--sm">+ Ещё одно</button>
                                        </form>
                                        <form method="post" action="index.php?action=admin_ktp_delete" style="margin:0;"
                                              onsubmit="return confirm('Удалить тему «<?= e($row['topic']) ?>» из КТП?');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                            <button type="submit" class="pzk-btn pzk-btn--sm">Удалить</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($unplaced > 0): ?>
                    <p class="pzk-form__hint" style="margin:6px 0; color:#b91c1c;">
                        ⚠ Тем не хватает блоков в семестре: <?= $unplaced ?> тем(ы) не показаны.
                        Увеличьте количество уроков в расписании или удалите лишние темы.
                    </p>
                <?php endif; ?>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</details>