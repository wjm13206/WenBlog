<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Page;
use App\Models\Post;

final class PageController extends FrontController
{
    public function show(string $slug): void
    {
        if (option('permalink_style', 'post') === 'slug'
            && (new Post($this->app))->findPublishedBySlug($slug) !== null) {
            // In root-level permalink mode posts and pages share the same URL
            // shape; a published post takes precedence over a page.
            (new PostController($this->app))->show($slug);

            return;
        }

        $page = (new Page($this->app))->findPublishedBySlug($slug);
        if ($page === null) {
            $this->notFound();
        }
        $content = (string) $page['content'];
        $page['content'] = \App\Core\Hook::filter('page.content', $content, $page);
        $template = (string) ($page['template'] ?? '');
        if ($template === '' || !$this->app->theme->hasTemplate($template)) {
            $template = 'page';
        }

        $this->theme($template, $this->pageData([
            'page'          => $page,
            'pageTitle'     => (string) $page['title'],
            'pageDescription' => post_excerpt($page, 160),
            'isPage'        => true,
        ]));
    }
}