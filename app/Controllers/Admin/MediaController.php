<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Paginator;
use App\Core\Response;
use App\Models\Media;

final class MediaController extends AdminController
{
    public function index(): void
    {
        $page = $this->pageNumber();
        $perPage = 24;
        $media = new Media($this->app);
        $result = $media->paginate($page, $perPage);
        $pagination = new Paginator($result['total'], $perPage, $page, static fn (int $number): string => admin_url('media?page=' . $number));

        $this->render('media/index', [
            'media'      => $result['items'],
            'pagination' => $pagination,
            'total'      => $result['total'],
        ], '媒体库');
    }

    public function upload(): void
    {
        $file = $this->app->request->file('file');
        if (!is_array($file)) {
            $this->error('请选择要上传的文件。');
            Response::redirect(admin_url('media'));
        }
        try {
            (new Media($this->app))->upload($file, (int) $this->app->auth->id(), $this->stringInput('alt'));
            $this->success('文件已上传。');
        } catch (\Throwable $e) {
            $this->error('上传失败：' . $e->getMessage());
        }
        Response::redirect(admin_url('media'));
    }

    /**
     * JSON upload endpoint used by the editor for pasted and dropped images.
     */
    public function ajaxUpload(): void
    {
        $file = $this->app->request->file('file');
        if (!is_array($file)) {
            Response::json(['error' => '请选择要上传的文件。'], 422);
        }

        try {
            $record = (new Media($this->app))->upload($file, (int) $this->app->auth->id(), $this->stringInput('alt'));
        } catch (\Throwable $e) {
            Response::json(['error' => $e->getMessage()], 422);
        }

        $alt = trim((string) ($record['alt'] ?? ''));
        if ($alt === '') {
            $alt = pathinfo((string) ($record['original_name'] ?? 'image'), PATHINFO_FILENAME);
        }
        $url = upload_url((string) $record['path']);

        Response::json([
            'id'       => (int) $record['id'],
            'url'      => $url,
            'path'     => (string) $record['path'],
            'alt'      => $alt,
            'width'    => $record['width'] !== null ? (int) $record['width'] : null,
            'height'   => $record['height'] !== null ? (int) $record['height'] : null,
            'markdown' => '![' . $alt . '](' . $url . ')',
        ]);
    }

    public function updateAlt(string $id): void
    {
        $media = new Media($this->app);
        $item = $media->find((int) $id);
        if ($item === null) {
            Response::error(404, '文件不存在。', true);
        }
        $media->updateAlt((int) $id, $this->stringInput('alt'));
        $this->success('替代文本已更新。');
        $this->back(admin_url('media'));
    }

    public function delete(string $id): void
    {
        $media = new Media($this->app);
        $item = $media->find((int) $id);
        if ($item === null) {
            Response::error(404, '文件不存在。', true);
        }
        $media->delete((int) $id);
        $this->success('文件已删除。');
        $this->back(admin_url('media'));
    }
}