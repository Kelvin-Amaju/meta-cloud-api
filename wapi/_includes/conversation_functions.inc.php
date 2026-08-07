<?php

// includes/conversations.php â€” Inbox conversation threading

require_once __DIR__ . '/db.inc.php';
require_once __DIR__ . '/customer_functions.inc.php';

/**
 * Upsert the customer + conversation state after any message (in or out).
 *
 * @return int|null customer id, or null on failure
 */
function sync_customer_conversation(int $businessId, string $phone, string $direction, string $preview, ?string $timestamp = null): ?int
{
    global $mysqli;

    $customerId = find_or_create_customer($businessId, $phone);
    if ($customerId <= 0) {
        return null;
    }

    $createdAt = $timestamp ?: date('Y-m-d H:i:s');
    $preview   = mb_substr((string)$preview, 0, 255);
    $direction = ($direction === 'inbound') ? 'inbound' : 'outbound';

    $stmt = $mysqli->prepare(
        "INSERT INTO conversations
            (business_id, customer_id, last_message_at, last_message_preview, last_direction, unread_count, status, created_at)
         VALUES (?, ?, ?, ?, ?, ?, 'open', NOW())
         ON DUPLICATE KEY UPDATE
            last_message_at     = VALUES(last_message_at),
            last_message_preview = VALUES(last_message_preview),
            last_direction       = VALUES(last_direction),
            unread_count         = IF(VALUES(last_direction) = 'inbound', unread_count + 1, unread_count),
            updated_at           = NOW()"
    );
    if (!$stmt) {
        return $customerId;
    }

    $unread = $direction === 'inbound' ? 1 : 0;
    $stmt->bind_param("iisssi", $businessId, $customerId, $createdAt, $preview, $direction, $unread);
    $stmt->execute();
    $stmt->close();

    $stmt = $mysqli->prepare("UPDATE customers SET last_message_at = ?, total_messages = total_messages + 1 WHERE id = ?");
    $stmt->bind_param("si", $createdAt, $customerId);
    $stmt->execute();
    $stmt->close();

    return $customerId;
}

/**
 * Paginated conversation list (with customer + business info).
 */
function get_conversations(array $filters = [], int $page = 1, int $perPage = 20): array
{
    global $mysqli;

    $page    = max(1, $page);
    $perPage = min(100, max(1, $perPage));
    $offset  = ($page - 1) * $perPage;

    $where  = [];
    $types  = '';
    $params = [];

    $businessId = (int)($filters['business_id'] ?? 0);
    if ($businessId > 0) {
        $where[]  = "cv.business_id = ?";
        $types   .= 'i';
        $params[] = $businessId;
    }

    $status = trim((string)($filters['status'] ?? ''));
    if ($status !== '' && in_array($status, ['open', 'closed'], true)) {
        $where[]  = "cv.status = ?";
        $types   .= 's';
        $params[] = $status;
    }

    $search = trim((string)($filters['search'] ?? ''));
    if ($search !== '') {
        $where[]  = "(cu.phone LIKE ? OR cu.name LIKE ? OR cv.last_message_preview LIKE ?)";
        $like     = '%' . $search . '%';
        $types   .= 'sss';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    $empty = [
        'data'       => [],
        'total'      => 0,
        'page'       => $page,
        'perPage'    => $perPage,
        'totalPages' => 0,
        'error'      => null,
    ];

    $countStmt = $mysqli->prepare(
        "SELECT COUNT(*) AS total FROM conversations cv JOIN customers cu ON cv.customer_id = cu.id {$whereSql}"
    );
    if (!$countStmt) {
        $empty['error'] = $mysqli->error;
        return $empty;
    }
    if ($types !== '') {
        $countStmt->bind_param($types, ...$params);
    }
    if (!$countStmt->execute()) {
        $empty['error'] = $countStmt->error;
        $countStmt->close();
        return $empty;
    }
    $total = (int)($countStmt->get_result()->fetch_assoc()['total'] ?? 0);
    $countStmt->close();

    $totalPages = $total > 0 ? (int)ceil($total / $perPage) : 0;
    if ($total === 0) {
        return $empty;
    }

    $sql = "SELECT cv.id, cv.business_id, cv.customer_id, cv.last_message_at, cv.last_message_preview,
                   cv.last_direction, cv.unread_count, cv.status, cv.updated_at,
                   cu.phone, cu.name AS customer_name, cu.email, cu.tags,
                   b.name AS business_name, b.display_phone_number AS business_phone
            FROM conversations cv
            JOIN customers cu ON cv.customer_id = cu.id
            LEFT JOIN businesses b ON cv.business_id = b.id
            {$whereSql}
            ORDER BY cv.last_message_at DESC
            LIMIT ? OFFSET ?";

    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        $empty['error'] = $mysqli->error;
        $empty['total'] = $total;
        return $empty;
    }

    $dataTypes  = $types . 'ii';
    $dataParams = $params;
    $dataParams[] = $perPage;
    $dataParams[] = $offset;
    $stmt->bind_param($dataTypes, ...$dataParams);

    if (!$stmt->execute()) {
        $empty['error'] = $stmt->error;
        $empty['total'] = $total;
        $stmt->close();
        return $empty;
    }

    $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return [
        'data'       => $data,
        'total'      => $total,
        'page'       => $page,
        'perPage'    => $perPage,
        'totalPages' => $totalPages,
        'error'      => null,
    ];
}

/**
 * Fetch a single conversation by business + customer.
 */
function getConversation(int $businessId, int $customerId): ?array
{
    global $mysqli;

    $sql = "SELECT cv.*, cu.phone, cu.name AS customer_name, cu.email, cu.tags, cu.notes,
                   b.name AS business_name, b.display_phone_number AS business_phone
            FROM conversations cv
            JOIN customers cu ON cv.customer_id = cu.id
            LEFT JOIN businesses b ON cv.business_id = b.id
            WHERE cv.business_id = ? AND cv.customer_id = ? LIMIT 1";
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) return null;
    $stmt->bind_param("ii", $businessId, $customerId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ?: null;
}

/**
 * Fetch a single conversation by its id.
 */
function get_conversation_by_id(int $id): ?array
{
    global $mysqli;

    $sql = "SELECT cv.*, cu.phone, cu.name AS customer_name, cu.email, cu.tags, cu.notes,
                   b.name AS business_name, b.display_phone_number AS business_phone
            FROM conversations cv
            JOIN customers cu ON cv.customer_id = cu.id
            LEFT JOIN businesses b ON cv.business_id = b.id
            WHERE cv.id = ? LIMIT 1";
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) return null;
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ?: null;
}

/**
 * Mark a conversation as read (zero unread count).
 */
function mark_conversation_read(int $businessId, int $customerId): bool
{
    global $mysqli;

    $stmt = $mysqli->prepare("UPDATE conversations SET unread_count = 0 WHERE business_id = ? AND customer_id = ?");
    if (!$stmt) return false;
    $stmt->bind_param("ii", $businessId, $customerId);
    $ok = $stmt->execute();
    $stmt->close();

    return $ok;
}

/**
 * Open/close a conversation.
 */
function set_conversation_status(int $businessId, int $customerId, string $status): bool
{
    global $mysqli;

    $status = in_array($status, ['open', 'closed'], true) ? $status : 'open';

    $stmt = $mysqli->prepare("UPDATE conversations SET status = ? WHERE business_id = ? AND customer_id = ?");
    if (!$stmt) return false;
    $stmt->bind_param("sii", $status, $businessId, $customerId);
    $ok = $stmt->execute();
    $stmt->close();

    return $ok;
}

/**
 * Fetch a conversation thread (chronological messages between a business and a customer).
 */
function getThreadMessages(int $businessId, string $phone, int $limit = 100): array
{
    global $mysqli;

    $limit = min(500, max(1, $limit));

    $stmt = $mysqli->prepare(
        "SELECT m.id, m.direction, m.wa_message_id, m.message_type, m.body, m.status,
                m.media_url, m.media_type, m.error_message, m.created_at
         FROM business_messages m
         WHERE m.business_id = ? AND (m.to_number = ? OR m.from_number = ?)
         ORDER BY m.id DESC
         LIMIT ?"
    );
    if (!$stmt) return [];
    $stmt->bind_param("issi", $businessId, $phone, $phone, $limit);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return array_reverse($rows);
}

/**
 * Total number of conversations with unread inbound messages.
 */
function get_unread_count(int $businessId = 0): int
{
    global $mysqli;

    if ($businessId > 0) {
        $stmt = $mysqli->prepare("SELECT COALESCE(SUM(unread_count), 0) AS cnt FROM conversations WHERE business_id = ?");
        $stmt->bind_param("i", $businessId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return (int)($row['cnt'] ?? 0);
    }

    $res = $mysqli->query("SELECT COALESCE(SUM(unread_count), 0) AS cnt FROM conversations");
    return (int)($res->fetch_assoc()['cnt'] ?? 0);
}
