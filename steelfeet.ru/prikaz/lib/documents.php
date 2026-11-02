<?php
declare(strict_types=1);

/**
 * Документы: типы, статусы, права, переходы.
 * Документ — самостоятельный объект workflow, не атрибут шага.
 */

function doc_types(): array
{
    return [
        'contract'    => ['label' => 'Договор',          'icon' => '📜', 'signable' => true,  'linked_steps' => [4, 5, 6]],
        'addendum'    => ['label' => 'Доп. соглашение',  'icon' => '➕', 'signable' => true,  'linked_steps' => [4, 5, 6]],
        'license'     => ['label' => 'Лицензия',          'icon' => '🔑', 'signable' => true,  'linked_steps' => [7]],
        'program'     => ['label' => 'Учебная программа', 'icon' => '📚', 'signable' => true,  'linked_steps' => [10, 12]],
        'materials'   => ['label' => 'Материалы',         'icon' => '📦', 'signable' => false, 'linked_steps' => [7]],
        'methodology' => ['label' => 'Методичка',         'icon' => '📖', 'signable' => false, 'linked_steps' => [12]],
        'act'         => ['label' => 'Акт',               'icon' => '✅', 'signable' => true,  'linked_steps' => [6]],
    ];
}

function doc_statuses(): array
{
    return [
        'draft'          => ['label' => 'Черновик',        'color' => '#6b7280'],
        'sent'           => ['label' => 'Отправлен',       'color' => '#2563eb'],
        'in_review'      => ['label' => 'На рассмотрении', 'color' => '#8b6914'],
        'needs_revision' => ['label' => 'На доработке',    'color' => '#dc2626'],
        'approved'       => ['label' => 'Согласован',      'color' => '#16a34a'],
        'active'         => ['label' => 'Действует',       'color' => '#0d9488'],
        'expired'        => ['label' => 'Истёк',           'color' => '#991b1b'],
        'archived'       => ['label' => 'В архиве',        'color' => '#9ca3af'],
    ];
}

function doc_status_label(string $status, string $type): string
{
    $base = doc_statuses()[$status]['label'] ?? $status;
    if ($type === 'materials' && $status === 'approved') return 'Получено, претензий нет';
    return $base;
}

/** Сторона, к которой относится роль в системе */
function doc_side_for_role(string $role): ?string
{
    if (in_array($role, ['manager', 'supervisor'], true)) return 'internal';
    if ($role === 'university') return 'external';
    return null; // student/parent — нет доступа к документам
}

function doc_is_overdue(array $doc): bool
{
    if (empty($doc['deadline'])) return false;
    if (in_array($doc['status'], ['archived', 'expired'], true)) return false;
    return strtotime($doc['deadline']) < strtotime('today');
}

/**
 * Действия, доступные пользователю данной стороны в текущем статусе.
 * Возвращает массив: [action, label, to_status, primary?, requires_version?]
 */
function doc_available_actions(string $status, string $side, int $signatureCount, string $type): array
{
    $signable = (doc_types()[$type]['signable'] ?? true);
    $actions = [];

    if ($status === 'draft' && $side === 'internal') {
        $actions[] = ['action' => 'send', 'label' => 'Отправить второй стороне', 'to_status' => 'sent', 'primary' => true];
    }

    if ($status === 'sent' && $side === 'external') {
        $actions[] = ['action' => 'accept_review', 'label' => 'Взять на рассмотрение', 'to_status' => 'in_review', 'primary' => true];
        $actions[] = ['action' => 'request_revision', 'label' => 'Вернуть на доработку', 'to_status' => 'needs_revision'];
    }

    if ($status === 'in_review' && $side === 'external') {
        $actions[] = ['action' => 'approve', 'label' => $signable ? 'Согласовать' : 'Принять', 'to_status' => 'approved', 'primary' => true];
        $actions[] = ['action' => 'request_revision', 'label' => 'Вернуть на доработку', 'to_status' => 'needs_revision'];
    }

    if ($status === 'needs_revision' && $side === 'internal') {
        $actions[] = ['action' => 'send', 'label' => 'Отправить новую версию', 'to_status' => 'sent', 'primary' => true, 'requires_version' => true];
    }

    if ($status === 'approved' && $signable && $signatureCount < 2) {
        $alreadySigned = false;
        if ($side === 'internal') $actions[] = ['action' => 'sign', 'label' => 'Подписать (ИТ Школа)', 'to_status' => 'approved', 'primary' => true, 'is_sign' => true];
        if ($side === 'external') $actions[] = ['action' => 'sign', 'label' => 'Подписать (вуз)', 'to_status' => 'approved', 'primary' => true, 'is_sign' => true];
    }

    if ($status === 'approved' && !$signable && $side === 'external') {
        $actions[] = ['action' => 'sign', 'label' => 'Подтвердить получение', 'to_status' => 'approved', 'primary' => true, 'is_sign' => true];
    }

    if (in_array($status, ['approved', 'active'], true) && $side === 'internal') {
        $actions[] = ['action' => 'archive', 'label' => 'В архив', 'to_status' => 'archived'];
    }

    return $actions;
}

/**
 * Применить переход. Возвращает [new_status, new_owner_side, event_action, comment].
 * Для sign — status не меняется здесь (проверяется отдельно по числу подписей).
 */
function doc_next_state(string $status, string $side, string $action, string $type, int $signatureCount): array
{
    $signable = (doc_types()[$type]['signable'] ?? true);

    switch ($action) {
        case 'send':
            return ['sent', 'external'];
        case 'accept_review':
            return ['in_review', 'external'];
        case 'approve':
            return ['approved', 'internal'];
        case 'request_revision':
            return ['needs_revision', 'internal'];
        case 'sign':
            // Если после этой подписи их станет 2 → active
            if ($signatureCount + 1 >= 2) {
                return ['active', 'internal'];
            }
            // Иначе — ждём вторую подпись, сторона переключается
            return ['approved', $side === 'internal' ? 'external' : 'internal'];
        case 'archive':
            return ['archived', 'internal'];
    }
    throw new RuntimeException("Неизвестное действие: {$action}");
}

/** Список документов для реестра с учётом стороны и вкладки */
function doc_fetch_list(PDO $pdo, string $side, string $tab = 'all', array $filters = []): array
{
    $where = ['1 = 1'];
    $params = [];

    if ($tab === 'inbox') {
        $where[] = "d.owner_side = :side";
        $where[] = "d.status NOT IN ('archived', 'expired', 'active')";
        $params[':side'] = $side;
    } elseif ($tab === 'outbox') {
        $where[] = "d.owner_side <> :side";
        $where[] = "d.status NOT IN ('archived', 'expired')";
        $params[':side'] = $side;
    } elseif ($tab === 'overdue') {
        $where[] = "d.deadline IS NOT NULL AND d.deadline < date('now')";
        $where[] = "d.status NOT IN ('archived', 'expired')";
    }

    if (!empty($filters['type'])) {
        $where[] = "d.type = :type";
        $params[':type'] = $filters['type'];
    }
    if (!empty($filters['status'])) {
        $where[] = "d.status = :status";
        $params[':status'] = $filters['status'];
    }
    if (!empty($filters['interaction_id'])) {
        $where[] = "d.interaction_id = :iid";
        $params[':iid'] = (int) $filters['interaction_id'];
    }

    $whereSql = implode(' AND ', $where);
    $sql = "SELECT d.*, 
                   i.university_id, i.product_id,
                   u.name AS uni_name, p.name AS product_name,
                   (SELECT version FROM document_versions v WHERE v.document_id = d.id ORDER BY version DESC LIMIT 1) AS last_version,
                   (SELECT COUNT(*) FROM document_signatures s WHERE s.document_id = d.id) AS sign_count,
                   (SELECT COUNT(*) FROM document_versions v WHERE v.document_id = d.id) AS versions_count
            FROM documents d
            JOIN interactions i ON i.id = d.interaction_id
            JOIN universities u ON u.id = i.university_id
            JOIN it_products p ON p.id = i.product_id
            WHERE {$whereSql}
            ORDER BY 
                CASE d.status 
                    WHEN 'needs_revision' THEN 1 
                    WHEN 'in_review' THEN 2 
                    WHEN 'sent' THEN 3 
                    WHEN 'approved' THEN 4 
                    WHEN 'draft' THEN 5 
                    WHEN 'active' THEN 6 
                    ELSE 7 
                END,
                d.deadline IS NULL, d.deadline ASC,
                d.id DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function doc_fetch_card(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare(
        "SELECT d.*,
                i.university_id, i.product_id,
                u.name AS uni_name, u.id AS uni_id,
                p.name AS product_name, p.id AS prod_id
         FROM documents d
         JOIN interactions i ON i.id = d.interaction_id
         JOIN universities u ON u.id = i.university_id
         JOIN it_products p ON p.id = i.product_id
         WHERE d.id = :id"
    );
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function doc_versions(PDO $pdo, int $docId): array
{
    $stmt = $pdo->prepare(
        "SELECT v.*, u.full_name AS uploader_name, u.login AS uploader_login
         FROM document_versions v
         LEFT JOIN users u ON u.id = v.uploaded_by
         WHERE v.document_id = :id
         ORDER BY v.version DESC"
    );
    $stmt->execute([':id' => $docId]);
    return $stmt->fetchAll();
}

function doc_events(PDO $pdo, int $docId, int $limit = 50): array
{
    $stmt = $pdo->prepare(
        "SELECT e.*, u.full_name AS actor_name, u.login AS actor_login,
                v.version AS version_num
         FROM document_events e
         LEFT JOIN users u ON u.id = e.actor_user_id
         LEFT JOIN document_versions v ON v.id = e.version_id
         WHERE e.document_id = :id
         ORDER BY e.created_at DESC, e.id DESC
         LIMIT " . (int) $limit
    );
    $stmt->execute([':id' => $docId]);
    return $stmt->fetchAll();
}

function doc_signatures(PDO $pdo, int $docId): array
{
    $stmt = $pdo->prepare(
        "SELECT s.*, u.full_name AS user_name, u.login AS user_login,
                v.version AS version_num
         FROM document_signatures s
         LEFT JOIN users u ON u.id = s.user_id
         LEFT JOIN document_versions v ON v.id = s.version_id
         WHERE s.document_id = :id
         ORDER BY s.signed_at ASC"
    );
    $stmt->execute([':id' => $docId]);
    return $stmt->fetchAll();
}

function doc_count_inbox(PDO $pdo, string $side): int
{
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM documents
         WHERE owner_side = :side
           AND status NOT IN ('archived', 'expired', 'active')"
    );
    $stmt->execute([':side' => $side]);
    return (int) $stmt->fetchColumn();
}