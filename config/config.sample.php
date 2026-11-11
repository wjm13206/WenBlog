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
        // 在线更新源：GitHub 仓库（owner/repo）或自定义 JSON 清单地址。
        // 清空仓库则回退到 wjm13206/WenBlog；清单地址非空时优先使用清单。
        'update_repo'     => 'wjm13206/WenBlog',
        'update_manifest' => '',
        'installed_at' => null,
    ],
];