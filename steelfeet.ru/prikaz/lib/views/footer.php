<?php
declare(strict_types=1);
?>
<footer class="no-reco-note" role="note" aria-label="Уведомление об отсутствии рекомендательных технологий">
    <span class="no-reco-note__icon" aria-hidden="true">ⓘ</span>
    <span>
        Сервис <strong>не использует рекомендательные технологии</strong>
        при предоставлении информации и не применяет профилирование пользователей.
    </span>
</footer>

<div class="cookie-banner" id="cookie-banner" role="dialog" aria-live="polite" aria-label="Использование cookie-файлов">
    <div class="cookie-banner__icon" aria-hidden="true">🍪</div>
    <div class="cookie-banner__body">
        <div class="cookie-banner__title">Мы используем cookie-файлы</div>
        <div class="cookie-banner__text">
            Сайт использует <strong>технические cookie-файлы</strong> для корректной работы
            авторизации и интерфейса. Персональные данные не передаются третьим лицам.
            Рекомендательные технологии не применяются.
            Продолжая пользоваться сервисом, вы соглашаетесь с использованием cookie.
        </div>
    </div>
    <div class="cookie-banner__actions">
        <button type="button" class="cookie-banner__btn cookie-banner__btn--ghost" id="cookie-banner-decline">
            Только необходимые
        </button>
        <button type="button" class="cookie-banner__btn" id="cookie-banner-accept">
            Принять
        </button>
    </div>
</div>

<?php if (isset($pdo, $viewRole)): ?>
    <?php require __DIR__ . '/help_widget.php'; ?>
<?php endif; ?>

<script>
(function () {
    'use strict';
    var root = document.querySelector('[data-help-root]');
    if (!root) return;

    var fab = root.querySelector('[data-help-toggle]');
    var panel = root.querySelector('[data-help-panel]');
    var closeBtn = root.querySelector('[data-help-close]');

    function open() { root.classList.add('is-open'); panel.setAttribute('aria-hidden', 'false'); }
    function close() { root.classList.remove('is-open'); panel.setAttribute('aria-hidden', 'true'); }
    fab.addEventListener('click', function () { root.classList.contains('is-open') ? close() : open(); });
    closeBtn.addEventListener('click', close);
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });

    root.querySelectorAll('[data-help-article-toggle]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            btn.closest('.help-article').classList.toggle('is-open');
        });
    });
})();
</script>