<?php
/**
 * Typecho migration screen.
 *
 * @var array<string, mixed>|null  $detection
 * @var string                     $error
 * @var list<array<string, mixed>> $users
 * @var int                        $currentUserId
 * @var string                     $prefixInput
 */
$available  = (bool) ($detection['available'] ?? false);
$tables     = $detection['tables'] ?? [];
$counts     = $detection['counts'] ?? [];
$map        = $detection['map'] ?? ['total' => 0, 'entities' => []];
$candidates = $detection['candidates'] ?? [];
$missing    = $detection['missing'] ?? [];
$prefix     = (string) ($detection['prefix'] ?? $prefixInput);

$tableLabels = [
    'contents'      => '内容',
    'metas'         => '分类 / 标签',
    'relationships' => '内容关联',
    'comments'      => '评论',
    'users'         => '用户',
    'fields'        => '自定义字段',
];
?>
<style>
.tcm-prefix{display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;margin-top:12px}
.tcm-prefix .field{margin:0;min-width:250px}
.tcm-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(112px,1fr));gap:10px;margin-top:16px}
.tcm-stat{padding:12px 14px;border:1px solid var(--border);border-radius:10px;background:var(--surface-2)}
.tcm-stat strong{display:block;font-size:21px;line-height:1.15;color:var(--text)}
.tcm-stat span{display:block;margin-top:3px;font-size:11.5px;color:var(--muted)}
.tcm-tables{display:flex;flex-wrap:wrap;gap:6px;margin-top:14px}
.tcm-missing{margin:12px 0 0;padding:10px 12px;border-radius:8px;background:var(--danger-soft,#fdecec);color:var(--danger,#b42318);font-size:12.5px;line-height:1.7}
.tcm-options{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;margin:16px 0}
.tcm-options .field{margin:0}
.tcm-hints{margin:14px 0 0;padding-left:18px;color:var(--muted);font-size:12.5px;line-height:1.9}
.tcm-hints code{font-size:11.5px}
.tcm-history{margin-top:14px;padding:10px 12px;border-radius:8px;background:var(--primary-soft);color:var(--primary-ink,var(--primary));font-size:12.5px}
.tcm-form-actions{display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-top:4px}
.tcm-empty{padding:26px 18px}
</style>

<div class="page-toolbar">
    <div>
        <a class="back-link" href="<?= e(admin_url('plugins')) ?>"><?= icon('arrow-left', 15) ?> 返回插件列表</a>
        <h2 class="page-subtitle">Typecho 数据迁移</h2>
        <p class="muted">自动扫描当前数据库中的 Typecho 数据表，把文章、页面、分类、标签与评论迁移到 WenBlog。</p>
    </div>
    <span class="pill">v1.0.0</span>
</div>

<?php if ($error !== ''): ?>
    <div class="alert alert-error"><?= icon('x', 18) ?><span><?= e($error) ?></span></div>
<?php endif; ?>

<section class="card">
    <div class="card-body">
        <h3><?= icon('database', 17) ?> 数据库检测</h3>
        <form method="get" action="<?= e(admin_url('plugins/typecho-migrate')) ?>" class="tcm-prefix">
            <label class="field">
                <span class="label">数据表前缀</span>
                <input class="input" type="text" name="prefix" value="<?= e($prefixInput) ?>" placeholder="留空自动检测，例如 typecho_">
            </label>
            <button class="btn btn-ghost" type="submit"><?= icon('refresh', 15) ?> 重新检测</button>
        </form>

        <?php if ($detection === null): ?>
            <p class="muted" style="margin-top:14px">暂时无法读取数据库表信息，请检查上面的提示。</p>
        <?php elseif ($available): ?>
            <div class="tcm-tables">
                <span class="pill"><?= icon('check-circle', 13) ?> 已检测到 <?= e($prefix !== '' ? $prefix : '（无前缀）') ?> 表</span>
                <?php foreach ($tableLabels as $suffix => $label): ?>
                    <span class="pill"><?= e($label) ?><?= !empty($tables[$suffix]) ? ' · 有' : ' · 无' ?></span>
                <?php endforeach; ?>
            </div>

            <div class="tcm-stats">
                <div class="tcm-stat"><strong><?= (int) ($counts['posts'] ?? 0) ?></strong><span>已发布文章</span></div>
                <div class="tcm-stat"><strong><?= (int) ($counts['pages'] ?? 0) ?></strong><span>已发布页面</span></div>
                <div class="tcm-stat"><strong><?= (int) ($counts['drafts'] ?? 0) ?></strong><span>草稿 / 隐藏内容</span></div>
                <div class="tcm-stat"><strong><?= (int) ($counts['categories'] ?? 0) ?></strong><span>分类</span></div>
                <div class="tcm-stat"><strong><?= (int) ($counts['tags'] ?? 0) ?></strong><span>标签</span></div>
                <div class="tcm-stat"><strong><?= (int) ($counts['comments'] ?? 0) ?></strong><span>评论</span></div>
                <div class="tcm-stat"><strong><?= (int) ($counts['users'] ?? 0) ?></strong><span>Typecho 用户</span></div>
                <div class="tcm-stat"><strong><?= (int) ($counts['attachments'] ?? 0) ?></strong><span>附件（不迁移）</span></div>
            </div>

            <?php if (($map['total'] ?? 0) > 0): ?>
                <div class="tcm-history">
                    已经迁移过 <?= (int) $map['total'] ?> 条记录，再次执行只会补齐新增内容，不会产生重复数据。
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="empty tcm-empty">
                <?= icon('database', 34) ?>
                <h3>没有找到完整的 Typecho 数据表</h3>
                <p class="muted">请把 Typecho 的数据库导入当前站点使用的数据库，或在上方填写正确的表前缀。</p>
                <?php if ($candidates !== []): ?>
                    <p class="muted">检测到候选前缀：<?php foreach ($candidates as $candidate): ?><code><?= e($candidate) ?></code> <?php endforeach; ?></p>
                <?php endif; ?>
                <?php if ($missing !== []): ?>
                    <div class="tcm-missing">
                        以下数据表缺少必要字段：
                        <?php foreach ($missing as $table => $columns): ?>
                            <div><code><?= e($prefix . $table) ?></code>：<?= e(implode('、', $columns)) ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <ul class="tcm-hints">
                    <li>默认会识别 <code>typecho_contents</code>、<code>typecho_metas</code>、<code>typecho_relationships</code> 等表。</li>
                    <li>如果 Typecho 与 WenBlog 不在同一个数据库，请先导出 Typecho 数据并导入当前库。</li>
                    <li>自定义前缀可直接填写，例如 <code>blog_</code> 或 <code>typecho_</code>。</li>
                </ul>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php if ($available): ?>
    <section class="card">
        <div class="card-body">
            <h3><?= icon('upload-cloud', 17) ?> 开始迁移</h3>
            <p class="muted">迁移会写入当前数据库。建议在操作前备份数据库；重复执行是安全的。</p>

            <form method="post" action="<?= e(admin_url('plugins/typecho-migrate/run')) ?>" class="tcm-form" data-confirm="确定开始迁移 Typecho 数据吗？建议先备份当前数据库。">
                <?= csrf_field() ?>
                <input type="hidden" name="prefix" value="<?= e($prefix) ?>">

                <div class="tcm-options">
                    <label class="check-row"><input type="checkbox" name="import_pages" value="1" checked> <span>迁移独立页面</span></label>
                    <label class="check-row"><input type="checkbox" name="import_drafts" value="1" checked> <span>迁移草稿与隐藏内容</span></label>
                    <label class="check-row"><input type="checkbox" name="import_comments" value="1" checked> <span>迁移评论与回复</span></label>
                    <label class="field">
                        <span class="label">未匹配作者时归入</span>
                        <select class="select" name="default_author">
                            <?php foreach ($users as $user): ?>
                                <option value="<?= (int) $user['id'] ?>"<?= (int) $user['id'] === $currentUserId ? ' selected' : '' ?>>
                                    <?= e((string) $user['display_name']) ?>（<?= e((string) $user['username']) ?>）
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>

                <div class="tcm-form-actions">
                    <button class="btn btn-primary" type="submit"><?= icon('database', 16) ?> 开始迁移</button>
                    <span class="muted">已发布内容会保留原发布时间，草稿进入后台草稿箱。</span>
                </div>
            </form>

            <ul class="tcm-hints">
                <li>作者按邮箱或用户名匹配现有用户，不会创建新用户；未匹配时归入上面选择的作者。</li>
                <li>私密、隐藏的文章会作为草稿迁入，避免未经确认直接公开。</li>
                <li>分类层级、标签、评论回复关系和审核状态都会保留。</li>
                <li>Typecho 的附件文件与自定义字段不会复制；正文如果引用 <code>/usr/uploads/...</code>，请同步附件目录。</li>
                <li>HTML 正文会自动转换为 Markdown；带 <code>&lt;!--markdown--&gt;</code> 标记的正文按 Markdown 原样保留。</li>
            </ul>
        </div>
    </section>
<?php endif; ?>
