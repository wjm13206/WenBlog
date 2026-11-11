<?php
/** @var array<string, mixed> $post */
/** @var list<array<string, mixed>> $revisions */
?>
<div class="page-toolbar">
    <div>
        <a class="text-link" href="<?= e(admin_url('posts/' . $post['id'] . '/edit')) ?>"><?= icon('arrow-left', 15) ?> 返回编辑</a>
        <h2 class="page-subtitle">《<?= e($post['title']) ?>》的修订历史</h2>
    </div>
    <span class="badge badge-soft">最多保留 30 个版本</span>
</div>

<section class="card">
    <?php if ($revisions === []): ?>
        <div class="empty">
            <?= icon('clock', 34) ?>
            <h3>还没有修订记录</h3>
            <p>每次保存文章时，系统会自动保留上一版内容。</p>
        </div>
    <?php else: ?>
        <div class="timeline">
            <?php foreach ($revisions as $revision): ?>
                <article class="timeline-item">
                    <div class="timeline-dot"></div>
                    <div class="timeline-body">
                        <div class="timeline-head">
                            <strong><?= e(format_date((string) $revision['created_at'], 'Y-m-d H:i:s')) ?></strong>
                            <span class="muted"><?= e($revision['author_name'] ?? '系统') ?></span>
                        </div>
                        <h4><?= e($revision['title']) ?></h4>
                        <p class="muted"><?= e(truncate(\App\Core\Markdown::text((string) $revision['content']), 160)) ?></p>
                        <details>
                            <summary>查看正文快照</summary>
                            <pre class="snapshot"><?= e($revision['content']) ?></pre>
                        </details>
                        <form method="post" action="<?= e(admin_url('posts/' . $post['id'] . '/revisions/' . $revision['id'] . '/restore')) ?>" data-confirm="恢复此版本会覆盖当前内容（旧内容仍会保留为新修订）。确定继续吗？">
                            <?= csrf_field() ?>
                            <button class="btn btn-ghost btn-sm" type="submit"><?= icon('refresh', 15) ?> 恢复此版本</button>
                        </form>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>