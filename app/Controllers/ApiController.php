<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Response;
use App\Models\Category;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Tag;

/**
 * Read-only JSON API. Authentication is handled by the router (X-API-Key).
 */
final class ApiController extends FrontController
{
    public function posts(): void
    {
        $page = $this->pageNumber();
        $perPage = min(50, max(1, (int) ($this->app->request->query('per_page') ?? 10)));
        $result = (new Post($this->app))->paginate([], $page, $perPage);
        Response::json([
            'data' => array_map([$this, 'postPayload'], $result['items']),
            'meta' => [
                'page'     => $page,
                'per_page' => $perPage,
                'total'    => $result['total'],
            ],
        ]);
    }

    public function post(string $slug): void
    {
        $post = (new Post($this->app))->findPublishedBySlug($slug);
        if ($post === null) {
            Response::json(['error' => 'not_found', 'message' => '文章不存在。'], 404);
        }
        $posts = new Post($this->app);
        $payload = $this->postPayload($post);
        $payload['content'] = $post['content'];
        $payload['html'] = \App\Core\Markdown::html((string) $post['content']);
        $payload['categories'] = $posts->categories((int) $post['id']);
        $payload['tags'] = $posts->tags((int) $post['id']);
        Response::json(['data' => $payload]);
    }

    public function categories(): void
    {
        $categories = (new Category($this->app))->allWithCounts();
        Response::json(['data' => $categories]);
    }

    public function tags(): void
    {
        Response::json(['data' => (new Tag($this->app))->allWithCounts()]);
    }

    public function comments(): void
    {
        $postId = (int) ($this->app->request->query('post_id') ?? 0);
        if ($postId <= 0) {
            Response::json(['error' => 'validation', 'message' => '缺少 post_id 参数。'], 422);
        }
        if ((new Post($this->app))->findPublishedById($postId) === null) {
            Response::json(['error' => 'not_found', 'message' => '文章不存在。'], 404);
        }
        $page = $this->pageNumber();
        $perPage = min(100, max(1, (int) ($this->app->request->query('per_page') ?? 20)));
        $result = (new Comment($this->app))->approvedForPost($postId, $page, $perPage);
        $items = array_map(function (array $comment): array {
            $payload = $this->commentPayload($comment);
            $payload['replies'] = array_map([$this, 'commentPayload'], $comment['replies'] ?? []);

            return $payload;
        }, $result['items']);
        Response::json([
            'data' => $items,
            'meta' => ['page' => $page, 'per_page' => $perPage, 'total' => $result['total']],
        ]);
    }

    /** @return array<string, mixed> */
    private function postPayload(array $post): array
    {
        return [
            'id'           => (int) $post['id'],
            'title'        => $post['title'],
            'slug'         => $post['slug'],
            'excerpt'      => post_excerpt($post, 200),
            'url'          => post_url($post),
            'cover'        => $post['cover'] ?? null,
            'author'       => $post['author_name'] ?? null,
            'views'        => (int) $post['views'],
            'pinned'       => (int) $post['pinned'] === 1,
            'published_at' => $post['published_at'],
            'updated_at'   => $post['updated_at'] ?? null,
        ];
    }

    /** @return array<string, mixed> */
    private function commentPayload(array $comment): array
    {
        return [
            'id'          => (int) $comment['id'],
            'parent_id'   => $comment['parent_id'] !== null ? (int) $comment['parent_id'] : null,
            'author_name' => $comment['author_name'],
            'author_url'  => $comment['author_url'] !== '' ? $comment['author_url'] : null,
            'content'     => $comment['content'],
            'created_at'  => $comment['created_at'],
        ];
    }
}