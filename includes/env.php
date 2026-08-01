<?php

// includes/env.php
// Minimal .env loader — no external dependency required.

function loadEnv(string $path): void
{
    if (!file_exists($path)) {
        throw new RuntimeException(".env file not found at: {$path}");
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {

        // Skip comments
        if (str_starts_with(trim($line), '#')) {
            continue;
        }

        // Must contain '='
        if (!str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);

        $key   = trim($key);
        $value = trim($value);

        // Strip surrounding quotes ("value" or 'value')
        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $value = substr($value, 1, -1);
        }

        // Populate $_ENV, $_SERVER and putenv — only if not already set
        if (!array_key_exists($key, $_ENV)) {
            $_ENV[$key] = $value;
            putenv("{$key}={$value}");
        }

        if (!array_key_exists($key, $_SERVER)) {
            $_SERVER[$key] = $value;
        }
    }
}

/**
 * Retrieve an environment variable with an optional default.
 *
 * Usage:  env('META_ACCESS_TOKEN')
 *         env('APP_DEBUG', false)
 */
function env(string $key, mixed $default = null): mixed
{
    $value = $_ENV[$key] ?? getenv($key);

    if ($value === false) {
        return $default;
    }

    // Cast common string booleans
    return match (strtolower((string) $value)) {
        'true',  '(true)'  => true,
        'false', '(false)' => false,
        'null',  '(null)'  => null,
        default            => $value,
    };
}
