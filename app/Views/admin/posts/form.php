<?php
/** @var array<string, mixed> $post */
/** @var list<array<string, mixed>> $categories */
/** @var list<int> $selectedCategories */
/** @var string $tags */
/** @var list<array<string, mixed>> $allTags */
/** @var int $revisionCount */
$errors = errors();
$isEdit = !empty($post['id']);
$action = $isEdit ? admin_url('posts/' . $post['id']) : admin_url('posts');
$slugPrefix = option('permalink_style', 'post') === 'slug' ? url('') : url('post/');
$selected = old('categories', $selectedCategories);
$selected = is_array($selected) ? array_map('intval', $selected) : [];
$publishedValue = old('published_at', !empty($post['published_at']) ? date('Y-m-d\TH:i', (int) strtotime((string) $post['published_at'])) : '');
$statusValue = (string) old('status', $post['status'] ?? 'draft');
?>
<form method="post" action="<?= e($action) ?>" class="editor-form" id="post-form"
      data-preview-url="<?= e(admin_url('preview')) ?>"
      data-upload-url="<?= e(admin_url('media/ajax')) ?>"
      data-draft-key="<?= e('wenblog:draft:post:' . ($post['id'] ?? 'new')) ?>"
      data-loaded-at="<?= time() ?>">
    <?= csrf_field() ?>
    <div class="editor-grid">
        <div class="editor-main">
            <section class="card">
                <div class="card-body stack-form">
                    <label class="field">
                        <span class="label">标题</span>
                        <input class="input input-title" type="text" name="title" value="<?= e(old('title', $post['title'] ?? '')) ?>" placeholder="给这篇文章一个清晰的标题" maxlength="255" required autofocus>
                        <?php if (isset($errors['title'])): ?><small class="error-text"><?= e($errors['title']) ?></small><?php endif; ?>
                    </label>
                    <label class="field">
                        <span class="label">固定链接 <small class="muted">留空将根据标题自动生成</small></span>
                        <div class="input-prefix">
                            <span><?= e($slugPrefix) ?></span>
                            <input class="input" type="text" name="slug" value="<?= e(old('slug', $post['slug'] ?? '')) ?>" placeholder="my-post" pattern="[a-z0-9\-]*">
                        </div>
                    </label>
                </div>
            </section>

            <section class="card editor-card">
                <div class="card-head">
                    <div><h3 class="card-title">正文</h3><p class="muted">支持 Markdown，内容会安全地渲染为 HTML</p></div>
                    <div class="editor-tabs" role="tablist">
                        <button class="editor-tab active" type="button" data-editor-tab="write">编辑</button>
                        <button class="editor-tab" type="button" data-editor-tab="split">分栏</button>
                        <button class="editor-tab" type="button" data-editor-tab="preview">预览</button>
                    </div>
                </div>
                <div class="draft-restore" data-draft-restore hidden>
                    <span data-draft-restore-text>检测到本地自动保存的内容。</span>
                    <button class="btn btn-sm" type="button" data-draft-accept>恢复</button>
                    <button class="btn btn-ghost btn-sm" type="button" data-draft-discard>丢弃</button>
                </div>
                <div class="md-toolbar" data-md-toolbar>
                    <button type="button" data-md="bold" title="加粗"><strong>B</strong></button>
                    <button type="button" data-md="italic" title="斜体"><em>I</em></button>
                    <button type="button" data-md="heading" title="标题">H2</button>
                    <span class="toolbar-sep"></span>
                    <button type="button" data-md="quote" title="引用">❝</button>
                    <button type="button" data-md="ul" title="无序列表">•</button>
                    <button type="button" data-md="ol" title="有序列表">1.</button>
                    <span class="toolbar-sep"></span>
                    <button type="button" data-md="link" title="链接"><?= icon('link', 15) ?></button>
                    <button type="button" data-md="image" title="上传图片（也可直接粘贴或拖入）"><?= icon('image', 15) ?></button>
                    <button type="button" data-md="code" title="代码"><?= icon('code', 15) ?></button>
                    <span class="toolbar-spacer"></span>
                    <span class="autosave-state" data-autosave-status></span>
                    <span class="word-count" data-word-count>0 字</span>
                </div>
                <div class="editor-panes" data-editor-panes>
                    <div class="editor-pane" data-editor-pane="write">
                        <textarea class="md-textarea" name="content" id="post-content" rows="22" placeholder="从这里开始写作……支持粘贴或拖入图片" required><?= e(old('content', $post['content'] ?? '')) ?></textarea>
                        <?php if (isset($errors['content'])): ?><small class="error-text"><?= e($errors['content']) ?></small><?php endif; ?>
                    </div>
                    <div class="editor-pane md-preview" data-editor-pane="preview" hidden>
                        <p class="muted">预览会使用与前台相同的 Markdown 渲染器。</p>
                        <div class="prose" data-preview-body></div>
                    </div>
                </div>
            </section>

            <section class="card">
                <div class="card-body stack-form">
                    <label class="field">
                        <span class="label">摘要 <small class="muted">留空则自动截取正文</small></span>
                        <textarea class="textarea" name="excerpt" rows="3" maxlength="500" placeholder="用一两句话概括这篇文章"><?= e(old('excerpt', $post['excerpt'] ?? '')) ?></textarea>
                    </label>
                </div>
            </section>
        </div>

        <aside class="editor-side">
            <section class="card">
                <div class="card-head"><h3 class="card-title">发布</h3></div>
                <div class="card-body stack-form">
                    <label class="field">
                        <span class="label">状态</span>
                        <select class="select" name="status" data-status-select>
                            <option value="draft"<?= selected($statusValue, 'draft') ?>>草稿</option>
                            <option value="published"<?= selected($statusValue, 'published') ?>>立即发布</option>
                            <option value="scheduled"<?= selected($statusValue, 'scheduled') ?>>定时发布</option>
                        </select>
                        <?php if (isset($errors['status'])): ?><small class="error-text"><?= e($errors['status']) ?></small><?php endif; ?>
                    </label>
                    <label class="field" data-schedule-field>
                        <span class="label">发布时间</span>
                        <input class="input" type="datetime-local" name="published_at" value="<?= e($publishedValue) ?>">
                        <small class="hint">定时发布必须晚于当前时间；留空则使用当前时间。</small>
                        <?php if (isset($errors['published_at'])): ?><small class="error-text"><?= e($errors['published_at']) ?></small><?php endif; ?>
                    </label>
                    <label class="check-row"><input type="checkbox" name="pinned" value="1"<?= checked(old('pinned', $post['pinned'] ?? 0)) ?>> <span>置顶这篇文章</span></label>
                    <label class="check-row"><input type="checkbox" name="allow_comments" value="1"<?= checked(old('allow_comments', $post['allow_comments'] ?? 1)) ?>> <span>允许评论</span></label>
                    <button class="btn btn-primary btn-block" type="submit"><?= icon('save') ?> <?= $isEdit ? '保存修改' : '创建文章' ?></button>
                    <?php if ($isEdit): ?>
                        <div class="subtle-actions">
                            <?php if (($post['status'] ?? '') === 'published'): ?>
                                <a href="<?= e(post_url($post)) ?>" target="_blank" rel="noopener"><?= icon('eye', 15) ?> 查看文章</a>
                            <?php endif; ?>
                            <a href="<?= e(admin_url('posts/' . $post['id'] . '/revisions')) ?>"><?= icon('clock', 15) ?> 修订记录 (<?= (int) $revisionCount ?>)</a>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <section class="card">
                <div class="card-head"><h3 class="card-title">分类</h3></div>
                <div class="card-body category-picker">
                    <?php if ($categories === []): ?>
                        <p class="muted">还没有分类，<a href="<?= e(admin_url('categories')) ?>">去创建</a>。</p>
                    <?php else: ?>
                        <?php foreach ($categories as $category): ?>
                            <label class="check-row" style="--depth: <?= (int) ($category['depth'] ?? 0) ?>">
                                <input type="checkbox" name="categories[]" value="<?= (int) $category['id'] ?>"<?= in_array((int) $category['id'], $selected, true) ? ' checked' : '' ?>>
                                <span><?= e($category['name']) ?> <small class="muted"><?= (int) ($category['post_count'] ?? 0) ?></small></span>
                            </label>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>

            <section class="card">
                <div class="card-head"><h3 class="card-title">标签</h3></div>
                <div class="card-body stack-form">
                    <label class="field">
                        <input class="input" type="text" name="tags" value="<?= e(old('tags', $tags)) ?>" placeholder="设计, 随笔, 生活">
                        <small class="hint">使用逗号分隔多个标签。</small>
                    </label>
                    <?php if ($allTags !== []): ?>
                        <div class="tag-cloud">
                            <?php foreach (array_slice($allTags, 0, 14) as $tag): ?>
                                <button class="tag-chip" type="button" data-tag="<?= e($tag['name']) ?>"><?= e($tag['name']) ?></button>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <section class="card">
                <div class="card-head"><h3 class="card-title">封面图</h3></div>
                <div class="card-body stack-form">
                    <label class="field">
                        <input class="input" type="text" name="cover" value="<?= e(old('cover', $post['cover'] ?? '')) ?>" placeholder="https://... 或 uploads/...">
                        <small class="hint">可填写图片地址，或从<a href="<?= e(admin_url('media')) ?>" target="_blank">媒体库</a>复制路径。</small>
                    </label>
                </div>
            </section>
        </aside>
    </div>
</form>