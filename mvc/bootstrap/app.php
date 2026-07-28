<?php

declare(strict_types=1);

use Dotenv\Dotenv;
use Netgrity\Core\App;

require_once dirname(__DIR__) . '/vendor/autoload.php';

$dotenv = Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

return new App();