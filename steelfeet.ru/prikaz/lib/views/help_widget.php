<?php
declare(strict_types=1);
/**
 * Плавающая кнопка «?» + сайдбар со статьями + popup-feedback.
 * Ожидает: $pdo, $viewRole.
 */

$roleKey = $viewRole;
try {
    $stmt = $pdo->prepare(
        "SELECT id, slug, title, body_md
         FROM help_articles
         WHERE role_visibility IN ('all', :role)
         ORDER BY id"
    );
    $stmt->execute([':role' => $roleKey]);
    $articles = $stmt->fetchAll();
} catch (Throwable $e) {
    $articles = [];
}
if ($articles === []) {
    return;
}
?>
<div class="help-widget" data-help-root>
    <button type="button" class="help-fab" aria-label="Открыть справку"
            data-help-toggle>?</button>

    <aside class="help-panel" data-help-panel aria-hidden="true">
        <header class="help-panel__head">
            <div>
                <div class="help-panel__title">Справка</div>
                <div class="help-panel__subtitle">по текущей роли: <strong><?= e($roleKey) ?></strong></div>
            </div>
            <button type="button" class="help-panel__close" data-help-close aria-label="Закрыть">×</button>
        </header>

        <div class="help-panel__body">
            <?php foreach ($articles as $a): ?>
                <article class="help-article" data-help-article>
                    <button type="button" class="help-article__head" data-help-article-toggle>
                        <span class="help-article__title"><?= e($a['title']) ?></span>
                        <span class="help-article__chev">▾</span>
                    </button>
                    <div class="help-article__body">
                        <div class="help-article__content"><?= nl2br(e($a['body_md'])) ?></div>

                        <form class="help-feedback" method="post" action="?action=help_feedback">
                            <input type="hidden" name="article_id" value="<?= (int) $a['id'] ?>">
                            <input type="text" name="body" placeholder="Что улучшить в этой статье?" maxlength="500">
                            <button type="submit" class="doc-btn doc-btn--sm">Отправить</button>
                        </form>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </aside>
</div>