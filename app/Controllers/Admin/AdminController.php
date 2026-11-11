<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;

/**
 * Admin controllers share the admin layout and redirect helpers.
 */
abstract class AdminController extends Controller
{
    /** @param array<string, mixed> $data */
    protected function render(string $view, array $data = [], string $title = ''): void
    {
        if ($title !== '') {
            $data['title'] = $title;
        }
        echo $this->app->view->render('admin/' . $view, $data);
    }

    protected function success(string $message): void
    {
        $this->app->session->flash('success', $message);
    }

    protected function error(string $message): void
    {
        $this->app->session->flash('error', $message);
    }

    protected function back(string $fallback = '/admin'): never
    {
        $referer = $this->app->request->header('Referer');
        if ($referer !== '') {
            $parts = parse_url($referer);
            $host = strtolower((string) ($parts['host'] ?? ''));
            $requestHost = strtolower(explode(':', (string) $this->app->request->header('Host'))[0]);
            $path = (string) ($parts['path'] ?? '');
            $base = rtrim($this->app->request->basePath(), '/');
            if (($host === '' || $host === $requestHost) && str_starts_with($path, $base . '/admin')) {
                \App\Core\Response::redirect($referer);
            }
        }
        \App\Core\Response::redirect($fallback);
    }
}