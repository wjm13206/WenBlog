<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;

/**
 * Shared helpers for HTTP controllers.
 */
abstract class Controller
{
    public function __construct(protected readonly App $app)
    {
    }

    /** @param array<string, mixed> $data */
    protected function theme(string $template, array $data = []): void
    {
        echo $this->app->theme->render($template, $data);
    }

    protected function notFound(string $message = '你访问的页面不存在。'): never
    {
        http_response_code(404);
        try {
            echo $this->app->theme->render('404', ['pageTitle' => '页面不存在', 'noIndex' => true, 'message' => $message]);
        } catch (\Throwable) {
            \App\Core\Response::error(404, $message);
        }
        exit;
    }

    protected function pageNumber(string $key = 'page', int $max = 100000): int
    {
        $value = $this->app->request->query($key);
        $page = is_scalar($value) ? (int) $value : 1;

        return max(1, min($max, $page));
    }

    protected function stringInput(string $key, string $default = ''): string
    {
        $value = $this->app->request->post($key);

        return is_string($value) ? trim($value) : $default;
    }

    /** @return list<int> */
    protected function intList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('intval', $value), static fn (int $id): bool => $id > 0)));
    }

    /** @return list<string> */
    protected function stringList(mixed $value): array
    {
        if (is_string($value)) {
            $value = preg_split('/[,，\n]+/u', $value) ?: [];
        }
        if (!is_array($value)) {
            return [];
        }

        $items = [];
        foreach ($value as $item) {
            if (!is_scalar($item)) {
                continue;
            }
            $item = trim((string) $item);
            if ($item !== '' && !in_array($item, $items, true)) {
                $items[] = mb_substr($item, 0, 100, 'UTF-8');
            }
        }

        return $items;
    }
}