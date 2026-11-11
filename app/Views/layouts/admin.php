<?php
/**
 * @var string $title
 * @var string $content
 * @var \App\Core\App $app
 */
$user = current_user();
$flashes = $app->session->flashes();
$allErrors = errors();
$generalErrors = array_intersect_key($allErrors, array_flip(['form', 'general', 'install', 'requirements', 'db', 'site', 'admin']));
$siteName = (string) option('site_name', 'WenBlog');
$isDashboard = $app->request->path() === '/admin';
?>
<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= e($title) ?> · <?= e($siteName) ?> 控制台</title>
    <link rel="stylesheet" href="<?= e(asset('admin/admin.css')) ?>">
    <?php if ((string) option('site_favicon', '') !== ''): ?>
        <link rel="icon" href="<?= e(upload_url((string) option('site_favicon'))) ?>">
    <?php endif; ?>
    <script>
        (function () {
            try {
                var saved = localStorage.getItem('wenblog-theme');
                if (saved === 'light' || saved === 'dark') document.documentElement.setAttribute('data-theme', saved);
            } catch (e) {}
        })();
    </script>
</head>
<body class="admin-body">
<div class="admin-shell">
    <aside class="admin-sidebar" id="admin-sidebar" aria-label="主导航">
        <a class="brand" href="<?= e(admin_url()) ?>">
            <span class="brand-mark">W</span>
            <span class="brand-text"><strong><?= e($siteName) ?></strong><small>内容管理系统</small></span>
        </a>
        <nav class="side-nav">
            <div class="nav-group">
                <span class="nav-label">概览</span>
                <a class="nav-link <?= $isDashboard ? 'active' : '' ?>" href="<?= e(admin_url()) ?>"><?= icon('dashboard') ?><span>仪表盘</span></a>
            </div>
            <div class="nav-group">
                <span class="nav-label">内容</span>
                <a class="nav-link <?= e(is_active('/admin/posts')) ?>" href="<?= e(admin_url('posts')) ?>"><?= icon('file-text') ?><span>文章</span></a>
                <a class="nav-link <?= e(is_active('/admin/pages')) ?>" href="<?= e(admin_url('pages')) ?>"><?= icon('layers') ?><span>页面</span></a>
                <a class="nav-link <?= e(is_active('/admin/categories')) ?>" href="<?= e(admin_url('categories')) ?>"><?= icon('folder') ?><span>分类</span></a>
                <a class="nav-link <?= e(is_active('/admin/tags')) ?>" href="<?= e(admin_url('tags')) ?>"><?= icon('tag') ?><span>标签</span></a>
                <a class="nav-link <?= e(is_active('/admin/comments')) ?>" href="<?= e(admin_url('comments')) ?>"><?= icon('message') ?><span>评论</span></a>
                <a class="nav-link <?= e(is_active('/admin/media')) ?>" href="<?= e(admin_url('media')) ?>"><?= icon('image') ?><span>媒体库</span></a>
            </div>
            <?php if (is_admin()): ?>
            <div class="nav-group">
                <span class="nav-label">外观与扩展</span>
                <a class="nav-link <?= e(is_active('/admin/themes')) ?>" href="<?= e(admin_url('themes')) ?>"><?= icon('palette') ?><span>主题</span></a>
                <a class="nav-link <?= e(is_active('/admin/plugins')) ?>" href="<?= e(admin_url('plugins')) ?>"><?= icon('puzzle') ?><span>插件</span></a>
                <a class="nav-link <?= e(is_active('/admin/links')) ?>" href="<?= e(admin_url('links')) ?>"><?= icon('link') ?><span>友情链接</span></a>
            </div>
            <div class="nav-group">
                <span class="nav-label">系统</span>
                <a class="nav-link <?= e(is_active('/admin/users')) ?>" href="<?= e(admin_url('users')) ?>"><?= icon('users') ?><span>用户</span></a>
                <a class="nav-link <?= e(is_active('/admin/settings')) ?>" href="<?= e(admin_url('settings')) ?>"><?= icon('settings') ?><span>设置</span></a>
            </div>
            <?php endif; ?>
        </nav>
        <div class="sidebar-foot">
            <a class="user-chip" href="<?= e(admin_url('profile')) ?>">
                <span class="avatar"><?= e(initials((string) ($user['display_name'] ?? 'W'))) ?></span>
                <span class="user-meta"><strong><?= e($user['display_name'] ?? $user['username'] ?? '用户') ?></strong><small><?= e(role_label((string) ($user['role'] ?? 'editor'))) ?></small></span>
            </a>
            <form method="post" action="<?= e(admin_url('logout')) ?>" class="logout-form">
                <?= csrf_field() ?>
                <button class="icon-btn" type="submit" title="退出登录" aria-label="退出登录"><?= icon('logout') ?></button>
            </form>
        </div>
    </aside>
    <div class="sidebar-overlay" data-sidebar-close></div>

    <div class="admin-main">
        <header class="admin-topbar">
            <button class="icon-btn mobile-toggle" type="button" data-sidebar-toggle aria-label="打开导航"><?= icon('menu', 20) ?></button>
            <div class="topbar-title">
                <span class="topbar-eyebrow"><?= $isDashboard ? '站点概览' : '控制台' ?></span>
                <h1><?= e($title) ?></h1>
            </div>
            <div class="topbar-actions">
                <a class="icon-btn" href="<?= e(url('/')) ?>" target="_blank" rel="noopener" title="查看站点" aria-label="查看站点"><?= icon('external') ?></a>
                <button class="icon-btn" type="button" data-theme-toggle title="切换深浅色" aria-label="切换深浅色">
                    <span class="theme-sun"><?= icon('sun') ?></span><span class="theme-moon"><?= icon('moon') ?></span>
                </button>
            </div>
        </header>

        <main class="admin-content">
            <?php foreach ($flashes as $type => $messages): ?>
                <?php foreach ((array) $messages as $message): ?>
                    <div class="alert alert-<?= e($type === 'error' ? 'error' : ($type === 'success' ? 'success' : 'info')) ?>" data-auto-hide>
                        <?= icon($type === 'error' ? 'x' : ($type === 'success' ? 'check' : 'circle'), 18) ?>
                        <span><?= e($message) ?></span>
                    </div>
                <?php endforeach; ?>
            <?php endforeach; ?>
            <?php if ($generalErrors !== []): ?>
                <div class="alert alert-error">
                    <?= icon('x', 18) ?>
                    <span><?= e(implode(' ', $generalErrors)) ?></span>
                </div>
            <?php endif; ?>
            <?= $content ?>
        </main>
        <footer class="admin-footer">
            <span>WenBlog <?= e((string) option('app_version', '1.0.0')) ?></span>
            <span>轻量 · 专注写作</span>
        </footer>
    </div>
</div>
<script src="<?= e(asset('admin/admin.js')) ?>" defer></script>
</body>
</html>