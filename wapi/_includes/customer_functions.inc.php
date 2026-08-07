<?php

// includes/customers.php â€” Contact / customer records

require_once __DIR__ . '/db.inc.php';

/**
 * Normalise a phone number to digits only.
 */
function normalize_phone(?string $phone): string
{
    return preg_replace('/[^0-9]/', '', (string)$phone);
}

/**
 * Find an existing customer or create a new one for a business/phone pair.
 * Returns the customer id, or 0 on failure.
 */
function find_or_create_customer(int $businessId, string $phone, ?string $name = null): int
{
    global $mysqli;

    $phone = normalize_phone($phone);
    if ($phone === '' || $businessId <= 0) {
        return 0;
    }

    $stmt = $mysqli->prepare("SELECT id FROM customers WHERE business_id = ? AND phone = ? LIMIT 1");
    $stmt->bind_param("is", $businessId, $phone);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($row) {
        return (int)$row['id'];
    }

    $name = ($name !== null && trim($name) !== '') ? trim($name) : null;

    $stmt = $mysqli->prepare(
        "INSERT INTO customers (business_id, phone, name, created_at) VALUES (?, ?, ?, NOW())"
    );
    $stmt->bind_param("iss", $businessId, $phone, $name);
    $ok = $stmt->execute();
    $id = $ok ? $stmt->insert_id : 0;
    $stmt->close();

    return (int)$id;
}

/**
 * Paginated, filterable customer list.
 */
function get_customers(array $filters = [], int $page = 1, int $perPage = 20): array
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
        $where[]  = "c.business_id = ?";
        $types   .= 'i';
        $params[] = $businessId;
    }

    $search = trim((string)($filters['search'] ?? ''));
    if ($search !== '') {
        $where[]  = "(c.phone LIKE ? OR c.name LIKE ? OR c.email LIKE ? OR c.tags LIKE ?)";
        $like     = '%' . $search . '%';
        $types   .= 'ssss';
        $params[] = $like;
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

    $countStmt = $mysqli->prepare("SELECT COUNT(*) AS total FROM customers c {$whereSql}");
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

    $sql = "SELECT c.id, c.business_id, c.phone, c.name, c.email, c.tags, c.notes,
                   c.last_message_at, c.total_messages, c.created_at,
                   b.name AS business_name, b.display_phone_number AS business_phone
            FROM customers c
            LEFT JOIN businesses b ON c.business_id = b.id
            {$whereSql}
            ORDER BY c.last_message_at IS NULL ASC, c.last_message_at DESC, c.id DESC
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
 * Fetch a single customer by id.
 */
function get_customer_by_id(int $id): ?array
{
    global $mysqli;

    $sql = "SELECT c.id, c.business_id, c.phone, c.name, c.email, c.tags, c.notes,
                   c.last_message_at, c.total_messages, c.created_at,
                   b.name AS business_name, b.display_phone_number AS business_phone
            FROM customers c
            LEFT JOIN businesses b ON c.business_id = b.id
            WHERE c.id = ? LIMIT 1";
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) return null;
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ?: null;
}

/**
 * Create a customer record manually.
 */
function create_customer(array $data): array
{
    global $mysqli;

    $businessId = (int)($data['business_id'] ?? 0);
    $phone      = normalize_phone($data['phone'] ?? '');
    $name       = trim($data['name'] ?? '');
    $email      = trim($data['email'] ?? '');
    $tags       = trim($data['tags'] ?? '');
    $notes      = trim($data['notes'] ?? '');

    if ($businessId <= 0) {
        return ['success' => false, 'id' => null, 'error' => 'Sender business is required.'];
    }
    if ($phone === '') {
        return ['success' => false, 'id' => null, 'error' => 'Phone number is required.'];
    }

    $dup = $mysqli->prepare("SELECT id FROM customers WHERE business_id = ? AND phone = ? LIMIT 1");
    $dup->bind_param("is", $businessId, $phone);
    $dup->execute();
    if ($dup->get_result()->fetch_assoc()) {
        $dup->close();
        return ['success' => false, 'id' => null, 'error' => 'A customer with this phone already exists for this business.'];
    }
    $dup->close();

    $stmt = $mysqli->prepare(
        "INSERT INTO customers (business_id, phone, name, email, tags, notes, created_at)
         VALUES (?, ?, ?, ?, ?, ?, NOW())"
    );
    if (!$stmt) {
        return ['success' => false, 'id' => null, 'error' => 'Failed to prepare INSERT: ' . $mysqli->error];
    }

    $bindName  = $name !== '' ? $name : null;
    $bindEmail = $email !== '' ? $email : null;
    $bindTags  = $tags !== '' ? $tags : null;
    $bindNotes = $notes !== '' ? $notes : null;

    $stmt->bind_param(
        "isssss",
        $businessId,
        $phone,
        $bindName,
        $bindEmail,
        $bindTags,
        $bindNotes
    );

    $ok  = $stmt->execute();
    $id  = $ok ? $stmt->insert_id : null;
    $err = $ok ? null : $stmt->error;
    $stmt->close();

    return ['success' => $ok, 'id' => $id, 'error' => $err];
}

/**
 * Update a customer record.
 */
function update_customer(int $id, array $data): array
{
    global $mysqli;

    $existing = get_customer_by_id($id);
    if (!$existing) {
        return ['success' => false, 'error' => 'Customer not found.'];
    }

    $phone = normalize_phone($data['phone'] ?? $existing['phone']);
    $name  = trim($data['name'] ?? $existing['name'] ?? '');
    $email = trim($data['email'] ?? $existing['email'] ?? '');
    $tags  = trim($data['tags'] ?? $existing['tags'] ?? '');
    $notes = trim($data['notes'] ?? $existing['notes'] ?? '');

    if ($phone === '') {
        return ['success' => false, 'error' => 'Phone number cannot be empty.'];
    }

    $dup = $mysqli->prepare("SELECT id FROM customers WHERE business_id = ? AND phone = ? AND id != ? LIMIT 1");
    $dup->bind_param("isi", $existing['business_id'], $phone, $id);
    $dup->execute();
    if ($dup->get_result()->fetch_assoc()) {
        $dup->close();
        return ['success' => false, 'error' => 'Another customer with this phone already exists.'];
    }
    $dup->close();

    $stmt = $mysqli->prepare(
        "UPDATE customers SET phone = ?, name = ?, email = ?, tags = ?, notes = ? WHERE id = ?"
    );
    if (!$stmt) {
        return ['success' => false, 'error' => 'Failed to prepare UPDATE: ' . $mysqli->error];
    }

    $stmt->bind_param(
        "sssssi",
        $phone,
        $name,
        $email,
        $tags,
        $notes,
        $id
    );

    $ok  = $stmt->execute();
    $err = $ok ? null : $stmt->error;
    $stmt->close();

    return ['success' => $ok, 'error' => $err];
}

/**
 * Delete a customer record.
 */
function delete_customer(int $id): bool
{
    global $mysqli;

    $stmt = $mysqli->prepare("DELETE FROM customers WHERE id = ?");
    if (!$stmt) return false;
    $stmt->bind_param("i", $id);
    $ok = $stmt->execute();
    $stmt->close();

    return $ok;
}

/**
 * Import customer rows from a parsed CSV array.
 *
 * @param int $businessId
 * @param array $rows Each row: ['phone' => string, 'name' => string, 'email' => string, 'tags' => string]
 * @return array ['imported' => int, 'skipped' => int, 'errors' => string[]]
 */
function importCustomersFromCsv(int $businessId, array $rows): array
{
    $imported = 0;
    $skipped  = 0;
    $errors   = [];

    foreach ($rows as $i => $row) {
        $phone = normalize_phone($row['phone'] ?? '');
        if ($phone === '') {
            $skipped++;
            continue;
        }

        $result = create_customer([
            'business_id' => $businessId,
            'phone'       => $phone,
            'name'        => $row['name'] ?? '',
            'email'       => $row['email'] ?? '',
            'tags'        => $row['tags'] ?? '',
        ]);

        if ($result['success']) {
            $imported++;
        } else {
            // Duplicate phone numbers for the same business are skipped silently
            if (stripos($result['error'], 'already exists') !== false) {
                $skipped++;
            } else {
                $errors[] = 'Row ' . ($i + 1) . ': ' . $result['error'];
            }
        }
    }

    return ['imported' => $imported, 'skipped' => $skipped, 'errors' => $errors];
}

/**
 * Summary stats for the contacts page header.
 */
function get_customer_stats(): array
{
    global $mysqli;

    $stats = [
        'total'          => 0,
        'with_email'     => 0,
        'active_7d'      => 0,
        'total_inbound'  => 0,
    ];

    if (!$mysqli) return $stats;

    $res = $mysqli->query("SELECT COUNT(*) AS total, SUM(email IS NOT NULL AND email <> '') AS with_email FROM customers");
    if ($res && $row = $res->fetch_assoc()) {
        $stats['total']      = (int)$row['total'];
        $stats['with_email'] = (int)$row['with_email'];
    }

    $res = $mysqli->query("SELECT COUNT(*) AS cnt FROM customers WHERE last_message_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    if ($res && $row = $res->fetch_assoc()) {
        $stats['active_7d'] = (int)$row['cnt'];
    }

    $res = $mysqli->query("SELECT COUNT(*) AS cnt FROM business_messages WHERE direction = 'inbound'");
    if ($res && $row = $res->fetch_assoc()) {
        $stats['total_inbound'] = (int)$row['cnt'];
    }

    return $stats;
}
