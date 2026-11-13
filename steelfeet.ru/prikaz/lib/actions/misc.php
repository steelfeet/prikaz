<?php
declare(strict_types=1);

if ($action === 'help_feedback') {
    $articleId = (int) ($_POST['article_id'] ?? 0);
    $body      = trim((string) ($_POST['body'] ?? ''));
    if ($articleId > 0 && $body !== '') {
        $pdo->prepare('INSERT INTO help_feedback (article_id, user_id, body) VALUES (:a, :u, :b)')
            ->execute([
                ':a' => $articleId,
                ':u' => (int) $currentUser['id'],
                ':b' => mb_substr($body, 0, 500),
            ]);
    }
    redirect($_SERVER['HTTP_REFERER'] ?? 'index.php');
}