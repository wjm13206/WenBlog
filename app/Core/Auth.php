<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Session-backed authentication with a small database rate limiter.
 */
final class Auth
{
    private ?array $user = null;
    private bool $loaded = false;

    public function __construct(private readonly App $app)
    {
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function user(): ?array
    {
        if ($this->loaded) {
            return $this->user;
        }
        $this->loaded = true;

        $id = (int) $this->app->session->get('user_id', 0);
        if ($id <= 0) {
            return null;
        }

        try {
            $user = $this->app->db->fetch(
                'SELECT id, username, email, display_name, role, status, avatar, bio, last_login_at
                 FROM users WHERE id = ? AND status = ? LIMIT 1',
                [$id, 'active']
            );
        } catch (\Throwable $e) {
            Logger::warning('Auth lookup failed: ' . $e->getMessage());

            return null;
        }

        if ($user === null) {
            $this->app->session->remove('user_id');

            return null;
        }

        $this->user = $user;

        return $this->user;
    }

    public function id(): int
    {
        return (int) ($this->user()['id'] ?? 0);
    }

    public function isAdmin(): bool
    {
        return ($this->user()['role'] ?? '') === 'admin';
    }

    public function attempt(string $login, string $password): bool
    {
        $login = trim($login);
        if ($login === '' || $password === '') {
            return false;
        }

        $user = $this->app->db->fetch(
            'SELECT * FROM users WHERE (email = ? OR username = ?) LIMIT 1',
            [$login, $login]
        );
        if ($user === null || ($user['status'] ?? '') !== 'active') {
            return false;
        }
        if (!Security::verifyPassword($password, (string) $user['password_hash'])) {
            return false;
        }

        if (password_needs_rehash((string) $user['password_hash'], PASSWORD_DEFAULT)) {
            $this->app->db->update('users', ['password_hash' => Security::hashPassword($password)], ['id' => (int) $user['id']]);
        }

        $this->app->session->regenerate();
        $this->app->session->set('user_id', (int) $user['id']);
        $this->app->db->update(
            'users',
            ['last_login_at' => date('Y-m-d H:i:s'), 'last_login_ip' => $this->app->request->ip()],
            ['id' => (int) $user['id']]
        );
        $this->user = $user;
        $this->loaded = true;

        return true;
    }

    public function logout(): void
    {
        $this->app->session->remove('user_id');
        $this->app->session->regenerate();
        $this->user = null;
        $this->loaded = true;
    }

    public function tooManyAttempts(string $login): bool
    {
        $since = date('Y-m-d H:i:s', time() - 900);
        $ip = $this->app->request->ip();
        $count = $this->app->db->count(
            'SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND login = ? AND attempted_at > ?',
            [$ip, mb_substr($login, 0, 190, 'UTF-8'), $since]
        );
        if ($count >= 5) {
            return true;
        }

        // A single IP may not spray attempts across many different accounts.
        $ipCount = $this->app->db->count(
            'SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND attempted_at > ?',
            [$ip, $since]
        );

        return $ipCount >= 20;
    }

    public function recordFailure(string $login): void
    {
        $this->app->db->insert('login_attempts', [
            'ip'         => $this->app->request->ip(),
            'login'      => mb_substr($login, 0, 190, 'UTF-8'),
            'attempted_at' => date('Y-m-d H:i:s'),
        ]);
        $this->app->db->query(
            'DELETE FROM login_attempts WHERE attempted_at < ?',
            [date('Y-m-d H:i:s', time() - 86400)]
        );
    }

    public function clearFailures(string $login): void
    {
        $this->app->db->query(
            'DELETE FROM login_attempts WHERE ip = ? AND login = ?',
            [$this->app->request->ip(), mb_substr($login, 0, 190, 'UTF-8')]
        );
    }
}