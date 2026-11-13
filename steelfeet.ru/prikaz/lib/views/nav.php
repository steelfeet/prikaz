<?php
declare(strict_types=1);

$tab     = $_GET['tab'] ?? '';
$section = $_GET['section'] ?? 'academic_years';
$pageNow = $_GET['page'] ?? '';
$isJournalPage = ($pageNow === 'journal');

// ===== Студент: верхнего меню нет — разделы во вкладках =====
if ($viewRole === 'student') {
    return;
}

// ===== Учитель / Родитель — плоское меню =====
if ($viewRole !== 'admin') {
    if ($viewRole === 'teacher') {
        echo '<nav class="mainnav"><span class="mainnav__caption">Разделы</span>';
        echo '<a class="' . ($isJournalPage ? 'active' : '') . '" href="?page=journal">📖 Журнал</a>';
        echo '<a class="' . ($tab === 'projects' ? 'active' : '') . '" href="?page=teacher&tab=projects">📋 Проекты</a>';
        echo '<a class="' . ($tab === 'summary'  ? 'active' : '') . '" href="?page=teacher&tab=summary">📊 Сводка</a>';
        echo '</nav>';
        return;
    }

    $links = [
        'projects' => '📋 Мои проекты',
        'journal'  => '📓 Журнал',
    ];
    echo '<nav class="mainnav"><span class="mainnav__caption">Разделы</span>';
    foreach ($links as $k => $label) {
        $active = ($tab === $k) ? 'active' : '';
        if ($tab === '' && $k === array_key_first($links)) $active = 'active';
        echo '<a class="' . $active . '" href="?page=' . e($viewRole) . '&tab=' . e($k) . '">' . e($label) . '</a>';
    }
    echo '</nav>';
    return;
}

// ===== Админ =====
$academicSections = ['academic_years', 'groups', 'subjects'];
$isAcademicActive = in_array($section, $academicSections, true);
?>
<style>
.mainnav__drop { position: relative; display: inline-block; }
.mainnav__drop-btn {
    padding: 0.5rem 0.75rem;
    font-size: 0.9rem;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    color: var(--color-secondary, #2d2d2d);
    background: transparent;
    border: none;
    border-bottom: 2px solid transparent;
    cursor: pointer;
    font-family: inherit;
    user-select: none;
    transition: background-color 0.15s, border-color 0.15s, color 0.15s;
}
.mainnav__drop-btn:hover,
.mainnav__drop-btn.is-active {
    background: rgba(139, 105, 20, 0.1);
    border-bottom-color: var(--color-accent, #8b6914);
    color: #1a1a1a;
}
.mainnav__drop-arrow { font-size: 0.65em; opacity: 0.6; transition: transform 0.18s; }
.mainnav__drop.is-open .mainnav__drop-arrow { transform: rotate(180deg); }
.mainnav__drop-menu {
    position: absolute;
    top: 100%;
    left: 0;
    background: #fff;
    border: 1px solid rgba(26, 26, 26, 0.1);
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.12);
    min-width: 200px;
    z-index: 100;
    padding: 6px 0;
    display: none;
}
.mainnav__drop.is-open .mainnav__drop-menu { display: block; }
.mainnav__drop-menu a {
    display: block;
    padding: 9px 18px;
    font-size: 0.88rem;
    color: var(--color-secondary, #2d2d2d);
    border-left: 3px solid transparent;
    transition: background-color 0.15s, border-color 0.15s;
}
.mainnav__drop-menu a:hover {
    background: rgba(139, 105, 20, 0.06);
    border-left-color: var(--color-accent, #8b6914);
    color: #1a1a1a;
}
.mainnav__drop-menu a.active {
    background: rgba(139, 105, 20, 0.1);
    border-left-color: var(--color-accent, #8b6914);
    font-weight: 600;
    color: #1a1a1a;
}
</style>

<nav class="mainnav">
    <span class="mainnav__caption">Админка</span>

    <a class="<?= $isJournalPage ? 'active' : '' ?>" href="?page=journal">📖 Журнал</a>

    <div class="mainnav__drop" id="academic-drop">
        <button type="button"
                class="mainnav__drop-btn <?= $isAcademicActive ? 'is-active' : '' ?>"
                aria-haspopup="true"
                aria-expanded="false">
            📅 Учебный процесс
            <span class="mainnav__drop-arrow" aria-hidden="true">▾</span>
        </button>
        <div class="mainnav__drop-menu" role="menu">
            <a class="<?= $section === 'academic_years' ? 'active' : '' ?>"
               href="?page=admin&section=academic_years" role="menuitem">Учебный год</a>
            <a class="<?= $section === 'groups' ? 'active' : '' ?>"
               href="?page=admin&section=groups" role="menuitem">Группы</a>
            <a class="<?= $section === 'subjects' ? 'active' : '' ?>"
               href="?page=admin&section=subjects" role="menuitem">Предметы и темы</a>
        </div>
    </div>

    <a class="<?= $section === 'schedule' ? 'active' : '' ?>" href="?page=admin&section=schedule">📆 Расписание</a>
    <a class="<?= $section === 'users'    ? 'active' : '' ?>" href="?page=admin&section=users">👥 Пользователи</a>
    <a class="<?= $section === 'students' ? 'active' : '' ?>" href="?page=admin&section=students">🎒 Студенты</a>
    <a class="<?= $section === 'parents'  ? 'active' : '' ?>" href="?page=admin&section=parents">👪 Родители</a>
    <a class="<?= $section === 'lessons'  ? 'active' : '' ?>" href="?page=admin&section=lessons">📓 Уроки</a>
    <a class="<?= $section === 'projects' ? 'active' : '' ?>" href="?page=admin&section=projects">📋 Проекты</a>
    <a class="<?= $section === 'audit'    ? 'active' : '' ?>" href="?page=admin&section=audit">📜 Аудит</a>
</nav>

<script>
(function () {
    'use strict';
    var drop = document.getElementById('academic-drop');
    if (!drop) return;
    var btn  = drop.querySelector('.mainnav__drop-btn');
    var menu = drop.querySelector('.mainnav__drop-menu');
    if (!btn || !menu) return;
    function close() { drop.classList.remove('is-open'); btn.setAttribute('aria-expanded', 'false'); }
    function open()  { drop.classList.add('is-open');    btn.setAttribute('aria-expanded', 'true');  }
    btn.addEventListener('click', function (e) {
        e.stopPropagation();
        drop.classList.contains('is-open') ? close() : open();
    });
    menu.querySelectorAll('a').forEach(function (a) { a.addEventListener('click', close); });
    document.addEventListener('click', function (e) { if (!drop.contains(e.target)) close(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });
})();
</script>