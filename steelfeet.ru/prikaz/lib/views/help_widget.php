<?php
declare(strict_types=1);
if (empty($helpArticles)) return;
$roleKey = $viewRole ?? 'all';
?>
<style>
/* =============================================================================
   Виджет справки на <details> — открывается/закрывается без JS.
   Никакой зависимости от styles.css, prikaz.css или внешних скриптов.
   ============================================================================= */

.help-widget {
    position: fixed;
    right: 24px;
    bottom: 24px;
    z-index: 5000;
    font-family: 'Inter', -apple-system, sans-serif;
}
.help-fab {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 48px;
    height: 48px;
    margin: 0;
    padding: 0;
    border: none;
    border-radius: 50%;
    background: #8b6914;
    color: #fff;
    font-family: 'Cormorant Garamond', serif;
    font-size: 1.5rem;
    font-weight: 600;
    line-height: 1;
    cursor: pointer;
    list-style: none;
    box-shadow: 0 4px 12px rgba(139, 105, 20, 0.35), 0 12px 24px rgba(0, 0, 0, 0.15);
    transition: background-color 0.2s, transform 0.2s, box-shadow 0.2s;
}
.help-fab::-webkit-details-marker { display: none; }
.help-fab::marker { content: ''; }
.help-fab:hover {
    background: #a67c00;
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(139, 105, 20, 0.4), 0 16px 32px rgba(0, 0, 0, 0.18);
}
.help-widget[open] > .help-fab {
    background: #6b5010;
}

.help-panel {
    display: none;
    position: fixed;
    top: 80px;
    right: 24px;
    bottom: 90px;
    width: 420px;
    max-width: calc(100vw - 48px);
    background: #fff;
    border: 1px solid rgba(26, 26, 26, 0.15);
    border-left: 4px solid #8b6914;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15), 0 24px 48px rgba(0, 0, 0, 0.1);
    flex-direction: column;
    z-index: 4999;
    box-sizing: border-box;
}
.help-widget[open] > .help-panel {
    display: flex;
}

.help-panel__head {
    padding: 14px 16px;
    border-bottom: 1px solid rgba(26, 26, 26, 0.08);
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 8px;
    flex-shrink: 0;
}
.help-panel__title {
    font-family: 'Cormorant Garamond', serif;
    font-size: 1.2rem;
    font-weight: 500;
    color: #1a1a1a;
    line-height: 1.2;
}
.help-panel__subtitle {
    font-size: 0.72rem;
    color: #777;
    margin-top: 2px;
}
.help-panel__close {
    background: none;
    border: none;
    font-size: 1.4rem;
    color: #777;
    cursor: pointer;
    padding: 0;
    line-height: 1;
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.help-panel__close:hover { color: #1a1a1a; }

.help-panel__body {
    flex: 1;
    overflow-y: auto;
    padding: 8px;
    min-height: 0;
}

.help-article {
    border-bottom: 1px solid rgba(26, 26, 26, 0.06);
}
.help-article:last-child {
    border-bottom: none;
}
.help-article__head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 8px;
    padding: 10px 8px;
    cursor: pointer;
    list-style: none;
    font-family: 'Inter', sans-serif;
    font-size: 0.86rem;
    color: #1a1a1a;
    font-weight: 500;
    line-height: 1.4;
}
.help-article__head::-webkit-details-marker { display: none; }
.help-article__head::marker { content: ''; }
.help-article__head:hover {
    background: rgba(139, 105, 20, 0.04);
}
.help-article__title { flex: 1; min-width: 0; }
.help-article__chev {
    color: #777;
    font-size: 0.9rem;
    flex-shrink: 0;
    transition: transform 0.2s;
}
.help-article[open] .help-article__chev {
    transform: rotate(180deg);
}

.help-article__body {
    padding: 0 8px 12px;
    font-size: 0.8rem;
    color: #2d2d2d;
    line-height: 1.55;
}
.help-article__content {
    margin-bottom: 10px;
    white-space: pre-wrap;
}

.help-feedback {
    display: flex;
    gap: 6px;
}
.help-feedback input {
    flex: 1;
    padding: 5px 8px;
    font-family: 'Inter', sans-serif;
    font-size: 0.78rem;
    border: 1px solid rgba(26, 26, 26, 0.15);
    box-sizing: border-box;
}
.help-feedback button {
    padding: 5px 10px;
    font-family: 'Inter', sans-serif;
    font-size: 0.75rem;
    border: 1px solid #8b6914;
    background: #8b6914;
    color: #fff;
    cursor: pointer;
    flex-shrink: 0;
}
.help-feedback button:hover { background: #a67c00; }

@media (max-width: 720px) {
    .help-widget { right: 16px; bottom: 16px; }
    .help-panel {
        top: 60px;
        right: 12px;
        left: 12px;
        width: auto;
        bottom: 80px;
    }
}
</style>

<details class="help-widget" data-help-root>
    <summary class="help-fab" aria-label="Открыть справку">?</summary>

    <aside class="help-panel">
        <header class="help-panel__head">
            <div>
                <div class="help-panel__title">Справка</div>
                <div class="help-panel__subtitle">роль: <strong><?= e($roleKey) ?></strong></div>
            </div>
            <button type="button" class="help-panel__close" data-help-close aria-label="Закрыть">×</button>
        </header>

        <div class="help-panel__body">
            <?php foreach ($helpArticles as $a): ?>
                <details class="help-article">
                    <summary class="help-article__head">
                        <span class="help-article__title"><?= e($a['title']) ?></span>
                        <span class="help-article__chev">▾</span>
                    </summary>
                    <div class="help-article__body">
                        <div class="help-article__content"><?= e($a['body_md']) ?></div>
                        <form class="help-feedback" method="post" action="index.php?action=help_feedback">
                            <?= csrf_field() ?>
                            <input type="hidden" name="article_id" value="<?= (int) $a['id'] ?>">
                            <input type="text" name="body" placeholder="Что улучшить в этой статье?" maxlength="500">
                            <button type="submit">Отправить</button>
                        </form>
                    </div>
                </details>
            <?php endforeach; ?>
        </div>
    </aside>
</details>

<script>
(function () {
    'use strict';
    var root = document.querySelector('[data-help-root]');
    if (!root) return;

    // Кнопка «×» в шапке закрывает панель.
    var closeBtn = root.querySelector('[data-help-close]');
    if (closeBtn) {
        closeBtn.addEventListener('click', function () {
            root.open = false;
        });
    }

    // Клик вне панели и FAB — закрыть.
    document.addEventListener('click', function (e) {
        if (!root.open) return;
        if (root.contains(e.target)) return;
        root.open = false;
    });

    // Escape — закрыть.
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && root.open) {
            root.open = false;
        }
    });
})();
</script>