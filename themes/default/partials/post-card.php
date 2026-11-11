<?php
$card = $cardPost ?? $post;
$cover = trim((string) ($card['cover'] ?? ''));
$hasCover = $cover !== '';
$excerpt = post_excerpt($card, 150);
?>
<article class="post-card<?= $hasCover ? ' has-cover' : '' ?>">
    <?php if ($hasCover): ?>
        <a class="post-cover" href="<?= e(post_url($card)) ?>" tabindex="-1" aria-hidden="true">
            <img src="<?= e(upload_url($cover)) ?>" alt="" loading="lazy">
        </a>
    <?php endif; ?>
    <div class="post-card-body">
        <div class="post-meta">
            <?php if (!empty($card['pinned'])): ?><span class="pin-badge"><?= icon('pin', 13) ?> 置顶</span><?php endif; ?>
            <time datetime="<?= e((string) $card['published_at']) ?>"><?= e(format_date($card['published_at'])) ?></time>
            <span aria-hidden="true">·</span>
            <span><?= reading_minutes((string) $card['content']) ?> 分钟阅读</span>
        </div>
        <h2 class="post-card-title"><a href="<?= e(post_url($card)) ?>"><?= isset($card['search_html_title']) ? $card['search_html_title'] : e($card['title']) ?></a></h2>
        <?php if (isset($card['search_html_excerpt'])): ?>
            <p class="post-card-excerpt"><?= $card['search_html_excerpt'] ?></p>
        <?php elseif ($excerpt !== ''): ?><p class="post-card-excerpt"><?= e($excerpt) ?></p><?php endif; ?>
        <div class="post-card-foot">
            <span class="post-author"><?= e((string) ($card['author_name'] ?? option('site_name', 'WenBlog'))) ?></span>
            <a class="read-more" href="<?= e(post_url($card)) ?>">阅读全文 <?= icon('arrow-right', 15) ?></a>
        </div>
    </div>
</article>