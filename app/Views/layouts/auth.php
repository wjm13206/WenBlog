<?php
/**
 * @var string $title
 * @var string $content
 */
$siteName = (string) option('site_name', 'WenBlog');
$flashes = app()?->session->flashes() ?? [];
?>
<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title><?= e($title) ?> · <?= e($siteName) ?></title>
    <link rel="stylesheet" href="<?= e(asset('admin/admin.css')) ?>">
    <script>
        (function () {
            try {
                var saved = localStorage.getItem('wenblog-theme');
                if (saved === 'light' || saved === 'dark') document.documentElement.setAttribute('data-theme', saved);
            } catch (e) {}
        })();
    </script>
</head>
<body class="auth-body">
<main class="auth-wrap">
    <section class="auth-brand">
        <a class="brand brand-large" href="<?= e(url('/')) ?>">
            <span class="brand-mark">W</span>
            <span class="brand-text"><strong><?= e($siteName) ?></strong><small>轻量级博客内容管理系统</small></span>
        </a>
        <div class="auth-quote">
            <h1>把注意力留给写作。</h1>
            <p>简洁的内容管理、可靠的性能与恰到好处的扩展能力。</p>
        </div>
        <a class="back-link" href="<?= e(url('/')) ?>"><?= icon('arrow-left') ?> 返回站点首页</a>
    </section>
    <section class="auth-panel">
        <?php foreach ($flashes as $type => $messages): ?>
            <?php foreach ((array) $messages as $message): ?>
                <div class="alert alert-<?= e($type === 'error' ? 'error' : 'success') ?>"><span><?= e($message) ?></span></div>
            <?php endforeach; ?>
        <?php endforeach; ?>
        <?= $content ?>
    </section>
</main>
<script src="<?= e(asset('admin/admin.js')) ?>" defer></script>
</body>
</html>