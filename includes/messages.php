<?php

// includes/messages.php

require_once __DIR__ . '/database.php';

/**
 * Save an outgoing WhatsApp message into business_messages.
 *
 * @param string $toPhone Recipient phone number
 * @param string $message Text content sent
 * @param string $wamid Meta WhatsApp Message ID
 * @param int $allowReply (Retained for application logic compatibility)
 * @param int $businessId Foreign key to businesses.id
 * @param string $status Message status ('sent', 'failed', 'queued')
 * @param string|null $errorMessage Optional error details
 * @return bool True on success
 */
function saveOutgoingMessage(
    string $toPhone,
    string $message,
    string $wamid,
    int $allowReply = 1,
    int $businessId = 1,
    string $status = 'sent',
    ?string $errorMessage = null,
    string $messageType = 'text',
    ?string $mediaUrl = null,
    ?string $mediaType = null,
    ?array $interactivePayload = null
): bool {

    global $mysqli;

    $allowedStatuses = ['queued', 'sent', 'delivered', 'read', 'failed'];
    if (!in_array($status, $allowedStatuses, true)) {
        $status = 'sent';
    }

    $interactiveJson = $interactivePayload ? json_encode($interactivePayload) : null;

    $sql = "INSERT INTO business_messages
            (
                business_id,
                direction,
                wa_message_id,
                to_number,
                message_type,
                body,
                status,
                delivered_at,
                read_at,
                error_message,
                media_url,
                media_type,
                interactive_payload,
                created_at
            )
            VALUES
            (
                ?, 'outbound', ?, ?, ?, ?, ?, NULL, NULL, ?, ?, ?, ?, NOW()
            )";

    $stmt = $mysqli->prepare($sql);

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param(
        "isssssssss",
        $businessId,
        $wamid,
        $toPhone,
        $messageType,
        $message,
        $status,
        $errorMessage,
        $mediaUrl,
        $mediaType,
        $interactiveJson
    );

    $result = $stmt->execute();
    $stmt->close();

    return $result;
}

/**
 * Save an inbound WhatsApp message delivered via webhook.
 *
 * @param int $businessId Foreign key to businesses.id
 * @param string $wamid Meta WhatsApp Message ID
 * @param string $fromNumber Sender phone number
 * @param string $messageType 'text', 'image', 'document', 'audio', 'video', 'interactive', ...
 * @param string|null $body Text body or caption
 * @param string|null $mediaUrl Direct media URL if provided by the payload
 * @param string|null $mediaType Normalized media type
 * @param int|null $timestamp Unix timestamp of the event
 * @return bool True on success
 */
function saveInboundMessage(
    int $businessId,
    string $wamid,
    string $fromNumber,
    string $messageType = 'text',
    ?string $body = null,
    ?string $mediaUrl = null,
    ?string $mediaType = null,
    ?int $timestamp = null
): bool {

    global $mysqli;

    $createdAt = $timestamp ? date('Y-m-d H:i:s', (int)$timestamp) : date('Y-m-d H:i:s');

    $sql = "INSERT INTO business_messages
            (
                business_id,
                direction,
                wa_message_id,
                from_number,
                message_type,
                body,
                status,
                media_url,
                media_type,
                created_at
            )
            VALUES
            (
                ?, 'inbound', ?, ?, ?, ?, 'received', ?, ?, ?
            )";

    $stmt = $mysqli->prepare($sql);

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param(
        "isssssss",
        $businessId,
        $wamid,
        $fromNumber,
        $messageType,
        $body,
        $mediaUrl,
        $mediaType,
        $createdAt
    );

    $result = $stmt->execute();
    $stmt->close();

    return $result;
}

/**
 * Update a message's status by Meta WAMID and record delivery/read timestamps.
 */
function updateMessageStatusByWamid(string $wamid, string $status, ?int $businessId = null, ?int $timestamp = null): bool
{
    global $mysqli;

    $allowedStatuses = ['queued', 'sent', 'delivered', 'read', 'failed'];
    if (!in_array($status, $allowedStatuses, true)) {
        return false;
    }

    $setParts = ['status = ?'];
    $types = 's';
    $params = [$status];

    $timestampString = null;
    if ($timestamp !== null) {
        $timestampString = date('Y-m-d H:i:s', (int)$timestamp);
    } else {
        $timestampString = date('Y-m-d H:i:s');
    }

    if ($status === 'delivered') {
        $setParts[] = 'delivered_at = ?';
        $types .= 's';
        $params[] = $timestampString;
    }

    if ($status === 'read') {
        $setParts[] = 'read_at = ?';
        $types .= 's';
        $params[] = $timestampString;
    }

    $sql = 'UPDATE business_messages SET ' . implode(', ', $setParts) . ' WHERE wa_message_id = ? LIMIT 1';
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return false;
    }

    $types .= 's';
    $params[] = $wamid;

    $stmt->bind_param($types, ...$params);
    $result = $stmt->execute();
    $stmt->close();

    return $result;
}

/**
 * Get the latest outgoing message sent to a phone number.
 */
function getLastOutgoingMessage(string $phone): ?array
{
    global $mysqli;

    $sql = "SELECT m.*, b.name AS business_name
            FROM business_messages m
            LEFT JOIN businesses b ON m.business_id = b.id
            WHERE m.to_number = ?
            ORDER BY m.id DESC
            LIMIT 1";

    $stmt = $mysqli->prepare($sql);
    if (!$stmt) return null;

    $stmt->bind_param("s", $phone);
    $stmt->execute();

    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    return $row ?: null;
}

/**
 * Fetch a paginated, filterable list of messages for the listing page.
 */
function getMessages(array $filters = [], int $page = 1, int $perPage = 20): array
{
    global $mysqli;

    $page    = max(1, $page);
    $perPage = min(100, max(1, $perPage));
    $offset  = ($page - 1) * $perPage;

    $where  = [];
    $types  = '';
    $params = [];

    $search = trim((string)($filters['search'] ?? ''));
    if ($search !== '') {
        $where[]  = "(m.to_number LIKE ? OR m.body LIKE ? OR b.name LIKE ?)";
        $like     = '%' . $search . '%';
        $types   .= 'sss';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    $businessId = (int)($filters['business_id'] ?? 0);
    if ($businessId > 0) {
        $where[]  = "m.business_id = ?";
        $types   .= 'i';
        $params[] = $businessId;
    }

    $status = trim((string)($filters['status'] ?? ''));
    if ($status !== '' && in_array($status, ['queued', 'sent', 'delivered', 'read', 'failed', 'received'], true)) {
        $where[]  = "m.status = ?";
        $types   .= 's';
        $params[] = $status;
    }

    $dateFrom = trim((string)($filters['date_from'] ?? ''));
    if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
        $where[]  = "m.created_at >= ?";
        $types   .= 's';
        $params[] = $dateFrom . ' 00:00:00';
    }

    $dateTo = trim((string)($filters['date_to'] ?? ''));
    if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
        $where[]  = "m.created_at <= ?";
        $types   .= 's';
        $params[] = $dateTo . ' 23:59:59';
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

    if (!$mysqli) {
        $empty['error'] = 'Database connection is not available.';
        return $empty;
    }

    // Count total rows
    $countSql = "SELECT COUNT(*) AS total 
                 FROM business_messages m
                 LEFT JOIN businesses b ON m.business_id = b.id
                 {$whereSql}";

    $countStmt = $mysqli->prepare($countSql);
    if (!$countStmt) {
        $empty['error'] = 'Failed to prepare count query: ' . $mysqli->error;
        return $empty;
    }

    if ($types !== '') {
        $countStmt->bind_param($types, ...$params);
    }

    if (!$countStmt->execute()) {
        $empty['error'] = 'Failed to execute count query: ' . $countStmt->error;
        $countStmt->close();
        return $empty;
    }

    $countResult = $countStmt->get_result();
    $total = (int)($countResult->fetch_assoc()['total'] ?? 0);
    $countStmt->close();

    $totalPages = $total > 0 ? (int)ceil($total / $perPage) : 0;

    if ($total === 0) {
        return $empty;
    }

    // Fetch page data
    $dataSql = "SELECT m.id, m.business_id, m.direction, m.wa_message_id, m.to_number, m.from_number,
                       m.message_type, m.body, m.status, m.delivered_at, m.read_at, m.error_message,
                       m.media_url, m.media_type, m.interactive_payload, m.created_at,
                       b.name AS business_name, b.product_line, b.display_phone_number AS business_display_phone
                FROM business_messages m
                LEFT JOIN businesses b ON m.business_id = b.id
                {$whereSql}
                ORDER BY m.id DESC
                LIMIT ? OFFSET ?";

    $dataStmt = $mysqli->prepare($dataSql);
    if (!$dataStmt) {
        $empty['error'] = 'Failed to prepare data query: ' . $mysqli->error;
        $empty['total'] = $total;
        return $empty;
    }

    $dataTypes  = $types . 'ii';
    $dataParams = $params;
    $dataParams[] = $perPage;
    $dataParams[] = $offset;

    $dataStmt->bind_param($dataTypes, ...$dataParams);

    if (!$dataStmt->execute()) {
        $empty['error'] = 'Failed to execute data query: ' . $dataStmt->error;
        $empty['total'] = $total;
        $dataStmt->close();
        return $empty;
    }

    $result = $dataStmt->get_result();
    $data   = [];

    while ($row = $result->fetch_assoc()) {
        // Compatibility mapping for existing UI views
        $row['phone']       = $row['direction'] === 'inbound' ? ($row['from_number'] ?? '') : ($row['to_number'] ?? '');
        $row['message']     = $row['body'];
        $row['wamid']       = $row['wa_message_id'];
        $row['allow_reply'] = 1;
        $row['tenant_name'] = $row['business_name'];
        $row['media_url']   = $row['media_url'] ?? null;
        $row['media_type']  = $row['media_type'] ?? null;
        $row['delivery_time'] = $row['delivered_at'] ?? null;
        $row['read_time']     = $row['read_at'] ?? null;
        $data[] = $row;
    }

    $dataStmt->close();

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
 * Get message statistics.
 */
function getMessageStats(): array
{
    global $mysqli;

    $stats = [
        'total'     => 0,
        'today'     => 0,
        'sent'      => 0,
        'delivered' => 0,
        'read'      => 0,
        'failed'    => 0,
        'received'  => 0,
    ];

    if (!$mysqli) return $stats;

    $res = $mysqli->query("SELECT status, COUNT(*) AS cnt FROM business_messages GROUP BY status");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $st = $row['status'];
            $cnt = (int)$row['cnt'];
            $stats['total'] += $cnt;
            if (isset($stats[$st])) {
                $stats[$st] = $cnt;
            }
        }
    }

    $resToday = $mysqli->query("SELECT COUNT(*) AS cnt FROM business_messages WHERE created_at >= CURDATE()");
    if ($resToday && $row = $resToday->fetch_assoc()) {
        $stats['today'] = (int)$row['cnt'];
    }

    return $stats;
}