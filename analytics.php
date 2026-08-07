<?php

// analytics.php — Robust analytics dashboard (Chart.js)

require_once 'includes/init.php';
require_once 'includes/analytics.php';
require_once 'includes/messages.php';
require_once 'includes/customers.php';

$activeNav = 'analytics';

$days = min(30, max(7, (int)($_GET['days'] ?? 14)));
$businessFilter = (int)($_GET['business_id'] ?? 0);

$activeBusinesses = getActiveBusinesses('active');

$timeSeries = getMessagesOverTime($days, $businessFilter);
$statuses   = getStatusBreakdown();
$businesses = getBusinessBreakdown(8);
$types      = getTypeBreakdown();
$templatePerf = getTemplatePerformance();
$topCustomers = getTopCustomers(5);

$msgStats = getMessageStats();
$contactStats = getCustomerStats();

function buildQ(array $overrides = []): string
{
    $params = [
        'days'        => $_GET['days'] ?? 14,
        'business_id' => $_GET['business_id'] ?? '',
    ];
    $params = array_merge($params, $overrides);
    $params = array_filter($params, fn($v) => $v !== '' && $v !== null);
    return http_build_query($params);
}

?>
<!DOCTYPE html>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Analytics - Netgrity WhatsApp API</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="assets/css/app.css" rel="stylesheet">

    <style>
        :root {
            --ng-body-bg: #f8fafc;
            --ng-card-bg: #ffffff;
            --ng-border-color: #e2e8f0;
            --ng-text-main: #0f172a;
            --ng-text-muted: #64748b;
            --ng-brand-primary: #10b981;
            --ng-brand-dark: #064e3b;
            --ng-radius: 16px;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--ng-body-bg);
            color: var(--ng-text-main);
        }

        .card-modern {
            background: var(--ng-card-bg);
            border: 1px solid var(--ng-border-color);
            border-radius: var(--ng-radius);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02), 0 2px 4px -2px rgba(0, 0, 0, 0.02);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .card-modern:hover {
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.01);
        }

        .stat-card {
            padding: 1.25rem;
            position: relative;
            overflow: hidden;
        }

        .stat-card .icon-shape {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            margin-bottom: 1rem;
        }

        .stat-card .stat-value {
            font-size: 1.75rem;
            font-weight: 700;
            letter-spacing: -0.02em;
            line-height: 1.2;
        }

        .stat-card .stat-label {
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--ng-text-muted);
        }

        .form-select-modern {
            background-color: #ffffff;
            border: 1px solid var(--ng-border-color);
            border-radius: 10px;
            font-weight: 500;
            font-size: 0.875rem;
            padding: 0.5rem 2.25rem 0.5rem 0.875rem;
            color: var(--ng-text-main);
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
            transition: all 0.2s ease;
        }

        .form-select-modern:focus {
            border-color: var(--ng-brand-primary);
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.15);
        }

        .card-header-modern {
            background: transparent;
            border-bottom: 1px solid var(--ng-border-color);
            padding: 1.25rem 1.5rem;
            font-weight: 700;
            font-size: 1rem;
            color: var(--ng-text-main);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .badge-soft {
            background-color: rgba(16, 185, 129, 0.1);
            color: var(--ng-brand-primary);
            border-radius: 8px;
            padding: 4px 8px;
            font-weight: 600;
            font-size: 0.75rem;
        }

        /* Color variations for icons & indicators */
        .bg-soft-primary { background: rgba(59, 130, 246, 0.1); color: #2563eb; }
        .bg-soft-success { background: rgba(16, 185, 129, 0.1); color: #059669; }
        .bg-soft-warning { background: rgba(245, 158, 11, 0.1); color: #d97706; }
        .bg-soft-danger  { background: rgba(239, 68, 68, 0.1);  color: #dc2626; }
        .bg-soft-info    { background: rgba(6, 182, 212, 0.1);  color: #0891b2; }
        .bg-soft-indigo  { background: rgba(99, 102, 241, 0.1); color: #4f46e5; }
    </style>
</head>

<body class="min-vh-100 d-flex flex-column">

    <?php require __DIR__ . '/includes/partials/navbar.php'; ?>

    <div class="container-fluid py-4 flex-grow-1" style="max-width: 1320px;">

        <!-- Header Section -->
        <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
            <div>
                <h4 class="fw-bold mb-1 d-flex align-items-center gap-2">
                    <span class="icon-shape bg-soft-success d-inline-flex m-0" style="width:36px; height:36px;">
                        <i class="bi bi-bar-chart-line-fill fs-6"></i>
                    </span>
                    Analytics Overview
                </h4>
                <p class="text-muted small mb-0">Track real-time delivery status, business distribution, and customer metrics.</p>
            </div>
            
            <div class="d-flex gap-2">
                <select class="form-select-modern" onchange="location.href='analytics?days='+this.value+'&business_id=<?= $businessFilter ?>'">
                    <option value="7" <?= $days === 7 ? 'selected' : '' ?>>Last 7 days</option>
                    <option value="14" <?= $days === 14 ? 'selected' : '' ?>>Last 14 days</option>
                    <option value="30" <?= $days === 30 ? 'selected' : '' ?>>Last 30 days</option>
                </select>
                
                <select class="form-select-modern" onchange="location.href='analytics?days=<?= $days ?>&business_id='+this.value">
                    <option value="">All Businesses</option>
                    <?php foreach ($activeBusinesses as $biz): ?>
                        <option value="<?= $biz['id'] ?>" <?= $businessFilter === (int)$biz['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($biz['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- Metric Stat Cards -->
        <div class="row g-3 mb-4">
            <div class="col-12 col-sm-6 col-xl-2">
                <div class="card card-modern h-100 stat-card">
                    <div class="icon-shape bg-soft-primary">
                        <i class="bi bi-chat-left-text"></i>
                    </div>
                    <div class="stat-label">Total Sent</div>
                    <div class="stat-value text-dark mt-1"><?= number_format($msgStats['total']) ?></div>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-xl-2">
                <div class="card card-modern h-100 stat-card">
                    <div class="icon-shape bg-soft-indigo">
                        <i class="bi bi-calendar-event"></i>
                    </div>
                    <div class="stat-label">Today</div>
                    <div class="stat-value text-dark mt-1"><?= number_format($msgStats['today']) ?></div>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-xl-2">
                <div class="card card-modern h-100 stat-card">
                    <div class="icon-shape bg-soft-success">
                        <i class="bi bi-check2-all"></i>
                    </div>
                    <div class="stat-label">Delivered</div>
                    <div class="stat-value text-success mt-1"><?= number_format($msgStats['delivered']) ?></div>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-xl-2">
                <div class="card card-modern h-100 stat-card">
                    <div class="icon-shape bg-soft-warning">
                        <i class="bi bi-eye"></i>
                    </div>
                    <div class="stat-label">Read</div>
                    <div class="stat-value text-warning mt-1"><?= number_format($msgStats['read']) ?></div>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-xl-2">
                <div class="card card-modern h-100 stat-card">
                    <div class="icon-shape bg-soft-danger">
                        <i class="bi bi-exclamation-triangle"></i>
                    </div>
                    <div class="stat-label">Failed</div>
                    <div class="stat-value text-danger mt-1"><?= number_format($msgStats['failed']) ?></div>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-xl-2">
                <div class="card card-modern h-100 stat-card">
                    <div class="icon-shape bg-soft-info">
                        <i class="bi bi-people"></i>
                    </div>
                    <div class="stat-label">Contacts</div>
                    <div class="stat-value text-dark mt-1"><?= number_format($contactStats['total']) ?></div>
                </div>
            </div>
        </div>

        <!-- Charts Section 1 -->
        <div class="row g-4 mb-4">
            <div class="col-lg-7">
                <div class="card card-modern h-100">
                    <div class="card-header-modern">
                        <span>Message Volume</span>
                        <span class="badge badge-soft"><?= $days ?> Days Range</span>
                    </div>
                    <div class="card-body">
                        <canvas id="volumeChart" height="260"></canvas>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-5">
                <div class="card card-modern h-100">
                    <div class="card-header-modern">
                        <span>Status Breakdown</span>
                    </div>
                    <div class="card-body d-flex align-items-center justify-content-center">
                        <canvas id="statusChart" height="260"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Section 2 -->
        <div class="row g-4 mb-4">
            <div class="col-lg-7">
                <div class="card card-modern h-100">
                    <div class="card-header-modern">
                        <span>Volume by Business</span>
                    </div>
                    <div class="card-body">
                        <canvas id="businessChart" height="260"></canvas>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card card-modern h-100">
                    <div class="card-header-modern">
                        <span>Top Active Customers</span>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($topCustomers)): ?>
                            <div class="text-muted small p-4 text-center">No customer activity recorded.</div>
                        <?php else: ?>
                            <div class="list-group list-group-flush border-0">
                                <?php foreach ($topCustomers as $i => $tc): ?>
                                    <div class="list-group-item d-flex justify-content-between align-items-center px-4 py-3 border-bottom border-light">
                                        <div class="d-flex align-items-center gap-3">
                                            <span class="badge bg-light text-dark fw-bold rounded-circle d-flex align-items-center justify-content-center" style="width: 28px; height: 28px;">
                                                <?= $i + 1 ?>
                                            </span>
                                            <span class="fw-semibold text-dark">
                                                <?= htmlspecialchars($tc['customer_name']) ?>
                                            </span>
                                        </div>
                                        <span class="badge bg-soft-primary rounded-pill px-3 py-2">
                                            <?= number_format((int)$tc['total_messages']) ?> msgs
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detailed Metrics Row -->
        <div class="row g-4">
            <div class="col-lg-6">
                <div class="card card-modern h-100">
                    <div class="card-header-modern">
                        <span>Message Type Mix</span>
                    </div>
                    <div class="card-body d-flex align-items-center justify-content-center">
                        <?php if (empty($types)): ?>
                            <div class="text-muted small">No message types recorded.</div>
                        <?php else: ?>
                            <canvas id="typeChart" height="240"></canvas>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card card-modern h-100 d-flex flex-column justify-content-between">
                    <div class="card-header-modern">
                        <span>Template Performance</span>
                    </div>
                    <div class="card-body">
                        <div class="row text-center g-3 mb-4">
                            <div class="col-3">
                                <div class="p-3 rounded-4 bg-light">
                                    <div class="h4 fw-bold text-info mb-1"><?= number_format($templatePerf['sent']) ?></div>
                                    <div class="stat-label">Sent</div>
                                </div>
                            </div>
                            <div class="col-3">
                                <div class="p-3 rounded-4 bg-light">
                                    <div class="h4 fw-bold text-primary mb-1"><?= number_format($templatePerf['delivered']) ?></div>
                                    <div class="stat-label">Delivered</div>
                                </div>
                            </div>
                            <div class="col-3">
                                <div class="p-3 rounded-4 bg-light">
                                    <div class="h4 fw-bold text-success mb-1"><?= number_format($templatePerf['read']) ?></div>
                                    <div class="stat-label">Read</div>
                                </div>
                            </div>
                            <div class="col-3">
                                <div class="p-3 rounded-4 bg-light">
                                    <div class="h4 fw-bold text-danger mb-1"><?= number_format($templatePerf['failed']) ?></div>
                                    <div class="stat-label">Failed</div>
                                </div>
                            </div>
                        </div>

                        <div class="p-3 rounded-3 bg-soft-info border-0 d-flex align-items-start gap-2 small">
                            <i class="bi bi-info-circle-fill fs-6 text-info mt-1"></i>
                            <div>
                                Template dispatches rely on pre-approved Meta components. Visit 
                                <a href="templates" class="fw-semibold text-decoration-none">Template Sync</a> 
                                to sync recent modifications.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Color definitions for smooth styling
        const theme = {
            emerald: '#10b981',
            emeraldLight: 'rgba(16, 185, 129, 0.15)',
            indigo: '#6366f1',
            indigoLight: 'rgba(99, 102, 241, 0.15)',
            slate: '#64748b',
            amber: '#f59e0b',
            rose: '#ef4444',
            cyan: '#06b6d4'
        };

        // Chart default style adjustments
        Chart.defaults.font.family = "'Plus Jakarta Sans', sans-serif";
        Chart.defaults.color = '#64748b';

        <?php
        $labels = array_column($timeSeries, 'day');
        $inboundData = array_map(fn($r) => $r['inbound'], $timeSeries);
        $outboundData = array_map(fn($r) => $r['outbound'], $timeSeries);
        $totalData = array_map(fn($r) => $r['total'], $timeSeries);
        ?>

        // Volume Chart (Smoother Curves + Soft Fills)
        new Chart(document.getElementById('volumeChart'), {
            type: 'line',
            data: {
                labels: <?= json_encode($labels) ?>,
                datasets: [
                    { 
                        label: 'Inbound', 
                        data: <?= json_encode($inboundData) ?>, 
                        borderColor: theme.emerald, 
                        backgroundColor: theme.emeraldLight,
                        borderWidth: 2,
                        fill: true, 
                        tension: 0.4,
                        pointRadius: 0,
                        pointHoverRadius: 6
                    },
                    { 
                        label: 'Outbound', 
                        data: <?= json_encode($outboundData) ?>, 
                        borderColor: theme.indigo, 
                        backgroundColor: theme.indigoLight,
                        borderWidth: 2,
                        fill: true, 
                        tension: 0.4,
                        pointRadius: 0,
                        pointHoverRadius: 6
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { 
                    legend: { position: 'top', align: 'end', labels: { boxWidth: 12, usePointStyle: true } } 
                },
                scales: {
                    x: { grid: { display: false } },
                    y: { grid: { color: '#f1f5f9' }, border: { dash: [4, 4] } }
                }
            }
        });

        // Status Donut Chart
        new Chart(document.getElementById('statusChart'), {
            type: 'doughnut',
            data: {
                labels: ['Queued', 'Sent', 'Delivered', 'Read', 'Failed', 'Received'],
                datasets: [{
                    data: [
                        <?= (int)$statuses['queued'] ?>,
                        <?= (int)$statuses['sent'] ?>,
                        <?= (int)$statuses['delivered'] ?>,
                        <?= (int)$statuses['read'] ?>,
                        <?= (int)$statuses['failed'] ?>,
                        <?= (int)$statuses['received'] ?>
                    ],
                    backgroundColor: [theme.slate, theme.cyan, theme.indigo, theme.emerald, theme.rose, '#334155'],
                    borderWidth: 0,
                    hoverOffset: 4
                }]
            },
            options: { 
                responsive: true, 
                maintainAspectRatio: false,
                cutout: '75%',
                plugins: {
                    legend: { position: 'right', labels: { boxWidth: 10, usePointStyle: true } }
                }
            }
        });

        // Horizontal Business Bar Chart
        new Chart(document.getElementById('businessChart'), {
            type: 'bar',
            data: {
                labels: <?= json_encode(array_map(fn($r) => mb_strimwidth($r['business_name'], 0, 18, '…'), $businesses)) ?>,
                datasets: [{
                    label: 'Messages',
                    data: <?= json_encode(array_map(fn($r) => $r['total'], $businesses)) ?>,
                    backgroundColor: theme.indigo,
                    borderRadius: 8,
                    barThickness: 16
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y',
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { color: '#f1f5f9' }, border: { dash: [4, 4] } },
                    y: { grid: { display: false } }
                }
            }
        });

        <?php if (!empty($types)): 
            $typeLabels = array_map('ucfirst', array_keys($types));
            $typeData = array_values($types);
        ?>
        // Type Mix Donut Chart
        new Chart(document.getElementById('typeChart'), {
            type: 'doughnut',
            data: {
                labels: <?= json_encode($typeLabels) ?>,
                datasets: [{
                    data: <?= json_encode($typeData) ?>,
                    backgroundColor: [theme.indigo, theme.emerald, theme.amber, theme.slate, theme.cyan, theme.rose],
                    borderWidth: 0
                }]
            },
            options: { 
                responsive: true, 
                maintainAspectRatio: false,
                cutout: '70%',
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 10, usePointStyle: true } }
                }
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>

</html>
