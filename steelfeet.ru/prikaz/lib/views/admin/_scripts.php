<?php
declare(strict_types=1);
?>
<script>
(function () {
    'use strict';

    // ===== Раскрытие <details> по кнопкам [data-open-details] =====
    document.querySelectorAll('[data-open-details]').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            var href = btn.getAttribute('href') || '';
            if (href.charAt(0) !== '#') return;
            var target = document.getElementById(href.slice(1));
            if (!target) return;
            e.preventDefault();
            if (target.tagName === 'DETAILS') {
                target.open = true;
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                setTimeout(function () {
                    var first = target.querySelector('input, select, textarea');
                    if (first) first.focus();
                }, 350);
            }
        });
    });

    // ===== Sticky-шапка КТП: синхронизация высоты =====
    // Шапка (.pzk-ktp__head) приклеена к top: 0. Заголовки таблицы
    // (.pzk-ktp-table thead th) — к top: var(--ktp-head-h). Высота шапки
    // зависит от переноса кнопок, поэтому меряем её и складываем в
    // CSS-переменную на самом details.
    function syncKtpHeadHeight(details) {
        if (!details) return;
        var head = details.querySelector('.pzk-ktp__head');
        if (!head) {
            details.style.removeProperty('--ktp-head-h');
            return;
        }
        details.style.setProperty('--ktp-head-h', head.getBoundingClientRect().height + 'px');
    }

    function initKtpBlock(details) {
        if (!details || details.dataset.ktpInited === '1') return;
        details.dataset.ktpInited = '1';
        syncKtpHeadHeight(details);
        details.addEventListener('toggle', function () {
            syncKtpHeadHeight(details);
        });
        if (typeof ResizeObserver !== 'undefined') {
            var head = details.querySelector('.pzk-ktp__head');
            if (head) {
                new ResizeObserver(function () {
                    syncKtpHeadHeight(details);
                }).observe(head);
            }
        }
    }

    document.querySelectorAll('.pzk-subject-ktp').forEach(initKtpBlock);

    window.addEventListener('resize', function () {
        document.querySelectorAll('.pzk-subject-ktp').forEach(syncKtpHeadHeight);
    });

    // ===== AJAX для КТП =====
    // Перехватываем submit у форм внутри .pzk-subject-ktp. Сервер возвращает
    // JSON {ok, html}, JS заменяет блок целиком. Rучные правки («ещё одно»,
    // «удалить») и генерация/перегенерация проходят без перезагрузки.
    document.addEventListener('submit', function (e) {
        var form = e.target;
        var block = form.closest('.pzk-subject-ktp');
        if (!block) return;

        e.preventDefault();

        if (form.dataset.busy === '1') return;
        form.dataset.busy = '1';

        var fd = new FormData(form);
        fd.set('ajax', '1');

        fetch(form.action, {
            method: 'POST',
            body: fd,
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
        .then(function (r) {
            if (!r.ok) throw new Error('HTTP ' + r.status);
            return r.json();
        })
        .then(function (data) {
            if (!data.ok) {
                alert(data.error || 'Не удалось выполнить действие.');
                return;
            }
            var wasOpen = block.open;
            var temp = document.createElement('div');
            temp.innerHTML = (data.html || '').trim();
            var newBlock = temp.firstElementChild;
            if (!newBlock) return;
            newBlock.open = wasOpen;
            block.replaceWith(newBlock);
            initKtpBlock(newBlock);
        })
        .catch(function (err) {
            alert('Ошибка сети: ' + err.message);
        })
        .finally(function () {
            form.dataset.busy = '';
        });
    });
})();
</script>