<script>
(function () {
    'use strict';
    var root = document.querySelector('[data-help-root]');
    if (!root) return;

    var fab      = root.querySelector('[data-help-toggle]');
    var panel    = root.querySelector('[data-help-panel]');
    var closeBtn = root.querySelector('[data-help-close]');

    if (!fab || !panel || !closeBtn) return;

    function open() {
        root.classList.add('is-open');
        panel.setAttribute('aria-hidden', 'false');
    }
    function close() {
        root.classList.remove('is-open');
        panel.setAttribute('aria-hidden', 'true');
    }

    fab.addEventListener('click', function () {
        root.classList.contains('is-open') ? close() : open();
    });
    closeBtn.addEventListener('click', close);
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') close();
    });

    root.querySelectorAll('[data-help-article-toggle]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var art = btn.closest('.help-article');
            if (art) art.classList.toggle('is-open');
        });
    });
})();
</script>