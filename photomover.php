<?php

if (PHP_SAPI !== 'cli') {
    echo 'PhotoMover is console only', PHP_EOL;
    exit(1);
}

require_once __DIR__ . '/vendor/autoload.php';

use Photomover\Application;
use Photomover\Environment;

$env = new Environment(__DIR__);
$env->init();

$app = new Application();
exit($app->run());
