<?php
$sidebarCategories = $sidebarCategories ?? [];
$sidebarTags = $sidebarTags ?? [];
$sidebarLinks = $sidebarLinks ?? [];
$sidebarLatest = $sidebarLatest ?? [];
$sidebarArchives = $sidebarArchives ?? [];
?>
<aside class="sidebar">
    <?php if ((string) option('site_description', '') !== ''): ?>
        <section class="widget widget-about">
            <h2 class="widget-title">关于本站</h2>
            <p><?= e((string) option('site_description', '')) ?></p>
        </section>
    <?php endif; ?>

    <?php if ($sidebarCategories !== []): ?>
        <section class="widget">
            <h2 class="widget-title">分类</h2>
            <ul class="widget-list category-list">
                <?php foreach ($sidebarCategories as $category): ?>
                    <li style="--depth: <?= (int) ($category['depth'] ?? 0) ?>">
                        <a href="<?= e(category_url($category)) ?>">
                            <span><?= e($category['name']) ?></span>
                            <em><?= (int) ($category['post_count'] ?? 0) ?></em>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>

    <?php if ($sidebarTags !== []): ?>
        <section class="widget">
            <h2 class="widget-title">标签</h2>
            <div class="tag-cloud">
                <?php foreach (array_slice($sidebarTags, 0, 24) as $tag): ?>
                    <a href="<?= e(tag_url($tag)) ?>"><?= e($tag['name']) ?><span><?= (int) ($tag['post_count'] ?? 0) ?></span></a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($sidebarLatest !== []): ?>
        <section class="widget">
            <h2 class="widget-title">最新文章</h2>
            <ol class="widget-list latest-list">
                <?php foreach ($sidebarLatest as $item): ?>
                    <li>
                        <a href="<?= e(post_url($item)) ?>"><?= e($item['title']) ?></a>
                        <time datetime="<?= e((string) $item['published_at']) ?>"><?= e(format_date($item['published_at'], 'n月j日')) ?></time>
                    </li>
                <?php endforeach; ?>
            </ol>
        </section>
    <?php endif; ?>

    <?php if ($sidebarArchives !== []): ?>
        <section class="widget">
            <h2 class="widget-title">归档</h2>
            <ul class="widget-list archive-list">
                <?php foreach (array_slice($sidebarArchives, 0, 8) as $archive): ?>
                    <li>
                        <a href="<?= e(url('archives')) ?>?year=<?= (int) $archive['year'] ?>&amp;month=<?= (int) $archive['month'] ?>">
                            <span><?= (int) $archive['year'] ?> 年 <?= (int) $archive['month'] ?> 月</span>
                            <em><?= (int) $archive['count'] ?></em>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>

    <?php if ($sidebarLinks !== []): ?>
        <section class="widget">
            <h2 class="widget-title">友情链接</h2>
            <ul class="widget-list links-list">
                <?php foreach ($sidebarLinks as $link): ?>
                    <li>
                        <a href="<?= e($link['url']) ?>" target="_blank" rel="noopener nofollow"><?= e($link['name']) ?></a>
                        <?php if (!empty($link['description'])): ?><small><?= e($link['description']) ?></small><?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>
</aside>