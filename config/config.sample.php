<?php

/**
 * Copy this file to config/config.php or let install.php generate it.
 * Never commit the generated config.php — it contains database credentials.
 */
return [
    'db' => [
        'host'    => '127.0.0.1',
        'port'    => 3306,
        'name'    => 'wenblog',
        'user'    => 'root',
        'pass'    => '',
        'charset' => 'utf8mb4',
    ],
    'app' => [
        'url'     => 'http://localhost',
        'key'     => '',
        'debug'   => false,
        'version' => '1.0.0',
        'installed_at' => null,
    ],
];