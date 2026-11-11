<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Post;
use App\Models\Tag;

final class TagController extends FrontController
{
    public function show(string $slug, string $page = '1'): void
    {
        $tag = (new Tag($this->app))->findBySlug($slug);
        if ($tag === null) {
            $this->notFound();
        }
        $currentPage = max(1, (int) $page);
        $perPage = max(1, (int) option('posts_per_page', 8));
        $result = (new Post($this->app))->paginate(['tag' => $slug], $currentPage, $perPage);
        if ($currentPage > 1 && $result['items'] === []) {
            $this->notFound();
        }
        $pagination = $this->paginator(
            $result['total'],
            $perPage,
            $currentPage,
            static fn (int $number): string => tag_url($tag) . ($number > 1 ? '/page/' . $number : '')
        );

        $this->theme('tag', $this->pageData([
            'tag'          => $tag,
            'posts'        => $result['items'],
            'pagination'   => $pagination,
            'pageTitle'    => '标签：' . (string) $tag['name'],
            'pageDescription' => '包含标签「' . (string) $tag['name'] . '」的文章。',
        ]));
    }
}