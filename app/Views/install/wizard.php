<?php
/**
 * @var int $step
 * @var list<array{label: string, ok: bool, detail: string, required: bool}> $requirements
 * @var bool $allMet
 * @var array<string, mixed> $db
 * @var array<string, mixed> $form
 * @var array{name?: string, url?: string} $site
 * @var array<string, mixed> $admin
 * @var array<string, string> $errors
 * @var string $basePath
 */
$stepLabels = ['环境检查', '数据库', '管理员', '完成'];
$fieldError = static fn (string $key): string => isset($errors[$key])
    ? '<span class="field-error">' . e($errors[$key]) . '</span>'
    : '';
$generalErrors = [];
foreach (['requirements', 'database', 'config', 'install'] as $key) {
    if (isset($errors[$key])) {
        $generalErrors[] = $errors[$key];
    }
}
?>
<ol class="install-steps">
    <?php foreach ($stepLabels as $index => $label): ?>
        <?php $number = $index + 1; ?>
        <li class="<?= $number < $step ? 'is-done' : ($number === $step ? 'is-current' : '') ?>">
            <b><?= $number < $step ? '✓' : $number ?></b><span><?= e($label) ?></span>
        </li>
    <?php endforeach; ?>
</ol>

<?php foreach ($generalErrors as $message): ?>
    <div class="alert alert-error"><?= e($message) ?></div>
<?php endforeach; ?>

<?php if ($step === 1): ?>
    <p class="install-kicker">Step 01</p>
    <h1>检查运行环境</h1>
    <p class="install-lead">确认服务器满足 WenBlog 的最低要求。标记为“必需”的项目未通过时无法继续。</p>

    <div class="check-list">
        <?php foreach ($requirements as $item): ?>
            <div class="check-row">
                <span class="check-state <?= $item['ok'] ? 'ok' : 'bad' ?>" aria-hidden="true"><?= $item['ok'] ? '✓' : '×' ?></span>
                <span class="check-name">
                    <?= e($item['label']) ?>
                    <span class="check-detail"><?= e($item['detail']) ?></span>
                </span>
                <span class="check-tag <?= $item['required'] ? '' : 'optional' ?>"><?= $item['required'] ? '必需' : '可选' ?></span>
            </div>
        <?php endforeach; ?>
    </div>

    <form method="post" action="<?= e($basePath) ?>/install/check">
        <?= csrf_field() ?>
        <div class="actions">
            <button type="button" class="btn btn-ghost" onclick="window.location.reload()">重新检测</button>
            <span class="spacer"></span>
            <button type="submit" class="btn btn-primary" <?= $allMet ? '' : 'disabled' ?>>
                <?= $allMet ? '继续设置数据库' : '请先解决未通过项' ?>
            </button>
        </div>
    </form>
<?php elseif ($step === 2): ?>
    <p class="install-kicker">Step 02</p>
    <h1>连接数据库</h1>
    <p class="install-lead">WenBlog 会自动创建数据表。数据库不存在时将尝试创建，请确保账号拥有相应权限。</p>

    <form method="post" action="<?= e($basePath) ?>/install/database">
        <?= csrf_field() ?>
        <h2>数据库</h2>
        <div class="form-grid">
            <div class="field <?= isset($errors['db_host']) ? 'has-error' : '' ?>">
                <label for="db_host">数据库主机</label>
                <input id="db_host" name="db_host" value="<?= e((string) ($form['db_host'] ?? '127.0.0.1')) ?>" autocomplete="off" required>
                <?= $fieldError('db_host') ?>
            </div>
            <div class="field <?= isset($errors['db_port']) ? 'has-error' : '' ?>">
                <label for="db_port">端口</label>
                <input id="db_port" name="db_port" type="number" min="1" max="65535" value="<?= e((string) ($form['db_port'] ?? '3306')) ?>" required>
                <?= $fieldError('db_port') ?>
            </div>
            <div class="field <?= isset($errors['db_name']) ? 'has-error' : '' ?>">
                <label for="db_name">数据库名</label>
                <input id="db_name" name="db_name" value="<?= e((string) ($form['db_name'] ?? 'wenblog')) ?>" autocomplete="off" required>
                <span class="hint">仅限字母、数字和下划线。</span>
                <?= $fieldError('db_name') ?>
            </div>
            <div class="field <?= isset($errors['db_user']) ? 'has-error' : '' ?>">
                <label for="db_user">用户名</label>
                <input id="db_user" name="db_user" value="<?= e((string) ($form['db_user'] ?? 'root')) ?>" autocomplete="username" required>
                <?= $fieldError('db_user') ?>
            </div>
            <div class="field full">
                <label for="db_pass">密码</label>
                <input id="db_pass" name="db_pass" type="password" value="" autocomplete="current-password">
                <span class="hint">如果数据库没有密码，请留空。密码只会保存在服务器配置文件中。</span>
            </div>
        </div>

        <h2>站点信息</h2>
        <div class="form-grid">
            <div class="field <?= isset($errors['site_name']) ? 'has-error' : '' ?>">
                <label for="site_name">网站名称</label>
                <input id="site_name" name="site_name" value="<?= e((string) ($form['site_name'] ?? $site['name'] ?? 'WenBlog')) ?>" required>
                <?= $fieldError('site_name') ?>
            </div>
            <div class="field <?= isset($errors['site_url']) ? 'has-error' : '' ?>">
                <label for="site_url">网站地址</label>
                <input id="site_url" name="site_url" type="url" value="<?= e((string) ($form['site_url'] ?? $site['url'] ?? '')) ?>" placeholder="https://example.com" required>
                <span class="hint">包含 http:// 或 https://，不要以斜杠结尾。</span>
                <?= $fieldError('site_url') ?>
            </div>
        </div>

        <div class="actions">
            <a class="btn btn-ghost" href="<?= e($basePath) ?>/install?step=1">返回检查</a>
            <span class="spacer"></span>
            <button type="submit" class="btn btn-primary">测试连接并继续</button>
        </div>
    </form>
<?php elseif ($step === 3): ?>
    <p class="install-kicker">Step 03</p>
    <h1>创建管理员账户</h1>
    <p class="install-lead">这是站点的第一个账户，拥有全部管理权限。请使用一个可靠且唯一的密码。</p>

    <form method="post" action="<?= e($basePath) ?>/install/admin">
        <?= csrf_field() ?>
        <div class="form-grid">
            <div class="field <?= isset($errors['username']) ? 'has-error' : '' ?>">
                <label for="username">登录用户名</label>
                <input id="username" name="username" value="<?= e((string) ($form['username'] ?? $admin['username'] ?? '')) ?>" autocomplete="username" required>
                <span class="hint">3-30 位字母、数字或下划线。</span>
                <?= $fieldError('username') ?>
            </div>
            <div class="field <?= isset($errors['display_name']) ? 'has-error' : '' ?>">
                <label for="display_name">显示名称</label>
                <input id="display_name" name="display_name" value="<?= e((string) ($form['display_name'] ?? $admin['display_name'] ?? '')) ?>" required>
                <?= $fieldError('display_name') ?>
            </div>
            <div class="field full <?= isset($errors['email']) ? 'has-error' : '' ?>">
                <label for="email">邮箱地址</label>
                <input id="email" name="email" type="email" value="<?= e((string) ($form['email'] ?? $admin['email'] ?? '')) ?>" autocomplete="email" required>
                <?= $fieldError('email') ?>
            </div>
            <div class="field <?= isset($errors['password']) ? 'has-error' : '' ?>">
                <label for="password">密码</label>
                <input id="password" name="password" type="password" minlength="8" autocomplete="new-password" required>
                <span class="hint">至少 8 个字符。</span>
                <?= $fieldError('password') ?>
            </div>
            <div class="field <?= isset($errors['password_confirm']) ? 'has-error' : '' ?>">
                <label for="password_confirm">确认密码</label>
                <input id="password_confirm" name="password_confirm" type="password" minlength="8" autocomplete="new-password" required>
                <?= $fieldError('password_confirm') ?>
            </div>
        </div>

        <div class="actions">
            <a class="btn btn-ghost" href="<?= e($basePath) ?>/install?step=2">返回数据库</a>
            <span class="spacer"></span>
            <button type="submit" class="btn btn-primary">继续</button>
        </div>
    </form>
<?php else: ?>
    <p class="install-kicker">Step 04</p>
    <h1>准备完成</h1>
    <p class="install-lead">确认以下信息后即可完成安装。提交后安装程序会被锁定。</p>

    <div class="summary">
        <div class="summary-row"><span>网站名称</span><strong><?= e((string) ($site['name'] ?? '')) ?></strong></div>
        <div class="summary-row"><span>网站地址</span><strong><?= e((string) ($site['url'] ?? '')) ?></strong></div>
        <div class="summary-row"><span>数据库</span><strong><?= e((string) ($db['name'] ?? '')) ?> @ <?= e((string) ($db['host'] ?? '')) ?>:<?= e((string) ($db['port'] ?? '3306')) ?></strong></div>
        <div class="summary-row"><span>管理员</span><strong><?= e((string) ($admin['username'] ?? '')) ?>（<?= e((string) ($admin['email'] ?? '')) ?>）</strong></div>
    </div>

    <div class="finish-note">
        <span class="finish-mark">✓</span>
        <span>数据表已创建。完成安装后，请删除或重命名 <code>install/</code> 目录，并确认 <code>config/config.php</code> 仅服务器账户可读。</span>
    </div>

    <form method="post" action="<?= e($basePath) ?>/install/finish">
        <?= csrf_field() ?>
        <div class="actions">
            <a class="btn btn-ghost" href="<?= e($basePath) ?>/install?step=3">返回管理员</a>
            <span class="spacer"></span>
            <button type="submit" class="btn btn-primary">完成安装</button>
        </div>
    </form>
<?php endif; ?>