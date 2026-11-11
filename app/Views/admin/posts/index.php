<?php
/** @var list<array<string, mixed>> $posts */
/** @var \App\Core\Paginator $pagination */
/** @var string $status */
/** @var string $search */
/** @var array<string, int> $counts */
$tabs = [
    ''          => ['全部', (int) $counts['total']],
    'published' => ['已发布', (int) $counts['published']],
    'draft'     => ['草稿', (int) $counts['draft']],
    'scheduled' => ['定时', (int) $counts['scheduled']],
];
?>
<div class="page-toolbar">
    <div class="tabs">
        <?php foreach ($tabs as $key => [$label, $count]): ?>
            <a class="tab <?= $status === $key ? 'active' : '' ?>" href="<?= e(admin_url('posts' . ($key !== '' ? '?status=' . $key : ''))) ?>">
                <?= e($label) ?> <span><?= $count ?></span>
            </a>
        <?php endforeach; ?>
    </div>
    <div class="toolbar-actions">
        <form class="search-form" method="get" action="<?= e(admin_url('posts')) ?>">
            <?php if ($status !== ''): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
            <?= icon('search', 17) ?>
            <input type="search" name="q" value="<?= e($search) ?>" placeholder="搜索标题或内容">
        </form>
        <a class="btn btn-primary" href="<?= e(admin_url('posts/create')) ?>"><?= icon('plus') ?> 写文章</a>
    </div>
</div>

<section class="card">
    <?php if ($posts === []): ?>
        <div class="empty">
            <?= icon('file-text', 34) ?>
            <h3>还没有符合条件的文章</h3>
            <p>开始记录你的第一个想法吧。</p>
            <a class="btn btn-primary" href="<?= e(admin_url('posts/create')) ?>">写第一篇文章</a>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table posts-table">
                <thead>
                <tr>
                    <th>标题</th>
                    <th>分类</th>
                    <th>状态</th>
                    <th class="num">浏览</th>
                    <th class="num">评论</th>
                    <th>发布时间</th>
                    <th class="actions-col">操作</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($posts as $post): ?>
                    <tr>
                        <td>
                            <div class="title-cell">
                                <?php if ((int) $post['pinned'] === 1): ?><span class="pin-mark" title="置顶"><?= icon('pin', 14) ?></span><?php endif; ?>
                                <a class="table-title" href="<?= e(admin_url('posts/' . $post['id'] . '/edit')) ?>"><?= e($post['title']) ?></a>
                                <small class="muted"><?= e($post['author_name'] ?? '') ?></small>
                            </div>
                        </td>
                        <td class="muted"><?= e($post['category_names'] ?? '—') ?></td>
                        <td><span class="badge badge-<?= e((string) $post['status']) ?>"><?= e(status_label((string) $post['status'])) ?></span></td>
                        <td class="num"><?= (int) $post['views'] ?></td>
                        <td class="num"><?= (int) $post['comment_count'] ?></td>
                        <td class="muted nowrap"><?= e(format_date((string) ($post['published_at'] ?: $post['created_at']), 'Y-m-d H:i')) ?></td>
                        <td>
                            <div class="row-actions">
                                <?php if ($post['status'] === 'published'): ?>
                                    <a class="icon-btn" href="<?= e(post_url($post)) ?>" target="_blank" rel="noopener" title="查看"><?= icon('eye', 16) ?></a>
                                <?php endif; ?>
                                <a class="icon-btn" href="<?= e(admin_url('posts/' . $post['id'] . '/revisions')) ?>" title="修订记录"><?= icon('clock', 16) ?></a>
                                <a class="icon-btn" href="<?= e(admin_url('posts/' . $post['id'] . '/edit')) ?>" title="编辑"><?= icon('edit', 16) ?></a>
                                <form method="post" action="<?= e(admin_url('posts/' . $post['id'] . '/delete')) ?>" data-confirm="确定删除这篇文章及其修订记录吗？此操作不可撤销。">
                                    <?= csrf_field() ?>
                                    <button class="icon-btn danger" type="submit" title="删除"><?= icon('trash', 16) ?></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?= pagination($pagination) ?>
    <?php endif; ?>
</section>