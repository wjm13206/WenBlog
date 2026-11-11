<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Post;

final class HomeController extends FrontController
{
    public function index(): void
    {
        $this->listing(1);
    }

    public function page(string $page): void
    {
        $this->listing(max(1, (int) $page));
    }

    private function listing(int $page): void
    {
        $perPage = max(1, (int) option('posts_per_page', 8));
        $result = (new Post($this->app))->paginate([], $page, $perPage);
        if ($page > 1 && $result['items'] === []) {
            $this->notFound();
        }
        $pagination = $this->paginator(
            $result['total'],
            $perPage,
            $page,
            static fn (int $number): string => $number <= 1 ? url('/') : url('page/' . $number)
        );

        $this->theme('index', $this->pageData([
            'posts'        => $result['items'],
            'pagination'   => $pagination,
            'pageTitle'    => option('site_name', 'WenBlog'),
            'currentPage'  => $page,
            'isHome'       => true,
        ]));
    }
}