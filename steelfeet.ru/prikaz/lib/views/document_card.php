<?php
declare(strict_types=1);
/**
 * Карточка документа: версии, лента, подписи, действия.
 * Ожидает: $pdo, $viewRole, $currentUser.
 */

$side = doc_side_for_role($viewRole);
if ($side === null) {
    echo '<div class="uni-empty">Раздел «Документы» недоступен для этой роли.</div>';
    return;
}

$docId = (int) ($_GET['id'] ?? 0);
$doc = doc_fetch_card($pdo, $docId);
if (!$doc) {
    echo '<div class="uni-empty">Документ не найден.</div>';
    return;
}

$versions = doc_versions($pdo, $docId);
$events = doc_events($pdo, $docId);
$signatures = doc_signatures($pdo, $docId);
$signatureCount = count($signatures);

$types = doc_types();
$statuses = doc_statuses();
$typeMeta = $types[$doc['type']] ?? ['label' => $doc['type'], 'icon' => '📄', 'linked_steps' => []];
$statusMeta = $statuses[$doc['status']] ?? ['label' => $doc['status'], 'color' => '#6b7280'];
$statusLabel = doc_status_label($doc['status'], $doc['type']);
$isOverdue = doc_is_overdue($doc);

$actions = doc_available_actions($doc['status'], $side, $signatureCount, $doc['type']);

$currentUserId = (int) $currentUser['id'];
$alreadySigned = false;
foreach ($signatures as $s) {
    if ((int) $s['user_id'] === $currentUserId) { $alreadySigned = true; break; }
}

$flash = $_SESSION['doc_flash'] ?? null;
unset($_SESSION['doc_flash']);
?>
<div class="workspace docs-workspace">
    <?php if ($flash !== null): ?>
        <div class="notification notification--<?= $flash['type'] === 'success' ? 'success' : 'danger' ?>">
            <span class="notification__icon"><?= $flash['type'] === 'success' ? '✓' : '✕' ?></span>
            <div class="notification__body"><?= e($flash['message']) ?></div>
        </div>
    <?php endif; ?>

    <section class="panel doc-card">
        <div class="doc-card__top">
            <a class="doc-card__back" href="?page=documents&role=<?= e($viewRole) ?>">← Все документы</a>
        </div>

        <header class="doc-card__header">
            <div class="doc-card__title-wrap">
                <h2 class="doc-card__title">
                    <span class="doc-card__icon"><?= $typeMeta['icon'] ?></span>
                    <?= e($doc['title']) ?>
                </h2>
                <p class="doc-card__meta">
                    <?= e($typeMeta['label']) ?>
                    · <?= e($doc['uni_name']) ?>
                    · <?= e($doc['product_name']) ?>
                    <?php if (!empty($doc['deadline'])): ?>
                        · срок: <span class="<?= $isOverdue ? 'docs-deadline docs-deadline--overdue' : '' ?>"><?= e($doc['deadline']) ?><?= $isOverdue ? ' (просрочен)' : '' ?></span>
                    <?php endif; ?>
                </p>
            </div>
            <div class="doc-card__status">
                <span class="docs-status docs-status--lg" style="--status-color: <?= e($statusMeta['color']) ?>">
                    <?= e($statusLabel) ?>
                </span>
                <span class="doc-card__owner">
                    сейчас у: <strong><?= $doc['owner_side'] === 'internal' ? 'ИТ Школа' : 'Вуз' ?></strong>
                </span>
            </div>
        </header>

        <div class="doc-card__body">
            <!-- Левая колонка: версии -->
            <div class="doc-card__col doc-card__col--versions">
                <h3 class="doc-section-title">Версии</h3>
                <?php if ($versions === []): ?>
                    <div class="uni-empty">Версий пока нет.</div>
                <?php else: ?>
                    <ol class="doc-versions">
                        <?php foreach ($versions as $i => $v): ?>
                            <?php
                            $isLatest = ($i === 0);
                            $sigOnThisVersion = array_filter($signatures, fn($s) => (int) $s['version_id'] === (int) $v['id']);
                            ?>
                            <li class="doc-version <?= $isLatest ? 'doc-version--latest' : '' ?>">
                                <div class="doc-version__head">
                                    <strong>v<?= (int) $v['version'] ?></strong>
                                    <span class="doc-version__date"><?= e($v['uploaded_at']) ?></span>
                                </div>
                                <div class="doc-version__file">
                                    <span class="doc-version__file-icon">📎</span>
                                    <a class="doc-version__file-link"
                                       href="?action=doc_download&id=<?= (int) $doc['id'] ?>&v=<?= (int) $v['version'] ?>">
                                        <?= e($v['file_name']) ?>
                                    </a>
                                    <span class="doc-version__file-size"><?= number_format((int) $v['file_size'] / 1024, 1) ?> КБ</span>
                                </div>
                                <div class="doc-version__meta">
                                    загрузил: <?= e($v['uploader_name'] ?? $v['uploader_login'] ?? '—') ?>
                                </div>
                                <?php if (!empty($v['comment'])): ?>
                                    <div class="doc-version__comment"><?= e($v['comment']) ?></div>
                                <?php endif; ?>
                                <?php if (count($sigOnThisVersion) > 0): ?>
                                    <div class="doc-version__sigs">
                                        <?php foreach ($sigOnThisVersion as $s): ?>
                                            <span class="doc-sig-chip">✍ <?= e($s['user_name'] ?? $s['user_login'] ?? '—') ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>

                <?php if ($doc['status'] !== 'archived'): ?>
                    <form class="doc-upload" method="post" action="?action=doc_upload_version"
                          enctype="multipart/form-data">
                        <input type="hidden" name="document_id" value="<?= (int) $doc['id'] ?>">
                        <h4 class="doc-section-title doc-section-title--sm">Загрузить новую версию</h4>
                        <input class="doc-upload__file" type="file" name="file" required
                               accept=".pdf,.png,.jpeg,.jpg,.zip,.gzip,.rar,.doc,.docx,.xls,.xlsx,.gz">
                        <input class="doc-upload__comment" type="text" name="comment"
                               placeholder="Комментарий к версии (что изменилось)">
                        <button type="submit" class="doc-btn doc-btn--primary">Загрузить</button>
                    </form>
                <?php endif; ?>
            </div>

            <!-- Правая колонка: лента событий -->
            <div class="doc-card__col doc-card__col--events">
                <h3 class="doc-section-title">Лента событий</h3>
                <?php if ($events === []): ?>
                    <div class="uni-empty">Событий пока нет.</div>
                <?php else: ?>
                    <ol class="doc-events">
                        <?php foreach ($events as $ev): ?>
                            <?php
                            $actionLabels = [
                                'uploaded'         => 'загрузил(а) версию',
                                'sent'             => 'отправил(а) второй стороне',
                                'accept_review'    => 'взял(а) на рассмотрение',
                                'approve'          => 'согласовал(а)',
                                'request_revision' => 'вернул(а) на доработку',
                                'sign'             => 'подписал(а)',
                                'archive'          => 'отправил(а) в архив',
                                'comment'          => 'оставил(а) комментарий',
                            ];
                            $actionLabel = $actionLabels[$ev['action']] ?? $ev['action'];
                            ?>
                            <li class="doc-event">
                                <div class="doc-event__head">
                                    <span class="doc-event__side doc-event__side--<?= e($ev['actor_side']) ?>">
                                        <?= $ev['actor_side'] === 'internal' ? 'ИТ Школа' : 'Вуз' ?>
                                    </span>
                                    <span class="doc-event__actor"><?= e($ev['actor_name'] ?? $ev['actor_login'] ?? '—') ?></span>
                                    <span class="doc-event__action"><?= e($actionLabel) ?></span>
                                    <?php if (!empty($ev['version_num'])): ?>
                                        <span class="doc-event__version">v<?= (int) $ev['version_num'] ?></span>
                                    <?php endif; ?>
                                    <span class="doc-event__date"><?= e($ev['created_at']) ?></span>
                                </div>
                                <?php if (!empty($ev['comment'])): ?>
                                    <div class="doc-event__comment"><?= e($ev['comment']) ?></div>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>

                <?php if ($doc['status'] !== 'archived'): ?>
                    <form class="doc-comment-form" method="post" action="?action=doc_comment">
                        <input type="hidden" name="document_id" value="<?= (int) $doc['id'] ?>">
                        <textarea name="comment" placeholder="Оставить комментарий к документу..." required></textarea>
                        <button type="submit" class="doc-btn">Отправить</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- Подписи -->
        <div class="doc-card__signatures">
            <h3 class="doc-section-title">Подписи <span class="doc-signatures-count"><?= $signatureCount ?> / 2</span></h3>
            <?php if ($signatureCount === 0): ?>
                <p class="doc-signatures__empty">Документ пока не подписан ни одной стороной.</p>
            <?php else: ?>
                <div class="doc-signatures">
                    <?php foreach ($signatures as $s): ?>
                        <div class="doc-signature doc-signature--<?= e($s['side']) ?>">
                            <span class="doc-signature__icon">✍</span>
                            <div class="doc-signature__body">
                                <strong><?= e($s['user_name'] ?? $s['user_login'] ?? '—') ?></strong>
                                <span class="doc-signature__side"><?= $s['side'] === 'internal' ? 'ИТ Школа' : 'Вуз' ?></span>
                                <span class="doc-signature__meta">
                                    v<?= (int) ($s['version_num'] ?? 1) ?>
                                    · <?= e($s['signed_at']) ?>
                                    · hash: <?= e(substr($s['file_hash'] ?? '', 0, 12)) ?>…
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Действия -->
        <?php if ($actions !== []): ?>
            <div class="doc-card__actions">
                <?php foreach ($actions as $a): ?>
                    <?php if (!empty($a['is_sign']) && $alreadySigned): ?>
                        <span class="doc-btn doc-btn--ghost" title="Вы уже подписали этот документ">
                            ✓ Вы подписали
                        </span>
                    <?php elseif (!empty($a['is_sign'])): ?>
                        <form method="post" action="?action=doc_sign" style="display:inline;">
                            <input type="hidden" name="document_id" value="<?= (int) $doc['id'] ?>">
                            <button type="submit" class="doc-btn doc-btn--primary">✍ <?= e($a['label']) ?></button>
                        </form>
                    <?php else: ?>
                        <form method="post" action="?action=doc_transition" style="display:inline;">
                            <input type="hidden" name="document_id" value="<?= (int) $doc['id'] ?>">
                            <input type="hidden" name="doc_action" value="<?= e($a['action']) ?>">
                            <?php if (!empty($a['requires_version'])): ?>
                                <button type="submit" class="doc-btn doc-btn--primary"
                                        onclick="return confirm('Убедитесь, что вы загрузили новую версию. Продолжить?');">
                                    <?= e($a['label']) ?>
                                </button>
                            <?php else: ?>
                                <button type="submit" class="doc-btn <?= !empty($a['primary']) ? 'doc-btn--primary' : '' ?>">
                                    <?= e($a['label']) ?>
                                </button>
                            <?php endif; ?>
                        </form>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="doc-card__actions doc-card__actions--empty">
                <?php if ($doc['status'] === 'active'): ?>
                    Документ действует. Обе стороны подписали. При необходимости — загрузите новую версию или отправьте в архив.
                <?php elseif ($doc['status'] === 'archived'): ?>
                    Документ в архиве.
                <?php elseif ($doc['owner_side'] !== $side): ?>
                    Сейчас документ у второй стороны. Действия появятся, когда он вернётся к вам.
                <?php else: ?>
                    Действий пока нет.
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </section>
</div>