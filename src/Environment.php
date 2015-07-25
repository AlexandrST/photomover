<?php

namespace Photomover;

use Dotenv\Dotenv;

class Environment
{
    const ERRLVL = -1;

    private $dotEnvPath;

    private static $required = [
        'CLIENT_ID',
        'CLIENT_SECRET',
        'API_VER',
        'VK_LOGIN',
        'VK_PASS',
    ];

    public function __construct($dotEnvPath)
    {
        $this->dotEnvPath = $dotEnvPath;
    }

    public function init()
    {
        $this->setupErrors();
        $this->loadEnv();
    }

    private function setupErrors()
    {
        error_reporting(self::ERRLVL);
        ini_set('display_errors', true);

        set_error_handler(
            function($severity, $message, $file, $line) {
                if (error_reporting() & $severity) {
                    throw new \ErrorException(
                        $message,
                        0,
                        $severity,
                        $file,
                        $line
                    );
                }
            },
            self::ERRLVL
        );

        set_exception_handler(function(\Exception $e) {
            $message = $e->getMessage();
            $trace = $e->getTraceAsString();

            echo $message, PHP_EOL, PHP_EOL, $trace, PHP_EOL;
            exit(2);
        });
    }

    private function loadEnv()
    {
        $dotenv = new Dotenv($this->dotEnvPath);
        $dotenv->load();
        $dotenv->required(self::$required)->notEmpty();
    }
}
