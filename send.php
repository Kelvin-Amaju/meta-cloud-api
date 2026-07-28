<?php

// send.php

require_once 'includes/whatsapp.php';

$alert = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Basic input sanitization
    $rawPhone = trim($_POST['phone'] ?? '');
    $message  = trim($_POST['message'] ?? '');

    // Normalize phone number (strip whitespace, +, hyphens, brackets)
    $cleanPhone = preg_replace('/[^0-9]/', '', $rawPhone);

    if (empty($cleanPhone) || empty($message)) {
        $alert = [
            'type'    => 'danger',
            'title'   => 'Validation Error',
            'message' => 'Please fill in both the phone number and the message content.'
        ];
    } else {
        // Send via includes/whatsapp.php
        $response = sendTextMessage($cleanPhone, $message);

        if (!empty($response['success'])) {
            $wamid = $response['data']['messages'][0]['id'] ?? 'N/A';
            $alert = [
                'type'    => 'success',
                'title'   => 'Message Sent Successfully!',
                'message' => "Sended to <strong>+{$cleanPhone}</strong>. <br><small class='font-monospace'>Message ID: {$wamid}</small>",
                'raw'     => $response
            ];
        } else {
            // Extract Meta's explicit error details if available
            $errorMessage = $response['data']['error']['message'] 
                ?? $response['error'] 
                ?? 'An unknown error occurred while contacting WhatsApp API.';
            
            $errorCode = $response['data']['error']['code'] ?? ($response['status'] ?? 'Unknown');

            $alert = [
                'type'    => 'danger',
                'title'   => "API Error (Code {$errorCode})",
                'message' => htmlspecialchars($errorMessage),
                'raw'     => $response
            ];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Send Message - Netgrity WhatsApp API</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
</head>

<body class="bg-light min-vh-100 d-flex flex-column">

    <!-- Top Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4 shadow-sm">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-2" href="index">
                <i class="bi bi-whatsapp text-success fs-4"></i>
                <span class="fw-bold">Netgrity</span> WhatsApp API
            </a>
            <a href="index" class="btn btn-outline-light btn-sm">
                <i class="bi bi-arrow-left me-1"></i> Dashboard
            </a>
        </div>
    </nav>

    <div class="container my-auto py-4" style="max-width: 680px;">
        
        <!-- Flash Response Alerts -->
        <?php if ($alert): ?>
            <div class="alert alert-<?= $alert['type'] ?> alert-dismissible fade show shadow-sm border-0 mb-4" role="alert">
                <div class="d-flex align-items-center gap-2 mb-1">
                    <i class="bi bi-<?= $alert['type'] === 'success' ? 'check-circle-fill' : 'exclamation-octagon-fill' ?> fs-5"></i>
                    <h5 class="alert-heading mb-0 fw-bold"><?= $alert['title'] ?></h5>
                </div>
                <div class="mt-2"><?= $alert['message'] ?></div>

                <!-- Toggle Raw Response Inspector -->
                <?php if (isset($alert['raw'])): ?>
                    <div class="mt-3">
                        <button class="btn btn-sm btn-outline-<?= $alert['type'] ?>" type="button" data-bs-toggle="collapse" data-bs-target="#debugResponse">
                            <i class="bi bi-code-slash me-1"></i> Toggle JSON Payload
                        </button>
                        <div class="collapse mt-2" id="debugResponse">
                            <pre class="bg-dark text-light p-3 rounded fs-7 mb-0"><code><?= htmlspecialchars(json_encode($alert['raw'], JSON_PRETTY_PRINT)) ?></code></pre>
                        </div>
                    </div>
                <?php endif; ?>

                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Send Message Card Form -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 border-0">
                <h4 class="card-title fw-bold mb-0 text-dark d-flex align-items-center gap-2">
                    <i class="bi bi-send text-success"></i> Send WhatsApp Message
                </h4>
            </div>
            <div class="card-body p-4">
                <form method="post" action="send">
                    <!-- Phone Number Input -->
                    <div class="mb-3">
                        <label for="phone" class="form-label fw-semibold text-secondary">
                            Recipient Phone Number <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted"><i class="bi bi-telephone-fill"></i></span>
                            <input 
                                type="text" 
                                class="form-control" 
                                id="phone" 
                                name="phone" 
                                placeholder="2348012345678" 
                                value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" 
                                required
                            >
                        </div>
                        <div class="form-text">Must include country code without leading zeros or plus signs (e.g., 2348012345678).</div>
                    </div>

                    <!-- Message Textarea -->
                    <div class="mb-4">
                        <label for="message" class="form-label fw-semibold text-secondary">
                            Message Content <span class="text-danger">*</span>
                        </label>
                        <textarea 
                            class="form-control" 
                            id="message" 
                            name="message" 
                            rows="4" 
                            placeholder="Type your WhatsApp notification message here..." 
                            required
                        ><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
                    </div>

                    <!-- Action Buttons -->
                    <div class="d-flex align-items-center justify-content-between pt-2">
                        <a href="index" class="btn btn-light border text-muted">Cancel</a>
                        <button type="submit" class="btn btn-success px-4 fw-semibold d-flex align-items-center gap-2">
                            <i class="bi bi-paperplane-fill"></i> Send Message
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>