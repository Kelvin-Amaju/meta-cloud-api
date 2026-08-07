<?php

// includes/broadcasts.php â€” Broadcast campaigns & synchronous bulk sending

require_once __DIR__ . '/db.inc.php';
require_once __DIR__ . '/business_functions.inc.php';
require_once __DIR__ . '/whatsapp_functions.inc.php';

/**
 * Create a broadcast campaign record.
 */
function create_campaign(array $data): array
{
    global $mysqli;

    $businessId    = (int)($data['business_id'] ?? 0);
    $campaignName  = trim($data['campaign_name'] ?? '');
    $payloadType   = trim($data['payload_type'] ?? 'template');
    $templateName  = trim($data['template_name'] ?? '');
    $messageBody   = trim($data['message_body'] ?? '');
    $mediaUrl      = trim($data['media_url'] ?? '');
    $mediaType     = trim($data['media_type'] ?? '');
    $recipientFile = trim($data['recipient_file'] ?? '');
    $totalRecipients = (int)($data['total_recipients'] ?? 0);

    if ($businessId <= 0) {
        return ['success' => false, 'id' => null, 'error' => 'Sender business is required.'];
    }
    if ($campaignName === '') {
        return ['success' => false, 'id' => null, 'error' => 'Campaign name is required.'];
    }

    $allowedPayloads = ['template', 'text', 'media', 'interactive'];
    if (!in_array($payloadType, $allowedPayloads, true)) {
        $payloadType = 'template';
    }

    if ($totalRecipients <= 0) {
        return ['success' => false, 'id' => null, 'error' => 'At least one recipient is required.'];
    }

    $stmt = $mysqli->prepare(
        "INSERT INTO broadcast_campaigns
            (business_id, campaign_name, payload_type, template_name, message_body, media_url, media_type, recipient_file, total_recipients, status, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft', NOW())"
    );
    if (!$stmt) {
        return ['success' => false, 'id' => null, 'error' => 'Failed to prepare INSERT: ' . $mysqli->error];
    }

    $bindTemplateName  = $templateName !== '' ? $templateName : null;
    $bindMessageBody   = $messageBody !== '' ? $messageBody : null;
    $bindMediaUrl      = $mediaUrl !== '' ? $mediaUrl : null;
    $bindMediaType     = $mediaType !== '' ? $mediaType : null;
    $bindRecipientFile = $recipientFile !== '' ? $recipientFile : null;

    $stmt->bind_param(
        "isssssssi",
        $businessId,
        $campaignName,
        $payloadType,
        $bindTemplateName,
        $bindMessageBody,
        $bindMediaUrl,
        $bindMediaType,
        $bindRecipientFile,
        $totalRecipients
    );

    $ok  = $stmt->execute();
    $id  = $ok ? $stmt->insert_id : null;
    $err = $ok ? null : $stmt->error;
    $stmt->close();

    return ['success' => $ok, 'id' => $id, 'error' => $err];
}

/**
 * Paginated campaign list.
 */
function get_campaigns(int $page = 1, int $perPage = 20): array
{
    global $mysqli;

    $page    = max(1, $page);
    $perPage = min(100, max(1, $perPage));
    $offset  = ($page - 1) * $perPage;

    $countStmt = $mysqli->prepare("SELECT COUNT(*) AS total FROM broadcast_campaigns");
    if (!$countStmt) {
        return ['data' => [], 'total' => 0, 'page' => $page, 'perPage' => $perPage, 'totalPages' => 0, 'error' => $mysqli->error];
    }
    $countStmt->execute();
    $total = (int)($countStmt->get_result()->fetch_assoc()['total'] ?? 0);
    $countStmt->close();

    $totalPages = $total > 0 ? (int)ceil($total / $perPage) : 0;
    if ($total === 0) {
        return ['data' => [], 'total' => 0, 'page' => $page, 'perPage' => $perPage, 'totalPages' => 0, 'error' => null];
    }

    $sql = "SELECT bc.*, b.name AS business_name, b.display_phone_number AS business_phone
            FROM broadcast_campaigns bc
            LEFT JOIN businesses b ON bc.business_id = b.id
            ORDER BY bc.id DESC
            LIMIT ? OFFSET ?";
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return ['data' => [], 'total' => $total, 'page' => $page, 'perPage' => $perPage, 'totalPages' => $totalPages, 'error' => $mysqli->error];
    }
    $stmt->bind_param("ii", $perPage, $offset);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return ['data' => $data, 'total' => $total, 'page' => $page, 'perPage' => $perPage, 'totalPages' => $totalPages, 'error' => null];
}

/**
 * Fetch a single campaign by id.
 */
function get_campaign_by_id(int $id): ?array
{
    global $mysqli;

    $stmt = $mysqli->prepare(
        "SELECT bc.*, b.name AS business_name
         FROM broadcast_campaigns bc
         LEFT JOIN businesses b ON bc.business_id = b.id
         WHERE bc.id = ? LIMIT 1"
    );
    if (!$stmt) return null;
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ?: null;
}

/**
 * Store a recipient row for a campaign.
 */
function save_campaign_recipient(int $campaignId, string $phone): bool
{
    global $mysqli;

    $phone = preg_replace('/[^0-9]/', '', (string)$phone);
    if ($phone === '') return false;

    $stmt = $mysqli->prepare(
        "INSERT INTO broadcast_recipients (campaign_id, phone, status) VALUES (?, ?, 'pending')"
    );
    if (!$stmt) return false;
    $stmt->bind_param("is", $campaignId, $phone);
    $ok = $stmt->execute();
    $stmt->close();

    return $ok;
}

/**
 * Get recipient counts for a campaign.
 */
function get_campaign_recipient_counts(int $campaignId): array
{
    global $mysqli;

    $counts = ['pending' => 0, 'sent' => 0, 'failed' => 0];

    $stmt = $mysqli->prepare("SELECT status, COUNT(*) AS cnt FROM broadcast_recipients WHERE campaign_id = ? GROUP BY status");
    if (!$stmt) return $counts;
    $stmt->bind_param("i", $campaignId);
    $stmt->execute();
    while ($row = $stmt->get_result()->fetch_assoc()) {
        if (isset($counts[$row['status']])) {
            $counts[$row['status']] = (int)$row['cnt'];
        }
    }
    $stmt->close();

    return $counts;
}

/**
 * Run a campaign synchronously â€” sends to every pending recipient now.
 *
 * @return array ['success' => bool, 'sent' => int, 'failed' => int, 'error' => ?string]
 */
function run_campaign(int $campaignId): array
{
    set_time_limit(0);
    global $mysqli;

    $campaign = get_campaign_by_id($campaignId);
    if (!$campaign) {
        return ['success' => false, 'sent' => 0, 'failed' => 0, 'error' => 'Campaign not found.'];
    }

    $business = get_business($campaign['business_id']);
    if (!$business) {
        return ['success' => false, 'sent' => 0, 'failed' => 0, 'error' => 'Sender business not found.'];
    }

    $stmt = $mysqli->prepare(
        "SELECT id, phone FROM broadcast_recipients WHERE campaign_id = ? AND status = 'pending' ORDER BY id ASC"
    );
    if (!$stmt) {
        return ['success' => false, 'sent' => 0, 'failed' => 0, 'error' => $mysqli->error];
    }
    $stmt->bind_param("i", $campaignId);
    $stmt->execute();
    $recipients = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (empty($recipients)) {
        return ['success' => false, 'sent' => 0, 'failed' => 0, 'error' => 'No pending recipients for this campaign.'];
    }

    $statusUpdate = $mysqli->prepare("UPDATE broadcast_campaigns SET status = 'running', updated_at = NOW() WHERE id = ?");
    if ($statusUpdate) {
        $statusUpdate->bind_param("i", $campaignId);
        $statusUpdate->execute();
        $statusUpdate->close();
    }

    $sentCount = 0;
    $failCount = 0;

    foreach ($recipients as $r) {
        $phone = $r['phone'];
        $response = null;

        switch ($campaign['payload_type']) {
            case 'template':
                $response = whatsapp_send_template($phone, $campaign['template_name'] ?? '', 'en_US', [], $business);
                break;

            case 'media':
                $response = whatsapp_send_media($phone, $campaign['media_type'] ?? 'image', $campaign['media_url'] ?? '', null, $business);
                break;

            case 'text':
            default:
                $response = whatsapp_send_text($phone, $campaign['message_body'] ?? '', $business);
                break;
        }

        $ok    = !empty($response['success']);
        $wamid = $response['data']['messages'][0]['id'] ?? null;
        $err   = $response['error'] ?? $response['data']['error']['message'] ?? null;

        $upd = $mysqli->prepare("UPDATE broadcast_recipients SET status = ?, wamid = ?, error_message = ?, sent_at = NOW() WHERE id = ?");
        if ($upd) {
            $st = $ok ? 'sent' : 'failed';
            $upd->bind_param("sssi", $st, $wamid, $err, $r['id']);
            $upd->execute();
            $upd->close();
        }

        if ($ok) {
            $sentCount++;
        } else {
            $failCount++;
        }

        // Small delay to stay comfortably within Meta rate limits
        usleep(200000); // 200ms between sends
    }

    $finalStatus = $sentCount > 0 ? 'completed' : 'failed';

    $updCampaign = $mysqli->prepare(
        "UPDATE broadcast_campaigns SET sent_count = ?, status = ?, updated_at = NOW() WHERE id = ?"
    );
    if ($updCampaign) {
        $updCampaign->bind_param("isi", $sentCount, $finalStatus, $campaignId);
        $updCampaign->execute();
        $updCampaign->close();
    }

    return ['success' => true, 'sent' => $sentCount, 'failed' => $failCount, 'error' => null];
}
