<?php

declare(strict_types=1);

/**
 * Directory-index fallback for servers that execute install/index.php instead
 * of rewriting /install to the front controller. Re-point the script name at
 * the root controller so Request can resolve the /install route.
 */
$script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/install/index.php'));
$base = str_ends_with($script, '/install/index.php')
    ? substr($script, 0, -strlen('/install/index.php'))
    : '';

$_SERVER['SCRIPT_NAME'] = ($base === '' ? '' : $base) . '/index.php';
$_SERVER['SCRIPT_FILENAME'] = dirname(__DIR__) . '/index.php';

require dirname(__DIR__) . '/index.php';