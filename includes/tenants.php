<?php

// includes/tenants.php

require_once __DIR__ . '/database.php';

/**
 * Fetch all active tenant business sender accounts.
 *
 * @param string $status Filter by status ('active', 'inactive', 'suspended', or 'all')
 * @return array<int, array<string, mixed>> List of tenants
 */
function getActiveTenants(string $status = 'active'): array
{
    global $mysqli;

    if ($status === 'all') {
        $sql = "SELECT id, company_name, contact_name, email, phone, status, 
                       phone_number_id, display_phone_number, whatsapp_business_account_name, 
                       waba_id, plan, created_at 
                FROM tenants 
                ORDER BY id ASC";
        $stmt = $mysqli->prepare($sql);
    } else {
        $sql = "SELECT id, company_name, contact_name, email, phone, status, 
                       phone_number_id, display_phone_number, whatsapp_business_account_name, 
                       waba_id, plan, created_at 
                FROM tenants 
                WHERE status = ? 
                ORDER BY id ASC";
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param("s", $status);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $tenants = [];

    while ($row = $result->fetch_assoc()) {
        $tenants[] = $row;
    }

    $stmt->close();
    return $tenants;
}

/**
 * Fetch a single tenant business record by ID (including API credentials).
 *
 * @param int $tenantId
 * @return array<string, mixed>|null
 */
function getTenantById(int $tenantId): ?array
{
    global $mysqli;

    $sql = "SELECT * FROM tenants WHERE id = ? LIMIT 1";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("i", $tenantId);
    $stmt->execute();

    $result = $stmt->get_result();
    $tenant = $result->fetch_assoc();

    $stmt->close();
    return $tenant ?: null;
}
