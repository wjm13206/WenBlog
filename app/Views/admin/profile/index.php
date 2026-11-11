<?php
/** @var array<string, mixed> $user */
$errors = errors();
?>
<div class="page-toolbar">
    <div>
        <h2 class="page-subtitle">个人资料</h2>
        <p class="muted">更新你的公开信息与登录密码。</p>
    </div>
</div>

<div class="profile-grid">
    <aside class="card profile-card">
        <div class="profile-cover"></div>
        <div class="profile-avatar"><?= e(initials((string) ($user['display_name'] ?: $user['username']))) ?></div>
        <h3><?= e($user['display_name'] ?: $user['username']) ?></h3>
        <p class="muted">@<?= e($user['username']) ?></p>
        <p class="profile-bio"><?= e($user['bio'] ?: '这个人很懒，还没有写简介。') ?></p>
        <div class="profile-meta">
            <span class="badge badge-soft"><?= e(role_label((string) $user['role'])) ?></span>
            <span class="muted">加入于 <?= e(format_date((string) $user['created_at'], 'Y年n月')) ?></span>
        </div>
    </aside>

    <form method="post" action="<?= e(admin_url('profile')) ?>" class="stack-form">
        <?= csrf_field() ?>
        <?php if (isset($errors['form'])): ?><div class="alert alert-error"><?= e($errors['form']) ?></div><?php endif; ?>
        <section class="card">
            <div class="card-head"><h3 class="card-title">基本信息</h3></div>
            <div class="card-body stack-form">
                <div class="form-grid-2">
                    <label class="field">
                        <span class="label">显示名称</span>
                        <input class="input" type="text" name="display_name" value="<?= e($user['display_name'] ?? '') ?>" required maxlength="100">
                        <?php if (isset($errors['display_name'])): ?><small class="error-text"><?= e($errors['display_name']) ?></small><?php endif; ?>
                    </label>
                    <label class="field">
                        <span class="label">邮箱</span>
                        <input class="input" type="email" name="email" value="<?= e($user['email'] ?? '') ?>" required>
                        <?php if (isset($errors['email'])): ?><small class="error-text"><?= e($errors['email']) ?></small><?php endif; ?>
                    </label>
                </div>
                <label class="field">
                    <span class="label">个人简介</span>
                    <textarea class="textarea" name="bio" rows="4" maxlength="500" placeholder="介绍一下自己……"><?= e($user['bio'] ?? '') ?></textarea>
                </label>
            </div>
        </section>

        <section class="card">
            <div class="card-head"><div><h3 class="card-title">修改密码</h3><p class="muted">不修改密码时，以下字段留空即可。</p></div></div>
            <div class="card-body stack-form">
                <label class="field">
                    <span class="label">当前密码</span>
                    <input class="input" type="password" name="current_password" autocomplete="current-password">
                    <?php if (isset($errors['current_password'])): ?><small class="error-text"><?= e($errors['current_password']) ?></small><?php endif; ?>
                </label>
                <div class="form-grid-2">
                    <label class="field">
                        <span class="label">新密码</span>
                        <input class="input" type="password" name="password" autocomplete="new-password" minlength="8">
                        <?php if (isset($errors['password'])): ?><small class="error-text"><?= e($errors['password']) ?></small><?php endif; ?>
                    </label>
                    <label class="field">
                        <span class="label">确认新密码</span>
                        <input class="input" type="password" name="password_confirm" autocomplete="new-password">
                        <?php if (isset($errors['password_confirm'])): ?><small class="error-text"><?= e($errors['password_confirm']) ?></small><?php endif; ?>
                    </label>
                </div>
            </div>
        </section>

        <div class="form-actions">
            <button class="btn btn-primary" type="submit"><?= icon('save') ?> 保存资料</button>
        </div>
    </form>
</div>