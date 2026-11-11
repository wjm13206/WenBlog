<?php
/**
 * Shared document head and site header.
 *
 * Variables such as $pageTitle, $pageDescription and $noIndex are provided by
 * the controller. Everything else is read from site options.
 */
$siteName = (string) option('site_name', 'WenBlog');
$siteDescription = (string) option('site_description', '');
$seoDescription = (string) option('seo_description', '');
$seoKeywords = (string) option('seo_keywords', '');
$pageTitle = isset($pageTitle) && $pageTitle !== '' ? (string) $pageTitle : $siteName;
$pageDescription = isset($pageDescription) && $pageDescription !== ''
    ? (string) $pageDescription
    : ($seoDescription !== '' ? $seoDescription : $siteDescription);
$noIndex = !empty($noIndex);
$suffix = (string) option('seo_title_suffix', '');
$documentTitle = $pageTitle === $siteName
    ? $siteName
    : $pageTitle . ($suffix !== '' ? $suffix : ' · ' . $siteName);
$accent = (string) theme_config('accent_color', '#4f46e5');
if (preg_match('/^#[0-9a-fA-F]{6}$/', $accent) !== 1) {
    $accent = '#4f46e5';
}
$logo = (string) option('site_logo', '');
$favicon = (string) option('site_favicon', '');
$path = (string) (app()?->request->path() ?? '/');
$isHome = $path === '/';
$showSidebar = (bool) theme_config('show_sidebar', '1');
$flashes = app()?->session->flashes() ?? [];
$brandLetter = mb_substr($siteName, 0, 1, 'UTF-8');
$canonical = url(ltrim($path, '/'));
if ($path === '/archives') {
    $canonicalQuery = [];
    foreach (['year', 'month', 'page'] as $key) {
        $value = app()?->request->query($key);
        if (is_string($value) && preg_match('/^\d{1,6}$/', $value) === 1) {
            $canonicalQuery[$key] = $value;
        }
    }
    if ($canonicalQuery !== []) {
        $canonical .= '?' . http_build_query($canonicalQuery);
    }
}
?>
<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($documentTitle) ?></title>
    <meta name="description" content="<?= e($pageDescription) ?>">
    <?php if ($seoKeywords !== ''): ?><meta name="keywords" content="<?= e($seoKeywords) ?>"><?php endif; ?>
    <?php if ($noIndex): ?><meta name="robots" content="noindex,follow"><?php endif; ?>
    <meta property="og:type" content="<?= isset($post) ? 'article' : 'website' ?>">
    <meta property="og:site_name" content="<?= e($siteName) ?>">
    <meta property="og:title" content="<?= e($pageTitle) ?>">
    <meta property="og:description" content="<?= e($pageDescription) ?>">
    <meta name="theme-color" content="<?= e($accent) ?>">
    <link rel="canonical" href="<?= e($canonical) ?>">
    <link rel="alternate" type="application/rss+xml" title="<?= e($siteName) ?> RSS" href="<?= e(url('rss')) ?>">
    <?php if ($favicon !== ''): ?>
        <link rel="icon" href="<?= e(upload_url($favicon)) ?>">
    <?php endif; ?>
    <link rel="stylesheet" href="<?= e(theme_asset('assets/style.css')) ?>">
    <style>:root { --accent: <?= e($accent) ?>; }</style>
    <script>
        (function () {
            try {
                var saved = localStorage.getItem('wenblog-theme');
                if (saved === 'light' || saved === 'dark') document.documentElement.setAttribute('data-theme', saved);
            } catch (error) {}
        })();
    </script>
    <?php \App\Core\Hook::do('theme.head'); ?>
</head>
<body class="<?= $isHome ? 'is-home' : 'is-inner' ?>">
<a class="skip-link" href="#main">跳到正文</a>

<header class="site-header">
    <div class="container header-inner">
        <a class="brand" href="<?= e(url('/')) ?>" aria-label="<?= e($siteName) ?> 首页">
            <?php if ($logo !== ''): ?>
                <img class="brand-logo" src="<?= e(upload_url($logo)) ?>" alt="">
            <?php else: ?>
                <span class="brand-mark"><?= e($brandLetter) ?></span>
            <?php endif; ?>
            <span class="brand-text">
                <strong><?= e($siteName) ?></strong>
                <?php if ($siteDescription !== ''): ?><small><?= e($siteDescription) ?></small><?php endif; ?>
            </span>
        </a>

        <nav class="desktop-nav" aria-label="主导航">
            <a href="<?= e(url('/')) ?>" class="<?= $isHome ? 'active' : '' ?>">首页</a>
            <a href="<?= e(url('archives')) ?>" class="<?= str_starts_with($path, '/archives') ? 'active' : '' ?>">归档</a>
            <a href="<?= e(url('rss')) ?>">RSS</a>
        </nav>

        <div class="header-tools">
            <form class="header-search" method="get" action="<?= e(url('search')) ?>" role="search">
                <label class="sr-only" for="header-q">搜索文章</label>
                <input id="header-q" type="search" name="q" value="<?= e(isset($query) ? (string) $query : '') ?>" placeholder="搜索文章…" autocomplete="off">
                <button type="submit" aria-label="搜索"><?= icon('search', 17) ?></button>
            </form>
            <button class="icon-button theme-toggle" type="button" data-theme-toggle aria-label="切换深浅色">
                <span class="icon-sun"><?= icon('sun', 18) ?></span>
                <span class="icon-moon"><?= icon('moon', 18) ?></span>
            </button>
            <button class="icon-button nav-toggle" type="button" data-nav-toggle aria-label="打开菜单" aria-expanded="false">
                <?= icon('menu', 20) ?>
            </button>
        </div>
    </div>

    <div class="mobile-menu" data-mobile-menu hidden>
        <nav aria-label="移动导航">
            <a href="<?= e(url('/')) ?>">首页</a>
            <a href="<?= e(url('archives')) ?>">文章归档</a>
            <a href="<?= e(url('rss')) ?>">RSS 订阅</a>
            <?php if (current_user() !== null): ?><a href="<?= e(admin_url()) ?>">管理后台</a><?php endif; ?>
        </nav>
        <form class="mobile-search" method="get" action="<?= e(url('search')) ?>" role="search">
            <label class="sr-only" for="mobile-q">搜索文章</label>
            <input id="mobile-q" type="search" name="q" value="<?= e(isset($query) ? (string) $query : '') ?>" placeholder="搜索文章…">
            <button type="submit"><?= icon('search', 17) ?> 搜索</button>
        </form>
    </div>
</header>

<?php if ($flashes !== []): ?>
    <div class="container flash-stack">
        <?php foreach ($flashes as $type => $messages): ?>
            <?php foreach ((array) $messages as $message): ?>
                <div class="flash flash-<?= e($type === 'error' ? 'error' : ($type === 'success' ? 'success' : 'info')) ?>" data-flash>
                    <span><?= e((string) $message) ?></span>
                    <button type="button" aria-label="关闭提示" data-flash-close><?= icon('x', 16) ?></button>
                </div>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<main id="main" class="site-main">