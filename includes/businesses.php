<?php

// includes/businesses.php

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/crypto.php';

/**
 * Generate a UUID v4 string.
 */
function generateUuid(): string
{
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // version 4
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // variant RFC 4122
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

/**
 * Fetch all active business sender accounts.
 *
 * @param string $status Filter by status ('active', 'pending', 'suspended', 'revoked', or 'all')
 * @return array<int, array<string, mixed>> List of business records
 */
function getActiveBusinesses(string $status = 'active'): array
{
    return getAllBusinesses(['status' => $status]);
}

/**
 * Fetch filterable business list with metadata.
 *
 * @param array $filters ['status' => string, 'product_line' => string, 'search' => string]
 * @return array<int, array<string, mixed>>
 */
function getAllBusinesses(array $filters = []): array
{
    global $mysqli;

    $where  = [];
    $types  = '';
    $params = [];

    $status = trim((string)($filters['status'] ?? ''));
    if ($status !== '' && $status !== 'all') {
        $where[]  = "status = ?";
        $types   .= 's';
        $params[] = $status;
    }

    $productLine = trim((string)($filters['product_line'] ?? ''));
    if ($productLine !== '' && $productLine !== 'all') {
        $where[]  = "product_line = ?";
        $types   .= 's';
        $params[] = $productLine;
    }

    $search = trim((string)($filters['search'] ?? ''));
    if ($search !== '') {
        $where[]  = "(name LIKE ? OR display_phone_number LIKE ? OR phone_number_id LIKE ? OR waba_id LIKE ?)";
        $like     = '%' . $search . '%';
        $types   .= 'ssss';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    $sql = "SELECT id, uuid, name, product_line, meta_business_id, waba_id, 
                   phone_number_id, display_phone_number, token_type, status, 
                   onboarding_method, onboarded_at, created_at, updated_at 
            FROM businesses 
            {$whereSql} 
            ORDER BY id DESC";

    $stmt = $mysqli->prepare($sql);
    if (!$stmt) return [];

    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $businesses = [];

    while ($row = $result->fetch_assoc()) {
        $businesses[] = $row;
    }

    $stmt->close();
    return $businesses;
}

/**
 * Fetch a single business record by ID (including API credentials).
 */
function getBusinessById(int $businessId): ?array
{
    global $mysqli;

    $sql = "SELECT * FROM businesses WHERE id = ? LIMIT 1";
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) return null;

    $stmt->bind_param("i", $businessId);
    $stmt->execute();

    $result = $stmt->get_result();
    $business = $result->fetch_assoc();

    $stmt->close();

    if ($business && isset($business['access_token'])) {
        $business['access_token'] = decryptToken($business['access_token']);
    }

    return $business ?: null;
}

/**
 * Fetch a single business record by UUID.
 */
function getBusinessByUuid(string $uuid): ?array
{
    global $mysqli;

    $sql = "SELECT * FROM businesses WHERE uuid = ? LIMIT 1";
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) return null;

    $stmt->bind_param("s", $uuid);
    $stmt->execute();

    $result = $stmt->get_result();
    $business = $result->fetch_assoc();

    $stmt->close();

    if ($business && isset($business['access_token'])) {
        $business['access_token'] = decryptToken($business['access_token']);
    }

    return $business ?: null;
}

/**
 * Fetch a single business record by Meta phone_number_id.
 */
function getBusinessByPhoneNumberId(string $phoneNumberId): ?array
{
    global $mysqli;

    $sql = "SELECT * FROM businesses WHERE phone_number_id = ? LIMIT 1";
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) return null;

    $stmt->bind_param("s", $phoneNumberId);
    $stmt->execute();

    $result = $stmt->get_result();
    $business = $result->fetch_assoc();

    $stmt->close();

    if ($business && isset($business['access_token'])) {
        $business['access_token'] = decryptToken($business['access_token']);
    }

    return $business ?: null;
}

/**
 * Create a new WhatsApp Business Profile.
 *
 * @param array $data Input fields
 * @return array ['success' => bool, 'id' => int|null, 'error' => string|null]
 */
function createBusiness(array $data): array
{
    global $mysqli;

    $name               = trim($data['name'] ?? '');
    $productLine        = trim($data['product_line'] ?? 'other');
    $metaBusinessId     = trim($data['meta_business_id'] ?? '');
    $wabaId             = trim($data['waba_id'] ?? '');
    $phoneNumberId      = trim($data['phone_number_id'] ?? '');
    $displayName        = trim($data['display_name'] ?? '');
    $displayPhoneNumber = trim($data['display_phone_number'] ?? '');
    $accessToken        = trim($data['access_token'] ?? '');
    $tokenType          = trim($data['token_type'] ?? 'system_user');
    $status             = trim($data['status'] ?? 'active');

    // Validation
    if (empty($name)) {
        return ['success' => false, 'id' => null, 'error' => 'Business Name is required.'];
    }
    if (empty($phoneNumberId)) {
        return ['success' => false, 'id' => null, 'error' => 'Phone Number ID is required.'];
    }
    if (empty($accessToken)) {
        return ['success' => false, 'id' => null, 'error' => 'Access Token is required.'];
    }

    // Allowed enum validation
    $allowedLines  = ['hotel', 'school', 'hospital', 'erp', 'crm', 'other'];
    if (!in_array($productLine, $allowedLines, true)) {
        $productLine = 'other';
    }

    $allowedTokenTypes = ['system_user', 'temporary'];
    if (!in_array($tokenType, $allowedTokenTypes, true)) {
        $tokenType = 'system_user';
    }

    $allowedStatuses = ['pending', 'active', 'suspended', 'revoked'];
    if (!in_array($status, $allowedStatuses, true)) {
        $status = 'active';
    }

    // Check duplicate phone_number_id
    $checkStmt = $mysqli->prepare("SELECT id FROM businesses WHERE phone_number_id = ? LIMIT 1");
    $checkStmt->bind_param("s", $phoneNumberId);
    $checkStmt->execute();
    if ($checkStmt->get_result()->fetch_assoc()) {
        $checkStmt->close();
        return ['success' => false, 'id' => null, 'error' => "A business with Phone Number ID '{$phoneNumberId}' already exists."];
    }
    $checkStmt->close();

    $uuid = generateUuid();
    try {
        $storedAccessToken = encryptToken($accessToken);
    } catch (RuntimeException $e) {
        return ['success' => false, 'id' => null, 'error' => $e->getMessage()];
    }

    $sql = "INSERT INTO businesses 
            (
                uuid, name, product_line, meta_business_id, waba_id, 
                phone_number_id, display_name, display_phone_number, access_token, 
                token_type, status, onboarding_method, onboarded_at, created_at
            )
            VALUES 
            (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'manual', NOW(), NOW()
            )";

    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return ['success' => false, 'id' => null, 'error' => 'Failed to prepare INSERT statement: ' . $mysqli->error];
    }

    $stmt->bind_param(
        "sssssssssss",
        $uuid,
        $name,
        $productLine,
        $metaBusinessId,
        $wabaId,
        $phoneNumberId,
        $displayName,
        $displayPhoneNumber,
        $storedAccessToken,
        $tokenType,
        $status
    );

    $executed = $stmt->execute();
    $insertId = $executed ? $stmt->insert_id : null;
    $error    = $executed ? null : $stmt->error;
    $stmt->close();

    return [
        'success' => $executed,
        'id'      => $insertId,
        'error'   => $error
    ];
}

/**
 * Update an existing WhatsApp Business Profile.
 *
 * @param int $id Business ID
 * @param array $data Input fields
 * @return array ['success' => bool, 'error' => string|null]
 */
function updateBusiness(int $id, array $data): array
{
    global $mysqli;

    $existing = getBusinessById($id);
    if (!$existing) {
        return ['success' => false, 'error' => 'Business record not found.'];
    }

    $name               = trim($data['name'] ?? $existing['name']);
    $productLine        = trim($data['product_line'] ?? $existing['product_line']);
    $metaBusinessId     = trim($data['meta_business_id'] ?? $existing['meta_business_id']);
    $wabaId             = trim($data['waba_id'] ?? $existing['waba_id']);
    $phoneNumberId      = trim($data['phone_number_id'] ?? $existing['phone_number_id']);
    $displayName        = trim($data['display_name'] ?? $existing['display_name'] ?? '');
    $displayPhoneNumber = trim($data['display_phone_number'] ?? $existing['display_phone_number']);
    $tokenType          = trim($data['token_type'] ?? $existing['token_type']);
    $status             = trim($data['status'] ?? $existing['status']);
    
    // Only update access_token if a new non-empty token is supplied;
    // otherwise preserve the raw stored value (never decrypt just to re-encrypt).
    $newAccessToken = trim($data['access_token'] ?? '');
    if ($newAccessToken !== '') {
        try {
            $storedAccessToken = encryptToken($newAccessToken);
        } catch (RuntimeException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    } else {
        $rawStmt = $mysqli->prepare("SELECT access_token FROM businesses WHERE id = ? LIMIT 1");
        $rawStmt->bind_param("i", $id);
        $rawStmt->execute();
        $rawRow = $rawStmt->get_result()->fetch_assoc();
        $rawStmt->close();
        $storedAccessToken = $rawRow['access_token'] ?? $existing['access_token'];
    }

    if (empty($name)) {
        return ['success' => false, 'error' => 'Business Name cannot be empty.'];
    }
    if (empty($phoneNumberId)) {
        return ['success' => false, 'error' => 'Phone Number ID cannot be empty.'];
    }

    // Check duplicate phone_number_id on other businesses
    $checkStmt = $mysqli->prepare("SELECT id FROM businesses WHERE phone_number_id = ? AND id != ? LIMIT 1");
    $checkStmt->bind_param("si", $phoneNumberId, $id);
    $checkStmt->execute();
    if ($checkStmt->get_result()->fetch_assoc()) {
        $checkStmt->close();
        return ['success' => false, 'error' => "Another business with Phone Number ID '{$phoneNumberId}' already exists."];
    }
    $checkStmt->close();

    $allowedLines = ['hotel', 'school', 'hospital', 'erp', 'crm', 'other'];
    if (!in_array($productLine, $allowedLines, true)) {
        $productLine = $existing['product_line'];
    }

    $allowedTokenTypes = ['system_user', 'temporary'];
    if (!in_array($tokenType, $allowedTokenTypes, true)) {
        $tokenType = $existing['token_type'];
    }

    $allowedStatuses = ['pending', 'active', 'suspended', 'revoked'];
    if (!in_array($status, $allowedStatuses, true)) {
        $status = $existing['status'];
    }

    $sql = "UPDATE businesses 
            SET name = ?,
                product_line = ?,
                meta_business_id = ?,
                waba_id = ?,
                phone_number_id = ?,
                display_name = ?,
                display_phone_number = ?,
                access_token = ?,
                token_type = ?,
                status = ?
            WHERE id = ?";

    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return ['success' => false, 'error' => 'Failed to prepare UPDATE statement: ' . $mysqli->error];
    }

    $stmt->bind_param(
        "ssssssssssi",
        $name,
        $productLine,
        $metaBusinessId,
        $wabaId,
        $phoneNumberId,
        $displayName,
        $displayPhoneNumber,
        $storedAccessToken,
        $tokenType,
        $status,
        $id
    );

    $executed = $stmt->execute();
    $error    = $executed ? null : $stmt->error;
    $stmt->close();

    return [
        'success' => $executed,
        'error'   => $error
    ];
}

/**
 * Delete a business profile by ID.
 */
function deleteBusiness(int $id): bool
{
    global $mysqli;

    $stmt = $mysqli->prepare("DELETE FROM businesses WHERE id = ?");
    if (!$stmt) return false;

    $stmt->bind_param("i", $id);
    $res = $stmt->execute();
    $stmt->close();

    return $res;
}

/**
 * Get summary stats for business dashboard.
 */
function getBusinessSummaryStats(): array
{
    global $mysqli;

    $stats = [
        'total'        => 0,
        'active'       => 0,
        'pending'      => 0,
        'suspended'    => 0,
        'product_lines' => 0,
    ];

    if (!$mysqli) return $stats;

    $res = $mysqli->query("SELECT status, COUNT(*) as cnt FROM businesses GROUP BY status");
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

    $resLines = $mysqli->query("SELECT COUNT(DISTINCT product_line) as line_count FROM businesses");
    if ($resLines && $row = $resLines->fetch_assoc()) {
        $stats['product_lines'] = (int)$row['line_count'];
    }

    return $stats;
}
