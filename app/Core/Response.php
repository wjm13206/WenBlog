<?php

declare(strict_types=1);

namespace App\Core;

final class Response
{
    public static function redirect(string $url, int $status = 302): never
    {
        if (!preg_match('#^https?://#i', $url)) {
            $url = url($url);
        }
        $url = str_replace(["\r", "\n"], '', $url);
        if (!headers_sent()) {
            header('Location: ' . $url, true, $status);
        }
        exit;
    }

    public static function json(mixed $data, int $status = 200): never
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=UTF-8');
            header('X-Content-Type-Options: nosniff');
        }
        echo json_encode(
            $data,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
        );
        exit;
    }

    public static function text(string $text, int $status = 200): never
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: text/plain; charset=UTF-8');
        }
        echo $text;
        exit;
    }

    public static function html(string $html, int $status = 200): never
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: text/html; charset=UTF-8');
        }
        echo $html;
        exit;
    }

    public static function error(int $status, string $message = '', bool $admin = false): never
    {
        $titles = [
            400 => '请求有误',
            401 => '需要登录',
            403 => '没有访问权限',
            404 => '页面不存在',
            419 => '页面已过期',
            429 => '请求过于频繁',
            500 => '服务器开小差了',
            503 => '站点暂时不可用',
        ];
        $title = $titles[$status] ?? '出错了';
        $message = $message !== '' ? $message : $title;
        $home = url('/');
        $adminHome = $admin ? '<a class="button" href="' . e(admin_url()) . '">返回控制台</a>' : '';
        $statusCode = $status;

        $html = '<!doctype html><html lang="zh-CN"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>' . $statusCode . ' · ' . e($title) . '</title>'
            . '<style>'
            . ':root{color-scheme:light dark;--bg:#f6f7fb;--card:#fff;--text:#171923;--muted:#6b7280;--accent:#4f46e5;--border:#e6e8ef}'
            . '@media(prefers-color-scheme:dark){:root{--bg:#101218;--card:#171a22;--text:#eef0f6;--muted:#a1a7b5;--border:#282c37}}'
            . '*{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;background:var(--bg);color:var(--text);font:15px/1.7 system-ui,-apple-system,"Segoe UI",sans-serif;padding:32px}'
            . '.error-card{width:min(520px,100%);background:var(--card);border:1px solid var(--border);border-radius:20px;padding:42px;box-shadow:0 24px 60px rgba(15,23,42,.08)}'
            . '.code{font-size:13px;font-weight:700;letter-spacing:.16em;color:var(--accent);text-transform:uppercase}'
            . 'h1{font-size:28px;line-height:1.25;margin:12px 0}p{color:var(--muted);margin:0 0 24px}'
            . '.actions{display:flex;gap:10px;flex-wrap:wrap}.button{display:inline-flex;padding:10px 16px;border-radius:10px;background:var(--accent);color:#fff;text-decoration:none;font-weight:600}'
            . 'a.ghost{background:transparent;color:var(--text);border:1px solid var(--border)}'
            . '</style></head><body><main class="error-card"><div class="code">ERROR ' . $statusCode . '</div>'
            . '<h1>' . e($title) . '</h1><p>' . e($message) . '</p><div class="actions">'
            . '<a class="button" href="' . e($home) . '">回到首页</a>' . $adminHome
            . '</div></main></body></html>';
        self::html($html, $status);
    }
}