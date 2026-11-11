<?php

declare(strict_types=1);

namespace WenBlog\Plugins\TypechoMigrate;

use App\Core\App;
use App\Core\Logger;
use App\Core\Response;
use Throwable;

/**
 * Admin screen for the Typecho importer.
 */
final class Controller
{
    public function __construct(private readonly App $app)
    {
    }

    public function index(): void
    {
        $prefix = $this->prefixInput();
        $error = '';
        $detection = null;

        try {
            $detection = (new Migrator($this->app, $prefix))->detect();
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }

        $users = $this->app->db->fetchAll(
            "SELECT id, username, display_name, role FROM users WHERE status = 'active'
             ORDER BY (role = 'admin') DESC, id ASC"
        );
        $current = $this->app->auth->user();

        $content = $this->app->view->renderFile(__DIR__ . '/../views/index.php', [
            'detection'     => $detection,
            'error'         => $error,
            'users'         => $users,
            'currentUserId' => (int) ($current['id'] ?? 0),
            'prefixInput'   => $prefix,
        ]);

        echo $this->app->view->render('layouts/admin', [
            'title'   => 'Typecho 数据迁移',
            'content' => $content,
        ]);
    }

    public function run(): void
    {
        $prefix = $this->prefixInput();
        $options = [
            'import_pages'    => $this->checkbox('import_pages'),
            'import_drafts'   => $this->checkbox('import_drafts'),
            'import_comments' => $this->checkbox('import_comments'),
            'default_author'  => (int) $this->post('default_author'),
        ];

        @set_time_limit(300);

        try {
            $stats = (new Migrator($this->app, $prefix))->run($options);
            if ($prefix !== '') {
                $config = $this->app->plugins->config('typecho-migrate');
                $config['table_prefix'] = $prefix;
                $this->app->plugins->saveConfig('typecho-migrate', $config);
            }
            $this->app->session->flash('success', $this->summary($stats));
        } catch (Throwable $e) {
            Logger::error('Typecho migration failed', [
                'error'  => $e->getMessage(),
                'prefix' => $prefix,
            ]);
            $this->app->session->flash('error', '迁移失败：' . $e->getMessage());
        }

        $target = admin_url('plugins/typecho-migrate');
        if ($prefix !== '') {
            $target .= '?prefix=' . rawurlencode($prefix);
        }
        Response::redirect($target);
    }

    /** @param array<string, int> $stats */
    private function summary(array $stats): string
    {
        $parts = [
            '文章 ' . $stats['posts'] . ' 篇',
            '页面 ' . $stats['pages'] . ' 个',
            '草稿 ' . $stats['drafts'] . ' 个',
            '分类 ' . $stats['categories'] . ' 个',
            '标签 ' . $stats['tags'] . ' 个',
            '评论 ' . $stats['comments'] . ' 条',
        ];
        $message = '迁移完成：' . implode('、', $parts) . '。';
        $skipped = $stats['skipped_contents'] + $stats['skipped_comments'] + $stats['skipped_metas'];
        if ($skipped > 0) {
            $message .= ' 已跳过 ' . $skipped . ' 条已迁移或不在导入范围内的记录。';
        }

        return $message;
    }

    private function prefixInput(): string
    {
        $posted = $this->app->request->post('prefix');
        if (is_string($posted)) {
            return trim($posted);
        }
        $queryPrefix = $this->app->request->query('prefix');
        if (is_string($queryPrefix)) {
            return trim($queryPrefix);
        }
        $config = $this->app->plugins->config('typecho-migrate');

        return trim((string) ($config['table_prefix'] ?? ''));
    }

    private function post(string $key): string
    {
        $value = $this->app->request->post($key);

        return is_string($value) ? trim($value) : '';
    }

    private function checkbox(string $key): bool
    {
        return $this->post($key) === '1';
    }
}
