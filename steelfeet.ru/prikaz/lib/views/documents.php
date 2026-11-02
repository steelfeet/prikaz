<?php
declare(strict_types=1);
/**
 * Реестр документов. Подключается из index.php при ?page=documents.
 * Ожидает: $pdo, $viewRole, $currentUser, $universities, $products.
 */

$side = doc_side_for_role($viewRole);
if ($side === null) {
    echo '<div class="uni-empty">Раздел «Документы» недоступен для этой роли.</div>';
    return;
}

$tab = $_GET['tab'] ?? 'all';
if (!in_array($tab, ['all', 'inbox', 'outbox', 'overdue'], true)) {
    $tab = 'all';
}

$filters = [
    'type'   => $_GET['f_type']   ?? '',
    'status' => $_GET['f_status'] ?? '',
];

$docs = doc_fetch_list($pdo, $side, $tab, $filters);
$types = doc_types();
$statuses = doc_statuses();

$tabsMeta = [
    'all'     => 'Все',
    'inbox'   => 'На мне',
    'outbox'  => 'От меня',
    'overdue' => 'Просрочены',
];
?>
<div class="workspace docs-workspace">
    <section class="panel panel--docs">
        <div class="panel__head">
            <div>
                <h2 class="panel__title">Документы</h2>
                <p class="panel__subtitle">
                    Каждый документ — самостоятельный объект workflow: версии, две подписи, лента событий.
                    Шаги 4, 5, 6, 7, 10, 12 из ТЗ — это чекпоинты в жизни документа.
                </p>
            </div>
            <span class="docs-side-badge">сторона: <strong><?= $side === 'internal' ? 'ИТ Школа РТК' : 'Вуз' ?></strong></span>
        </div>

        <div class="docs-tabs" role="tablist">
            <?php foreach ($tabsMeta as $key => $label): ?>
                <a class="docs-tab <?= $tab === $key ? 'is-active' : '' ?>"
                   href="?page=documents&role=<?= e($viewRole) ?>&tab=<?= e($key) ?>">
                    <?= e($label) ?>
                    <?php if ($key === 'overdue'): ?>
                        <?php $overdueCount = count(doc_fetch_list($pdo, $side, 'overdue')); ?>
                        <?php if ($overdueCount > 0): ?><span class="docs-tab__badge"><?= $overdueCount ?></span><?php endif; ?>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>

        <form class="docs-filters" method="get" action="index.php">
            <input type="hidden" name="page" value="documents">
            <input type="hidden" name="role" value="<?= e($viewRole) ?>">
            <input type="hidden" name="tab" value="<?= e($tab) ?>">

            <label>
                Тип:
                <select name="f_type">
                    <option value="">— все —</option>
                    <?php foreach ($types as $code => $meta): ?>
                        <option value="<?= e($code) ?>" <?= ($filters['type'] === $code) ? 'selected' : '' ?>><?= e($meta['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                Статус:
                <select name="f_status">
                    <option value="">— все —</option>
                    <?php foreach ($statuses as $code => $meta): ?>
                        <option value="<?= e($code) ?>" <?= ($filters['status'] === $code) ? 'selected' : '' ?>><?= e($meta['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <button type="submit" class="dash-toolbar__btn is-active">Применить</button>
            <a class="docs-filter-reset" href="?page=documents&role=<?= e($viewRole) ?>&tab=<?= e($tab) ?>">Сбросить</a>
        </form>

        <?php if ($docs === []): ?>
            <div class="uni-empty">По выбранным фильтрам документов нет.</div>
        <?php else: ?>
            <div class="docs-table-wrap">
                <table class="docs-table">
                    <thead>
                        <tr>
                            <th>Документ</th>
                            <th>Вуз · продукт</th>
                            <th>Тип</th>
                            <th>Версия</th>
                            <th>Статус</th>
                            <th>У кого</th>
                            <th>Срок</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($docs as $doc): ?>
                            <?php
                            $typeMeta = $types[$doc['type']] ?? ['label' => $doc['type'], 'icon' => '📄'];
                            $statusMeta = $statuses[$doc['status']] ?? ['label' => $doc['status'], 'color' => '#6b7280'];
                            $statusLabel = doc_status_label($doc['status'], $doc['type']);
                            $isOverdue = doc_is_overdue($doc);
                            $ownerLabel = $doc['owner_side'] === 'internal' ? 'ИТ Школа' : 'Вуз';
                            $ownerIsMine = ($doc['owner_side'] === $side);
                            ?>
                            <tr class="docs-row <?= $isOverdue ? 'docs-row--overdue' : '' ?>">
                                <td>
                                    <a class="docs-row__title" href="?page=document&role=<?= e($viewRole) ?>&id=<?= (int) $doc['id'] ?>">
                                        <span class="docs-row__icon"><?= $typeMeta['icon'] ?></span>
                                        <?= e($doc['title']) ?>
                                    </a>
                                </td>
                                <td class="docs-row__meta">
                                    <?= e($doc['uni_name']) ?><br>
                                    <span class="docs-row__meta-sub"><?= e($doc['product_name']) ?></span>
                                </td>
                                <td>
                                    <span class="docs-badge docs-badge--type"><?= e($typeMeta['label']) ?></span>
                                </td>
                                <td>
                                    v<?= (int) ($doc['last_version'] ?? 1) ?>
                                    <span class="docs-row__meta-sub">(<?= (int) ($doc['versions_count'] ?? 1) ?> шт.)</span>
                                </td>
                                <td>
                                    <span class="docs-status" style="--status-color: <?= e($statusMeta['color']) ?>">
                                        <?= e($statusLabel) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="docs-owner <?= $ownerIsMine ? 'docs-owner--mine' : '' ?>">
                                        <?= $ownerIsMine ? '◆ ' : '' ?><?= e($ownerLabel) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($doc['deadline'])): ?>
                                        <span class="<?= $isOverdue ? 'docs-deadline docs-deadline--overdue' : 'docs-deadline' ?>">
                                            <?= e($doc['deadline']) ?>
                                            <?php if ($isOverdue): ?> · просрочен<?php endif; ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="docs-row__meta-sub">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a class="docs-row__open" href="?page=document&role=<?= e($viewRole) ?>&id=<?= (int) $doc['id'] ?>">Открыть →</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <div class="docs-legend">
            <span class="docs-legend__item"><span class="docs-status" style="--status-color: #2563eb">Отправлен</span> ожидает реакции второй стороны</span>
            <span class="docs-legend__item"><span class="docs-status" style="--status-color: #8b6914">На рассмотрении</span> вуз изучает</span>
            <span class="docs-legend__item"><span class="docs-status" style="--status-color: #16a34a">Согласован</span> обе стороны ещё подписывают</span>
            <span class="docs-legend__item"><span class="docs-status" style="--status-color: #0d9488">Действует</span> обе подписи есть</span>
        </div>
    </section>
</div>