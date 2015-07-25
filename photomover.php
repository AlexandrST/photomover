<?php

if (PHP_SAPI !== 'cli') {
    echo 'PhotoMover is console only', PHP_EOL;
    exit(1);
}

require_once __DIR__ . '/vendor/autoload.php';
