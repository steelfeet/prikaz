<?php
declare(strict_types=1);

/**
 * Partial: блок КТП для одного предмета.
 *
 * Ожидает $s — массив с полями:
 *   id, short_name, full_name, hours, group_name, year_name,
 *   topics, ktp, ktp_layout, ktp_tariff, ktp_slots,
 *   ktp_fact_hours, ktp_holiday_losses, ktp_blocks_count
 *
 * В шапке: «Часов по учебному плану / По тарификации / В КТП /
 * Фактически часов», список праздников, фильтр по виду занятий
 * (Все / Лекция / Практикум) и кнопка «Перегенерировать».
 *
 * Фильтр работает на клиенте: JS скрывает строки неподходящего вида и
 * заполняет столбец «№ по типу» номерами уроков этого типа. Нумерация
 * учитывает слоты блока: пара слотов даёт «23-24», один слот — «23».
 * При фильтре «Все» столбец скрыт, даты объединены через rowspan.
 */

$sTopics        = $s['topics']             ?? [];
$sKtp           = $s['ktp']                ?? [];
$sKtpLayout     = $s['ktp_layout']         ?? [];
$sKtpTariff     = (int) ($s['ktp_tariff']  ?? 0);
$sKtpSlots      = (int) ($s['ktp_slots']   ?? 0);
$sPlanHours     = (int) $s['hours'];
$sFactHours     = (int) ($s['ktp_fact_hours']     ?? $sPlanHours);
$sHolidayLosses = $s['ktp_holiday_losses'] ?? [];
$sHoursMatch    = ($sPlanHours > 0 && $sKtpTariff === $sPlanHours);

$sSlotsFactMatch = ($sKtpSlots > 0 && $sKtpSlots === $sFactHours);

$renderRows = [];
foreach ($sKtpLayout as $row) {
    if (!empty($row['placed'])) $renderRows[] = $row;
}

$unplaced = 0;
foreach ($sKtpLayout as $row) if (empty($row['placed'])) $unplaced++;
?>
<details class="pzk-subject-ktp" id="ktp-<?= (int) $s['id'] ?>">
    <summary class="pzk-subject-topics__summary">
        <?php if ($sKtp !== []): ?>
            📅 КТП (<?= $sKtpSlots ?> ч.) — развернуть
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
                    Часов по учебному плану: <strong><?= $sPlanHours ?></strong>
                    / По тарификации: <strong><?= $sKtpTariff ?></strong>
                    / <span class="<?= $sSlotsFactMatch ? 'pzk-ktp__match-value' : '' ?>">В КТП: <strong><?= $sKtpSlots ?></strong></span>
                    / <span class="<?= $sSlotsFactMatch ? 'pzk-ktp__match-value' : '' ?>">Фактически часов: <strong><?= $sFactHours ?></strong></span>
                    <?php if ($sHoursMatch): ?>
                        <span class="pzk-ktp__match-tag">✓ совпадает</span>
                    <?php endif; ?>
                </span>
                <?php if ($sHolidayLosses !== []): ?>
                    <span class="pzk-ktp__holidays">
                        Праздники:
                        <?php
                            $parts = [];
                            foreach ($sHolidayLosses as $date => $hours) {
                                $parts[] = e(date('d.m', strtotime((string) $date))) . ' — ' . (int) $hours . ' ч.';
                            }
                            echo implode(', ', $parts);
                        ?>
                    </span>
                <?php endif; ?>
                <div style="margin-left:auto; display:flex; gap:6px; flex-wrap:wrap; align-items:center;">
                    <select class="pzk-ktp-filter pzk-field__input pzk-field__input--sm"
                            style="width:auto; min-width:140px;"
                            title="Фильтр по виду занятий">
                        <option value="all">Все занятия</option>
                        <option value="group">Лекция</option>
                        <option value="sub">Практикум</option>
                    </select>
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
                                <th class="pzk-ktp-type-num" style="width:90px; display:none;">№ по типу</th>
                                <th style="width:130px;">Дата</th>
                                <th>Тема урока</th>
                                <th style="width:150px;">Вид занятий</th>
                                <th style="width:190px;">Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($renderRows as $row): ?>
                                <?php
                                    $rowKind    = (string) $row['kind'];
                                    $rowDateIso = (string) $row['date'];
                                    $rowDateRu  = date('d.m.Y', strtotime($rowDateIso));
                                    $rowSlots   = max(1, (int) $row['slots']);
                                ?>
                                <tr data-kind="<?= e($rowKind) ?>"
                                    data-date="<?= e($rowDateIso) ?>"
                                    data-slots="<?= $rowSlots ?>">
                                    <td>
                                        <?php if ($row['num_start'] === $row['num_end']): ?>
                                            <?= (int) $row['num_start'] ?>
                                        <?php else: ?>
                                            <?= (int) $row['num_start'] ?>-<?= (int) $row['num_end'] ?>
                                        <?php endif; ?>
                                    </td>
                                    <td class="pzk-ktp-type-num" style="display:none;"></td>
                                    <td class="pzk-ktp-date" data-date="<?= e($rowDateIso) ?>"
                                        style="vertical-align:middle; white-space:nowrap;">
                                        <?= e($rowDateRu) ?>
                                    </td>
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