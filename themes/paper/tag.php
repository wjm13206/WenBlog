<?php
/** @var array<string, mixed> $tag */
/** @var list<array<string, mixed>> $posts */
/** @var \App\Core\Paginator $pagination */
require __DIR__ . '/partials/header.php';
?>
<div class="container page-heading">
    <p class="eyebrow">标签</p>
    <h1># <?= e($tag['name']) ?></h1>
    <?php if (trim((string) ($tag['description'] ?? '')) !== ''): ?><p class="page-description"><?= e((string) $tag['description']) ?></p><?php endif; ?>
</div>

<section class="home-content">
    <div class="container content-grid<?= $showSidebar ? '' : ' no-sidebar' ?>">
        <div class="post-feed">
            <?php if ($posts === []): ?>
                <div class="empty-state"><span class="empty-icon"><?= icon('tag', 28) ?></span><h2>这个标签下还没有文章</h2></div>
            <?php else: ?>
                <?php foreach ($posts as $post): ?><?php require __DIR__ . '/partials/post-card.php'; ?><?php endforeach; ?>
                <?php require __DIR__ . '/partials/pagination.php'; ?>
            <?php endif; ?>
        </div>
        <?php if ($showSidebar): ?><?php require __DIR__ . '/partials/sidebar.php'; ?><?php endif; ?>
    </div>
</section>
<?php require __DIR__ . '/partials/footer.php'; ?>