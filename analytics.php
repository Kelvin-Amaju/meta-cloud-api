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
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Analytics - Netgrity WhatsApp API</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/app.css" rel="stylesheet">
</head>

<body class="bg-light min-vh-100 d-flex flex-column">

    <?php require __DIR__ . '/includes/partials/navbar.php'; ?>

    <div class="container py-2 flex-grow-1" style="max-width:1200px;">

        <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
            <h4 class="fw-bold mb-0 ng-title">
                <i class="bi bi-bar-chart-line text-success"></i> Analytics Dashboard
            </h4>
            <div class="d-flex gap-2">
                <select class="form-select form-select-sm" onchange="location.href='analytics?days='+this.value+'&business_id=<?= $businessFilter ?>'">
                    <option value="7" <?= $days === 7 ? 'selected' : '' ?>>Last 7 days</option>
                    <option value="14" <?= $days === 14 ? 'selected' : '' ?>>Last 14 days</option>
                    <option value="30" <?= $days === 30 ? 'selected' : '' ?>>Last 30 days</option>
                </select>
                <select class="form-select form-select-sm" onchange="location.href='analytics?days=<?= $days ?>&business_id='+this.value">
                    <option value="">All Businesses</option>
                    <?php foreach ($activeBusinesses as $biz): ?>
                        <option value="<?= $biz['id'] ?>" <?= $businessFilter === (int)$biz['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($biz['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- Stat cards -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-2">
                <div class="card card-ng h-100 stat-card">
                    <div class="card-body">
                        <div class="text-muted small text-uppercase fw-semibold">Total</div>
                        <div class="display-6"><?= number_format($msgStats['total']) ?></div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="card card-ng h-100 stat-card">
                    <div class="card-body">
                        <div class="text-muted small text-uppercase fw-semibold">Today</div>
                        <div class="display-6"><?= number_format($msgStats['today']) ?></div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="card card-ng h-100 stat-card">
                    <div class="card-body">
                        <div class="text-muted small text-uppercase fw-semibold">Delivered</div>
                        <div class="display-6 text-success"><?= number_format($msgStats['delivered']) ?></div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="card card-ng h-100 stat-card">
                    <div class="card-body">
                        <div class="text-muted small text-uppercase fw-semibold">Read</div>
                        <div class="display-6 text-warning"><?= number_format($msgStats['read']) ?></div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="card card-ng h-100 stat-card">
                    <div class="card-body">
                        <div class="text-muted small text-uppercase fw-semibold">Failed</div>
                        <div class="display-6 text-danger"><?= number_format($msgStats['failed']) ?></div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="card card-ng h-100 stat-card">
                    <div class="card-body">
                        <div class="text-muted small text-uppercase fw-semibold">Contacts</div>
                        <div class="display-6"><?= number_format($contactStats['total']) ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts row 1 -->
        <div class="row g-4 mb-4">
            <div class="col-lg-7">
                <div class="card card-ng h-100">
                    <div class="card-header-ng py-3 px-3 fw-semibold">Message Volume (<?= $days ?> days)</div>
                    <div class="card-body">
                        <canvas id="volumeChart" height="280"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="card card-ng h-100">
                    <div class="card-header-ng py-3 px-3 fw-semibold">Status Breakdown</div>
                    <div class="card-body d-flex align-items-center">
                        <canvas id="statusChart" height="280"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts row 2 -->
        <div class="row g-4 mb-4">
            <div class="col-lg-7">
                <div class="card card-ng h-100">
                    <div class="card-header-ng py-3 px-3 fw-semibold">Volume by Business</div>
                    <div class="card-body">
                        <canvas id="businessChart" height="280"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="card card-ng h-100">
                    <div class="card-header-ng py-3 px-3 fw-semibold">Top Customers</div>
                    <div class="card-body">
                        <?php if (empty($topCustomers)): ?>
                            <div class="text-muted small">No customer activity yet.</div>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($topCustomers as $i => $tc): ?>
                                    <div class="list-group-item d-flex justify-content-between align-items-center px-0 border-start-0 border-end-0">
                                        <span class="fw-semibold">
                                            <span class="badge-ng-soft badge me-2">#<?= $i + 1 ?></span>
                                            <?= htmlspecialchars($tc['customer_name']) ?>
                                        </span>
                                        <span class="text-muted small"><?= number_format((int)$tc['total_messages']) ?> msgs</span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Message type + template performance -->
        <div class="row g-4">
            <div class="col-lg-6">
                <div class="card card-ng h-100">
                    <div class="card-header-ng py-3 px-3 fw-semibold">Message Type Mix</div>
                    <div class="card-body">
                        <?php if (empty($types)): ?>
                            <div class="text-muted small">No messages recorded yet.</div>
                        <?php else: ?>
                            <canvas id="typeChart" height="240"></canvas>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card card-ng h-100">
                    <div class="card-header-ng py-3 px-3 fw-semibold">Template Performance</div>
                    <div class="card-body">
                        <div class="row text-center g-3">
                            <div class="col-3">
                                <div class="display-6 text-info"><?= number_format($templatePerf['sent']) ?></div>
                                <div class="text-muted small text-uppercase fw-semibold">Sent</div>
                            </div>
                            <div class="col-3">
                                <div class="display-6 text-primary"><?= number_format($templatePerf['delivered']) ?></div>
                                <div class="text-muted small text-uppercase fw-semibold">Delivered</div>
                            </div>
                            <div class="col-3">
                                <div class="display-6 text-success"><?= number_format($templatePerf['read']) ?></div>
                                <div class="text-muted small text-uppercase fw-semibold">Read</div>
                            </div>
                            <div class="col-3">
                                <div class="display-6 text-danger"><?= number_format($templatePerf['failed']) ?></div>
                                <div class="text-muted small text-uppercase fw-semibold">Failed</div>
                            </div>
                        </div>
                        <hr>
                        <div class="small text-muted">
                            <i class="bi bi-info-circle me-1"></i>
                            Template sends use Meta approved templates. Run <a href="templates">Template Sync</a> to keep your template list current.
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        var ngOrange = '#ff6b00';
        var ngBlack = '#272626';
        var ngGreen = '#198754';

        <?php
        $labels = array_column($timeSeries, 'day');
        $inboundData = array_map(fn($r) => $r['inbound'], $timeSeries);
        $outboundData = array_map(fn($r) => $r['outbound'], $timeSeries);
        $totalData = array_map(fn($r) => $r['total'], $timeSeries);
        ?>

        // Volume chart
        new Chart(document.getElementById('volumeChart'), {
            type: 'line',
            data: {
                labels: <?= json_encode($labels) ?>,
                datasets: [
                    { label: 'Inbound', data: <?= json_encode($inboundData) ?>, borderColor: ngGreen, backgroundColor: 'rgba(25,135,84,.1)', tension: 0.3 },
                    { label: 'Outbound', data: <?= json_encode($outboundData) ?>, borderColor: ngOrange, backgroundColor: 'rgba(255,107,0,.1)', tension: 0.3 }
                ]
            },
            options: {
                responsive: true,
                plugins: { legend: { position: 'top' } }
            }
        });

        // Status donut
        new Chart(document.getElementById('statusChart'), {
            type: 'doughnut',
            data: {
                labels: ['Queued', 'Sent', 'Delivered', 'Read', 'Failed', 'Received'],
                datasets: [{
                    data: [
                        <?= $statuses['queued'] ?>,
                        <?= $statuses['sent'] ?>,
                        <?= $statuses['delivered'] ?>,
                        <?= $statuses['read'] ?>,
                        <?= $statuses['failed'] ?>,
                        <?= $statuses['received'] ?>
                    ],
                    backgroundColor: ['#6c757d', '#0dcaf0', '#0d6efd', '#198754', '#dc3545', '#212529']
                }]
            },
            options: { responsive: true, cutout: '60%' }
        });

        // Business bar
        new Chart(document.getElementById('businessChart'), {
            type: 'bar',
            data: {
                labels: <?= json_encode(array_map(fn($r) => mb_strimwidth($r['business_name'], 0, 18, '…'), $businesses)) ?>,
                datasets: [{
                    label: 'Messages',
                    data: <?= json_encode(array_map(fn($r) => $r['total'], $businesses)) ?>,
                    backgroundColor: 'rgba(255,107,0,.75)',
                    borderColor: ngOrange,
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                indexAxis: 'y',
                plugins: { legend: { display: false } }
            }
        });

        <?php
        $typeLabels = array_map('ucfirst', array_keys($types));
        $typeData = array_values($types);
        ?>
        // Type mix
        new Chart(document.getElementById('typeChart'), {
            type: 'doughnut',
            data: {
                labels: <?= json_encode($typeLabels) ?>,
                datasets: [{
                    data: <?= json_encode($typeData) ?>,
                    backgroundColor: [ngOrange, '#0d6efd', '#198754', '#6c757d', '#ffc107', '#0dcaf0']
                }]
            },
            options: { responsive: true, cutout: '50%' }
        });
    </script>
</body>

</html>
