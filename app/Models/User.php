<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\App;
use App\Core\Database;
use App\Core\Security;
use RuntimeException;

final class User
{
    protected Database $db;

    public function __construct(protected readonly App $app)
    {
        $this->db = $app->db;
    }

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        return $this->db->fetchAll(
            'SELECT id, username, email, display_name, role, status, avatar, bio, last_login_at, created_at
             FROM users ORDER BY id ASC'
        );
    }

    public function find(int $id): ?array
    {
        return $this->db->fetch('SELECT * FROM users WHERE id = ? LIMIT 1', [$id]);
    }

    public function create(array $data): int
    {
        $username = trim((string) ($data['username'] ?? ''));
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $password = (string) ($data['password'] ?? '');
        $role = in_array($data['role'] ?? '', ['admin', 'editor'], true) ? (string) $data['role'] : 'editor';

        if ($this->db->fetch('SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1', [$username, $email]) !== null) {
            throw new RuntimeException('用户名或邮箱已存在。');
        }

        return $this->db->insert('users', [
            'username'      => $username,
            'email'         => $email,
            'password_hash' => Security::hashPassword($password),
            'display_name'  => mb_substr(trim((string) ($data['display_name'] ?? $username)), 0, 100, 'UTF-8'),
            'role'          => $role,
            'status'        => ($data['status'] ?? 'active') === 'disabled' ? 'disabled' : 'active',
            'bio'           => mb_substr(trim((string) ($data['bio'] ?? '')), 0, 1000, 'UTF-8'),
            'created_at'    => date('Y-m-d H:i:s'),
            'updated_at'    => date('Y-m-d H:i:s'),
        ]);
    }

    public function update(int $id, array $data): void
    {
        $user = $this->find($id);
        if ($user === null) {
            throw new RuntimeException('用户不存在。');
        }
        $username = trim((string) ($data['username'] ?? $user['username']));
        $email = strtolower(trim((string) ($data['email'] ?? $user['email'])));
        $role = in_array($data['role'] ?? '', ['admin', 'editor'], true) ? (string) $data['role'] : (string) $user['role'];
        $status = ($data['status'] ?? $user['status']) === 'disabled' ? 'disabled' : 'active';

        if ($this->db->fetch('SELECT id FROM users WHERE (username = ? OR email = ?) AND id <> ? LIMIT 1', [$username, $email, $id]) !== null) {
            throw new RuntimeException('用户名或邮箱已存在。');
        }
        if ((int) $user['id'] === (int) $this->app->auth->id() && $role !== 'admin') {
            throw new RuntimeException('不能取消自己的管理员权限。');
        }
        if ((int) $user['id'] === (int) $this->app->auth->id() && $status !== 'active') {
            throw new RuntimeException('不能停用当前登录的账户。');
        }
        if ($user['role'] === 'admin' && $role === 'editor' && $this->adminCount() <= 1) {
            throw new RuntimeException('必须至少保留一名管理员。');
        }

        $update = [
            'username'     => $username,
            'email'        => $email,
            'display_name' => mb_substr(trim((string) ($data['display_name'] ?? $username)), 0, 100, 'UTF-8'),
            'role'         => $role,
            'status'       => $status,
            'bio'          => mb_substr(trim((string) ($data['bio'] ?? $user['bio'])), 0, 1000, 'UTF-8'),
            'updated_at'   => date('Y-m-d H:i:s'),
        ];
        if (!empty($data['password'])) {
            $update['password_hash'] = Security::hashPassword((string) $data['password']);
        }
        $this->db->update('users', $update, ['id' => $id]);
        $this->app->cache->clear();
    }

    public function updateProfile(int $id, array $data): void
    {
        $update = [
            'display_name' => mb_substr(trim((string) ($data['display_name'] ?? '')), 0, 100, 'UTF-8'),
            'email'        => strtolower(trim((string) ($data['email'] ?? ''))),
            'bio'          => mb_substr(trim((string) ($data['bio'] ?? '')), 0, 1000, 'UTF-8'),
            'updated_at'   => date('Y-m-d H:i:s'),
        ];
        if ($this->db->fetch('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1', [$update['email'], $id]) !== null) {
            throw new RuntimeException('该邮箱已被使用。');
        }
        if (!empty($data['password'])) {
            $update['password_hash'] = Security::hashPassword((string) $data['password']);
        }
        $this->db->update('users', $update, ['id' => $id]);
        $this->app->cache->clear();
    }

    public function delete(int $id): void
    {
        $user = $this->find($id);
        if ($user === null) {
            return;
        }
        if ((int) $user['id'] === (int) $this->app->auth->id()) {
            throw new RuntimeException('不能删除当前登录的账户。');
        }
        if ($user['role'] === 'admin' && $this->adminCount() <= 1) {
            throw new RuntimeException('必须至少保留一名管理员。');
        }
        $replacement = $this->app->auth->id();
        $this->db->transaction(function () use ($id, $replacement): void {
            if ($replacement > 0) {
                $this->db->update('posts', ['author_id' => $replacement], ['author_id' => $id]);
                $this->db->update('pages', ['author_id' => $replacement], ['author_id' => $id]);
            }
            $this->db->delete('users', ['id' => $id]);
        });
        $this->app->cache->clear();
    }

    public function adminCount(): int
    {
        return $this->db->count("SELECT COUNT(*) FROM users WHERE role = 'admin' AND status = 'active'");
    }

    public function count(): int
    {
        return $this->db->count('SELECT COUNT(*) FROM users');
    }
}