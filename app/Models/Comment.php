<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\App;
use App\Core\Database;

final class Comment
{
    protected Database $db;

    public function __construct(protected readonly App $app)
    {
        $this->db = $app->db;
    }

    /**
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function approvedForPost(int $postId, int $page, int $perPage): array
    {
        $total = $this->db->count(
            "SELECT COUNT(*) FROM comments WHERE post_id = ? AND status = 'approved' AND parent_id IS NULL",
            [$postId]
        );
        $offset = max(0, ($page - 1) * $perPage);
        $roots = $this->db->fetchAll(
            "SELECT * FROM comments WHERE post_id = ? AND status = 'approved' AND parent_id IS NULL
             ORDER BY created_at ASC LIMIT " . max(1, $perPage) . ' OFFSET ' . $offset,
            [$postId]
        );

        $items = [];
        if ($roots !== []) {
            $rootIds = array_map(static fn (array $row): int => (int) $row['id'], $roots);
            $placeholders = implode(',', array_fill(0, count($rootIds), '?'));
            $replies = $this->db->fetchAll(
                "SELECT * FROM comments WHERE post_id = ? AND status = 'approved' AND parent_id IN ($placeholders)
                 ORDER BY created_at ASC",
                array_merge([$postId], $rootIds)
            );
            $byParent = [];
            foreach ($replies as $reply) {
                $byParent[(int) $reply['parent_id']][] = $reply;
            }
            foreach ($roots as $root) {
                $root['replies'] = $byParent[(int) $root['id']] ?? [];
                $items[] = $root;
            }
        }

        return ['items' => $items, 'total' => $total];
    }

    public function countApproved(int $postId): int
    {
        return $this->db->count("SELECT COUNT(*) FROM comments WHERE post_id = ? AND status = 'approved'", [$postId]);
    }

    /** @return array{items: list<array<string, mixed>>, total: int} */
    public function adminPaginate(string $status, string $search, int $page, int $perPage): array
    {
        $where = ['1 = 1'];
        $params = [];
        if (in_array($status, ['pending', 'approved', 'spam'], true)) {
            $where[] = 'c.status = ?';
            $params[] = $status;
        }
        if ($search !== '') {
            $where[] = '(c.content LIKE ? OR c.author_name LIKE ? OR c.author_email LIKE ?)';
            $like = '%' . $search . '%';
            array_push($params, $like, $like, $like);
        }
        $whereSql = implode(' AND ', $where);
        $total = $this->db->count('SELECT COUNT(*) FROM comments c WHERE ' . $whereSql, $params);
        $offset = max(0, ($page - 1) * $perPage);
        $items = $this->db->fetchAll(
            'SELECT c.*, p.title AS post_title, p.slug AS post_slug FROM comments c
             LEFT JOIN posts p ON p.id = c.post_id
             WHERE ' . $whereSql . ' ORDER BY c.created_at DESC, c.id DESC
             LIMIT ' . max(1, $perPage) . ' OFFSET ' . $offset,
            $params
        );

        return ['items' => $items, 'total' => $total];
    }

    public function find(int $id): ?array
    {
        return $this->db->fetch('SELECT * FROM comments WHERE id = ? LIMIT 1', [$id]);
    }

    /** @param array<string, mixed> $data */
    public function create(array $data, string $status): int
    {
        $id = $this->db->insert('comments', array_merge($data, [
            'status'     => $status,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]));
        $this->refreshPostCount((int) ($data['post_id'] ?? 0));
        $this->app->cache->clear();

        return $id;
    }

    public function updateStatus(int $id, string $status): void
    {
        $comment = $this->find($id);
        if ($comment === null) {
            return;
        }
        $this->db->update('comments', ['status' => $status, 'updated_at' => date('Y-m-d H:i:s')], ['id' => $id]);
        $this->refreshPostCount((int) $comment['post_id']);
        $this->app->cache->clear();
    }

    public function delete(int $id): void
    {
        $comment = $this->find($id);
        if ($comment === null) {
            return;
        }

        // Replies may be nested, so collect the whole branch instead of only
        // deleting direct children and leaving orphaned grandchildren.
        $ids = $this->descendantIds($id);
        $ids[] = $id;
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $this->db->query('DELETE FROM comments WHERE id IN (' . $placeholders . ')', $ids);

        $this->refreshPostCount((int) $comment['post_id']);
        $this->app->cache->clear();
    }

    /** @return list<int> */
    private function descendantIds(int $id): array
    {
        $found = [];
        $queue = [$id];
        while ($queue !== []) {
            $parent = array_shift($queue);
            $rows = $this->db->fetchAll('SELECT id FROM comments WHERE parent_id = ?', [$parent]);
            foreach ($rows as $row) {
                $child = (int) $row['id'];
                if (isset($found[$child]) || $child === $id) {
                    continue;
                }
                $found[$child] = true;
                $queue[] = $child;
            }
        }

        return array_map('intval', array_keys($found));
    }

    /**
     * Returns a spam reason, or null when the comment can be accepted.
     *
     * @param array<string, mixed> $input
     */
    public function spamReason(int $postId, array $input): ?string
    {
        $content = (string) ($input['content'] ?? '');
        $name = (string) ($input['author_name'] ?? '');
        $email = (string) ($input['author_email'] ?? '');

        if (trim((string) ($input['website'] ?? '')) !== '') {
            return 'honeypot';
        }
        if (mb_strlen(trim($content), 'UTF-8') < 2 || mb_strlen(trim($name), 'UTF-8') < 1) {
            return 'empty';
        }
        if ((int) $this->app->options->get('comments_min_seconds', 3) > 0) {
            $started = (int) $this->app->session->get('comment_started', 0);
            if ($started > 0 && (time() - $started) < (int) $this->app->options->get('comments_min_seconds', 3)) {
                return 'too_fast';
            }
        }
        if (preg_match_all('#https?://#i', $content) > 2) {
            return 'too_many_links';
        }
        $blocklist = trim((string) $this->app->options->get('comments_blocked_words', ''));
        if ($blocklist !== '') {
            foreach (preg_split('/[,，\n]+/u', $blocklist) ?: [] as $word) {
                $word = trim($word);
                if ($word !== '' && (stripos($content, $word) !== false || stripos($name, $word) !== false)) {
                    return 'blocklist';
                }
            }
        }
        $recent = $this->db->count(
            'SELECT COUNT(*) FROM comments WHERE ip = ? AND created_at > ?',
            [$this->app->request->ip(), date('Y-m-d H:i:s', time() - 600)]
        );
        if ($recent >= 5) {
            return 'rate_limit';
        }
        $duplicate = $this->db->count(
            "SELECT COUNT(*) FROM comments WHERE post_id = ? AND content = ? AND created_at > ?",
            [$postId, $content, date('Y-m-d H:i:s', time() - 300)]
        );
        if ($duplicate > 0) {
            return 'duplicate';
        }
        if ((string) $this->app->options->get('comments_require_email', '1') === '1'
            && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return 'email';
        }

        return null;
    }

    public function refreshPostCount(int $postId): void
    {
        if ($postId <= 0) {
            return;
        }
        $this->db->query(
            "UPDATE posts SET comment_count = (SELECT COUNT(*) FROM comments WHERE post_id = ? AND status = 'approved') WHERE id = ?",
            [$postId, $postId]
        );
    }
}