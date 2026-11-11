<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Category;
use App\Models\Post;

final class CategoryController extends FrontController
{
    public function show(string $slug, string $page = '1'): void
    {
        $categories = new Category($this->app);
        $category = $categories->findBySlug($slug);
        if ($category === null) {
            $this->notFound();
        }
        $currentPage = max(1, (int) $page);
        $perPage = max(1, (int) option('posts_per_page', 8));
        $result = (new Post($this->app))->paginate(['category' => $slug], $currentPage, $perPage);
        if ($currentPage > 1 && $result['items'] === []) {
            $this->notFound();
        }
        $pagination = $this->paginator(
            $result['total'],
            $perPage,
            $currentPage,
            static fn (int $number): string => category_url($category) . ($number > 1 ? '/page/' . $number : '')
        );

        $this->theme('category', $this->pageData([
            'category'     => $category,
            'breadcrumb'   => $categories->breadcrumb($category),
            'posts'        => $result['items'],
            'pagination'   => $pagination,
            'pageTitle'    => (string) $category['name'],
            'pageDescription' => (string) ($category['description'] ?? ''),
        ]));
    }
}