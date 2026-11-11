<?php

declare(strict_types=1);

namespace App\Core;

final class Session
{
    private bool $started = false;
    /** @var array<string, string>|null */
    private ?array $errorsCache = null;
    /** @var array<string, mixed>|null */
    private ?array $oldCache = null;

    public function __construct(private readonly string $name = 'wenblog_session')
    {
    }

    public function start(bool $secure = false): void
    {
        if ($this->started || PHP_SAPI === 'cli' || session_status() === PHP_SESSION_ACTIVE) {
            $this->started = true;

            return;
        }

        @ini_set('session.use_strict_mode', '1');
        @ini_set('session.use_only_cookies', '1');
        session_name($this->name);
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        @session_start();
        $this->started = true;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public function pull(string $key, mixed $default = null): mixed
    {
        $value = $this->get($key, $default);
        $this->remove($key);

        return $value;
    }

    public function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            @session_regenerate_id(true);
        }
    }

    public function destroy(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires'  => time() - 42000,
                'path'     => $params['path'] ?? '/',
                'domain'   => $params['domain'] ?? '',
                'secure'   => (bool) ($params['secure'] ?? false),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            @session_destroy();
        }
    }

    public function flash(string $type, string $message): void
    {
        $_SESSION['_flash'][$type][] = $message;
    }

    /** @return array<string, list<string>> */
    public function flashes(): array
    {
        $flashes = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);

        return is_array($flashes) ? $flashes : [];
    }

    public function hasFlash(): bool
    {
        return !empty($_SESSION['_flash']);
    }

    /** @param array<string, mixed> $input */
    public function flashInput(array $input): void
    {
        $_SESSION['_old'] = $input;
        $this->oldCache = null;
    }

    /** @return array<string, mixed> */
    public function oldInput(): array
    {
        if ($this->oldCache === null) {
            $old = $_SESSION['_old'] ?? [];
            unset($_SESSION['_old']);
            $this->oldCache = is_array($old) ? $old : [];
        }

        return $this->oldCache;
    }

    /** @param array<string, string> $errors */
    public function setErrors(array $errors): void
    {
        $_SESSION['_errors'] = $errors;
        $this->errorsCache = null;
    }

    /** @return array<string, string> */
    public function errors(): array
    {
        if ($this->errorsCache === null) {
            $errors = $_SESSION['_errors'] ?? [];
            unset($_SESSION['_errors']);
            $this->errorsCache = is_array($errors) ? $errors : [];
        }

        return $this->errorsCache;
    }

    public function clearTransient(): void
    {
        unset($_SESSION['_old'], $_SESSION['_errors']);
        $this->oldCache = null;
        $this->errorsCache = null;
    }
}