<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Response;
use App\Models\Category;

final class CategoryController extends AdminController
{
    public function index(): void
    {
        $categories = new Category($this->app);
        $editing = null;
        $editId = (int) ($this->app->request->query('edit') ?? 0);
        if ($editId > 0) {
            $editing = $categories->find($editId);
        }
        $this->render('categories/index', [
            'categories' => $categories->allWithCounts(),
            'editing'    => $editing,
        ], '分类');
    }

    public function store(): void
    {
        $name = $this->stringInput('name');
        if ($name === '' || mb_strlen($name, 'UTF-8') > 100) {
            $this->error('请填写分类名称（最多 100 个字符）。');
            $this->app->session->flashInput(['name' => $name, 'slug' => $this->stringInput('slug'), 'description' => $this->stringInput('description')]);
            Response::redirect(admin_url('categories'));
        }
        $parentId = (int) $this->stringInput('parent_id');
        (new Category($this->app))->create(
            $name,
            $this->stringInput('slug'),
            $parentId > 0 ? $parentId : null,
            $this->stringInput('description'),
            (int) $this->stringInput('sort_order', '0')
        );
        $this->success('分类已创建。');
        Response::redirect(admin_url('categories'));
    }

    public function update(string $id): void
    {
        $category = (new Category($this->app))->find((int) $id);
        if ($category === null) {
            Response::error(404, '分类不存在。', true);
        }
        $name = $this->stringInput('name');
        if ($name === '') {
            $this->error('分类名称不能为空。');
            Response::redirect(admin_url('categories?edit=' . $id));
        }
        $parentId = (int) $this->stringInput('parent_id');
        (new Category($this->app))->update(
            (int) $id,
            $name,
            $this->stringInput('slug'),
            $parentId > 0 ? $parentId : null,
            $this->stringInput('description'),
            (int) $this->stringInput('sort_order', '0')
        );
        $this->success('分类已更新。');
        Response::redirect(admin_url('categories'));
    }

    public function delete(string $id): void
    {
        $category = (new Category($this->app))->find((int) $id);
        if ($category === null) {
            Response::error(404, '分类不存在。', true);
        }
        (new Category($this->app))->delete((int) $id);
        $this->success('分类已删除，子分类已提升到上一级。');
        Response::redirect(admin_url('categories'));
    }
}