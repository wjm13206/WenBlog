<?php
/** @var list<array<string, mixed>> $posts */
/** @var list<array<string, mixed>> $archives */
/** @var \App\Core\Paginator $pagination */
/** @var string $filterYear */
/** @var string $filterMonth */
require __DIR__ . '/partials/header.php';
?>
<div class="container page-heading">
    <p class="eyebrow">时间线</p>
    <h1>文章归档</h1>
    <p class="page-description">所有发布过的文章，按时间整理。</p>
</div>

<section class="home-content">
    <div class="container content-grid<?= $showSidebar ? '' : ' no-sidebar' ?>">
        <div class="post-feed">
            <?php if ($archives !== []): ?>
                <div class="archive-filter">
                    <a class="archive-chip<?= $filterYear === '' ? ' is-active' : '' ?>" href="<?= e(url('archives')) ?>">全部</a>
                    <?php foreach ($archives as $archive): ?>
                        <?php
                        $year = (string) $archive['year'];
                        $month = (string) $archive['month'];
                        $isActive = $filterYear === $year && ($filterMonth === '' || $filterMonth === $month);
                        ?>
                        <a class="archive-chip<?= $isActive ? ' is-active' : '' ?>" href="<?= e(url('archives')) ?>?year=<?= (int) $year ?>&amp;month=<?= (int) $month ?>">
                            <?= (int) $year ?>.<?= str_pad($month, 2, '0', STR_PAD_LEFT) ?><span><?= (int) $archive['count'] ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($posts === []): ?>
                <div class="empty-state"><span class="empty-icon"><?= icon('clock', 28) ?></span><h2>这个时间段没有文章</h2></div>
            <?php else: ?>
                <?php foreach ($posts as $post): ?><?php require __DIR__ . '/partials/post-card.php'; ?><?php endforeach; ?>
                <?php require __DIR__ . '/partials/pagination.php'; ?>
            <?php endif; ?>
        </div>
        <?php if ($showSidebar): ?><?php require __DIR__ . '/partials/sidebar.php'; ?><?php endif; ?>
    </div>
</section>
<?php require __DIR__ . '/partials/footer.php'; ?>