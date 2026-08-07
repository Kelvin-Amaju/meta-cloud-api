<?php

// includes/analytics.php â€” Analytics queries for the dashboard

require_once __DIR__ . '/db.inc.php';

/**
 * Daily message volume for the last N days (in/out split).
 */
function get_messages_over_time(int $days = 14, int $businessId = 0): array
{
    global $mysqli;

    $days  = min(90, max(1, $days));
    $where = '';
    $types = '';
    $params = [];

    if ($businessId > 0) {
        $where  = " AND business_id = ?";
        $types  = 'i';
        $params[] = $businessId;
    }

    $sql = "SELECT DATE(created_at) AS day,
                   COUNT(*) AS total,
                   SUM(direction = 'inbound')  AS inbound,
                   SUM(direction = 'outbound') AS outbound
            FROM business_messages
            WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
            {$where}
            GROUP BY DATE(created_at)
            ORDER BY day ASC";

    $stmt = $mysqli->prepare($sql);
    if (!$stmt) return [];

    $types = 'i' . $types;
    array_unshift($params, $days);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $map = [];
    foreach ($rows as $r) {
        $map[$r['day']] = $r;
    }

    $out = [];
    $start = strtotime('-' . ($days - 1) . ' days');
    for ($i = 0; $i < $days; $i++) {
        $d = date('Y-m-d', strtotime("+{$i} days", $start));
        $out[] = [
            'day'      => $d,
            'total'    => (int)($map[$d]['total'] ?? 0),
            'inbound'  => (int)($map[$d]['inbound'] ?? 0),
            'outbound' => (int)($map[$d]['outbound'] ?? 0),
        ];
    }

    return $out;
}

/**
 * Message status funnel counts.
 */
function get_status_breakdown(): array
{
    global $mysqli;

    $out = [
        'queued'    => 0,
        'sent'      => 0,
        'delivered' => 0,
        'read'      => 0,
        'failed'    => 0,
        'received'  => 0,
    ];

    $res = $mysqli->query("SELECT status, COUNT(*) AS cnt FROM business_messages GROUP BY status");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            if (isset($out[$row['status']])) {
                $out[$row['status']] = (int)$row['cnt'];
            }
        }
    }

    return $out;
}

/**
 * Per-business message volume (top senders).
 */
function get_business_breakdown(int $limit = 8): array
{
    global $mysqli;

    $limit = min(50, max(1, $limit));

    $stmt = $mysqli->prepare(
        "SELECT b.id, COALESCE(b.name, 'Unknown') AS business_name,
                COUNT(m.id) AS total,
                SUM(m.direction = 'inbound')  AS inbound,
                SUM(m.direction = 'outbound') AS outbound
         FROM business_messages m
         LEFT JOIN businesses b ON m.business_id = b.id
         GROUP BY b.id, b.name
         ORDER BY total DESC
         LIMIT ?"
    );
    if (!$stmt) return [];
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($rows as &$r) {
        $r['total']    = (int)$r['total'];
        $r['inbound']  = (int)$r['inbound'];
        $r['outbound'] = (int)$r['outbound'];
    }
    unset($r);

    return $rows;
}

/**
 * Message-type distribution (text / template / media / interactive).
 */
function getTypeBreakdown(): array
{
    global $mysqli;

    $out = [];
    $res = $mysqli->query("SELECT message_type, COUNT(*) AS cnt FROM business_messages GROUP BY message_type");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $out[$row['message_type'] ?? 'unknown'] = (int)$row['cnt'];
        }
    }

    return $out;
}

/**
 * Template send performance by status.
 */
function get_template_performance(): array
{
    global $mysqli;

    $res = $mysqli->query(
        "SELECT status, COUNT(*) AS cnt FROM business_messages WHERE message_type = 'template' GROUP BY status"
    );
    $out = ['sent' => 0, 'delivered' => 0, 'read' => 0, 'failed' => 0];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            if (isset($out[$row['status']])) {
                $out[$row['status']] = (int)$row['cnt'];
            }
        }
    }

    return $out;
}

/**
 * Top recipients by message volume.
 */
function get_top_customers(int $limit = 5): array
{
    global $mysqli;

    $limit = min(20, max(1, $limit));

    $stmt = $mysqli->prepare(
        "SELECT c.phone, COALESCE(c.name, c.phone) AS customer_name, c.total_messages
         FROM customers c
         ORDER BY c.total_messages DESC, c.last_message_at DESC
         LIMIT ?"
    );
    if (!$stmt) return [];
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $rows;
}
