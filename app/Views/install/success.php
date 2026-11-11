<?php
/**
 * @var string $siteUrl
 * @var string $adminUrl
 */
?>
<ol class="install-steps">
    <li class="is-done"><b>✓</b><span>环境检查</span></li>
    <li class="is-done"><b>✓</b><span>数据库</span></li>
    <li class="is-done"><b>✓</b><span>管理员</span></li>
    <li class="is-done"><b>✓</b><span>完成</span></li>
</ol>

<span class="success-mark">✓</span>
<h1>安装完成</h1>
<p class="install-lead">WenBlog 已经可以使用了。建议先发布一篇文章，或者到后台调整站点设置与主题。</p>

<div class="finish-note">
    <span class="finish-mark">!</span>
    <span>出于安全考虑，请立即删除或重命名 <code>install/</code> 目录，并确认 <code>config/config.php</code> 的权限为 0640。安装实例已通过 <code>config/installed.lock</code> 锁定。</span>
</div>

<div class="success-links">
    <a class="success-link" href="<?= e($siteUrl) ?>">
        <strong>访问站点</strong>
        <span><?= e($siteUrl) ?></span>
    </a>
    <a class="success-link" href="<?= e($adminUrl) ?>">
        <strong>进入后台</strong>
        <span>登录并开始写作</span>
    </a>
</div>

<div class="actions">
    <span></span>
    <span class="spacer"></span>
    <a class="btn btn-primary" href="<?= e($adminUrl) ?>">前往登录</a>
</div>