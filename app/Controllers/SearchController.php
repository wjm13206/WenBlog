<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Post;

final class SearchController extends FrontController
{
    public function index(): void
    {
        $query = $this->app->request->query('q');
        $query = is_string($query) ? trim($query) : '';
        $query = mb_substr($query, 0, 100, 'UTF-8');
        $page = $this->pageNumber();
        $perPage = max(1, (int) option('posts_per_page', 8));
        $result = ['items' => [], 'total' => 0];
        if ($query !== '') {
            $result = (new Post($this->app))->paginate(['search' => $query], $page, $perPage);
            if ($page > 1 && $result['items'] === []) {
                $this->notFound('没有更多搜索结果。');
            }
            $result['items'] = array_map(static function (array $post) use ($query): array {
                $post['search_html_title'] = search_highlight((string) $post['title'], $query);
                $post['search_html_excerpt'] = search_snippet(
                    \App\Core\Markdown::text((string) $post['content'], 4000),
                    $query,
                    150
                );

                return $post;
            }, $result['items']);
        }
        $pagination = $this->paginator(
            $result['total'],
            $perPage,
            $page,
            static fn (int $number): string => url('search') . '?q=' . rawurlencode($query) . ($number > 1 ? '&page=' . $number : '')
        );

        $this->theme('search', $this->pageData([
            'query'       => $query,
            'posts'       => $result['items'],
            'pagination'  => $pagination,
            'pageTitle'   => $query !== '' ? '搜索：' . $query : '搜索',
            'noIndex'     => true,
        ]));
    }
}