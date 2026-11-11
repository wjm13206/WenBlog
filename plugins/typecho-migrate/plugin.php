<?php

/**
 * Typecho migration plugin.
 *
 * Registers an admin screen at /admin/plugins/typecho-migrate. The screen is
 * only reachable while the plugin is active, because this file is included by
 * PluginManager::loadActive() at boot time.
 */

declare(strict_types=1);

use App\Core\Hook;
use App\Core\Router;
use WenBlog\Plugins\TypechoMigrate\Controller;

require_once __DIR__ . '/src/Content.php';
require_once __DIR__ . '/src/Migrator.php';
require_once __DIR__ . '/src/Controller.php';

Hook::add('app.routes', static function (Router $router): void {
    $router->get('/admin/plugins/typecho-migrate', [Controller::class, 'index'], ['auth' => true, 'admin' => true]);
    $router->post('/admin/plugins/typecho-migrate/run', [Controller::class, 'run'], ['auth' => true, 'admin' => true]);
});

Hook::add('admin.plugins.actions', static function (array $plugin): void {
    if ((string) ($plugin['slug'] ?? '') !== 'typecho-migrate') {
        return;
    }
    echo '<a class="btn btn-ghost btn-sm" href="' . e(admin_url('plugins/typecho-migrate')) . '">'
        . icon('database', 15) . ' 迁移工具</a>';
});
