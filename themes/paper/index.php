<?php
/** @var list<array<string, mixed>> $posts */
/** @var \App\Core\Paginator $pagination */
/** @var int $currentPage */
$siteName = (string) option('site_name', 'WenBlog');
$siteDescription = (string) option('site_description', '');
require __DIR__ . '/partials/header.php';
?>
<?php if ($currentPage <= 1): ?>
    <section class="home-hero">
        <div class="container hero-inner">
            <p class="eyebrow"><?= e(date('Y')) ?> · 持续写作中</p>
            <h1><?= e($siteName) ?></h1>
            <p class="hero-description"><?= e($siteDescription !== '' ? $siteDescription : '在这里记录想法、经验与那些值得被记住的瞬间。') ?></p>
            <div class="hero-actions">
                <a class="button button-primary" href="<?= e(url('archives')) ?>">浏览全部文章 <?= icon('arrow-right', 16) ?></a>
                <a class="button button-ghost" href="<?= e(url('rss')) ?>">RSS 订阅</a>
            </div>
        </div>
    </section>
<?php else: ?>
    <div class="container page-heading">
        <p class="eyebrow">文章列表</p>
        <h1>第 <?= (int) $currentPage ?> 页</h1>
    </div>
<?php endif; ?>

<section class="home-content">
    <div class="container content-grid<?= $showSidebar ? '' : ' no-sidebar' ?>">
        <div class="post-feed">
            <?php if ($posts === []): ?>
                <div class="empty-state">
                    <span class="empty-icon"><?= icon('file-text', 28) ?></span>
                    <h2>还没有文章</h2>
                    <p>新的内容正在路上，稍后再来看看吧。</p>
                </div>
            <?php else: ?>
                <?php foreach ($posts as $post): ?>
                    <?php require __DIR__ . '/partials/post-card.php'; ?>
                <?php endforeach; ?>
                <?php require __DIR__ . '/partials/pagination.php'; ?>
            <?php endif; ?>
        </div>
        <?php if ($showSidebar): ?><?php require __DIR__ . '/partials/sidebar.php'; ?><?php endif; ?>
    </div>
</section>
<?php require __DIR__ . '/partials/footer.php'; ?>