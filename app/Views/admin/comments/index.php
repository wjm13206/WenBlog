<?php
/** @var list<array<string, mixed>> $comments */
/** @var \App\Core\Paginator $pagination */
/** @var string $status */
/** @var string $search */
$tabs = [
    ''         => '全部',
    'pending'  => '待审核',
    'approved' => '已通过',
    'spam'     => '垃圾',
];
?>
<div class="page-toolbar">
    <div>
        <h2 class="page-subtitle">评论管理</h2>
        <p class="muted">审核读者反馈，回复讨论，或标记垃圾评论。</p>
    </div>
</div>

<section class="card">
    <div class="card-head comments-head">
        <div class="tabs">
            <?php foreach ($tabs as $value => $label): ?>
                <a class="tab<?= $status === $value ? ' active' : '' ?>" href="<?= e(admin_url('comments' . ($value !== '' ? '?status=' . $value : ''))) ?>"><?= e($label) ?></a>
            <?php endforeach; ?>
        </div>
        <form class="search-form" method="get" action="<?= e(admin_url('comments')) ?>">
            <?php if ($status !== ''): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
            <input class="input" type="search" name="q" value="<?= e($search) ?>" placeholder="搜索内容或作者">
            <button class="btn btn-ghost" type="submit"><?= icon('search', 17) ?></button>
        </form>
    </div>

    <?php if ($comments === []): ?>
        <div class="empty">
            <?= icon('message', 34) ?>
            <h3>没有匹配的评论</h3>
            <p>换个筛选条件，或等待读者留言。</p>
        </div>
    <?php else: ?>
        <div class="comment-list">
            <?php foreach ($comments as $comment): ?>
                <article class="comment-admin-item">
                    <div class="avatar avatar-sm"><?= e(initials((string) $comment['author_name'])) ?></div>
                    <div class="comment-admin-body">
                        <div class="comment-admin-head">
                            <div>
                                <strong><?= e($comment['author_name']) ?></strong>
                                <span class="muted"><?= e($comment['author_email']) ?></span>
                                <span class="muted">· <?= e(format_date((string) $comment['created_at'], 'Y-m-d H:i')) ?></span>
                            </div>
                            <span class="badge badge-<?= e((string) $comment['status']) ?>"><?= e(comment_status_label((string) $comment['status'])) ?></span>
                        </div>
                        <p class="comment-admin-meta">
                            <?= icon('file-text', 13) ?>
                            <?php if (!empty($comment['post_slug'])): ?>
                                <a href="<?= e(post_url(['slug' => $comment['post_slug']])) ?>" target="_blank" rel="noopener"><?= e($comment['post_title'] ?: '（文章已删除）') ?></a>
                            <?php else: ?>
                                文章已删除
                            <?php endif; ?>
                            <span class="muted">· IP <?= e($comment['ip'] ?: '未知') ?></span>
                        </p>
                        <div class="comment-admin-content"><?= nl2br(e($comment['content'])) ?></div>
                        <div class="comment-admin-actions">
                            <?php if ($comment['status'] !== 'approved'): ?>
                                <form method="post" action="<?= e(admin_url('comments/' . $comment['id'] . '/status')) ?>">
                                    <?= csrf_field() ?><input type="hidden" name="status" value="approved">
                                    <button class="btn btn-ghost btn-sm" type="submit"><?= icon('check', 15) ?> 通过</button>
                                </form>
                            <?php else: ?>
                                <form method="post" action="<?= e(admin_url('comments/' . $comment['id'] . '/status')) ?>">
                                    <?= csrf_field() ?><input type="hidden" name="status" value="pending">
                                    <button class="btn btn-ghost btn-sm" type="submit"><?= icon('clock', 15) ?> 转为待审</button>
                                </form>
                            <?php endif; ?>
                            <?php if ($comment['status'] !== 'spam'): ?>
                                <form method="post" action="<?= e(admin_url('comments/' . $comment['id'] . '/status')) ?>">
                                    <?= csrf_field() ?><input type="hidden" name="status" value="spam">
                                    <button class="btn btn-ghost btn-sm" type="submit"><?= icon('alert', 15) ?> 垃圾</button>
                                </form>
                            <?php endif; ?>
                            <button class="btn btn-ghost btn-sm" type="button" data-reply-toggle="reply-<?= (int) $comment['id'] ?>"><?= icon('reply', 15) ?> 回复</button>
                            <form method="post" action="<?= e(admin_url('comments/' . $comment['id'] . '/delete')) ?>" data-confirm="删除评论会同时删除其回复，确定吗？">
                                <?= csrf_field() ?>
                                <button class="btn btn-ghost btn-sm danger-text" type="submit"><?= icon('trash', 15) ?> 删除</button>
                            </form>
                        </div>
                        <form class="reply-form" id="reply-<?= (int) $comment['id'] ?>" method="post" action="<?= e(admin_url('comments/' . $comment['id'] . '/reply')) ?>" hidden>
                            <?= csrf_field() ?>
                            <textarea class="textarea" name="content" rows="3" placeholder="回复 <?= e($comment['author_name']) ?>……" required></textarea>
                            <button class="btn btn-primary btn-sm" type="submit">发布回复</button>
                        </form>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
        <?= pagination($pagination) ?>
    <?php endif; ?>
</section>