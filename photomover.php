<?php

use Photomover\Application;
use Photomover\Container;

if (PHP_SAPI !== 'cli') {
    echo 'PhotoMover is console only', PHP_EOL;
    exit(1);
}

define('CONFIG_PATH', __DIR__ . '/config');
define('LOG_PATH', __DIR__ . '/log');

require_once __DIR__ . '/vendor/autoload.php';

$container = new Container();
$container->init();

$application = new Application('PhotoMover', '0.0.1');
$application->setContainer($container);

exit($application->run());
