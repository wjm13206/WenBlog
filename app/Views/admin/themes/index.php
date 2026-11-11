<?php
/** @var list<array<string, mixed>> $themes */
?>
<div class="page-toolbar">
    <div>
        <h2 class="page-subtitle">主题</h2>
        <p class="muted">切换站点外观，或为当前主题调整配色与布局。</p>
    </div>
    <form method="post" action="<?= e(admin_url('themes/upload')) ?>" enctype="multipart/form-data" class="inline-upload">
        <?= csrf_field() ?>
        <label class="btn btn-ghost file-btn">
            <?= icon('upload', 16) ?> 安装主题
            <input type="file" name="file" accept=".zip" required data-auto-submit>
        </label>
    </form>
</div>

<div class="theme-grid">
    <?php foreach ($themes as $theme): ?>
        <article class="theme-card<?= $theme['active'] ? ' is-active' : '' ?>">
            <div class="theme-shot" style="<?= $theme['screenshot'] === '' ? 'background: linear-gradient(135deg, var(--surface-2), var(--border));' : '' ?>">
                <?php if ($theme['screenshot'] !== ''): ?>
                    <img src="<?= e($theme['screenshot']) ?>" alt="<?= e($theme['name']) ?> 预览" loading="lazy">
                <?php else: ?>
                    <div class="theme-shot-placeholder"><?= icon('layout', 34) ?></div>
                <?php endif; ?>
                <?php if ($theme['active']): ?><span class="theme-active-badge"><?= icon('check', 13) ?> 使用中</span><?php endif; ?>
            </div>
            <div class="theme-info">
                <div class="theme-title-row">
                    <h3><?= e($theme['name']) ?></h3>
                    <span class="muted">v<?= e($theme['version']) ?></span>
                </div>
                <p class="muted"><?= e($theme['description'] ?: '暂无主题描述。') ?></p>
                <div class="theme-meta">
                    <span><?= e($theme['author'] ?: '未知作者') ?></span>
                    <code><?= e($theme['slug']) ?></code>
                </div>
                <div class="theme-actions">
                    <?php if (!$theme['active']): ?>
                        <form method="post" action="<?= e(admin_url('themes/activate')) ?>">
                            <?= csrf_field() ?><input type="hidden" name="slug" value="<?= e($theme['slug']) ?>">
                            <button class="btn btn-primary btn-sm" type="submit">启用主题</button>
                        </form>
                    <?php else: ?>
                        <span class="text-success"><?= icon('check-circle', 15) ?> 当前主题</span>
                    <?php endif; ?>
                    <?php if ($theme['active'] && $theme['config_count'] > 0): ?>
                        <a class="btn btn-ghost btn-sm" href="<?= e(admin_url('themes/settings')) ?>"><?= icon('sliders', 15) ?> 主题设置</a>
                    <?php endif; ?>
                    <?php if (!$theme['active'] && $theme['slug'] !== 'default'): ?>
                        <form method="post" action="<?= e(admin_url('themes/delete')) ?>" data-confirm="确定删除主题「<?= e($theme['name']) ?>」吗？">
                            <?= csrf_field() ?><input type="hidden" name="slug" value="<?= e($theme['slug']) ?>">
                            <button class="btn btn-ghost btn-sm danger-text" type="submit">删除</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </article>
    <?php endforeach; ?>
</div>

<section class="card theme-help">
    <div class="card-body">
        <h3><?= icon('info', 17) ?> 主题开发</h3>
        <p class="muted">主题放在 <code>themes/主题名/</code>，至少包含 <code>index.php</code> 与 <code>theme.json</code>。可选 <code>functions.php</code>、<code>partials/</code> 与自定义页面模板。</p>
    </div>
</section>