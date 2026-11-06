<?php
declare(strict_types=1);

$section = $_GET['section'] ?? 'academic_years';
$allowedSections = [
    'academic_years', 'groups', 'subjects', 'schedule',
    'users', 'students', 'parents', 'lessons', 'projects', 'audit',
];
if (!in_array($section, $allowedSections, true)) {
    $section = 'academic_years';
}

$flash = flash_get();
?>
<div class="workspace workspace--prikaz">
    <?php if ($flash !== null): ?>
        <div class="notification notification--<?= $flash['type'] === 'success' ? 'success' : 'danger' ?>">
            <div class="notification__body"><?= e($flash['message']) ?></div>
        </div>
    <?php endif; ?>

    <?php require __DIR__ . '/admin/' . $section . '.php'; ?>
</div>

<?php require __DIR__ . '/admin/_styles.php'; ?>
<?php require __DIR__ . '/admin/_scripts.php'; ?>