<?php

declare(strict_types=1);

/**
 * WenBlog front controller.
 *
 * All HTTP traffic is routed through this file. Static assets and uploaded
 * files are served directly by the web server (see .htaccess / nginx.conf).
 */

define('BASE_PATH', __DIR__);

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

require BASE_PATH . '/app/autoload.php';
require BASE_PATH . '/app/helpers.php';
require BASE_PATH . '/app/icons.php';

$configFile = BASE_PATH . '/config/config.php';
$sampleFile = BASE_PATH . '/config/config.sample.php';

$config = [];
try {
    if (is_file($configFile)) {
        $config = require $configFile;
    } elseif (is_file($sampleFile)) {
        $config = require $sampleFile;
    } else {
        $config = [
            'db' => [
                'host' => '127.0.0.1',
                'port' => 3306,
                'name' => 'wenblog',
                'user' => 'root',
                'pass' => '',
                'charset' => 'utf8mb4',
            ],
            'app' => [
                'url' => '',
                'key' => '',
                'debug' => false,
                'version' => '1.0.0',
                'installed_at' => null,
            ],
        ];
    }
} catch (\Throwable $e) {
    \App\Core\Logger::error('Configuration file could not be loaded: ' . $e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ]);
    \App\Core\Response::error(500, '配置文件无法读取，请检查 config/config.php 的格式与权限。');
}

if (!is_array($config)) {
    $config = [];
}

try {
    $app = \App\Core\App::boot($config);
    $app->run();
} catch (\Throwable $e) {
    \App\Core\Logger::error('Unhandled front-controller error: ' . $e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ]);
    $debug = (bool) ($config['app']['debug'] ?? false);
    \App\Core\Response::error(
        500,
        $debug ? $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() : '服务器暂时无法处理该请求，请稍后再试。'
    );
}