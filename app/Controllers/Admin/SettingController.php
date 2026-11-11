<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Response;
use App\Core\Security;
use App\Core\Str;

final class SettingController extends AdminController
{
    public function index(): void
    {
        $this->render('settings/index', [
            'options' => $this->app->options->all(),
        ], '系统设置');
    }

    public function clearCache(): void
    {
        $this->app->cache->clear();
        $this->success('页面缓存已清空。');
        $this->back(admin_url('settings'));
    }

    public function rebuildSearchIndex(): void
    {
        try {
            $message = (new \App\Models\Post($this->app))->rebuildSearchIndex();
            $this->success($message);
        } catch (\Throwable $e) {
            $this->error('重建索引失败：' . $e->getMessage());
        }
        $this->back(admin_url('settings'));
    }

    public function save(): void
    {
        $post = $this->app->request->all();
        $errors = [];

        $siteName = trim((string) ($post['site_name'] ?? ''));
        if ($siteName === '') {
            $errors['site_name'] = '网站名称不能为空。';
        }
        $siteUrl = rtrim(trim((string) ($post['site_url'] ?? '')), '/');
        if ($siteUrl !== '' && (!Security::isSafeUrl($siteUrl) || !preg_match('#^https?://#i', $siteUrl))) {
            $errors['site_url'] = '网站地址必须是有效的 http/https URL。';
        }
        $adminEmail = trim((string) ($post['admin_email'] ?? ''));
        if ($adminEmail !== '' && filter_var($adminEmail, FILTER_VALIDATE_EMAIL) === false) {
            $errors['admin_email'] = '管理员邮箱格式不正确。';
        }
        $timezone = (string) ($post['timezone'] ?? '');
        if (!Security::validTimezone($timezone)) {
            $errors['timezone'] = '请选择有效的时区。';
        }
        $dateFormat = trim((string) ($post['date_format'] ?? ''));
        if ($dateFormat === '' || mb_strlen($dateFormat, 'UTF-8') > 40) {
            $errors['date_format'] = '日期格式不能为空，且最多 40 个字符。';
        }
        $permalink = (string) ($post['permalink_style'] ?? 'post');
        if (!in_array($permalink, ['post', 'slug'], true)) {
            $errors['permalink_style'] = '无效的固定链接风格。';
        }
        $postsPerPage = (int) ($post['posts_per_page'] ?? 8);
        if ($postsPerPage < 1 || $postsPerPage > 100) {
            $errors['posts_per_page'] = '每页文章数需在 1-100 之间。';
        }
        $excerptLength = (int) ($post['excerpt_length'] ?? 160);
        if ($excerptLength < 20 || $excerptLength > 1000) {
            $errors['excerpt_length'] = '摘要长度需在 20-1000 之间。';
        }
        $commentsPerPage = (int) ($post['comments_per_page'] ?? 20);
        if ($commentsPerPage < 1 || $commentsPerPage > 200) {
            $errors['comments_per_page'] = '每页评论数需在 1-200 之间。';
        }
        $commentsMinSeconds = (int) ($post['comments_min_seconds'] ?? 3);
        if ($commentsMinSeconds < 0 || $commentsMinSeconds > 120) {
            $errors['comments_min_seconds'] = '评论最短提交时间需在 0-120 秒之间。';
        }
        $uploadMax = (int) ($post['upload_max_size'] ?? 5);
        if ($uploadMax < 1 || $uploadMax > 1024) {
            $errors['upload_max_size'] = '上传大小限制需在 1-1024 MB 之间。';
        }
        $extensions = strtolower(str_replace([' ', '.'], '', (string) ($post['upload_extensions'] ?? '')));
        $extensionList = array_values(array_filter(array_unique(explode(',', $extensions)), static fn (string $ext): bool => (bool) preg_match('/^[a-z0-9]{1,10}$/', $ext)));
        if ($extensionList === []) {
            $errors['upload_extensions'] = '至少填写一个允许的上传扩展名。';
        }
        $cacheTtl = (int) ($post['cache_ttl'] ?? 300);
        if ($cacheTtl < 10 || $cacheTtl > 86400) {
            $errors['cache_ttl'] = '缓存时间需在 10-86400 秒之间。';
        }
        $apiEnabled = isset($post['api_enabled']) ? '1' : '0';
        $apiKey = trim((string) ($post['api_key'] ?? ''));
        if ($apiEnabled === '1' && $apiKey === '') {
            $apiKey = Str::random(40);
        }
        if ($apiKey !== '' && !preg_match('/^[A-Za-z0-9_\-]{16,80}$/', $apiKey)) {
            $errors['api_key'] = 'API 密钥需为 16-80 位字母、数字、短横线或下划线。';
        }
        $logo = trim((string) ($post['site_logo'] ?? ''));
        $favicon = trim((string) ($post['site_favicon'] ?? ''));
        foreach (['site_logo' => $logo, 'site_favicon' => $favicon] as $key => $value) {
            if ($value !== '' && !Security::isSafeUrl($value) && !str_starts_with($value, 'uploads/')) {
                $errors[$key] = '请填写有效的图片地址或 uploads/ 路径。';
            }
        }
        if ($permalink === 'slug') {
            $conflicts = $this->rootPermalinkConflicts();
            if ($conflicts !== []) {
                $errors['permalink_style'] = '切换到根目录固定链接前，请先处理这些冲突别名：'
                    . mb_substr(implode('、', $conflicts), 0, 120, 'UTF-8') . '。';
            }
        }

        if ($errors !== []) {
            $this->app->session->setErrors($errors);
            Response::redirect(admin_url('settings'));
        }

        $this->app->options->setMany([
            'site_name'               => $siteName,
            'site_description'        => trim((string) ($post['site_description'] ?? '')),
            'site_url'                => $siteUrl,
            'site_logo'               => $logo,
            'site_favicon'            => $favicon,
            'admin_email'             => $adminEmail,
            'timezone'                => $timezone,
            'date_format'             => $dateFormat,
            'posts_per_page'          => (string) $postsPerPage,
            'excerpt_length'          => (string) $excerptLength,
            'permalink_style'         => $permalink,
            'comments_enabled'        => isset($post['comments_enabled']) ? '1' : '0',
            'comments_moderation'     => isset($post['comments_moderation']) ? '1' : '0',
            'comments_require_email'  => isset($post['comments_require_email']) ? '1' : '0',
            'comments_per_page'       => (string) $commentsPerPage,
            'comments_min_seconds'    => (string) $commentsMinSeconds,
            'comments_blocked_words'  => trim((string) ($post['comments_blocked_words'] ?? '')),
            'upload_max_size'         => (string) $uploadMax,
            'upload_extensions'       => implode(',', $extensionList),
            'cache_enabled'           => isset($post['cache_enabled']) ? '1' : '0',
            'cache_ttl'               => (string) $cacheTtl,
            'seo_keywords'            => trim((string) ($post['seo_keywords'] ?? '')),
            'seo_description'         => trim((string) ($post['seo_description'] ?? '')),
            'seo_title_suffix'        => trim((string) ($post['seo_title_suffix'] ?? '')),
            'api_enabled'             => $apiEnabled,
            'api_key'                 => $apiKey,
            'footer_text'             => trim((string) ($post['footer_text'] ?? '')),
            'maintenance_mode'        => isset($post['maintenance_mode']) ? '1' : '0',
        ]);

        $this->app->cache->clear();
        $this->success('设置已保存。');
        Response::redirect(admin_url('settings'));
    }

    /**
     * A root-level permalink style only works when no post/page uses a
     * reserved prefix and no post and page share a slug.
     *
     * @return list<string>
     */
    private function rootPermalinkConflicts(): array
    {
        $reserved = [
            'admin', 'api', 'post', 'category', 'tag', 'page', 'search',
            'archives', 'install', 'rss', 'feed', 'uploads', 'assets',
            'themes', 'plugins', 'comments',
        ];
        $placeholders = implode(',', array_fill(0, count($reserved), '?'));
        $params = array_merge($reserved, $reserved);
        $conflicts = [];

        $reservedRows = $this->app->db->fetchAll(
            'SELECT slug FROM posts WHERE slug IN (' . $placeholders . ')
             UNION
             SELECT slug FROM pages WHERE slug IN (' . $placeholders . ')',
            $params
        );
        foreach ($reservedRows as $row) {
            $conflicts[] = (string) $row['slug'];
        }

        $duplicateRows = $this->app->db->fetchAll(
            'SELECT p.slug FROM posts p INNER JOIN pages pg ON pg.slug = p.slug LIMIT 10'
        );
        foreach ($duplicateRows as $row) {
            $conflicts[] = (string) $row['slug'];
        }

        return array_values(array_unique($conflicts));
    }
}