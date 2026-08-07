<?php
// _includes/messaging_limit_banner.inc.php
//
// Shared warning: all phone numbers under the same Meta Business Portfolio
// share ONE messaging limit tier. A high-volume send from one tenant can
// throttle every other tenant's number. Include this wherever a dev/admin
// or the client-facing dashboard needs to see it.
//
// Usage: require __DIR__ . '/../_includes/messaging_limit_banner.inc.php';
// Optional: set $bannerCompact = true before including for a slim version.

$bannerCompact = $bannerCompact ?? false;
?>
<?php if ($bannerCompact): ?>
    <div class="alert alert-warning border-0 shadow-sm d-flex align-items-center gap-2 py-2 px-3 mb-4 small">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <span>
            <strong>Shared messaging limits:</strong> all numbers in this Meta Business Portfolio
            share one tier — a high-volume sender can throttle everyone else.
        </span>
    </div>
<?php else: ?>
    <div class="alert alert-warning border-0 shadow-sm mb-4" role="alert">
        <div class="d-flex align-items-start gap-3">
            <i class="bi bi-exclamation-triangle-fill fs-4"></i>
            <div>
                <div class="fw-semibold">Shared messaging limits across businesses</div>
                <div class="small mt-1">
                    Meta calculates and enforces messaging limits at the <strong>business portfolio</strong> level,
                    not per phone number. Every business's number registered under this portfolio shares the same
                    tier — if one business (e.g. a large campaign send) pushes volume close to the limit, it can
                    throttle sending for every other business in this list. Monitor per-business send volume
                    individually and coordinate large broadcasts across tenants.
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>
