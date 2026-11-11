<?php
/** @var array<string, mixed> $user */
$errors = errors();
$isEdit = !empty($user['id']);
$action = $isEdit ? admin_url('users/' . $user['id']) : admin_url('users');
?>
<div class="page-toolbar">
    <div>
        <a class="text-link" href="<?= e(admin_url('users')) ?>"><?= icon('arrow-left', 15) ?> 返回用户列表</a>
        <h2 class="page-subtitle"><?= $isEdit ? '编辑用户' : '新增用户' ?></h2>
    </div>
</div>

<form method="post" action="<?= e($action) ?>" class="form-narrow">
    <?= csrf_field() ?>
    <?php if (isset($errors['form'])): ?><div class="alert alert-error"><?= e($errors['form']) ?></div><?php endif; ?>
    <section class="card">
        <div class="card-head"><h3 class="card-title">账户信息</h3></div>
        <div class="card-body stack-form">
            <div class="form-grid-2">
                <label class="field">
                    <span class="label">用户名</span>
                    <input class="input" type="text" name="username" value="<?= e(old('username', $user['username'] ?? '')) ?>" pattern="[A-Za-z0-9_]{3,30}" required>
                    <small class="hint">3-30 位字母、数字或下划线，用于登录。</small>
                    <?php if (isset($errors['username'])): ?><small class="error-text"><?= e($errors['username']) ?></small><?php endif; ?>
                </label>
                <label class="field">
                    <span class="label">显示名称</span>
                    <input class="input" type="text" name="display_name" value="<?= e(old('display_name', $user['display_name'] ?? '')) ?>" maxlength="100">
                </label>
            </div>
            <label class="field">
                <span class="label">邮箱</span>
                <input class="input" type="email" name="email" value="<?= e(old('email', $user['email'] ?? '')) ?>" required>
                <?php if (isset($errors['email'])): ?><small class="error-text"><?= e($errors['email']) ?></small><?php endif; ?>
            </label>
            <label class="field">
                <span class="label">密码 <?php if ($isEdit): ?><small class="muted">留空则不修改</small><?php endif; ?></span>
                <input class="input" type="password" name="password" <?= $isEdit ? '' : 'required' ?> autocomplete="new-password" placeholder="<?= $isEdit ? '••••••••' : '至少 8 个字符' ?>">
                <?php if (isset($errors['password'])): ?><small class="error-text"><?= e($errors['password']) ?></small><?php endif; ?>
            </label>
            <div class="form-grid-2">
                <label class="field">
                    <span class="label">角色</span>
                    <select class="select" name="role">
                        <option value="editor"<?= selected(old('role', $user['role'] ?? 'editor'), 'editor') ?>>编辑</option>
                        <option value="admin"<?= selected(old('role', $user['role'] ?? 'editor'), 'admin') ?>>管理员</option>
                    </select>
                    <small class="hint">管理员拥有全部权限，编辑只能管理内容与媒体。</small>
                </label>
                <label class="field">
                    <span class="label">状态</span>
                    <select class="select" name="status">
                        <option value="active"<?= selected(old('status', $user['status'] ?? 'active'), 'active') ?>>正常</option>
                        <option value="disabled"<?= selected(old('status', $user['status'] ?? 'active'), 'disabled') ?>>停用</option>
                    </select>
                </label>
            </div>
            <label class="field">
                <span class="label">个人简介 <small class="muted">可选</small></span>
                <textarea class="textarea" name="bio" rows="4"><?= e(old('bio', $user['bio'] ?? '')) ?></textarea>
            </label>
        </div>
    </section>
    <div class="form-actions">
        <button class="btn btn-primary" type="submit"><?= icon('save') ?> <?= $isEdit ? '保存用户' : '创建用户' ?></button>
        <a class="btn btn-ghost" href="<?= e(admin_url('users')) ?>">取消</a>
    </div>
</form>