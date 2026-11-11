<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Comment;
use App\Models\Post;

final class CommentController extends FrontController
{
    public function store(): void
    {
        // WenBlog forms post `post_id`; Typecho themes only post the comment
        // fields, so the id also travels in the action URL (see commentUrl()).
        $postId = (int) ($this->app->request->post('post_id', 0)
            ?: $this->app->request->query('post_id', 0)
            ?: $this->app->request->post('cid', 0));
        $post = (new Post($this->app))->find($postId);
        if ($post === null
            || !in_array($post['status'], ['published', 'scheduled'], true)
            || ($post['status'] === 'scheduled' && strtotime((string) $post['published_at']) > time())) {
            $this->notFound('文章不存在。');
        }
        $target = post_url($post) . '#comment-form';

        if ((string) option('comments_enabled', '1') !== '1' || (int) ($post['allow_comments'] ?? 1) !== 1) {
            $this->app->session->flash('error', '该文章已关闭评论。');
            \App\Core\Response::redirect($target);
        }

        $input = [
            'author_name'  => $this->stringInput('author_name') ?: $this->stringInput('author'),
            'author_email' => $this->stringInput('author_email') ?: $this->stringInput('mail'),
            'author_url'   => $this->stringInput('author_url') ?: $this->stringInput('url'),
            'content'      => $this->stringInput('content') ?: $this->stringInput('text'),
            'website'      => $this->stringInput('website'),
        ];
        $this->app->session->flashInput([
            'author_name'  => $input['author_name'],
            'author_email' => $input['author_email'],
            'author_url'   => $input['author_url'],
            'content'      => $input['content'],
        ]);

        $errors = [];
        if ($input['author_name'] === '' || mb_strlen($input['author_name'], 'UTF-8') > 50) {
            $errors['author_name'] = '请填写昵称（最多 50 个字符）。';
        }
        $emailRequired = (string) option('comments_require_email', '1') === '1';
        if (($emailRequired || $input['author_email'] !== '') && filter_var($input['author_email'], FILTER_VALIDATE_EMAIL) === false) {
            $errors['author_email'] = '请填写有效的邮箱地址。';
        }
        if ($input['author_url'] !== '' && !\App\Core\Security::isSafeUrl($input['author_url'])) {
            $errors['author_url'] = '网站地址格式不正确。';
        }
        $length = mb_strlen($input['content'], 'UTF-8');
        if ($length < 2 || $length > 5000) {
            $errors['content'] = '评论内容需为 2 到 5000 个字符。';
        }
        if ($errors !== []) {
            $this->app->session->setErrors($errors);
            \App\Core\Response::redirect($target);
        }

        if ($input['website'] !== '') {
            // Honeypot: silently accept for bots without storing anything.
            $this->app->session->flash('success', '评论已提交。');
            \App\Core\Response::redirect(post_url($post) . '#comments');
        }

        $comments = new Comment($this->app);
        $parentId = (int) ($this->app->request->post('parent_id', 0)
            ?: $this->app->request->post('parent', 0));
        if ($parentId > 0) {
            $parent = $comments->find($parentId);
            if ($parent === null || (int) $parent['post_id'] !== $postId || $parent['status'] !== 'approved') {
                $parentId = 0;
            } else {
                // Threads are displayed one level deep; attach a reply to a
                // reply to its root comment so it remains visible.
                while ($parent !== null && $parent['parent_id'] !== null) {
                    $next = $comments->find((int) $parent['parent_id']);
                    if ($next === null || (int) $next['post_id'] !== $postId) {
                        break;
                    }
                    $parent = $next;
                }
                $parentId = $parent === null ? 0 : (int) $parent['id'];
            }
        }

        $spamReason = $comments->spamReason($postId, $input);
        $status = $spamReason !== null ? 'spam' : ((string) option('comments_moderation', '1') === '1' ? 'pending' : 'approved');
        $userId = $this->app->auth->id();

        $comments->create([
            'post_id'      => $postId,
            'parent_id'    => $parentId > 0 ? $parentId : null,
            'user_id'      => $userId,
            'author_name'  => mb_substr($input['author_name'], 0, 50, 'UTF-8'),
            'author_email' => mb_substr($input['author_email'], 0, 190, 'UTF-8'),
            'author_url'   => $input['author_url'] !== '' ? mb_substr($input['author_url'], 0, 255, 'UTF-8') : null,
            'content'      => $input['content'],
            'ip'           => $this->app->request->ip(),
            'user_agent'   => mb_substr($this->app->request->userAgent(), 0, 255, 'UTF-8'),
        ], $status);

        $this->app->session->remove('comment_started');
        \App\Core\Hook::do('comment.created', $postId, $status);
        $message = $status === 'approved'
            ? '评论已发布。'
            : '评论已提交，审核通过后会显示。';
        $this->app->session->flash('success', $message);
        \App\Core\Response::redirect(post_url($post) . '#comments');
    }
}