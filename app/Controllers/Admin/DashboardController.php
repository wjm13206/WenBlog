<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\Comment;
use App\Models\Media;
use App\Models\Post;
use App\Models\User;

final class DashboardController extends AdminController
{
    public function index(): void
    {
        $posts = new Post($this->app);
        $comments = new Comment($this->app);
        $counts = $posts->counts();
        $recentComments = $comments->adminPaginate('pending', '', 1, 6)['items'];
        $latestPosts = $this->app->db->fetchAll(
            'SELECT p.id, p.title, p.status, p.views, p.comment_count, p.created_at, p.published_at, u.display_name AS author_name
             FROM posts p LEFT JOIN users u ON u.id = p.author_id
             ORDER BY p.created_at DESC LIMIT 6'
        );
        $popular = $this->app->db->fetchAll(
            "SELECT id, title, views FROM posts WHERE status = 'published' ORDER BY views DESC LIMIT 5"
        );
        $chartRows = $this->app->db->fetchAll(
            "SELECT DATE(created_at) AS day, COUNT(*) AS count FROM posts
             WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
             GROUP BY DATE(created_at) ORDER BY day ASC"
        );
        $chart = [];
        for ($i = 13; $i >= 0; $i--) {
            $day = date('Y-m-d', strtotime('-' . $i . ' days'));
            $chart[$day] = 0;
        }
        foreach ($chartRows as $row) {
            if (isset($chart[$row['day']])) {
                $chart[$row['day']] = (int) $row['count'];
            }
        }

        $this->render('dashboard', [
            'counts'         => $counts,
            'userCount'      => (new User($this->app))->count(),
            'mediaCount'     => (new Media($this->app))->count(),
            'recentComments' => $recentComments,
            'latestPosts'    => $latestPosts,
            'popular'        => $popular,
            'chart'          => $chart,
        ], '仪表盘');
    }
}