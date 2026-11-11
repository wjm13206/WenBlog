<?php
/** @var list<array<string, mixed>> $links */
/** @var array<string, mixed>|null $editing */
$isEdit = $editing !== null;
$action = $isEdit ? admin_url('links/' . $editing['id']) : admin_url('links');
?>
<div class="page-toolbar">
    <div>
        <h2 class="page-subtitle">友情链接</h2>
        <p class="muted">管理侧边栏或友链页面展示的站点。</p>
    </div>
</div>

<div class="split-grid">
    <section class="card">
        <div class="card-head">
            <h3 class="card-title"><?= $isEdit ? '编辑链接' : '添加链接' ?></h3>
            <?php if ($isEdit): ?><a class="text-link" href="<?= e(admin_url('links')) ?>">取消编辑</a><?php endif; ?>
        </div>
        <div class="card-body">
            <form method="post" action="<?= e($action) ?>" class="stack-form">
                <?= csrf_field() ?>
                <label class="field">
                    <span class="label">站点名称</span>
                    <input class="input" type="text" name="name" value="<?= e(old('name', $editing['name'] ?? '')) ?>" required maxlength="100">
                </label>
                <label class="field">
                    <span class="label">网址</span>
                    <input class="input" type="url" name="url" value="<?= e(old('url', $editing['url'] ?? '')) ?>" placeholder="https://example.com" required>
                </label>
                <label class="field">
                    <span class="label">描述 <small class="muted">可选</small></span>
                    <textarea class="textarea" name="description" rows="3"><?= e(old('description', $editing['description'] ?? '')) ?></textarea>
                </label>
                <label class="field">
                    <span class="label">排序</span>
                    <input class="input" type="number" name="sort_order" value="<?= e(old('sort_order', $editing['sort_order'] ?? 0)) ?>">
                </label>
                <button class="btn btn-primary" type="submit"><?= icon('save') ?> <?= $isEdit ? '保存链接' : '添加链接' ?></button>
            </form>
        </div>
    </section>

    <section class="card">
        <div class="card-head"><h3 class="card-title">全部链接 <span class="badge badge-soft"><?= count($links) ?></span></h3></div>
        <?php if ($links === []): ?>
            <div class="empty"><h3>还没有友情链接</h3><p>添加朋友的站点，建立互相链接。</p></div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>站点</th><th>描述</th><th>排序</th><th class="actions-col">操作</th></tr></thead>
                    <tbody>
                    <?php foreach ($links as $link): ?>
                        <tr>
                            <td>
                                <a href="<?= e($link['url']) ?>" target="_blank" rel="noopener nofollow" class="table-title"><?= e($link['name']) ?></a>
                                <div class="muted"><code><?= e($link['url']) ?></code></div>
                            </td>
                            <td class="muted"><?= e($link['description'] ?: '—') ?></td>
                            <td><?= (int) $link['sort_order'] ?></td>
                            <td>
                                <div class="row-actions">
                                    <a class="icon-btn" href="<?= e(admin_url('links?edit=' . $link['id'])) ?>" title="编辑"><?= icon('edit', 16) ?></a>
                                    <form method="post" action="<?= e(admin_url('links/' . $link['id'] . '/delete')) ?>" data-confirm="确定删除链接「<?= e($link['name']) ?>」吗？">
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