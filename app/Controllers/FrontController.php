<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Paginator;
use App\Models\Category;
use App\Models\Link;
use App\Models\Post;
use App\Models\Tag;

/**
 * Shared data for public-facing controllers.
 */
abstract class FrontController extends Controller
{
    /** @return array<string, mixed> */
    protected function sidebar(): array
    {
        return [
            'sidebarCategories' => (new Category($this->app))->allWithCounts(),
            'sidebarTags'       => (new Tag($this->app))->allWithCounts(),
            'sidebarLinks'      => (new Link($this->app))->all(),
            'sidebarLatest'     => (new Post($this->app))->latest(5),
            'sidebarArchives'   => (new Post($this->app))->archiveCounts(),
        ];
    }

    protected function paginator(int $total, int $perPage, int $page, callable $urlBuilder): Paginator
    {
        return new Paginator($total, $perPage, $page, $urlBuilder);
    }

    /** @param array<string, mixed> $extra */
    protected function pageData(array $extra = []): array
    {
        return array_merge($this->sidebar(), [
            'pageTitle'       => option('site_name', 'WenBlog'),
            'pageDescription' => option('site_description', ''),
        ], $extra);
    }
}