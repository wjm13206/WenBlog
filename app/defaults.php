<?php

declare(strict_types=1);

/**
 * Default site options. Values are used when an option is missing from the
 * database and are seeded by the installer.
 */
return [
    'site_name'            => 'WenBlog',
    'site_description'     => '一个轻量、快速、专注写作的个人博客',
    'site_url'             => '',
    'site_logo'            => '',
    'site_favicon'         => '',
    'admin_email'          => '',
    'timezone'             => 'Asia/Shanghai',
    'date_format'          => 'Y年n月j日',
    'posts_per_page'       => '8',
    'excerpt_length'       => '160',
    'permalink_style'      => 'post',
    'comments_enabled'     => '1',
    'comments_moderation'  => '1',
    'comments_per_page'    => '20',
    'comments_min_seconds' => '3',
    'comments_blocked_words' => '',
    'comments_require_email' => '1',
    'upload_max_size'      => '5',
    'upload_extensions'    => 'jpg,jpeg,png,gif,webp,pdf',
    'cache_enabled'        => '0',
    'cache_ttl'            => '300',
    'seo_keywords'         => '',
    'seo_description'      => '',
    'seo_title_suffix'     => '',
    'api_enabled'          => '0',
    'api_key'              => '',
    'footer_text'          => '© {year} {site_name}. 保留所有权利。',
    'active_theme'         => 'default',
    'maintenance_mode'     => '0',
    'app_version'          => '1.0.0',
];