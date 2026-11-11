<?php
/** @var array<string, mixed> $page */
require __DIR__ . '/partials/header.php';
?>
<div class="container narrow-container">
    <article class="post-single page-single">
        <header class="post-header">
            <p class="eyebrow">页面</p>
            <h1><?= e($page['title']) ?></h1>
            <?php if (trim((string) ($page['excerpt'] ?? '')) !== ''): ?>
                <p class="post-lead"><?= e((string) $page['excerpt']) ?></p>
            <?php endif; ?>
            <div class="post-meta">
                <time datetime="<?= e((string) $page['updated_at']) ?>">更新于 <?= e(format_date($page['updated_at'])) ?></time>
            </div>
        </header>
        <div class="prose">
            <?= \App\Core\Hook::filter('page.html', markdown((string) $page['content']), $page) ?>
        </div>
    </article>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>