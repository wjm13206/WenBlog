<?php
/** @var array<string, int> $counts */
/** @var list<array<string, mixed>> $recentComments */
/** @var list<array<string, mixed>> $latestPosts */
/** @var list<array<string, mixed>> $popular */
/** @var array<string, int> $chart */
$maxChart = max(1, ...array_values($chart ?: [1]));
$user = current_user();
$hour = (int) date('G');
$greeting = $hour < 6 ? '夜深了' : ($hour < 12 ? '早上好' : ($hour < 18 ? '下午好' : '晚上好'));
?>
<section class="hero-card">
    <div>
        <span class="eyebrow">DASHBOARD</span>
        <h2><?= e($greeting) ?>，<?= e($user['display_name'] ?? $user['username'] ?? '朋友') ?></h2>
        <p>今天也写下一点值得被记住的东西。</p>
    </div>
    <div class="hero-actions">
        <a class="btn btn-primary" href="<?= e(admin_url('posts/create')) ?>"><?= icon('plus') ?> 写文章</a>
        <a class="btn btn-ghost" href="<?= e(url('/')) ?>" target="_blank" rel="noopener"><?= icon('eye') ?> 查看站点</a>
    </div>
</section>

<div class="stats-grid">
    <a class="stat-card" href="<?= e(admin_url('posts')) ?>">
        <span class="stat-icon tone-indigo"><?= icon('file-text', 20) ?></span>
        <span class="stat-meta"><strong><?= (int) $counts['published'] ?></strong><small>已发布文章</small></span>
        <span class="stat-foot">共 <?= (int) $counts['total'] ?> 篇</span>
    </a>
    <a class="stat-card" href="<?= e(admin_url('comments')) ?>">
        <span class="stat-icon tone-amber"><?= icon('message', 20) ?></span>
        <span class="stat-meta"><strong><?= (int) $counts['comments'] ?></strong><small>待审核评论</small></span>
        <span class="stat-foot">保持讨论友好</span>
    </a>
    <a class="stat-card" href="<?= e(admin_url('media')) ?>">
        <span class="stat-icon tone-emerald"><?= icon('image', 20) ?></span>
        <span class="stat-meta"><strong><?= (int) $mediaCount ?></strong><small>媒体文件</small></span>
        <span class="stat-foot">图片与附件</span>
    </a>
    <div class="stat-card">
        <span class="stat-icon tone-sky"><?= icon('eye', 20) ?></span>
        <span class="stat-meta"><strong><?= number_format((int) $counts['views']) ?></strong><small>累计浏览</small></span>
        <span class="stat-foot"><?= (int) $userCount ?> 位用户</span>
    </div>
</div>

<div class="dashboard-grid">
    <section class="card chart-card">
        <div class="card-head">
            <div><h3 class="card-title">近 14 天发布节奏</h3><p class="muted">每天新建的文章数量</p></div>
            <span class="badge badge-soft"><?= (int) $counts['draft'] ?> 篇草稿 · <?= (int) $counts['scheduled'] ?> 篇定时</span>
        </div>
        <div class="mini-chart" aria-label="近 14 天文章发布数量">
            <?php foreach ($chart as $day => $value): ?>
                <div class="chart-col" title="<?= e($day) ?>：<?= (int) $value ?> 篇">
                    <span class="chart-bar" style="height: <?= max(4, (int) round(((int) $value / $maxChart) * 100)) ?>%"></span>
                    <small><?= e(date('m/d', (int) strtotime((string) $day))) ?></small>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="card">
        <div class="card-head">
            <div><h3 class="card-title">待处理评论</h3><p class="muted">审核后才会展示在文章页</p></div>
            <a class="text-link" href="<?= e(admin_url('comments?status=pending')) ?>">全部</a>
        </div>
        <?php if ($recentComments === []): ?>
            <div class="empty compact"><p>没有待审核的评论，很好。</p></div>
        <?php else: ?>
            <ul class="comment-mini-list">
                <?php foreach ($recentComments as $comment): ?>
                    <li>
                        <span class="avatar avatar-sm"><?= e(initials((string) $comment['author_name'])) ?></span>
                        <div>
                            <strong><?= e($comment['author_name']) ?></strong>
                            <p><?= e(truncate((string) $comment['content'], 52)) ?></p>
                            <small>《<?= e($comment['post_title'] ?? '文章') ?>》 · <?= e(human_time((string) $comment['created_at'])) ?></small>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>

<div class="dashboard-grid">
    <section class="card">
        <div class="card-head">
            <div><h3 class="card-title">最近文章</h3><p class="muted">继续编辑或查看表现</p></div>
            <a class="text-link" href="<?= e(admin_url('posts')) ?>">全部文章</a>
        </div>
        <?php if ($latestPosts === []): ?>
            <div class="empty compact"><p>还没有文章，<a href="<?= e(admin_url('posts/create')) ?>">写第一篇</a>。</p></div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>标题</th><th>状态</th><th class="num">浏览</th><th>时间</th></tr></thead>
                    <tbody>
                    <?php foreach ($latestPosts as $post): ?>
                        <tr>
                            <td><a class="table-title" href="<?= e(admin_url('posts/' . $post['id'] . '/edit')) ?>"><?= e($post['title']) ?></a></td>
                            <td><span class="badge badge-<?= e((string) $post['status']) ?>"><?= e(status_label((string) $post['status'])) ?></span></td>
                            <td class="num"><?= (int) $post['views'] ?></td>
                            <td class="muted"><?= e(human_time((string) $post['created_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="card">
        <div class="card-head"><div><h3 class="card-title">热门内容</h3><p class="muted">按累计浏览量排序</p></div></div>
        <?php if ($popular === []): ?>
            <div class="empty compact"><p>暂无浏览数据。</p></div>
        <?php else: ?>
            <ol class="rank-list">
                <?php foreach ($popular as $index => $item): ?>
                    <li>
                        <span class="rank-num"><?= $index + 1 ?></span>
                        <a href="<?= e(admin_url('posts/' . $item['id'] . '/edit')) ?>"><?= e($item['title']) ?></a>
                        <small><?= (int) $item['views'] ?> 次</small>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </section>
</div>