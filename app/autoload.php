<?php

declare(strict_types=1);

/**
 * Minimal PSR-4 autoloader for the App namespace.
 *
 * The project deliberately avoids Composer at runtime. Every class lives under
 * app/ and maps one-to-one to its namespace.
 */
spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    if ($relative === '' || str_contains($relative, '..')) {
        return;
    }

    $file = BASE_PATH . '/app/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require $file;
    }
});