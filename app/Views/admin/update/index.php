<?php
/** @var string $currentVersion */
/** @var array<string, mixed>|null $lastCheck */
/** @var list<array<string, mixed>> $history */
/** @var string $repository */
/** @var string $manifest */
/** @var array{ok: bool, items: list<array<string, mixed>>} $writable */
/** @var bool $zipSupported */
/** @var string $phpVersion */

$latest = (string) ($lastCheck['latest'] ?? '');
$available = (bool) ($lastCheck['available'] ?? false);
$download = (string) ($lastCheck['download'] ?? '');
$checkedAt = (string) ($lastCheck['checked_at'] ?? '');
$checkError = (string) ($lastCheck['error'] ?? '');
$changelog = trim((string) ($lastCheck['changelog'] ?? ''));
$sourceLabels = ['online' => '在线更新', 'upload' => '手动上传'];
?>
<div class="page-toolbar">
    <div>
        <h2 class="page-subtitle">系统更新</h2>
        <p class="muted">检查并安装 WenBlog 核心更新。主题、插件、上传文件与配置不会被覆盖。</p>
    </div>
    <form method="post" action="<?= e(admin_url('update/check')) ?>">
        <?= csrf_field() ?>
        <button class="btn btn-primary" type="submit"><?= icon('refresh', 16) ?> 检查更新</button>
    </form>
</div>

<?php if (!$zipSupported): ?>
    <div class="alert alert-error">
        <?= icon('alert', 18) ?>
        <span>服务器未启用 ZipArchive 扩展，无法解压更新包。请启用 PHP 的 zip 扩展后再试。</span>
    </div>
<?php endif; ?>

<?php if (!$writable['ok']): ?>
    <div class="alert alert-error">
        <?= icon('alert', 18) ?>
        <span>部分核心文件或目录不可写，在线更新可能失败。请检查下方环境状态中的项目权限。</span>
    </div>
<?php endif; ?>

<section class="card update-summary">
    <div class="update-version">
        <span class="update-version-label">当前版本</span>
        <strong>v<?= e($currentVersion) ?></strong>
    </div>
    <div class="update-version<?= $available ? ' is-new' : '' ?>">
        <span class="update-version-label">最新版本</span>
        <strong><?= $latest !== '' ? 'v' . e($latest) : '未检查' ?></strong>
    </div>
    <div class="update-summary-state">
        <?php if ($lastCheck === null): ?>
            <span class="badge badge-soft">尚未检查</span>
        <?php elseif ($checkError !== ''): ?>
            <span class="badge badge-spam">检查失败</span>
        <?php elseif (($lastCheck['source'] ?? '') === 'none'): ?>
            <span class="badge badge-soft">无发布包</span>
        <?php elseif ($available): ?>
            <span class="badge badge-pending">有新版本</span>
        <?php else: ?>
            <span class="badge badge-approved">已是最新</span>
        <?php endif; ?>
        <?php if ($checkedAt !== ''): ?>
            <small class="muted">检查于 <?= e($checkedAt) ?></small>
        <?php endif; ?>
    </div>
</section>

<?php if ($checkError !== ''): ?>
    <div class="alert alert-error" style="margin-top: 18px;">
        <?= icon('x', 18) ?>
        <span><?= e($checkError) ?></span>
    </div>
<?php elseif (($lastCheck['source'] ?? '') === 'none'): ?>
    <div class="alert alert-info" style="margin-top: 18px;">
        <?= icon('info', 18) ?>
        <span><?= e((string) ($lastCheck['message'] ?? '更新源暂时没有可用的发布包。')) ?></span>
    </div>
<?php endif; ?>

<?php if ($changelog !== ''): ?>
    <section class="card">
        <div class="card-head">
            <div>
                <h3 class="card-title"><?= icon('file-text', 17) ?> 更新说明</h3>
                <p class="muted"><?= e($latest !== '' ? 'v' . $latest : '最新版本') ?><?= ($lastCheck['published_at'] ?? '') !== '' ? ' · ' . e((string) $lastCheck['published_at']) : '' ?></p>
            </div>
        </div>
        <div class="card-body">
            <div class="update-changelog"><?= nl2br(e($changelog)) ?></div>
        </div>
    </section>
<?php endif; ?>

<?php if ($available && $download !== ''): ?>
    <section class="card">
        <div class="card-body update-action">
            <div>
                <h3 class="card-title"><?= icon('upload-cloud', 17) ?> 在线更新到 v<?= e($latest) ?></h3>
                <p class="muted">将下载更新包并覆盖核心文件。更新前会自动备份到 <code>storage/updates</code>，主题、插件与上传内容保持不变。</p>
            </div>
            <form method="post" action="<?= e(admin_url('update/apply')) ?>" data-confirm="确定立即更新到 v<?= e($latest) ?> 吗？更新期间站点可能短暂不可用。">
                <?= csrf_field() ?>
                <button class="btn btn-primary" type="submit"><?= icon('refresh', 16) ?> 立即更新</button>
            </form>
        </div>
    </section>
<?php elseif ($available): ?>
    <div class="alert alert-info" style="margin-top: 18px;">
        <?= icon('info', 18) ?>
        <span>更新源没有提供下载地址，请下载更新包后使用下方的「手动上传」完成升级。</span>
    </div>
<?php endif; ?>

<section class="card">
    <div class="card-head">
        <div>
            <h3 class="card-title"><?= icon('file', 17) ?> 手动上传更新包</h3>
            <p class="muted">适用于服务器无法访问外网，或更新源没有在线发布包的情况。</p>
        </div>
    </div>
    <div class="card-body">
        <form method="post" action="<?= e(admin_url('update/upload')) ?>" enctype="multipart/form-data" class="stack-form">
            <?= csrf_field() ?>
            <div class="form-grid-2">
                <label class="field">
                    <span class="label">更新包（ZIP）</span>
                    <input class="input" type="file" name="file" accept=".zip" required>
                    <span class="hint">压缩包中应包含 index.php 与 app/ 目录，允许再套一层文件夹。</span>
                </label>
                <label class="field">
                    <span class="label">版本号（可选）</span>
                    <input class="input" type="text" name="version" placeholder="例如 1.2.0">
                    <span class="hint">留空则从包内 version.json 或 app/defaults.php 读取。</span>
                </label>
            </div>
            <div class="settings-footer">
                <button class="btn btn-primary" type="submit"><?= icon('upload', 16) ?> 上传并更新</button>
                <span class="muted">更新只覆盖核心文件，不会改动 config/、storage/、uploads/、themes/ 与 plugins/。</span>
            </div>
        </form>
    </div>
</section>

<section class="card">
    <div class="card-head">
        <div>
            <h3 class="card-title"><?= icon('sliders', 17) ?> 更新源</h3>
            <p class="muted">默认使用 GitHub 仓库 <code><?= e(\App\Core\Updater::DEFAULT_REPOSITORY) ?></code> 的 Releases 或 Tags。也可以填写自定义 JSON 清单地址。</p>
        </div>
    </div>
    <div class="card-body">
        <form method="post" action="<?= e(admin_url('update/source')) ?>" class="stack-form">
            <?= csrf_field() ?>
            <div class="form-grid-2">
                <label class="field">
                    <span class="label">GitHub 仓库</span>
                    <input class="input" type="text" name="update_repository" value="<?= e($repository) ?>" placeholder="owner/repo">
                    <span class="hint">留空则使用默认仓库。</span>
                </label>
                <label class="field">
                    <span class="label">更新清单 URL</span>
                    <input class="input" type="url" name="update_manifest" value="<?= e($manifest) ?>" placeholder="https://example.com/wenblog.json">
                    <span class="hint">填写后优先使用清单，留空则读取 GitHub Releases / Tags。</span>
                </label>
            </div>
            <div class="settings-footer">
                <button class="btn btn-primary" type="submit"><?= icon('save', 16) ?> 保存更新源</button>
                <span class="muted">清单需包含 version 字段，可选 download、changelog、published_at。</span>
            </div>
        </form>
    </div>
</section>

<section class="card">
    <div class="card-head">
        <div>
            <h3 class="card-title"><?= icon('shield', 17) ?> 环境检查</h3>
            <p class="muted">PHP <?= e($phpVersion) ?> · ZipArchive <?= $zipSupported ? '可用' : '不可用' ?> · 临时目录 <code>storage/updates</code></p>
        </div>
    </div>
    <div class="card-body">
        <div class="update-paths">
            <?php foreach ($writable['items'] as $item): ?>
                <div class="update-path">
                    <code><?= e((string) $item['path']) ?></code>
                    <span>
                        <?php if ($item['writable']): ?>
                            <span class="badge badge-approved"><?= icon('check', 12) ?> 可写</span>
                        <?php else: ?>
                            <span class="badge badge-spam"><?= icon('x', 12) ?> 不可写</span>
                        <?php endif; ?>
                        <small class="muted"><?= $item['exists'] ? '已存在' : '将新建' ?></small>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="card">
    <div class="card-head">
        <div>
            <h3 class="card-title"><?= icon('clock', 17) ?> 更新记录</h3>
            <p class="muted">最近 20 次通过后台完成的更新，备份保留在 <code>storage/updates</code>。</p>
        </div>
    </div>
    <?php if ($history === []): ?>
        <div class="empty compact">
            <h3>暂无更新记录</h3>
            <p>完成一次更新后，这里会显示版本、时间和备份文件。</p>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>版本</th>
                        <th>原版本</th>
                        <th>时间</th>
                        <th>方式</th>
                        <th>文件</th>
                        <th>备份</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($history as $entry): ?>
                        <tr>
                            <td class="table-title">v<?= e((string) ($entry['version'] ?? '')) ?></td>
                            <td class="muted">v<?= e((string) ($entry['previous'] ?? '')) ?></td>
                            <td class="muted"><?= e((string) ($entry['applied_at'] ?? '')) ?></td>
                            <td><span class="badge badge-soft"><?= e($sourceLabels[(string) ($entry['source'] ?? '')] ?? '未知') ?></span></td>
                            <td class="muted"><?= (int) ($entry['files'] ?? 0) ?> 个<?= ($entry['migrations'] ?? []) !== [] ? ' · ' . count((array) $entry['migrations']) . ' 个迁移' : '' ?></td>
                            <td><code class="muted"><?= ($entry['backup'] ?? '') !== '' ? e((string) $entry['backup']) : '未备份' ?></code></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
