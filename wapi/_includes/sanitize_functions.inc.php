<?php

// _includes/sanitize_functions.inc.php
// Input sanitizing + output escaping helpers (reference §4.1).

/**
 * Sanitize raw user input on READ. Strips tags, slashes and special chars.
 */
function test_input(?string $value): string
{
    return htmlspecialchars(
        stripslashes(strip_tags(trim((string) $value))),
        ENT_QUOTES,
        'UTF-8'
    );
}

/**
 * Escape a value for safe echo into HTML. Use for ALL output (reference §E.19).
 */
function reyon_h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/**
 * Normalize a WhatsApp phone number: digits only.
 */
function fix_phone(?string $value): string
{
    return preg_replace('/[^0-9]/', '', (string) $value);
}

/**
 * Cast to int with a sensible default.
 */
function to_int(mixed $value, int $default = 0): int
{
    $n = filter_var($value, FILTER_VALIDATE_INT);
    return $n === false ? $default : $n;
}

/**
 * Redirect to a relative URL and stop.
 */
function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

/**
 * Send a JSON response and stop.
 */
function json_out(mixed $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}
