<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Paginator;
use App\Core\Response;
use App\Models\Page;

final class PageController extends AdminController
{
    public function index(): void
    {
        $page = $this->pageNumber();
        $perPage = 15;
        $result = (new Page($this->app))->adminPaginate($page, $perPage);
        $pagination = new Paginator($result['total'], $perPage, $page, static fn (int $number): string => admin_url('pages?page=' . $number));

        $this->render('pages/index', [
            'pages'      => $result['items'],
            'pagination' => $pagination,
        ], '页面');
    }

    public function create(): void
    {
        $this->form($this->emptyPage(), '新建页面');
    }

    public function store(): void
    {
        $data = $this->payload();
        $errors = $this->validate($data);
        if ($errors !== []) {
            $this->app->session->setErrors($errors);
            $this->app->session->flashInput($this->inputForForm());
            Response::redirect(admin_url('pages/create'));
        }
        try {
            $id = (new Page($this->app))->create($data);
            $this->success('页面已创建。');
            Response::redirect(admin_url('pages/' . $id . '/edit'));
        } catch (\Throwable $e) {
            $this->app->session->setErrors(['form' => $e->getMessage()]);
            $this->app->session->flashInput($this->inputForForm());
            Response::redirect(admin_url('pages/create'));
        }
    }

    public function edit(string $id): void
    {
        $page = (new Page($this->app))->find((int) $id);
        if ($page === null) {
            Response::error(404, '页面不存在。', true);
        }
        $this->form($page, '编辑页面');
    }

    public function update(string $id): void
    {
        $pages = new Page($this->app);
        $page = $pages->find((int) $id);
        if ($page === null) {
            Response::error(404, '页面不存在。', true);
        }
        $data = $this->payload();
        $data['author_id'] = (int) $page['author_id'];
        $errors = $this->validate($data);
        if ($errors !== []) {
            $this->app->session->setErrors($errors);
            $this->app->session->flashInput($this->inputForForm());
            Response::redirect(admin_url('pages/' . $id . '/edit'));
        }
        try {
            $pages->update((int) $id, $data);
            $this->success('页面已保存。');
        } catch (\Throwable $e) {
            $this->app->session->setErrors(['form' => $e->getMessage()]);
        }
        Response::redirect(admin_url('pages/' . $id . '/edit'));
    }

    public function delete(string $id): void
    {
        $page = (new Page($this->app))->find((int) $id);
        if ($page === null) {
            Response::error(404, '页面不存在。', true);
        }
        (new Page($this->app))->delete((int) $id);
        $this->success('页面已删除。');
        Response::redirect(admin_url('pages'));
    }

    /** @param array<string, mixed> $page */
    private function form(array $page, string $title): void
    {
        $this->render('pages/form', [
            'page'      => $page,
            'templates' => $this->app->theme->templates(),
        ], $title);
    }

    /** @return array<string, mixed> */
    private function emptyPage(): array
    {
        return [
            'id'      => null,
            'title'   => '',
            'slug'    => '',
            'excerpt' => '',
            'content' => '',
            'template' => '',
            'status'  => 'draft',
        ];
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        $status = $this->stringInput('status', 'draft');
        if (!in_array($status, ['draft', 'published'], true)) {
            $status = 'draft';
        }

        return [
            'title'      => $this->stringInput('title'),
            'slug'       => $this->stringInput('slug'),
            'excerpt'    => $this->stringInput('excerpt'),
            'content'    => (string) ($this->app->request->post('content') ?? ''),
            'template'   => $this->stringInput('template'),
            'status'     => $status,
            'author_id'  => $this->app->auth->id(),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
    }

    /** @param array<string, mixed> $data @return array<string, string> */
    private function validate(array $data): array
    {
        $errors = [];
        if (trim((string) $data['title']) === '') {
            $errors['title'] = '请填写页面标题。';
        }
        if (trim((string) $data['content']) === '') {
            $errors['content'] = '请填写页面内容。';
        }
        if ($data['template'] !== '' && !$this->app->theme->hasTemplate((string) $data['template'])) {
            $errors['template'] = '所选模板不存在。';
        }

        return $errors;
    }

    /** @return array<string, mixed> */
    private function inputForForm(): array
    {
        return [
            'title'    => $this->stringInput('title'),
            'slug'     => $this->stringInput('slug'),
            'excerpt'  => $this->stringInput('excerpt'),
            'content'  => (string) ($this->app->request->post('content') ?? ''),
            'template' => $this->stringInput('template'),
            'status'   => $this->stringInput('status', 'draft'),
        ];
    }
}