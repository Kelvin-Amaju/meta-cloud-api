<?php

//includes/helpers.php

function response($status, $message, $data = [])
{
    header('Content-Type: application/json');

    echo json_encode([
        'status'  => $status,
        'message' => $message,
        'data'    => $data
    ]);

    exit;
}

function sanitize($value)
{
    return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
}

function post($key, $default = '')
{
    return isset($_POST[$key])
        ? sanitize($_POST[$key])
        : $default;
}

function get($key, $default = '')
{
    return isset($_GET[$key])
        ? sanitize($_GET[$key])
        : $default;
}

function redirect($url)
{
    header("Location: {$url}");
    exit;
}

function dd($data)
{
    echo "<pre>";
    print_r($data);
    echo "</pre>";
    exit;
}