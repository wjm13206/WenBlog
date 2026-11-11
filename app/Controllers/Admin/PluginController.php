<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Response;

final class PluginController extends AdminController
{
    public function index(): void
    {
        $this->render('plugins/index', [
            'plugins' => $this->app->plugins->all(),
        ], '插件');
    }

    public function toggle(): void
    {
        $slug = $this->stringInput('slug');
        $enable = $this->stringInput('enabled') === '1';
        try {
            $this->app->plugins->setActive($slug, $enable);
            $this->app->cache->clear();
            $this->success($enable ? '插件已启用。' : '插件已禁用。');
        } catch (\Throwable $e) {
            $this->error('操作失败：' . $e->getMessage());
        }
        Response::redirect(admin_url('plugins'));
    }

    public function config(): void
    {
        $slug = $this->stringInput('slug');
        $meta = $this->app->plugins->metadata($slug);
        if ($meta === null) {
            $this->error('插件不存在。');
            Response::redirect(admin_url('plugins'));
        }
        $fields = is_array($meta['config'] ?? null) ? $meta['config'] : [];
        $input = $this->app->request->post('config');
        $input = is_array($input) ? $input : [];
        $saved = $this->app->plugins->config($slug);
        foreach ($fields as $key => $field) {
            $type = is_array($field) ? (string) ($field['type'] ?? 'text') : 'text';
            if ($type === 'checkbox') {
                $saved[$key] = !empty($input[$key]) ? '1' : '0';
            } elseif (array_key_exists($key, $input)) {
                $saved[$key] = trim((string) $input[$key]);
            }
        }
        $this->app->plugins->saveConfig($slug, $saved);
        $this->success('插件配置已保存。');
        Response::redirect(admin_url('plugins'));
    }

    public function upload(): void
    {
        $file = $this->app->request->file('file');
        if (!is_array($file)) {
            $this->error('请选择插件压缩包。');
            Response::redirect(admin_url('plugins'));
        }
        try {
            $slug = $this->app->plugins->installFromZip($file);
            $this->success('插件「' . $slug . '」已安装，请手动启用。');
        } catch (\Throwable $e) {
            $this->error('安装失败：' . $e->getMessage());
        }
        Response::redirect(admin_url('plugins'));
    }

    public function delete(): void
    {
        $slug = $this->stringInput('slug');
        try {
            $this->app->plugins->setActive($slug, false);
            $this->app->plugins->delete($slug);
            $this->app->cache->clear();
            $this->success('插件已删除。');
        } catch (\Throwable $e) {
            $this->error('删除失败：' . $e->getMessage());
        }
        Response::redirect(admin_url('plugins'));
    }
}