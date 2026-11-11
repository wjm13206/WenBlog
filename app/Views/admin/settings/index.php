<?php
$errors = errors();
$timezones = DateTimeZone::listIdentifiers();
$currentTimezone = (string) option('timezone', 'Asia/Shanghai');
$groups = [];
foreach ($timezones as $timezone) {
    $parts = explode('/', $timezone, 2);
    $groups[$parts[0]][] = $timezone;
}
$tabs = [
    'general'  => '基本',
    'reading'  => '阅读',
    'comments' => '评论',
    'upload'   => '上传',
    'seo'      => 'SEO',
    'advanced' => '缓存与 API',
];
?>
<form method="post" action="<?= e(admin_url('settings')) ?>" class="settings-form">
    <?= csrf_field() ?>
    <div class="settings-shell">
        <nav class="settings-nav" aria-label="设置分类">
            <?php foreach ($tabs as $key => $label): ?>
                <button class="settings-nav-item<?= $key === 'general' ? ' active' : '' ?>" type="button" data-settings-tab="<?= e($key) ?>"><?= e($label) ?></button>
            <?php endforeach; ?>
        </nav>

        <div class="settings-panels">
            <?php if (isset($errors['form'])): ?><div class="alert alert-error"><?= e($errors['form']) ?></div><?php endif; ?>

            <section class="card settings-panel" data-settings-panel="general">
                <div class="card-head"><div><h3 class="card-title">站点信息</h3><p class="muted">这些信息会显示在前台与搜索结果中。</p></div></div>
                <div class="card-body stack-form">
                    <div class="form-grid-2">
                        <label class="field">
                            <span class="label">网站名称</span>
                            <input class="input" type="text" name="site_name" value="<?= e(option('site_name')) ?>" required>
                            <?php if (isset($errors['site_name'])): ?><small class="error-text"><?= e($errors['site_name']) ?></small><?php endif; ?>
                        </label>
                        <label class="field">
                            <span class="label">网站地址</span>
                            <input class="input" type="url" name="site_url" value="<?= e(option('site_url')) ?>" placeholder="https://example.com">
                            <small class="hint">留空则自动使用当前访问地址。</small>
                            <?php if (isset($errors['site_url'])): ?><small class="error-text"><?= e($errors['site_url']) ?></small><?php endif; ?>
                        </label>
                    </div>
                    <label class="field">
                        <span class="label">网站描述</span>
                        <textarea class="textarea" name="site_description" rows="3" maxlength="500"><?= e(option('site_description')) ?></textarea>
                    </label>
                    <div class="form-grid-2">
                        <label class="field">
                            <span class="label">Logo 地址</span>
                            <input class="input" type="text" name="site_logo" value="<?= e(option('site_logo')) ?>" placeholder="uploads/logo.png">
                            <?php if (isset($errors['site_logo'])): ?><small class="error-text"><?= e($errors['site_logo']) ?></small><?php endif; ?>
                        </label>
                        <label class="field">
                            <span class="label">Favicon 地址</span>
                            <input class="input" type="text" name="site_favicon" value="<?= e(option('site_favicon')) ?>" placeholder="uploads/favicon.png">
                            <?php if (isset($errors['site_favicon'])): ?><small class="error-text"><?= e($errors['site_favicon']) ?></small><?php endif; ?>
                        </label>
                    </div>
                    <div class="form-grid-2">
                        <label class="field">
                            <span class="label">管理员邮箱</span>
                            <input class="input" type="email" name="admin_email" value="<?= e(option('admin_email')) ?>">
                            <?php if (isset($errors['admin_email'])): ?><small class="error-text"><?= e($errors['admin_email']) ?></small><?php endif; ?>
                        </label>
                        <label class="field">
                            <span class="label">时区</span>
                            <select class="select" name="timezone">
                                <?php foreach ($groups as $region => $zones): ?>
                                    <optgroup label="<?= e($region) ?>">
                                        <?php foreach ($zones as $timezone): ?>
                                            <option value="<?= e($timezone) ?>"<?= selected($currentTimezone, $timezone) ?>><?= e($timezone) ?></option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['timezone'])): ?><small class="error-text"><?= e($errors['timezone']) ?></small><?php endif; ?>
                        </label>
                    </div>
                    <div class="form-grid-2">
                        <label class="field">
                            <span class="label">日期格式</span>
                            <input class="input" type="text" name="date_format" value="<?= e(option('date_format')) ?>">
                            <small class="hint">PHP date() 格式，例如 <code>Y年n月j日</code>。</small>
                            <?php if (isset($errors['date_format'])): ?><small class="error-text"><?= e($errors['date_format']) ?></small><?php endif; ?>
                        </label>
                        <label class="field">
                            <span class="label">页脚文字</span>
                            <input class="input" type="text" name="footer_text" value="<?= e(option('footer_text')) ?>">
                        </label>
                    </div>
                    <label class="check-row"><input type="checkbox" name="maintenance_mode" value="1"<?= checked(option('maintenance_mode')) ?>> <span>开启维护模式 <small class="muted">（仅管理员可访问前台）</small></span></label>
                </div>
            </section>

            <section class="card settings-panel" data-settings-panel="reading" hidden>
                <div class="card-head"><h3 class="card-title">阅读与链接</h3></div>
                <div class="card-body stack-form">
                    <div class="form-grid-2">
                        <label class="field">
                            <span class="label">每页文章数</span>
                            <input class="input" type="number" name="posts_per_page" min="1" max="100" value="<?= e(option('posts_per_page')) ?>">
                            <?php if (isset($errors['posts_per_page'])): ?><small class="error-text"><?= e($errors['posts_per_page']) ?></small><?php endif; ?>
                        </label>
                        <label class="field">
                            <span class="label">自动摘要长度</span>
                            <input class="input" type="number" name="excerpt_length" min="20" max="1000" value="<?= e(option('excerpt_length')) ?>">
                            <small class="hint">按字符数截取，仅在文章未填写摘要时使用。</small>
                            <?php if (isset($errors['excerpt_length'])): ?><small class="error-text"><?= e($errors['excerpt_length']) ?></small><?php endif; ?>
                        </label>
                    </div>
                    <label class="field">
                        <span class="label">文章固定链接</span>
                        <select class="select" name="permalink_style">
                            <option value="post"<?= selected(option('permalink_style'), 'post') ?>>/post/文章别名</option>
                            <option value="slug"<?= selected(option('permalink_style'), 'slug') ?>>/文章别名</option>
                        </select>
                        <small class="hint">修改后如遇 404，请重新保存固定链接或清理缓存。</small>
                        <?php if (isset($errors['permalink_style'])): ?><small class="error-text"><?= e($errors['permalink_style']) ?></small><?php endif; ?>
                    </label>
                </div>
            </section>

            <section class="card settings-panel" data-settings-panel="comments" hidden>
                <div class="card-head"><h3 class="card-title">评论</h3></div>
                <div class="card-body stack-form">
                    <label class="check-row"><input type="checkbox" name="comments_enabled" value="1"<?= checked(option('comments_enabled')) ?>> <span>启用全站评论</span></label>
                    <label class="check-row"><input type="checkbox" name="comments_moderation" value="1"<?= checked(option('comments_moderation')) ?>> <span>新评论需要审核后显示</span></label>
                    <label class="check-row"><input type="checkbox" name="comments_require_email" value="1"<?= checked(option('comments_require_email')) ?>> <span>评论必须填写邮箱</span></label>
                    <div class="form-grid-2">
                        <label class="field">
                            <span class="label">每页评论数</span>
                            <input class="input" type="number" name="comments_per_page" min="1" max="200" value="<?= e(option('comments_per_page')) ?>">
                            <?php if (isset($errors['comments_per_page'])): ?><small class="error-text"><?= e($errors['comments_per_page']) ?></small><?php endif; ?>
                        </label>
                        <label class="field">
                            <span class="label">最短提交时间（秒）</span>
                            <input class="input" type="number" name="comments_min_seconds" min="0" max="120" value="<?= e(option('comments_min_seconds')) ?>">
                            <small class="hint">低于该时间视为机器人，0 表示关闭。</small>
                            <?php if (isset($errors['comments_min_seconds'])): ?><small class="error-text"><?= e($errors['comments_min_seconds']) ?></small><?php endif; ?>
                        </label>
                    </div>
                    <label class="field">
                        <span class="label">敏感词黑名单</span>
                        <textarea class="textarea" name="comments_blocked_words" rows="3" placeholder="每行一个词"><?= e(option('comments_blocked_words')) ?></textarea>
                        <small class="hint">命中后评论会直接标记为垃圾。</small>
                    </label>
                </div>
            </section>

            <section class="card settings-panel" data-settings-panel="upload" hidden>
                <div class="card-head"><h3 class="card-title">上传</h3></div>
                <div class="card-body stack-form">
                    <label class="field">
                        <span class="label">单文件大小上限（MB）</span>
                        <input class="input" type="number" name="upload_max_size" min="1" max="1024" value="<?= e(option('upload_max_size')) ?>">
                        <small class="hint">还需确保 PHP <code>upload_max_filesize</code> 与 <code>post_max_size</code> 不低于该值。</small>
                        <?php if (isset($errors['upload_max_size'])): ?><small class="error-text"><?= e($errors['upload_max_size']) ?></small><?php endif; ?>
                    </label>
                    <label class="field">
                        <span class="label">允许的扩展名</span>
                        <input class="input" type="text" name="upload_extensions" value="<?= e(option('upload_extensions')) ?>">
                        <small class="hint">逗号分隔，例如 <code>jpg,png,webp,pdf</code>。请勿添加 php、phtml 等可执行类型。</small>
                        <?php if (isset($errors['upload_extensions'])): ?><small class="error-text"><?= e($errors['upload_extensions']) ?></small><?php endif; ?>
                    </label>
                </div>
            </section>

            <section class="card settings-panel" data-settings-panel="seo" hidden>
                <div class="card-head"><h3 class="card-title">搜索引擎优化</h3></div>
                <div class="card-body stack-form">
                    <label class="field">
                        <span class="label">关键词</span>
                        <input class="input" type="text" name="seo_keywords" value="<?= e(option('seo_keywords')) ?>" placeholder="博客, 技术, 生活">
                    </label>
                    <label class="field">
                        <span class="label">默认描述</span>
                        <textarea class="textarea" name="seo_description" rows="3" maxlength="300"><?= e(option('seo_description')) ?></textarea>
                    </label>
                    <label class="field">
                        <span class="label">标题后缀</span>
                        <input class="input" type="text" name="seo_title_suffix" value="<?= e(option('seo_title_suffix')) ?>" placeholder="例如： | 我的博客">
                    </label>
                </div>
            </section>

            <section class="card settings-panel" data-settings-panel="advanced" hidden>
                <div class="card-head"><h3 class="card-title">缓存与 API</h3></div>
                <div class="card-body stack-form">
                    <label class="check-row"><input type="checkbox" name="cache_enabled" value="1"<?= checked(option('cache_enabled')) ?>> <span>启用页面缓存 <small class="muted">（仅缓存访客的 GET 页面）</small></span></label>
                    <label class="field">
                        <span class="label">缓存有效期（秒）</span>
                        <input class="input" type="number" name="cache_ttl" min="10" max="86400" value="<?= e(option('cache_ttl')) ?>">
                        <?php if (isset($errors['cache_ttl'])): ?><small class="error-text"><?= e($errors['cache_ttl']) ?></small><?php endif; ?>
                    </label>
                    <hr class="divider">
                    <label class="check-row"><input type="checkbox" name="api_enabled" value="1"<?= checked(option('api_enabled')) ?>> <span>启用只读 REST API</span></label>
                    <label class="field">
                        <span class="label">API 密钥</span>
                        <input class="input" type="text" name="api_key" value="<?= e(option('api_key')) ?>" placeholder="留空将自动生成">
                        <small class="hint">请求时通过 <code>X-API-Key</code> 头或 <code>?api_key=</code> 传递。</small>
                        <?php if (isset($errors['api_key'])): ?><small class="error-text"><?= e($errors['api_key']) ?></small><?php endif; ?>
                    </label>
                    <div class="endpoint-list">
                        <span class="muted">接口：</span>
                        <code>GET /api/posts</code>
                        <code>GET /api/posts/{slug}</code>
                        <code>GET /api/categories</code>
                        <code>GET /api/tags</code>
                        <code>GET /api/comments?post_id=1</code>
                    </div>
                </div>
            </section>

            <div class="settings-footer">
                <button class="btn btn-primary" type="submit"><?= icon('save') ?> 保存设置</button>
                <span class="muted">保存后会自动清理页面缓存。</span>
            </div>
        </div>
    </div>
</form>

<section class="card utility-card">
    <div class="card-body utility-row">
        <div>
            <h3><?= icon('search', 17) ?> 重建全文搜索索引</h3>
            <p class="muted">为标题、摘要和正文建立 MySQL FULLTEXT 索引（优先使用 ngram 中文分词）。文章较多时需要几秒。</p>
        </div>
        <form method="post" action="<?= e(admin_url('settings/search-index')) ?>">
            <?= csrf_field() ?>
            <button class="btn btn-ghost" type="submit">重建索引</button>
        </form>
    </div>
</section>

<section class="card utility-card">
    <div class="card-body utility-row">
        <div>
            <h3><?= icon('trash-2', 17) ?> 清空页面缓存</h3>
            <p class="muted">当修改主题或发现内容未更新时，可手动清理缓存。</p>
        </div>
        <form method="post" action="<?= e(admin_url('cache/clear')) ?>">
            <?= csrf_field() ?>
            <button class="btn btn-ghost" type="submit">立即清理</button>
        </form>
    </div>
</section>