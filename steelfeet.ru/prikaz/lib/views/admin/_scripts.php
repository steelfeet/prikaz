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

    // ===== Inline-редактирование =====
    function openInline(id, focusSelector, groupSelector) {
        var d = document.getElementById(id);
        if (!d) return;
        document.querySelectorAll(groupSelector + '[open]').forEach(function (other) {
            if (other !== d) other.open = false;
        });
        d.open = true;
        var field = d.querySelector(focusSelector);
        if (field) field.focus();
        d.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    document.addEventListener('click', function (e) {
        var subjBtn = e.target.closest('[data-open-subject-edit]');
        if (subjBtn) {
            openInline(
                subjBtn.getAttribute('data-open-subject-edit'),
                'input:not([type="hidden"]), select, textarea',
                'details.pzk-subject-edit'
            );
            return;
        }

        var editBtn = e.target.closest('[data-open-topic-edit]');
        if (editBtn) {
            openInline(
                editBtn.getAttribute('data-open-topic-edit'),
                'textarea[name="topic"], input[name="topic"]',
                'details.pzk-topic-edit'
            );
        }
    });

    document.addEventListener('toggle', function (e) {
        var d = e.target;
        if (!d.classList || !d.classList.contains('pzk-topic-edit')) return;
        if (!d.open) return;
        var cell = d.parentElement;
        if (!cell) return;
        cell.querySelectorAll('details.pzk-topic-edit[open]').forEach(function (other) {
            if (other !== d) other.open = false;
        });
    }, true);

    // ===== Sticky-шапка КТП =====
    function syncKtpHeadHeight(details) {
        if (!details) return;
        var head = details.querySelector('.pzk-ktp__head');
        if (!head) {
            details.style.removeProperty('--ktp-head-h');
            return;
        }
        details.style.setProperty('--ktp-head-h', head.getBoundingClientRect().height + 'px');
    }

    // ===== Фильтр КТП по виду занятий =====
    //
    // Значения value в select: all / group / sub.
    // При all: столбец «№ по типу» скрыт, даты объединены rowspan по группам.
    // При group/sub: строки других видов скрыты, столбец «№ по типу» показан
    // и в нём — накопленный номер урока этого типа с учётом слотов:
    // один слот → «23», два слота → «23-24», три слота → «23-25».
    function initKtpFilter(details) {
        if (!details || details.dataset.ktpFilterInited === '1') return;
        var sel = details.querySelector('.pzk-ktp-filter');
        var table = details.querySelector('.pzk-ktp-table');
        if (!sel || !table) return;

        details.dataset.ktpFilterInited = '1';

        var tbody     = table.querySelector('tbody');
        var thTypeNum = table.querySelector('thead th.pzk-ktp-type-num');
        if (!tbody) return;

        var rows = Array.prototype.slice.call(tbody.querySelectorAll('tr[data-kind]'));

        function applyFilter(filter) {
            var counter = 0;
            var visibleRows = [];
            var dateGroups  = {};

            // 1. Показ / скрытие строк, нумерация «№ по типу» по слотам
            rows.forEach(function (tr) {
                var kind  = tr.dataset.kind || '';
                var date  = tr.dataset.date || '';
                var slots = parseInt(tr.dataset.slots, 10) || 1;
                var show  = (filter === 'all') || (kind === filter) || (kind === 'mixed');

                tr.style.display = show ? '' : 'none';

                var tdTypeNum = tr.querySelector('td.pzk-ktp-type-num');
                var tdDate    = tr.querySelector('td.pzk-ktp-date');

                if (show) {
                    var start = counter + 1;
                    var end   = counter + slots;
                    counter = end;

                    if (tdTypeNum) {
                        if (filter === 'all') {
                            tdTypeNum.style.display = 'none';
                            tdTypeNum.textContent = '';
                        } else {
                            tdTypeNum.style.display = '';
                            tdTypeNum.textContent = (start === end)
                                ? String(start)
                                : start + '-' + end;
                        }
                    }
                    if (tdDate) tdDate.style.display = '';

                    visibleRows.push(tr);
                    if (!dateGroups[date]) dateGroups[date] = [];
                    dateGroups[date].push(tr);
                } else {
                    if (tdTypeNum) {
                        tdTypeNum.style.display = 'none';
                        tdTypeNum.textContent = '';
                    }
                    if (tdDate) {
                        tdDate.style.display = 'none';
                        tdDate.removeAttribute('rowspan');
                    }
                }
            });

            // 2. Заголовок столбца «№ по типу»
            if (thTypeNum) {
                thTypeNum.style.display = (filter === 'all') ? 'none' : '';
            }

            // 3. Даты: сброс rowspan, потом объединение — только для all
            visibleRows.forEach(function (tr) {
                var tdDate = tr.querySelector('td.pzk-ktp-date');
                if (!tdDate) return;
                tdDate.style.display = '';
                tdDate.removeAttribute('rowspan');
            });

            if (filter === 'all') {
                Object.keys(dateGroups).forEach(function (date) {
                    var group = dateGroups[date];
                    if (group.length <= 1) return;
                    group.forEach(function (tr, i) {
                        var tdDate = tr.querySelector('td.pzk-ktp-date');
                        if (!tdDate) return;
                        if (i === 0) {
                            tdDate.setAttribute('rowspan', String(group.length));
                        } else {
                            tdDate.style.display = 'none';
                        }
                    });
                });
            }
        }

        sel.addEventListener('change', function () {
            applyFilter(sel.value || 'all');
        });

        applyFilter(sel.value || 'all');
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
        initKtpFilter(details);
    }

    document.querySelectorAll('.pzk-subject-ktp').forEach(initKtpBlock);

    window.addEventListener('resize', function () {
        document.querySelectorAll('.pzk-subject-ktp').forEach(syncKtpHeadHeight);
    });

    // ===== Универсальный AJAX-submit для блоков (КТП и Темы) =====
    function ajaxSubmit(form, block) {
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

            if (newBlock.classList.contains('pzk-subject-ktp')) {
                initKtpBlock(newBlock);
            }
        })
        .catch(function (err) {
            alert('Ошибка сети: ' + err.message);
        })
        .finally(function () {
            form.dataset.busy = '';
        });
    }

    document.addEventListener('submit', function (e) {
        var form = e.target;

        var ktpBlock = form.closest('.pzk-subject-ktp');
        if (ktpBlock) {
            e.preventDefault();
            ajaxSubmit(form, ktpBlock);
            return;
        }

        var topicsBlock = form.closest('.pzk-subject-topics');
        if (topicsBlock) {
            e.preventDefault();
            ajaxSubmit(form, topicsBlock);
            return;
        }
    });
})();
</script>