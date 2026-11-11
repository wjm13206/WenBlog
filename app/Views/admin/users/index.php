<?php
/** @var list<array<string, mixed>> $users */
$me = current_user();
?>
<div class="page-toolbar">
    <div>
        <h2 class="page-subtitle">用户管理</h2>
        <p class="muted">管理员可以管理站点与用户；编辑可以撰写内容但不能修改系统设置。</p>
    </div>
    <a class="btn btn-primary" href="<?= e(admin_url('users/create')) ?>"><?= icon('plus') ?> 新增用户</a>
</div>

<section class="card">
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>用户</th><th>邮箱</th><th>角色</th><th>状态</th><th>最近登录</th><th class="actions-col">操作</th></tr></thead>
            <tbody>
            <?php foreach ($users as $user): ?>
                <tr>
                    <td>
                        <div class="user-cell">
                            <span class="avatar"><?= e(initials((string) ($user['display_name'] ?: $user['username']))) ?></span>
                            <div>
                                <strong><?= e($user['display_name'] ?: $user['username']) ?></strong>
                                <span class="muted">@<?= e($user['username']) ?><?php if ((int) $user['id'] === (int) ($me['id'] ?? 0)): ?> · 你<?php endif; ?></span>
                            </div>
                        </div>
                    </td>
                    <td class="muted"><?= e($user['email']) ?></td>
                    <td><span class="badge badge-<?= $user['role'] === 'admin' ? 'published' : 'soft' ?>"><?= e(role_label((string) $user['role'])) ?></span></td>
                    <td><span class="badge badge-<?= $user['status'] === 'active' ? 'approved' : 'draft' ?>"><?= $user['status'] === 'active' ? '正常' : '已停用' ?></span></td>
                    <td class="muted nowrap"><?= e($user['last_login'] ? format_date((string) $user['last_login'], 'Y-m-d H:i') : '从未登录') ?></td>
                    <td>
                        <div class="row-actions">
                            <a class="icon-btn" href="<?= e(admin_url('users/' . $user['id'] . '/edit')) ?>" title="编辑"><?= icon('edit', 16) ?></a>
                            <?php if ((int) $user['id'] !== (int) ($me['id'] ?? 0)): ?>
                                <form method="post" action="<?= e(admin_url('users/' . $user['id'] . '/delete')) ?>" data-confirm="确定删除用户「<?= e($user['username']) ?>」吗？其文章将保留。">
                                    <?= csrf_field() ?>
                                    <button class="icon-btn danger" type="submit" title="删除"><?= icon('trash', 16) ?></button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>