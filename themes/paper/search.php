<?php
/** @var string $query */
/** @var list<array<string, mixed>> $posts */
/** @var \App\Core\Paginator $pagination */
require __DIR__ . '/partials/header.php';
?>
<div class="container page-heading">
    <p class="eyebrow">搜索</p>
    <h1><?= $query !== '' ? '“' . e($query) . '” 的结果' : '搜索文章' ?></h1>
    <form class="page-search" method="get" action="<?= e(url('search')) ?>" role="search">
        <label class="sr-only" for="page-q">搜索关键词</label>
        <input id="page-q" type="search" name="q" value="<?= e($query) ?>" placeholder="输入关键词…" autofocus>
        <button class="button button-primary" type="submit"><?= icon('search', 16) ?> 搜索</button>
    </form>
</div>

<section class="home-content">
    <div class="container content-grid<?= $showSidebar ? '' : ' no-sidebar' ?>">
        <div class="post-feed">
            <?php if ($query === ''): ?>
                <div class="empty-state"><span class="empty-icon"><?= icon('search', 28) ?></span><h2>输入关键词开始搜索</h2><p>可以搜索标题、摘要和正文。</p></div>
            <?php elseif ($posts === []): ?>
                <div class="empty-state"><span class="empty-icon"><?= icon('file-text', 28) ?></span><h2>没有找到相关文章</h2><p>试试更短或更常见的关键词。</p></div>
            <?php else: ?>
                <p class="result-count">共找到 <?= (int) $pagination->total() ?> 篇文章</p>
                <?php foreach ($posts as $post): ?><?php require __DIR__ . '/partials/post-card.php'; ?><?php endforeach; ?>
                <?php require __DIR__ . '/partials/pagination.php'; ?>
            <?php endif; ?>
        </div>
        <?php if ($showSidebar): ?><?php require __DIR__ . '/partials/sidebar.php'; ?><?php endif; ?>
    </div>
</section>
<?php require __DIR__ . '/partials/footer.php'; ?>