<?php
declare(strict_types=1);

$page = $_GET['page'] ?? '';
$fullName = $currentUser['full_name'] ?? $currentUser['login'] ?? 'Пользователь';
$roles = $currentUser['roles'] ?? [];
?>
<header class="topbar">
    <div class="topbar__title">
        Приказ
        <span>управление проектами и аттестацией</span>
    </div>
    <div class="topbar__user">
        <div class="role-switch" role="tablist" aria-label="Переключение роли">
            <?php if (in_array('teacher', $roles, true)): ?>
                <a class="role-switch__btn <?= $viewRole === 'teacher' ? 'active' : '' ?>"
                   href="?page=teacher" role="tab">🧑‍🏫 Преподаватель</a>
            <?php endif; ?>
            <?php if (in_array('student', $roles, true)): ?>
                <a class="role-switch__btn <?= $viewRole === 'student' ? 'active' : '' ?>"
                   href="?page=student" role="tab">🎒 Студент</a>
            <?php endif; ?>
            <?php if (in_array('parent', $roles, true)): ?>
                <a class="role-switch__btn <?= $viewRole === 'parent' ? 'active' : '' ?>"
                   href="?page=parent" role="tab">👪 Родитель</a>
            <?php endif; ?>
            <?php if (in_array('admin', $roles, true)): ?>
                <a class="role-switch__btn <?= $viewRole === 'admin' ? 'active' : '' ?>"
                   href="?page=admin" role="tab">⚙️ Админ</a>
            <?php endif; ?>
        </div>
        <a class="role-switch__btn <?= $page === 'profile' ? 'active' : '' ?>"
           href="?page=profile" title="Профиль и смена пароля">👤 <?= e($fullName) ?></a>
        <form method="post" action="index.php?action=logout" style="display:inline;">
            <?= csrf_field() ?>
            <button type="submit" class="topbar__logout">Выйти</button>
        </form>
    </div>
</header>