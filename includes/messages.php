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
    ?string $errorMessage = null
): bool {

    global $mysqli;

    $sql = "INSERT INTO business_messages
            (
                business_id,
                direction,
                wa_message_id,
                to_number,
                message_type,
                body,
                status,
                error_message,
                created_at
            )
            VALUES
            (
                ?, 'outbound', ?, ?, 'text', ?, ?, ?, NOW()
            )";

    $stmt = $mysqli->prepare($sql);

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param(
        "isssss",
        $businessId,
        $wamid,
        $toPhone,
        $message,
        $status,
        $errorMessage
    );

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
    if ($status !== '' && in_array($status, ['queued', 'sent', 'delivered', 'read', 'failed'], true)) {
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
                       m.message_type, m.body, m.status, m.error_message, m.created_at,
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
        $row['phone']       = $row['to_number'];
        $row['message']     = $row['body'];
        $row['wamid']       = $row['wa_message_id'];
        $row['allow_reply'] = 1;
        $row['tenant_name'] = $row['business_name'];
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
        'sent'      => 0,
        'delivered' => 0,
        'read'      => 0,
        'failed'    => 0,
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

    return $stats;
}