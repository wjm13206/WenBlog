<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Response;
use App\Models\Tag;

final class TagController extends AdminController
{
    public function index(): void
    {
        $tags = new Tag($this->app);
        $editing = null;
        $editId = (int) ($this->app->request->query('edit') ?? 0);
        if ($editId > 0) {
            $editing = $tags->find($editId);
        }
        $this->render('tags/index', [
            'tags'    => $tags->allWithCounts(),
            'editing' => $editing,
        ], '标签');
    }

    public function store(): void
    {
        $name = $this->stringInput('name');
        if ($name === '' || mb_strlen($name, 'UTF-8') > 100) {
            $this->error('请填写标签名称（最多 100 个字符）。');
            Response::redirect(admin_url('tags'));
        }
        try {
            (new Tag($this->app))->create($name, $this->stringInput('slug'));
            $this->success('标签已创建。');
        } catch (\Throwable $e) {
            $this->error('标签创建失败：' . $e->getMessage());
        }
        Response::redirect(admin_url('tags'));
    }

    public function update(string $id): void
    {
        $tag = (new Tag($this->app))->find((int) $id);
        if ($tag === null) {
            Response::error(404, '标签不存在。', true);
        }
        $name = $this->stringInput('name');
        if ($name === '') {
            $this->error('标签名称不能为空。');
            Response::redirect(admin_url('tags?edit=' . $id));
        }
        try {
            (new Tag($this->app))->update((int) $id, $name, $this->stringInput('slug'));
            $this->success('标签已更新。');
        } catch (\Throwable $e) {
            $this->error('标签更新失败：' . $e->getMessage());
        }
        Response::redirect(admin_url('tags'));
    }

    public function delete(string $id): void
    {
        $tag = (new Tag($this->app))->find((int) $id);
        if ($tag === null) {
            Response::error(404, '标签不存在。', true);
        }
        (new Tag($this->app))->delete((int) $id);
        $this->success('标签已删除。');
        Response::redirect(admin_url('tags'));
    }
}