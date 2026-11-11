<?php
/** @var list<array<string, mixed>> $plugins */
?>
<div class="page-toolbar">
    <div>
        <h2 class="page-subtitle">插件</h2>
        <p class="muted">通过钩子扩展功能。启用插件前请确认其来源可信。</p>
    </div>
    <form method="post" action="<?= e(admin_url('plugins/upload')) ?>" enctype="multipart/form-data" class="inline-upload">
        <?= csrf_field() ?>
        <label class="btn btn-ghost file-btn">
            <?= icon('upload', 16) ?> 安装插件
            <input type="file" name="file" accept=".zip" required data-auto-submit>
        </label>
    </form>
</div>

<?php if ($plugins === []): ?>
    <section class="card">
        <div class="empty">
            <?= icon('grid', 34) ?>
            <h3>还没有安装插件</h3>
            <p>将插件压缩包上传，或把插件目录放入 <code>plugins/</code>。</p>
        </div>
    </section>
<?php else: ?>
    <div class="plugin-list">
        <?php foreach ($plugins as $plugin): ?>
            <article class="plugin-card<?= $plugin['active'] ? ' is-active' : '' ?>">
                <div class="plugin-icon"><?= icon('grid', 22) ?></div>
                <div class="plugin-main">
                    <div class="plugin-head">
                        <div>
                            <h3><?= e($plugin['name']) ?> <span class="muted">v<?= e($plugin['version']) ?></span></h3>
                            <p class="muted"><?= e($plugin['description'] ?: '暂无描述') ?> · <?= e($plugin['author'] ?: '未知作者') ?></p>
                        </div>
                        <span class="badge badge-<?= $plugin['active'] ? 'approved' : 'draft' ?>"><?= $plugin['active'] ? '已启用' : '已停用' ?></span>
                    </div>
                    <div class="plugin-actions">
                        <form method="post" action="<?= e(admin_url('plugins/toggle')) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="slug" value="<?= e($plugin['slug']) ?>">
                            <input type="hidden" name="enabled" value="<?= $plugin['active'] ? '0' : '1' ?>">
                            <button class="btn btn-sm <?= $plugin['active'] ? 'btn-ghost' : 'btn-primary' ?>" type="submit"><?= $plugin['active'] ? '停用' : '启用' ?></button>
                        </form>
                        <?php if (($plugin['config_fields'] ?? []) !== []): ?>
                            <button class="btn btn-ghost btn-sm" type="button" data-toggle-panel="plugin-config-<?= e($plugin['slug']) ?>"><?= icon('sliders', 15) ?> 配置</button>
                        <?php endif; ?>
                        <?php \App\Core\Hook::do('admin.plugins.actions', $plugin); ?>
                        <form method="post" action="<?= e(admin_url('plugins/delete')) ?>" data-confirm="确定删除插件「<?= e($plugin['name']) ?>」吗？插件文件会被移除。">
                            <?= csrf_field() ?>
                            <input type="hidden" name="slug" value="<?= e($plugin['slug']) ?>">
                            <button class="btn btn-ghost btn-sm danger-text" type="submit">删除</button>
                        </form>
                    </div>
                    <?php if (($plugin['config_fields'] ?? []) !== []): ?>
                        <div class="plugin-config" id="plugin-config-<?= e($plugin['slug']) ?>" hidden>
                            <form method="post" action="<?= e(admin_url('plugins/config')) ?>" class="stack-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="slug" value="<?= e($plugin['slug']) ?>">
                                <?php foreach ($plugin['config_fields'] as $key => $field): ?>
                                    <?php
                                    $type = (string) ($field['type'] ?? 'text');
                                    $label = (string) ($field['label'] ?? $key);
                                    $value = $plugin['config'][$key] ?? ($field['default'] ?? '');
                                    ?>
                                    <?php if ($type === 'checkbox'): ?>
                                        <label class="check-row"><input type="checkbox" name="config[<?= e($key) ?>]" value="1"<?= checked($value) ?>> <span><?= e($label) ?></span></label>
                                    <?php elseif ($type === 'textarea'): ?>
                                        <label class="field"><span class="label"><?= e($label) ?></span><textarea class="textarea" name="config[<?= e($key) ?>]" rows="3"><?= e((string) $value) ?></textarea></label>
                                    <?php elseif ($type === 'select'): ?>
                                        <label class="field"><span class="label"><?= e($label) ?></span>
                                            <select class="select" name="config[<?= e($key) ?>]">
                                                <?php foreach (($field['options'] ?? []) as $optionValue => $optionLabel): ?>
                                                    <option value="<?= e((string) $optionValue) ?>"<?= selected((string) $value, (string) $optionValue) ?>><?= e((string) $optionLabel) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </label>
                                    <?php else: ?>
                                        <label class="field"><span class="label"><?= e($label) ?></span><input class="input" type="text" name="config[<?= e($key) ?>]" value="<?= e((string) $value) ?>"></label>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                                <button class="btn btn-primary btn-sm" type="submit">保存配置</button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<section class="card theme-help">
    <div class="card-body">
        <h3><?= icon('info', 17) ?> 插件开发</h3>
        <p class="muted">在 <code>plugins/插件名/plugin.json</code> 声明信息，入口文件通过 <code>Hook::add('事件', $callback)</code> 挂载逻辑。可用事件包括 <code>post.content</code>、<code>comment.created</code>、<code>plugins.loaded</code> 等。</p>
    </div>
</section>