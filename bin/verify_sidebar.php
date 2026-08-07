<?php

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST']      = 'localhost';

$tests = [
    'root page'        => ['home.php', '#href="home"#', '#href="business"#'],
    'business subdir'  => ['business/index.php', '#href="../home"#', '#href="../settings/whatsapp"#'],
];

$fail = 0;

foreach ($tests as $label => [$file, $pat, $pat2]) {
    ob_start();
    include $file;
    $html = ob_get_clean();

    $ok1 = preg_match($pat, $html) === 1;
    $ok2 = preg_match($pat2, $html) === 1;
    echo "[{$label}] sidebar=" . (preg_match('/ng-sidebar/', $html) ? 'yes' : 'no')
        . ' linkBase=' . ($ok1 && $ok2 ? 'ok' : 'BAD') . PHP_EOL;
    if (!$ok1 || !$ok2 || !preg_match('/ng-sidebar/', $html)) $fail++;
}

exit($fail > 0 ? 1 : 0);
