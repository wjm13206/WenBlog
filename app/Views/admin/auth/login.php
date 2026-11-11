<?php
/** @var string $next */
$errors = errors();
?>
<div class="auth-card">
    <div class="auth-head">
        <span class="eyebrow">WELCOME BACK</span>
        <h2>登录控制台</h2>
        <p>使用管理员或编辑账号继续。</p>
    </div>
    <?php if (isset($errors['login'])): ?>
        <div class="alert alert-error"><span><?= e($errors['login']) ?></span></div>
    <?php endif; ?>
    <form method="post" action="<?= e(admin_url('login')) ?>" class="stack-form" autocomplete="on">
        <?= csrf_field() ?>
        <input type="hidden" name="next" value="<?= e($next) ?>">
        <label class="field">
            <span class="label">账号或邮箱</span>
            <input class="input" type="text" name="login" value="<?= e(old('login')) ?>" required autofocus autocomplete="username" placeholder="admin">
        </label>
        <label class="field">
            <span class="label">密码</span>
            <input class="input" type="password" name="password" required autocomplete="current-password" placeholder="••••••••">
        </label>
        <button class="btn btn-primary btn-block" type="submit">登录</button>
    </form>
    <p class="auth-hint">连续登录失败 5 次后，该账号将暂时锁定 15 分钟。</p>
</div>