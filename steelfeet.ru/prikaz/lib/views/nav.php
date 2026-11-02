<?php
declare(strict_types=1);
/** Навигация. Ожидает $viewRole, $docsInboxCount (опционально). */
$docsInbox = $docsInboxCount ?? 0;
$currentPage = $_GET['page'] ?? 'dashboard';
?>
<nav class="mainnav">
    <span class="mainnav__caption">Разделы</span>
    <a class="<?= $currentPage === 'dashboard' ? 'active' : '' ?>" href="?role=<?= e($viewRole) ?>">Главная</a>
    <a class="<?= $currentPage === 'documents' ? 'active' : '' ?>" href="?page=documents&role=<?= e($viewRole) ?>">
        Документы
        <?php if ($docsInbox > 0): ?>
            <span class="mainnav__badge"><?= (int) $docsInbox ?></span>
        <?php endif; ?>
    </a>
    <a href="#">Взаимодействия</a>
    <a href="#">Рабочий процесс</a>
    <a href="#">Отчёты</a>
    <a href="#">Аудит</a>
</nav>