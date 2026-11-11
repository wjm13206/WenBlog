<?php
/** @var array<string, mixed> $page */
/** @var list<string> $templates */
$errors = errors();
$isEdit = !empty($page['id']);
$action = $isEdit ? admin_url('pages/' . $page['id']) : admin_url('pages');
?>
<form method="post" action="<?= e($action) ?>" class="editor-form" id="page-form"
      data-preview-url="<?= e(admin_url('preview')) ?>"
      data-upload-url="<?= e(admin_url('media/ajax')) ?>"
      data-draft-key="<?= e('wenblog:draft:page:' . ($page['id'] ?? 'new')) ?>"
      data-loaded-at="<?= time() ?>">
    <?= csrf_field() ?>
    <div class="editor-grid">
        <div class="editor-main">
            <section class="card">
                <div class="card-body stack-form">
                    <label class="field">
                        <span class="label">页面标题</span>
                        <input class="input input-title" type="text" name="title" value="<?= e(old('title', $page['title'] ?? '')) ?>" maxlength="255" required autofocus>
                        <?php if (isset($errors['title'])): ?><small class="error-text"><?= e($errors['title']) ?></small><?php endif; ?>
                    </label>
                    <label class="field">
                        <span class="label">页面路径 <small class="muted">留空按标题生成</small></span>
                        <div class="input-prefix">
                            <span><?= e(url('')) ?></span>
                            <input class="input" type="text" name="slug" value="<?= e(old('slug', $page['slug'] ?? '')) ?>" placeholder="about" pattern="[a-z0-9\-]*">
                        </div>
                    </label>
                </div>
            </section>

            <section class="card editor-card">
                <div class="card-head">
                    <div><h3 class="card-title">页面内容</h3><p class="muted">支持 Markdown</p></div>
                    <div class="editor-tabs">
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
                    <button type="button" data-md="ul" title="列表">•</button>
                    <button type="button" data-md="link" title="链接"><?= icon('link', 15) ?></button>
                    <button type="button" data-md="image" title="上传图片（也可直接粘贴或拖入）"><?= icon('image', 15) ?></button>
                    <button type="button" data-md="code" title="代码"><?= icon('code', 15) ?></button>
                    <span class="toolbar-spacer"></span>
                    <span class="autosave-state" data-autosave-status></span>
                </div>
                <div class="editor-panes" data-editor-panes>
                    <div class="editor-pane" data-editor-pane="write">
                        <textarea class="md-textarea" name="content" id="page-content" rows="20" required placeholder="页面正文……支持粘贴或拖入图片"><?= e(old('content', $page['content'] ?? '')) ?></textarea>
                        <?php if (isset($errors['content'])): ?><small class="error-text"><?= e($errors['content']) ?></small><?php endif; ?>
                    </div>
                    <div class="editor-pane md-preview" data-editor-pane="preview" hidden>
                        <div class="prose" data-preview-body></div>
                    </div>
                </div>
            </section>
        </div>

        <aside class="editor-side">
            <section class="card">
                <div class="card-head"><h3 class="card-title">页面设置</h3></div>
                <div class="card-body stack-form">
                    <label class="field">
                        <span class="label">状态</span>
                        <select class="select" name="status">
                            <option value="draft"<?= selected(old('status', $page['status'] ?? 'draft'), 'draft') ?>>草稿</option>
                            <option value="published"<?= selected(old('status', $page['status'] ?? 'draft'), 'published') ?>>已发布</option>
                        </select>
                    </label>
                    <label class="field">
                        <span class="label">页面模板</span>
                        <select class="select" name="template">
                            <option value="">默认模板</option>
                            <?php foreach ($templates as $template): ?>
                                <option value="<?= e($template) ?>"<?= selected(old('template', $page['template'] ?? ''), $template) ?>><?= e($template) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="hint">主题可在根目录提供自定义模板文件。</small>
                        <?php if (isset($errors['template'])): ?><small class="error-text"><?= e($errors['template']) ?></small><?php endif; ?>
                    </label>
                    <label class="field">
                        <span class="label">摘要 <small class="muted">可选</small></span>
                        <textarea class="textarea" name="excerpt" rows="3" maxlength="500"><?= e(old('excerpt', $page['excerpt'] ?? '')) ?></textarea>
                    </label>
                    <button class="btn btn-primary btn-block" type="submit"><?= icon('save') ?> <?= $isEdit ? '保存页面' : '创建页面' ?></button>
                    <?php if ($isEdit && ($page['status'] ?? '') === 'published'): ?>
                        <a class="btn btn-ghost btn-block" href="<?= e(page_url($page)) ?>" target="_blank" rel="noopener"><?= icon('eye') ?> 查看页面</a>
                    <?php endif; ?>
                </div>
            </section>
        </aside>
    </div>
</form>