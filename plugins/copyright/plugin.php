<?php
/**
 * Copyright notice plugin.
 *
 * Filtering rendered HTML (rather than Markdown source) keeps the notice out
 * of the Markdown parser and makes it easy for themes to opt in.
 */

declare(strict_types=1);

use App\Core\Hook;

Hook::add('theme.head', static function (): void {
    ?>
    <style>
        .plugin-copyright{margin:2.2em 0 0;padding:18px 20px;border:1px solid var(--border,#e6e5df);border-left:3px solid var(--accent,#4f46e5);border-radius:8px;background:var(--surface-2,#f3f3ef);font-size:13.5px;line-height:1.7;color:var(--muted,#6f6f68)}
        .plugin-copyright strong{display:block;margin-bottom:4px;color:var(--text,#1c1c1a);font-size:12px;letter-spacing:.08em;text-transform:uppercase}
        .plugin-copyright p{margin:0;color:inherit;font-size:inherit}
        .plugin-copyright p + p{margin-top:6px}
        .plugin-copyright a{color:var(--accent-ink,#4338ca);text-decoration:underline;text-underline-offset:2px}
    </style>
    <?php
});

Hook::add('post.html', static function (string $html, array $post): string {
    $app = app();
    if ($app === null) {
        return $html;
    }
    $config = $app->plugins->config('copyright');
    $notice = trim((string) ($config['notice'] ?? ''));
    $showSource = (string) ($config['show_source'] ?? '1') === '1';
    if ($notice === '') {
        return $html;
    }
    $source = '';
    if ($showSource) {
        $source = '<p>原文：<a href="' . e(post_url($post)) . '" rel="bookmark">'
            . e((string) ($post['title'] ?? '')) . '</a></p>';
    }

    return $html . '<aside class="plugin-copyright"><strong>版权声明</strong><p>'
        . nl2br(e($notice)) . '</p>' . $source . '</aside>';
}, 20);