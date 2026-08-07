<?php

// wapi/bin/smoke_one.php <page> — render a single page in CLI mode

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST']      = 'localhost';

$page = isset($argv[1]) ? $argv[1] : '';
if ($page === '' || !is_file($page)) {
    fwrite(STDERR, "[FAIL] unknown page: {$page}\n");
    exit(1);
}

try {
    ob_start();
    include $page;
    ob_end_clean();
    fwrite(STDOUT, "[OK]   {$page}\n");
    exit(0);
} catch (Throwable $e) {
    ob_end_clean();
    fwrite(STDERR, "[FAIL] {$page} — {$e->getMessage()} in {$e->getFile()}:{$e->getLine()}\n");
    exit(1);
}
