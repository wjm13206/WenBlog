<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Response;

final class ThemeController extends AdminController
{
    public function index(): void
    {
        $themes = $this->app->theme->all();
        foreach ($themes as &$theme) {
            $theme['config_count'] = count($this->app->theme->configFields($theme['slug']));
        }
        unset($theme);

        $this->render('themes/index', [
            'themes' => $themes,
        ], '主题');
    }

    /**
     * Dedicated settings screen for the active theme, laid out like Typecho's
     * "主题设置" page: grouped sections with a Chinese label and a short
     * explanation for every option.
     */
    public function settings(): void
    {
        $slug = $this->app->theme->active();
        $theme = $this->app->theme->metadata($slug);
        $theme['active'] = true;
        $theme['slug'] = $slug;

        $fields = $this->app->theme->configFields($slug);
        $saved = $this->app->theme->savedConfig();
        $values = [];
        foreach ($fields as $field) {
            $key = (string) $field['key'];
            $values[$key] = $saved[$key] ?? $field['default'];
        }

        $this->render('themes/settings', [
            'theme'      => $theme,
            'groups'     => $this->app->theme->configGroups($slug),
            'values'     => $values,
            'fieldCount' => count($fields),
        ], '主题设置');
    }

    public function activate(): void
    {
        $slug = $this->stringInput('slug');
        try {
            $this->app->theme->activate($slug);
            $this->success('主题已切换为「' . $slug . '」。');
        } catch (\Throwable $e) {
            $this->error('主题切换失败：' . $e->getMessage());
        }
        Response::redirect(admin_url('themes'));
    }

    public function config(): void
    {
        $wantsJson = $this->app->request->wantsJson()
            || (string) $this->app->request->query('format', '') === 'json';

        $slug = $this->stringInput('slug');
        if ($slug === '' || $slug !== $this->app->theme->active()) {
            if ($wantsJson) {
                Response::json(['ok' => false, 'message' => '只能配置当前启用的主题。'], 422);
            }
            $this->error('只能配置当前启用的主题。');
            Response::redirect(admin_url('themes'));
        }

        $input = $this->app->request->post('config');
        $input = is_array($input) ? $input : [];
        try {
            $saved = $this->app->theme->saveConfigValues($input);
        } catch (\Throwable $e) {
            if ($wantsJson) {
                Response::json(['ok' => false, 'message' => '主题配置保存失败：' . $e->getMessage()], 500);
            }
            $this->error('主题配置保存失败：' . $e->getMessage());
            Response::redirect(admin_url('themes/settings'));
        }

        if ($wantsJson) {
            Response::json([
                'ok'      => true,
                'message' => '主题配置已保存。',
                'config'  => $saved,
            ]);
        }

        $this->success('主题配置已保存。');
        Response::redirect(admin_url('themes/settings'));
    }

    public function upload(): void
    {
        $file = $this->app->request->file('file');
        if (!is_array($file)) {
            $this->error('请选择主题压缩包。');
            Response::redirect(admin_url('themes'));
        }
        try {
            $slug = $this->app->theme->installFromZip($file);
            $this->success('主题「' . $slug . '」已安装。');
        } catch (\Throwable $e) {
            $this->error('安装失败：' . $e->getMessage());
        }
        Response::redirect(admin_url('themes'));
    }

    public function delete(): void
    {
        $slug = $this->stringInput('slug');
        try {
            $this->app->theme->delete($slug);
            $this->success('主题已删除。');
        } catch (\Throwable $e) {
            $this->error('删除失败：' . $e->getMessage());
        }
        Response::redirect(admin_url('themes'));
    }
}