<?php
/** @var list<array<string, mixed>> $categories */
/** @var array<string, mixed>|null $editing */
$errors = errors();
$isEdit = $editing !== null;
$action = $isEdit ? admin_url('categories/' . $editing['id']) : admin_url('categories');
?>
<div class="page-toolbar">
    <div>
        <h2 class="page-subtitle">分类管理</h2>
        <p class="muted">用层级分类组织文章，最多支持无限级嵌套。</p>
    </div>
</div>

<div class="split-grid">
    <section class="card">
        <div class="card-head">
            <h3 class="card-title"><?= $isEdit ? '编辑分类' : '新建分类' ?></h3>
            <?php if ($isEdit): ?><a class="text-link" href="<?= e(admin_url('categories')) ?>">取消编辑</a><?php endif; ?>
        </div>
        <div class="card-body stack-form">
            <?php if (isset($errors['form'])): ?><div class="alert alert-error"><?= e($errors['form']) ?></div><?php endif; ?>
            <form method="post" action="<?= e($action) ?>" class="stack-form">
                <?= csrf_field() ?>
                <label class="field">
                    <span class="label">名称</span>
                    <input class="input" type="text" name="name" value="<?= e(old('name', $editing['name'] ?? '')) ?>" maxlength="100" required>
                </label>
                <label class="field">
                    <span class="label">别名 <small class="muted">用于 URL，留空自动生成</small></span>
                    <input class="input" type="text" name="slug" value="<?= e(old('slug', $editing['slug'] ?? '')) ?>" placeholder="tech">
                </label>
                <label class="field">
                    <span class="label">父分类</span>
                    <select class="select" name="parent_id">
                        <option value="0">— 顶级分类 —</option>
                        <?php foreach ($categories as $category): ?>
                            <?php if ($isEdit && (int) $category['id'] === (int) $editing['id']) { continue; } ?>
                            <option value="<?= (int) $category['id'] ?>"<?= selected((int) old('parent_id', $editing['parent_id'] ?? 0), (int) $category['id']) ?>>
                                <?= e(str_repeat('— ', (int) ($category['depth'] ?? 0)) . $category['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="field">
                    <span class="label">描述 <small class="muted">可选</small></span>
                    <textarea class="textarea" name="description" rows="3"><?= e(old('description', $editing['description'] ?? '')) ?></textarea>
                </label>
                <label class="field">
                    <span class="label">排序</span>
                    <input class="input" type="number" name="sort_order" value="<?= e(old('sort_order', $editing['sort_order'] ?? 0)) ?>">
                    <small class="hint">数字越小越靠前。</small>
                </label>
                <button class="btn btn-primary" type="submit"><?= icon('save') ?> <?= $isEdit ? '保存分类' : '创建分类' ?></button>
            </form>
        </div>
    </section>

    <section class="card">
        <div class="card-head"><h3 class="card-title">全部分类 <span class="badge badge-soft"><?= count($categories) ?></span></h3></div>
        <?php if ($categories === []): ?>
            <div class="empty"><h3>还没有分类</h3><p>先在左侧创建第一个分类。</p></div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>名称</th><th>别名</th><th>文章</th><th class="actions-col">操作</th></tr></thead>
                    <tbody>
                    <?php foreach ($categories as $category): ?>
                        <tr>
                            <td>
                                <span style="padding-left: <?= (int) ($category['depth'] ?? 0) * 18 ?>px" class="cat-name">
                                    <?php if ((int) ($category['depth'] ?? 0) > 0): ?><span class="muted">└</span><?php endif; ?>
                                    <?= e($category['name']) ?>
                                </span>
                            </td>
                            <td><code><?= e($category['slug']) ?></code></td>
                            <td><?= (int) ($category['post_count'] ?? 0) ?></td>
                            <td>
                                <div class="row-actions">
                                    <a class="icon-btn" href="<?= e(admin_url('categories?edit=' . $category['id'])) ?>" title="编辑"><?= icon('edit', 16) ?></a>
                                    <form method="post" action="<?= e(admin_url('categories/' . $category['id'] . '/delete')) ?>" data-confirm="删除后，其子分类会提升到上一级。确定删除「<?= e($category['name']) ?>」吗？">
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