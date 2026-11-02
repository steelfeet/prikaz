<?php
declare(strict_types=1);
$viewStudentIdForLink = (int) ($_GET['student_id'] ?? 0);
$viewParentChildIdForLink = (int) ($_GET['child_id'] ?? 0);
$viewUniIdForLink = (int) ($_GET['uni_id'] ?? 0);
?>
<header class="topbar">
    <div class="topbar__title">
        ИТ Школа РТК
        <span>CRM · двунаправленный контроль обучения</span>
    </div>
    <div class="topbar__user">
        <div class="role-switch" role="tablist" aria-label="Переключение роли">
            <div class="role-switch__row">
                <a class="role-switch__btn <?= $viewRole === 'supervisor' ? 'active' : '' ?>" role="tab" aria-selected="<?= $viewRole === 'supervisor' ? 'true' : 'false' ?>" href="?role=supervisor">
                    <span class="role-switch__emoji" aria-hidden="true">🧭</span>
                    <span class="role-switch__label">Руководитель</span>
                </a>
                <a class="role-switch__btn <?= $viewRole === 'manager' ? 'active' : '' ?>" role="tab" aria-selected="<?= $viewRole === 'manager' ? 'true' : 'false' ?>" href="?role=manager<?= $viewUniIdForLink ? '&uni_id=' . $viewUniIdForLink : '' ?>">
                    <span class="role-switch__emoji" aria-hidden="true">📋</span>
                    <span class="role-switch__label"><?= e($managerLabel) ?> · Менеджер</span>
                </a>
            </div>
            <div class="role-switch__row">
                <a class="role-switch__btn <?= $viewRole === 'university' ? 'active' : '' ?>" role="tab" aria-selected="<?= $viewRole === 'university' ? 'true' : 'false' ?>" href="?role=university<?= $viewUniIdForLink ? '&uni_id=' . $viewUniIdForLink : '' ?>">
                    <span class="role-switch__emoji" aria-hidden="true">🎓</span>
                    <span class="role-switch__label">Представитель Вуза</span>
                </a>
                <a class="role-switch__btn <?= $viewRole === 'student' ? 'active' : '' ?>" role="tab" aria-selected="<?= $viewRole === 'student' ? 'true' : 'false' ?>" href="?role=student<?= $viewStudentIdForLink ? '&student_id=' . $viewStudentIdForLink : '' ?>">
                    <span class="role-switch__emoji" aria-hidden="true">🎒</span>
                    <span class="role-switch__label">Студент</span>
                </a>
                <a class="role-switch__btn <?= $viewRole === 'parent' ? 'active' : '' ?>" role="tab" aria-selected="<?= $viewRole === 'parent' ? 'true' : 'false' ?>" href="?role=parent<?= $viewParentChildIdForLink ? '&child_id=' . $viewParentChildIdForLink : '' ?>">
                    <span class="role-switch__emoji" aria-hidden="true">👪</span>
                    <span class="role-switch__label">Родитель</span>
                </a>
            </div>
        </div>
        <a class="topbar__logout" href="index.php?logout=1">Выйти</a>
    </div>
</header>