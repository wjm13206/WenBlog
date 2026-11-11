<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;
use Throwable;

/**
 * PHP view renderer with optional layout wrapping.
 */
final class View
{
    public function __construct(private readonly App $app)
    {
    }

    /** @param array<string, mixed> $data */
    public function render(string $view, array $data = [], ?string $layout = 'layouts/admin'): string
    {
        $viewFile = $this->viewPath($view);
        if (!is_file($viewFile)) {
            throw new RuntimeException('View not found: ' . $view);
        }

        $data['app'] = $this->app;
        $content = $this->capture($viewFile, $data);
        if ($layout === null || $layout === '') {
            return $content;
        }

        $layoutFile = $this->viewPath($layout);
        if (!is_file($layoutFile)) {
            throw new RuntimeException('Layout not found: ' . $layout);
        }
        $data['content'] = $content;

        return $this->capture($layoutFile, $data);
    }

    /** @param array<string, mixed> $data */
    public function capture(string $file, array $data = []): string
    {
        extract($data, EXTR_SKIP);
        ob_start();
        try {
            require $file;

            return (string) ob_get_clean();
        } catch (Throwable $e) {
            ob_end_clean();
            throw $e;
        }
    }

    public function errorPage(int $status, string $message = '', bool $admin = false): void
    {
        Response::error($status, $message, $admin);
    }

    private function viewPath(string $view): string
    {
        $view = str_replace(['..', '\\'], '', $view);
        $view = str_replace('.', '/', $view);

        return BASE_PATH . '/app/Views/' . $view . '.php';
    }
}