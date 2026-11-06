<?php
declare(strict_types=1);
?>
<section class="panel">
    <div class="panel__head">
        <div>
            <h2 class="panel__title">Аудит</h2>
            <p class="panel__subtitle">Последние 100 действий: логины, оценки, смены фаз, действия админа. С IP и временем.</p>
        </div>
    </div>
    <div class="pzk-table-wrap" style="max-height:600px;">
        <table class="pzk-table pzk-table--audit">
            <thead>
                <tr>
                    <th style="width:150px;">Время</th>
                    <th style="width:110px;">Пользователь</th>
                    <th style="width:150px;">Действие</th>
                    <th style="width:150px;">Объект</th>
                    <th>Мета</th>
                    <th style="width:110px;">IP</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($adminAudit as $a): ?>
                    <tr>
                        <td style="white-space:nowrap;"><?= e($a['created_at']) ?></td>
                        <td><?= e((string) ($a['login'] ?? '—')) ?></td>
                        <td><code><?= e($a['action']) ?></code></td>
                        <td>
                            <?php if (!empty($a['target_type'])): ?>
                                <?= e($a['target_type']) ?>
                                <?php if (!empty($a['target_id'])): ?>#<?= (int) $a['target_id'] ?><?php endif; ?>
                            <?php else: ?>
                                <span style="color:var(--color-tertiary);">—</span>
                            <?php endif; ?>
                        </td>
                        <td style="font-size:0.78rem; color:var(--color-tertiary);">
                            <?= e((string) ($a['meta'] ?? '')) ?>
                        </td>
                        <td><code class="pzk-ip"><?= e((string) ($a['ip'] ?? '')) ?></code></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($adminAudit === []): ?>
                    <tr><td colspan="6" class="pzk-table__empty">Записей пока нет.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>