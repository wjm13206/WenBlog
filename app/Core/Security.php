<?php

declare(strict_types=1);

namespace App\Core;

final class Security
{
    public function __construct(private readonly Session $session)
    {
    }

    public function csrfToken(): string
    {
        $token = $this->session->get('_csrf');
        if (!is_string($token) || strlen($token) < 32) {
            $token = bin2hex(random_bytes(32));
            $this->session->set('_csrf', $token);
        }

        return $token;
    }

    public function verifyCsrf(?string $token): bool
    {
        $expected = $this->session->get('_csrf');
        if (!is_string($expected) || $expected === '' || $token === null || $token === '') {
            return false;
        }

        return hash_equals($expected, $token);
    }

    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    public static function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    public static function randomToken(int $bytes = 32): string
    {
        return bin2hex(random_bytes($bytes));
    }

    public static function safeFilename(string $filename): string
    {
        $filename = basename(str_replace('\\', '/', $filename));
        $filename = preg_replace('/[^A-Za-z0-9._-]+/', '-', $filename) ?? 'file';
        $filename = trim($filename, '.-');

        return $filename === '' ? 'file' : $filename;
    }

    public static function isSafeUrl(string $url): bool
    {
        $url = trim($url);
        if ($url === '') {
            return false;
        }
        if (str_starts_with($url, '/') || str_starts_with($url, '#') || str_starts_with($url, '?')) {
            return true;
        }
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https', 'mailto'], true);
    }

    public static function validTimezone(string $timezone): bool
    {
        return in_array($timezone, timezone_identifiers_list(), true);
    }

    public static function safeReferer(string $fallback = '/'): string
    {
        $referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
        $host = parse_url($referer, PHP_URL_HOST);
        $ownHost = parse_url(url('/'), PHP_URL_HOST);
        if ($referer !== '' && $host !== null && $host === $ownHost) {
            return $referer;
        }

        return $fallback;
    }
}