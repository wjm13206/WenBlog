<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Logger;
use App\Core\Response;
use App\Core\Updater;
use Throwable;

final class UpdateController extends AdminController
{
    public function index(): void
    {
        $updater = new Updater($this->app);

        $this->render('update/index', [
            'currentVersion' => $updater->currentVersion(),
            'lastCheck'      => $updater->lastCheck(),
            'history'        => $updater->history(),
            'repository'     => $updater->repository(),
            'manifest'       => $updater->manifestUrl(),
            'writable'       => $updater->writableStatus(),
            'zipSupported'   => $updater->zipSupported(),
            'phpVersion'     => PHP_VERSION,
        ], '系统更新');
    }

    public function check(): void
    {
        $updater = new Updater($this->app);
        $result = $updater->check();
        $updater->saveCheck($result);

        if ($result['error'] !== '') {
            $this->error($result['message']);
        } elseif ($result['available']) {
            $this->success('发现新版本 ' . $result['latest'] . '，可在线更新。');
        } else {
            $this->success((string) $result['message']);
        }
        Response::redirect(admin_url('update'));
    }

    public function apply(): void
    {
        @set_time_limit(180);
        $updater = new Updater($this->app);

        try {
            $result = $updater->check();
            $updater->saveCheck($result);
            if ($result['error'] !== '') {
                throw new \RuntimeException((string) $result['error']);
            }
            if (!$result['available']) {
                throw new \RuntimeException($result['message'] !== '' ? $result['message'] : '当前没有可用更新。');
            }
            $download = (string) $result['download'];
            if ($download === '') {
                throw new \RuntimeException('更新源没有提供下载地址，请使用更新包手动升级。');
            }

            $file = $updater->download($download);
            try {
                $installed = $updater->applyFromZip($file, (string) $result['latest'], 'online');
            } finally {
                @unlink($file);
            }
            $this->success('已更新到 ' . $installed['version'] . '，共写入 ' . count($installed['files']) . ' 个文件。');
        } catch (Throwable $e) {
            Logger::error('Online update failed: ' . $e->getMessage());
            $this->error('更新失败：' . $e->getMessage() . ' 你仍可以下载更新包后手动上传。');
        }

        Response::redirect(admin_url('update'));
    }

    public function upload(): void
    {
        @set_time_limit(180);
        $updater = new Updater($this->app);
        $file = $this->app->request->file('file');

        try {
            if (!is_array($file)) {
                throw new \RuntimeException('请选择更新包 ZIP 文件。');
            }
            $version = $this->stringInput('version');
            $installed = $updater->installUpload($file, $version);
            $this->success('更新包安装完成，当前版本 ' . $installed['version'] . '。');
        } catch (Throwable $e) {
            Logger::error('Uploaded update failed: ' . $e->getMessage());
            $this->error('更新失败：' . $e->getMessage());
        }

        Response::redirect(admin_url('update'));
    }

    public function source(): void
    {
        $updater = new Updater($this->app);
        try {
            $updater->saveSource(
                $this->stringInput('update_repository'),
                $this->stringInput('update_manifest')
            );
            $this->success('更新源已保存。');
        } catch (Throwable $e) {
            $this->error('保存失败：' . $e->getMessage());
        }
        Response::redirect(admin_url('update'));
    }
}
