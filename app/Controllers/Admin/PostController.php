<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Markdown;
use App\Core\Paginator;
use App\Core\Response;
use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;

final class PostController extends AdminController
{
    public function index(): void
    {
        $status = (string) ($this->app->request->query('status') ?? '');
        $search = trim((string) ($this->app->request->query('q') ?? ''));
        $page = $this->pageNumber();
        $perPage = 15;
        $result = (new Post($this->app))->adminPaginate($status, $search, $page, $perPage);
        $query = array_filter(['status' => $status, 'q' => $search]);
        $pagination = new Paginator($result['total'], $perPage, $page, static function (int $number) use ($query): string {
            $query['page'] = $number;

            return admin_url('posts?' . http_build_query($query));
        });

        $this->render('posts/index', [
            'posts'       => $result['items'],
            'pagination'  => $pagination,
            'status'      => $status,
            'search'      => $search,
            'counts'      => (new Post($this->app))->counts(),
        ], '文章');
    }

    public function create(): void
    {
        $this->form($this->emptyPost(), [], '', '撰写文章');
    }

    public function preview(): void
    {
        $content = (string) ($this->app->request->post('content') ?? '');
        Response::json(['html' => Markdown::html($content)]);
    }

    public function store(): void
    {
        $data = $this->payload();
        $errors = $this->validate($data);
        if ($errors !== []) {
            $this->app->session->setErrors($errors);
            $this->app->session->flashInput($this->inputForForm());
            Response::redirect(admin_url('posts/create'));
        }

        try {
            $id = (new Post($this->app))->create($data, $this->intList($this->app->request->post('categories')), $this->stringList($this->app->request->post('tags')));
            $this->success('文章已创建。');
            Response::redirect(admin_url('posts/' . $id . '/edit'));
        } catch (\Throwable $e) {
            $this->app->session->setErrors(['form' => $e->getMessage()]);
            $this->app->session->flashInput($this->inputForForm());
            Response::redirect(admin_url('posts/create'));
        }
    }

    public function edit(string $id): void
    {
        $post = (new Post($this->app))->find((int) $id);
        if ($post === null) {
            Response::error(404, '文章不存在。', true);
        }
        $this->form(
            $post,
            (new Post($this->app))->categoryIds((int) $post['id']),
            implode(', ', array_column((new Post($this->app))->tags((int) $post['id']), 'name')),
            '编辑文章'
        );
    }

    public function update(string $id): void
    {
        $posts = new Post($this->app);
        $post = $posts->find((int) $id);
        if ($post === null) {
            Response::error(404, '文章不存在。', true);
        }
        $data = $this->payload();
        $data['author_id'] = (int) $post['author_id'];
        $errors = $this->validate($data);
        if ($errors !== []) {
            $this->app->session->setErrors($errors);
            $this->app->session->flashInput($this->inputForForm());
            Response::redirect(admin_url('posts/' . $id . '/edit'));
        }
        try {
            $posts->update((int) $id, $data, $this->intList($this->app->request->post('categories')), $this->stringList($this->app->request->post('tags')));
            $this->success('文章已保存。');
        } catch (\Throwable $e) {
            $this->app->session->setErrors(['form' => $e->getMessage()]);
        }
        Response::redirect(admin_url('posts/' . $id . '/edit'));
    }

    public function delete(string $id): void
    {
        $post = (new Post($this->app))->find((int) $id);
        if ($post === null) {
            Response::error(404, '文章不存在。', true);
        }
        (new Post($this->app))->delete((int) $id);
        $this->success('文章已删除。');
        Response::redirect(admin_url('posts'));
    }

    public function revisions(string $id): void
    {
        $post = (new Post($this->app))->find((int) $id);
        if ($post === null) {
            Response::error(404, '文章不存在。', true);
        }
        $this->render('posts/revisions', [
            'post'      => $post,
            'revisions' => (new Post($this->app))->revisions((int) $id),
        ], '修订记录');
    }

    public function restore(string $id, string $revision): void
    {
        $posts = new Post($this->app);
        $post = $posts->find((int) $id);
        if ($post === null) {
            Response::error(404, '文章不存在。', true);
        }
        if (!$posts->restoreRevision((int) $id, (int) $revision)) {
            $this->error('修订版本不存在。');
        } else {
            $this->success('已恢复到选定的修订版本。');
        }
        Response::redirect(admin_url('posts/' . $id . '/edit'));
    }

    /** @param array<string, mixed> $post @param list<int> $selectedCategories */
    private function form(array $post, array $selectedCategories, string $tags, string $title): void
    {
        $this->render('posts/form', [
            'post'               => $post,
            'categories'         => (new Category($this->app))->allWithCounts(),
            'selectedCategories' => $selectedCategories,
            'tags'               => $tags,
            'allTags'            => (new Tag($this->app))->allWithCounts(),
            'revisionCount'      => isset($post['id']) ? count((new Post($this->app))->revisions((int) $post['id'])) : 0,
        ], $title);
    }

    /** @return array<string, mixed> */
    private function emptyPost(): array
    {
        return [
            'id'             => null,
            'title'          => '',
            'slug'           => '',
            'excerpt'        => '',
            'content'        => '',
            'cover'          => '',
            'status'         => 'draft',
            'allow_comments' => 1,
            'pinned'         => 0,
            'published_at'   => null,
        ];
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        $status = $this->stringInput('status', 'draft');
        if (!in_array($status, ['draft', 'published', 'scheduled'], true)) {
            $status = 'draft';
        }
        $publishedAt = $this->stringInput('published_at');
        $publishedAt = $publishedAt !== '' && strtotime($publishedAt) !== false
            ? date('Y-m-d H:i:s', (int) strtotime($publishedAt))
            : null;
        if ($status === 'published' && $publishedAt === null) {
            $publishedAt = date('Y-m-d H:i:s');
        }
        if ($status === 'draft' && $publishedAt === null) {
            $publishedAt = null;
        }
        $cover = $this->stringInput('cover');
        if ($cover !== '' && !\App\Core\Security::isSafeUrl($cover) && !str_starts_with($cover, 'uploads/')) {
            $cover = '';
        }

        return [
            'title'          => $this->stringInput('title'),
            'slug'           => $this->stringInput('slug'),
            'excerpt'        => $this->stringInput('excerpt'),
            'content'        => (string) ($this->app->request->post('content') ?? ''),
            'cover'          => $cover,
            'status'         => $status,
            'author_id'      => $this->app->auth->id(),
            'allow_comments' => $this->app->request->post('allow_comments') === '1' ? 1 : 0,
            'pinned'         => $this->app->request->post('pinned') === '1' ? 1 : 0,
            'published_at'   => $publishedAt,
            'updated_at'     => date('Y-m-d H:i:s'),
        ];
    }

    /** @param array<string, mixed> $data @return array<string, string> */
    private function validate(array $data): array
    {
        $errors = [];
        if (trim((string) $data['title']) === '') {
            $errors['title'] = '请填写文章标题。';
        } elseif (mb_strlen((string) $data['title'], 'UTF-8') > 255) {
            $errors['title'] = '标题最多 255 个字符。';
        }
        if (trim((string) $data['content']) === '') {
            $errors['content'] = '请填写文章内容。';
        }
        $rawPublishedAt = $this->stringInput('published_at');
        if ($rawPublishedAt !== '' && strtotime($rawPublishedAt) === false) {
            $errors['published_at'] = '发布时间格式无效。';
        }
        if ($data['status'] === 'scheduled') {
            if ($data['published_at'] === null) {
                $errors['published_at'] = '定时发布需要选择发布时间。';
            } elseif (strtotime((string) $data['published_at']) <= time()) {
                $errors['published_at'] = '定时发布时间必须晚于当前时间。';
            }
        }

        return $errors;
    }

    /** @return array<string, mixed> */
    private function inputForForm(): array
    {
        return [
            'title'          => $this->stringInput('title'),
            'slug'           => $this->stringInput('slug'),
            'excerpt'        => $this->stringInput('excerpt'),
            'content'        => (string) ($this->app->request->post('content') ?? ''),
            'cover'          => $this->stringInput('cover'),
            'status'         => $this->stringInput('status', 'draft'),
            'allow_comments' => $this->app->request->post('allow_comments') === '1' ? 1 : 0,
            'pinned'         => $this->app->request->post('pinned') === '1' ? 1 : 0,
            'published_at'   => $this->stringInput('published_at'),
            'categories'     => $this->app->request->post('categories'),
            'tags'           => $this->stringInput('tags'),
        ];
    }
}