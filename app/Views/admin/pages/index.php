<?php
/** @var list<array<string, mixed>> $pages */
/** @var \App\Core\Paginator $pagination */
?>
<div class="page-toolbar">
    <div>
        <h2 class="page-subtitle">独立页面</h2>
        <p class="muted">用于「关于」「留言」「友链」等不进入文章流的固定内容。</p>
    </div>
    <a class="btn btn-primary" href="<?= e(admin_url('pages/create')) ?>"><?= icon('plus') ?> 新建页面</a>
</div>

<section class="card">
    <?php if ($pages === []): ?>
        <div class="empty">
            <?= icon('layers', 34) ?>
            <h3>还没有独立页面</h3>
            <p>创建一个「关于」页面，让读者认识你。</p>
            <a class="btn btn-primary" href="<?= e(admin_url('pages/create')) ?>">新建页面</a>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>标题</th><th>路径</th><th>模板</th><th>状态</th><th>更新时间</th><th class="actions-col">操作</th></tr></thead>
                <tbody>
                <?php foreach ($pages as $page): ?>
                    <tr>
                        <td><a class="table-title" href="<?= e(admin_url('pages/' . $page['id'] . '/edit')) ?>"><?= e($page['title']) ?></a></td>
                        <td class="muted"><code>/<?= e($page['slug']) ?></code></td>
                        <td class="muted"><?= e($page['template'] ?: '默认') ?></td>
                        <td><span class="badge badge-<?= e((string) $page['status']) ?>"><?= e(status_label((string) $page['status'])) ?></span></td>
                        <td class="muted nowrap"><?= e(format_date((string) $page['updated_at'], 'Y-m-d H:i')) ?></td>
                        <td>
                            <div class="row-actions">
                                <?php if ($page['status'] === 'published'): ?>
                                    <a class="icon-btn" href="<?= e(page_url($page)) ?>" target="_blank" rel="noopener" title="查看"><?= icon('eye', 16) ?></a>
                                <?php endif; ?>
                                <a class="icon-btn" href="<?= e(admin_url('pages/' . $page['id'] . '/edit')) ?>" title="编辑"><?= icon('edit', 16) ?></a>
                                <form method="post" action="<?= e(admin_url('pages/' . $page['id'] . '/delete')) ?>" data-confirm="确定删除这个页面吗？">
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