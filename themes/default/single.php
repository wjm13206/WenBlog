<?php
/** @var array<string, mixed> $post */
/** @var list<array<string, mixed>> $categories */
/** @var list<array<string, mixed>> $tags */
/** @var array{previous: ?array, next: ?array} $adjacent */
/** @var list<array<string, mixed>> $related */
require __DIR__ . '/partials/header.php';
$authorName = (string) ($post['author_name'] ?? option('site_name', 'WenBlog'));
?>
<div class="container content-grid reading-grid<?= $showSidebar ? '' : ' no-sidebar' ?>">
    <article class="post-single">
        <header class="post-header">
            <?php if ($categories !== []): ?>
                <div class="post-categories">
                    <?php foreach ($categories as $category): ?>
                        <a href="<?= e(category_url($category)) ?>"><?= e($category['name']) ?></a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <h1><?= e($post['title']) ?></h1>
            <?php if (trim((string) $post['excerpt']) !== ''): ?>
                <p class="post-lead"><?= e((string) $post['excerpt']) ?></p>
            <?php endif; ?>
            <div class="post-meta post-meta-large">
                <span class="author-chip"><span class="author-dot"><?= e(initials($authorName)) ?></span><?= e($authorName) ?></span>
                <time datetime="<?= e((string) $post['published_at']) ?>"><?= e(format_date($post['published_at'])) ?></time>
                <span><?= reading_minutes((string) $post['content']) ?> 分钟阅读</span>
                <span><?= icon('eye', 15) ?> <?= (int) $post['views'] ?></span>
            </div>
        </header>

        <?php if (trim((string) ($post['cover'] ?? '')) !== ''): ?>
            <figure class="post-cover-full">
                <img src="<?= e(upload_url((string) $post['cover'])) ?>" alt="">
            </figure>
        <?php endif; ?>

        <div class="prose">
            <?= \App\Core\Hook::filter('post.html', markdown((string) $post['content']), $post) ?>
        </div>

        <?php if ($tags !== []): ?>
            <footer class="post-tags">
                <?php foreach ($tags as $tag): ?>
                    <a href="<?= e(tag_url($tag)) ?>"># <?= e($tag['name']) ?></a>
                <?php endforeach; ?>
            </footer>
        <?php endif; ?>

        <?php if ($adjacent['previous'] !== null || $adjacent['next'] !== null): ?>
            <nav class="post-adjacent" aria-label="上一篇和下一篇">
                <?php if ($adjacent['previous'] !== null): ?>
                    <a class="adjacent-link" href="<?= e(post_url($adjacent['previous'])) ?>">
                        <small><?= icon('arrow-left', 14) ?> 上一篇</small>
                        <strong><?= e($adjacent['previous']['title']) ?></strong>
                    </a>
                <?php endif; ?>
                <?php if ($adjacent['next'] !== null): ?>
                    <a class="adjacent-link adjacent-next" href="<?= e(post_url($adjacent['next'])) ?>">
                        <small>下一篇 <?= icon('arrow-right', 14) ?></small>
                        <strong><?= e($adjacent['next']['title']) ?></strong>
                    </a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>

        <?php require __DIR__ . '/partials/comments.php'; ?>
    </article>

    <?php if ($showSidebar): ?>
        <div class="reading-sidebar">
            <?php require __DIR__ . '/partials/sidebar.php'; ?>
        </div>
    <?php endif; ?>
</div>

<?php if ($related !== []): ?>
    <section class="related-section">
        <div class="container">
            <div class="section-heading">
                <p class="eyebrow">继续阅读</p>
                <h2>相关文章</h2>
            </div>
            <div class="related-grid">
                <?php foreach ($related as $cardPost): ?>
                    <?php require __DIR__ . '/partials/post-card.php'; ?>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
<?php endif; ?>
<?php require __DIR__ . '/partials/footer.php'; ?>