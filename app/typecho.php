<?php

/**
 * Typecho compatibility bootstrap.
 *
 * Loaded by App\Core\Theme::boot() before a Typecho theme's functions.php when
 * the theme relies on `__TYPECHO_ROOT_DIR__`. It defines the handful of
 * constants, global helpers and stub classes that native Typecho themes expect.
 */

if (!defined('__TYPECHO_ROOT_DIR__')) {
    define('__TYPECHO_ROOT_DIR__', BASE_PATH);
}
if (!defined('__TYPECHO_THEME_DIR__')) {
    define('__TYPECHO_THEME_DIR__', BASE_PATH . '/themes');
}
if (!defined('__TYPECHO_PLUGIN_DIR__')) {
    define('__TYPECHO_PLUGIN_DIR__', BASE_PATH . '/plugins');
}
if (!defined('__TYPECHO_ADMIN_DIR__')) {
    define('__TYPECHO_ADMIN_DIR__', '/admin/');
}
if (!defined('__TYPECHO_VERSION__')) {
    define('__TYPECHO_VERSION__', '1.2.1');
}

if (!function_exists('_t')) {
    /**
     * Typecho's translation helper. WenBlog is single-language, so the source
     * string is returned unchanged; sprintf-style placeholders still work.
     */
    function _t(string $string, mixed ...$arguments): string
    {
        if ($arguments === []) {
            return $string;
        }

        try {
            return vsprintf($string, $arguments);
        } catch (\Throwable) {
            return $string;
        }
    }
}

if (!function_exists('_e')) {
    function _e(string $string, mixed ...$arguments): void
    {
        echo _t($string, ...$arguments);
    }
}

if (!class_exists('Helper', false)) {
    /**
     * `Helper::options()` is how Typecho themes reach the option bag.
     */
    final class Helper
    {
        public static function options(): \App\Core\ThemeOptions
        {
            return \App\Core\TypechoCompat::options();
        }
    }
}

if (!class_exists('Typecho_Widget', false)) {
    final class Typecho_Widget
    {
        public static function widget(string $alias, mixed $params = null): object
        {
            return \App\Core\TypechoCompat::widget($alias, $params);
        }
    }
}

if (!class_exists('Typecho_Db', false)) {
    final class Typecho_Db
    {
        public static function get(): \App\Core\TypechoCompatDb
        {
            return \App\Core\TypechoCompat::db();
        }
    }
}