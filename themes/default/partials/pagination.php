<?php if (isset($pagination) && $pagination->hasPages()): ?>
    <nav class="pagination" aria-label="分页">
        <?php if ($pagination->hasPrevious()): ?>
            <a class="page-link page-prev" href="<?= e($pagination->url($pagination->previousPage())) ?>" rel="prev"><?= icon('arrow-left', 15) ?> 上一页</a>
        <?php endif; ?>
        <?php foreach ($pagination->pages() as $page): ?>
            <?php if ($page === '…'): ?>
                <span class="page-ellipsis">…</span>
            <?php elseif ((int) $page === $pagination->current()): ?>
                <span class="page-link is-current" aria-current="page"><?= (int) $page ?></span>
            <?php else: ?>
                <a class="page-link" href="<?= e($pagination->url((int) $page)) ?>"><?= (int) $page ?></a>
            <?php endif; ?>
        <?php endforeach; ?>
        <?php if ($pagination->hasNext()): ?>
            <a class="page-link page-next" href="<?= e($pagination->url($pagination->nextPage())) ?>" rel="next">下一页 <?= icon('arrow-right', 15) ?></a>
        <?php endif; ?>
    </nav>
<?php endif; ?>