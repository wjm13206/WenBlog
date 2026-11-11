<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Comment;
use App\Models\Post;

final class PostController extends FrontController
{
    public function show(string $slug): void
    {
        $posts = new Post($this->app);
        $post = $posts->findPublishedBySlug($slug);
        if ($post === null) {
            $this->notFound();
        }

        $posts->incrementViews((int) $post['id']);
        $post['views'] = (int) $post['views'] + 1;
        $content = (string) $post['content'];
        $post['content'] = \App\Core\Hook::filter('post.content', $content, $post);

        $comments = new Comment($this->app);
        $perPage = max(1, (int) option('comments_per_page', 20));
        $commentPage = $this->pageNumber('cpage');
        $commentResult = $comments->approvedForPost((int) $post['id'], $commentPage, $perPage);
        $commentPagination = $this->paginator(
            $commentResult['total'],
            $perPage,
            $commentPage,
            static fn (int $number): string => post_url($post) . '?cpage=' . $number . '#comments'
        );

        $this->app->session->set('comment_started', time());

        $this->theme('single', $this->pageData([
            'post'              => $post,
            'categories'        => $posts->categories((int) $post['id']),
            'tags'              => $posts->tags((int) $post['id']),
            'adjacent'          => $posts->adjacent($post),
            'related'           => $posts->related($post, 3),
            'comments'          => $commentResult['items'],
            'commentTotal'      => $commentResult['total'],
            'commentPagination' => $commentPagination,
            'commentPage'       => $commentPage,
            'pageTitle'         => (string) $post['title'],
            'pageDescription'   => post_excerpt($post, 160),
            'currentPost'       => $post,
        ]));
    }
}