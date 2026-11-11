<?php
/** @var array<string, mixed> $category */
/** @var list<array<string, mixed>> $breadcrumb */
/** @var list<array<string, mixed>> $posts */
/** @var \App\Core\Paginator $pagination */
require __DIR__ . '/partials/header.php';
?>
<div class="container page-heading">
    <?php if ($breadcrumb !== []): ?>
        <nav class="breadcrumb" aria-label="面包屑">
            <a href="<?= e(url('/')) ?>">首页</a>
            <?php foreach ($breadcrumb as $crumb): ?>
                <span>/</span><a href="<?= e(category_url($crumb)) ?>"><?= e($crumb['name']) ?></a>
            <?php endforeach; ?>
        </nav>
    <?php endif; ?>
    <p class="eyebrow">分类</p>
    <h1><?= e($category['name']) ?></h1>
    <?php if (trim((string) ($category['description'] ?? '')) !== ''): ?><p class="page-description"><?= e((string) $category['description']) ?></p><?php endif; ?>
</div>

<section class="home-content">
    <div class="container content-grid<?= $showSidebar ? '' : ' no-sidebar' ?>">
        <div class="post-feed">
            <?php if ($posts === []): ?>
                <div class="empty-state"><span class="empty-icon"><?= icon('folder', 28) ?></span><h2>这个分类还没有文章</h2><p>换个分类看看吧。</p></div>
            <?php else: ?>
                <?php foreach ($posts as $post): ?><?php require __DIR__ . '/partials/post-card.php'; ?><?php endforeach; ?>
                <?php require __DIR__ . '/partials/pagination.php'; ?>
            <?php endif; ?>
        </div>
        <?php if ($showSidebar): ?><?php require __DIR__ . '/partials/sidebar.php'; ?><?php endif; ?>
    </div>
</section>
<?php require __DIR__ . '/partials/footer.php'; ?>