<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Paginator;
use App\Core\Response;
use App\Models\Comment;

final class CommentController extends AdminController
{
    public function index(): void
    {
        $status = (string) ($this->app->request->query('status') ?? '');
        $search = trim((string) ($this->app->request->query('q') ?? ''));
        $page = $this->pageNumber();
        $perPage = 20;
        $comments = new Comment($this->app);
        $result = $comments->adminPaginate($status, $search, $page, $perPage);
        $query = array_filter(['status' => $status, 'q' => $search]);
        $pagination = new Paginator($result['total'], $perPage, $page, static function (int $number) use ($query): string {
            $query['page'] = $number;

            return admin_url('comments?' . http_build_query($query));
        });

        $this->render('comments/index', [
            'comments'   => $result['items'],
            'pagination' => $pagination,
            'status'     => $status,
            'search'     => $search,
        ], '评论');
    }

    public function status(string $id): void
    {
        $comments = new Comment($this->app);
        $comment = $comments->find((int) $id);
        if ($comment === null) {
            Response::error(404, '评论不存在。', true);
        }
        $status = $this->stringInput('status');
        if (!in_array($status, ['pending', 'approved', 'spam'], true)) {
            $this->error('无效的评论状态。');
            Response::redirect($this->back());
        }
        $comments->updateStatus((int) $id, $status);
        $this->success('评论状态已更新。');
        Response::redirect($this->back());
    }

    public function reply(string $id): void
    {
        $comments = new Comment($this->app);
        $parent = $comments->find((int) $id);
        if ($parent === null) {
            Response::error(404, '评论不存在。', true);
        }
        $content = trim((string) ($this->app->request->post('content') ?? ''));
        if ($content === '' || mb_strlen($content, 'UTF-8') > 5000) {
            $this->error('回复内容不能为空，且最多 5000 个字符。');
            Response::redirect($this->back());
        }
        // The front-end renders one reply level. If an editor replies to a
        // reply, attach the new comment to the thread root so it stays visible.
        $rootId = (int) $parent['id'];
        $cursor = $parent;
        while ($cursor['parent_id'] !== null) {
            $next = $comments->find((int) $cursor['parent_id']);
            if ($next === null) {
                break;
            }
            $cursor = $next;
            $rootId = (int) $cursor['id'];
        }

        $user = $this->app->auth->user();
        $comments->create([
            'post_id'      => (int) $parent['post_id'],
            'parent_id'    => $rootId,
            'author_name'  => (string) ($user['display_name'] ?? $user['username'] ?? '管理员'),
            'author_email' => (string) ($user['email'] ?? ''),
            'author_url'   => '',
            'content'      => $content,
            'ip'           => $this->app->request->ip(),
            'user_agent'   => mb_substr($this->app->request->userAgent(), 0, 255),
            'user_id'      => $this->app->auth->id(),
        ], 'approved');
        $this->success('回复已发布。');
        Response::redirect($this->back());
    }

    public function delete(string $id): void
    {
        $comments = new Comment($this->app);
        $comment = $comments->find((int) $id);
        if ($comment === null) {
            Response::error(404, '评论不存在。', true);
        }
        $comments->delete((int) $id);
        $this->success('评论及其回复已删除。');
        Response::redirect($this->back());
    }
}