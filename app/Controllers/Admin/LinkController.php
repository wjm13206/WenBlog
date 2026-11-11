<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Response;
use App\Models\Link;

final class LinkController extends AdminController
{
    public function index(): void
    {
        $links = new Link($this->app);
        $editing = null;
        $editId = (int) ($this->app->request->query('edit') ?? 0);
        if ($editId > 0) {
            $editing = $links->find($editId);
        }
        $this->render('links/index', [
            'links'   => $links->all(),
            'editing' => $editing,
        ], '友情链接');
    }

    public function store(): void
    {
        $data = $this->payload();
        $error = $this->validate($data);
        if ($error !== null) {
            $this->error($error);
            Response::redirect(admin_url('links'));
        }
        (new Link($this->app))->create($data['name'], $data['url'], $data['description'], $data['sort_order']);
        $this->success('链接已添加。');
        Response::redirect(admin_url('links'));
    }

    public function update(string $id): void
    {
        $links = new Link($this->app);
        if ($links->find((int) $id) === null) {
            Response::error(404, '链接不存在。', true);
        }
        $data = $this->payload();
        $error = $this->validate($data);
        if ($error !== null) {
            $this->error($error);
            Response::redirect(admin_url('links?edit=' . $id));
        }
        $links->update((int) $id, $data['name'], $data['url'], $data['description'], $data['sort_order']);
        $this->success('链接已更新。');
        Response::redirect(admin_url('links'));
    }

    public function delete(string $id): void
    {
        $links = new Link($this->app);
        if ($links->find((int) $id) === null) {
            Response::error(404, '链接不存在。', true);
        }
        $links->delete((int) $id);
        $this->success('链接已删除。');
        Response::redirect(admin_url('links'));
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return [
            'name'        => $this->stringInput('name'),
            'url'         => $this->stringInput('url'),
            'description' => $this->stringInput('description'),
            'sort_order'  => (int) $this->stringInput('sort_order', '0'),
        ];
    }

    /** @param array<string, mixed> $data */
    private function validate(array $data): ?string
    {
        if ($data['name'] === '') {
            return '请填写链接名称。';
        }
        if (!\App\Core\Security::isSafeUrl((string) $data['url']) || !preg_match('#^https?://#i', (string) $data['url'])) {
            return '请填写有效的 http/https 链接。';
        }

        return null;
    }
}