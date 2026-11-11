<?php
/** @var list<array<string, mixed>> $tags */
/** @var array<string, mixed>|null $editing */
$isEdit = $editing !== null;
$action = $isEdit ? admin_url('tags/' . $editing['id']) : admin_url('tags');
?>
<div class="page-toolbar">
    <div>
        <h2 class="page-subtitle">标签管理</h2>
        <p class="muted">标签适合描述文章的具体主题，可跨分类聚合内容。</p>
    </div>
</div>

<div class="split-grid">
    <section class="card">
        <div class="card-head">
            <h3 class="card-title"><?= $isEdit ? '编辑标签' : '新建标签' ?></h3>
            <?php if ($isEdit): ?><a class="text-link" href="<?= e(admin_url('tags')) ?>">取消编辑</a><?php endif; ?>
        </div>
        <div class="card-body">
            <form method="post" action="<?= e($action) ?>" class="stack-form">
                <?= csrf_field() ?>
                <label class="field">
                    <span class="label">标签名称</span>
                    <input class="input" type="text" name="name" value="<?= e(old('name', $editing['name'] ?? '')) ?>" maxlength="100" required>
                </label>
                <label class="field">
                    <span class="label">别名 <small class="muted">留空自动生成</small></span>
                    <input class="input" type="text" name="slug" value="<?= e(old('slug', $editing['slug'] ?? '')) ?>" placeholder="design">
                </label>
                <button class="btn btn-primary" type="submit"><?= icon('save') ?> <?= $isEdit ? '保存标签' : '创建标签' ?></button>
            </form>
        </div>
    </section>

    <section class="card">
        <div class="card-head"><h3 class="card-title">全部标签 <span class="badge badge-soft"><?= count($tags) ?></span></h3></div>
        <?php if ($tags === []): ?>
            <div class="empty"><h3>还没有标签</h3><p>标签会在编辑文章时自动创建。</p></div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>名称</th><th>别名</th><th>文章</th><th class="actions-col">操作</th></tr></thead>
                    <tbody>
                    <?php foreach ($tags as $tag): ?>
                        <tr>
                            <td><span class="badge badge-soft"><?= e($tag['name']) ?></span></td>
                            <td><code><?= e($tag['slug']) ?></code></td>
                            <td><?= (int) ($tag['post_count'] ?? 0) ?></td>
                            <td>
                                <div class="row-actions">
                                    <a class="icon-btn" href="<?= e(admin_url('tags?edit=' . $tag['id'])) ?>" title="编辑"><?= icon('edit', 16) ?></a>
                                    <form method="post" action="<?= e(admin_url('tags/' . $tag['id'] . '/delete')) ?>" data-confirm="确定删除标签「<?= e($tag['name']) ?>」吗？">
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
        <?php endif; ?>
    </section>
</div>