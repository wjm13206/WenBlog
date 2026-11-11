<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\App;
use App\Core\Database;
use App\Core\Str;

/**
 * Post persistence and front-end query helpers.
 */
final class Post
{
    private const MATCH_SQL = 'MATCH(p.title, p.content, p.excerpt) AGAINST(? IN BOOLEAN MODE)';

    private static ?bool $fulltextReady = null;

    protected Database $db;

    public function __construct(protected readonly App $app)
    {
        $this->db = $app->db;
    }

    /**
     * SQL fragment for posts visible on the public site (scheduled posts
     * become visible automatically once their time has passed).
     */
    public function publishedWhere(string $alias = 'p'): string
    {
        return '(' . $alias . ".status = 'published' OR (" . $alias . ".status = 'scheduled' AND "
            . $alias . '.published_at IS NOT NULL AND ' . $alias . '.published_at <= NOW()))';
    }

    /**
     * Detect whether the FULLTEXT index exists and the server supports ngram
     * tokenisation (MySQL 5.7.6+). MariaDB falls back to LIKE.
     */
    private function fulltextReady(): bool
    {
        if (self::$fulltextReady !== null) {
            return self::$fulltextReady;
        }

        try {
            if ($this->db->fetch("SHOW INDEX FROM posts WHERE Key_name = 'posts_fulltext'") === null) {
                return self::$fulltextReady = false;
            }

            return self::$fulltextReady = $this->db->fetch("SHOW VARIABLES LIKE 'ngram_token_size'") !== null;
        } catch (\Throwable) {
            return self::$fulltextReady = false;
        }
    }

    /**
     * Turn user input into a BOOLEAN MODE query. Operator characters are
     * stripped so a query can never break the SQL expression.
     */
    private function booleanQuery(string $search): string
    {
        $clean = preg_replace('/[+\-<>()~*"@\\\\]+/u', ' ', $search) ?? '';
        $terms = [];
        foreach (preg_split('/\s+/u', trim($clean)) ?: [] as $part) {
            $part = trim((string) $part);
            if ($part !== '' && mb_strlen($part, 'UTF-8') >= 2) {
                $terms[] = $part;
            }
        }

        return implode(' ', array_slice($terms, 0, 8));
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    /**
     * Create the FULLTEXT index when it is missing. The ngram parser is tried
     * first so Chinese text can be searched by substring.
     */
    public function rebuildSearchIndex(): string
    {
        $exists = $this->db->fetch("SHOW INDEX FROM posts WHERE Key_name = 'posts_fulltext'");
        if ($exists === null) {
            try {
                $this->db->query(
                    'ALTER TABLE posts ADD FULLTEXT INDEX posts_fulltext (title, content, excerpt) WITH PARSER ngram'
                );
            } catch (\Throwable) {
                try {
                    $this->db->query('ALTER TABLE posts ADD FULLTEXT INDEX posts_fulltext (title, content, excerpt)');
                } catch (\Throwable $e) {
                    throw new \RuntimeException('无法创建全文索引：' . $e->getMessage());
                }
            }
        }

        self::$fulltextReady = null;

        return $this->fulltextReady()
            ? '全文索引已就绪，中文按 ngram 分词检索。'
            : '索引已创建，但数据库不支持 ngram 分词，中文搜索将回退为模糊匹配。';
    }

    /**
     * @param array{category?: string, tag?: string, search?: string, year?: int, month?: int} $filters
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function paginate(array $filters, int $page, int $perPage): array
    {
        $joins = '';
        $where = [$this->publishedWhere('p')];
        $params = [];

        if (!empty($filters['category'])) {
            $categoryModel = new Category($this->app);
            $category = $categoryModel->findBySlug((string) $filters['category']);
            if ($category === null) {
                $where[] = '1 = 0';
            } else {
                $categoryIds = $categoryModel->descendantIds((int) $category['id']);
                $joins .= ' INNER JOIN post_categories pc ON pc.post_id = p.id';
                $where[] = 'pc.category_id IN (' . implode(',', array_fill(0, count($categoryIds), '?')) . ')';
                array_push($params, ...$categoryIds);
            }
        }
        if (!empty($filters['tag'])) {
            $joins .= ' INNER JOIN post_tags pt ON pt.post_id = p.id INNER JOIN tags t ON t.id = pt.tag_id';
            $where[] = 't.slug = ?';
            $params[] = $filters['tag'];
        }
        $selectExtra = '';
        $selectParams = [];
        if (!empty($filters['search'])) {
            $boolean = $this->booleanQuery((string) $filters['search']);
            if ($boolean !== '' && $this->fulltextReady()) {
                $where[] = self::MATCH_SQL;
                $params[] = $boolean;
                $selectExtra = ', ' . self::MATCH_SQL . ' AS relevance';
                $selectParams[] = $boolean;
            } else {
                $where[] = '(p.title LIKE ? OR p.content LIKE ? OR p.excerpt LIKE ?)';
                $like = '%' . $this->escapeLike((string) $filters['search']) . '%';
                array_push($params, $like, $like, $like);
            }
        }
        if (!empty($filters['year'])) {
            $where[] = 'YEAR(p.published_at) = ?';
            $params[] = (int) $filters['year'];
        }
        if (!empty($filters['month'])) {
            $where[] = 'MONTH(p.published_at) = ?';
            $params[] = (int) $filters['month'];
        }

        $whereSql = implode(' AND ', $where);
        $total = $this->db->count('SELECT COUNT(DISTINCT p.id) FROM posts p' . $joins . ' WHERE ' . $whereSql, $params);

        $orderSql = 'p.pinned DESC, ' . ($selectExtra !== '' ? 'relevance DESC, ' : '') . 'p.published_at DESC, p.id DESC';

        $offset = max(0, ($page - 1) * $perPage);
        $items = $this->db->fetchAll(
            'SELECT DISTINCT p.*, u.display_name AS author_name, u.username AS author_username' . $selectExtra . '
             FROM posts p LEFT JOIN users u ON u.id = p.author_id' . $joins . '
             WHERE ' . $whereSql . '
             ORDER BY ' . $orderSql . '
             LIMIT ' . max(1, $perPage) . ' OFFSET ' . $offset,
            array_merge($selectParams, $params)
        );

        return ['items' => $items, 'total' => $total];
    }

    public function findPublishedBySlug(string $slug): ?array
    {
        return $this->db->fetch(
            'SELECT p.*, u.display_name AS author_name, u.username AS author_username, u.avatar AS author_avatar
             FROM posts p LEFT JOIN users u ON u.id = p.author_id
             WHERE p.slug = ? AND ' . $this->publishedWhere('p') . ' LIMIT 1',
            [$slug]
        );
    }

    public function find(int $id): ?array
    {
        return $this->db->fetch(
            'SELECT p.*, u.display_name AS author_name FROM posts p LEFT JOIN users u ON u.id = p.author_id WHERE p.id = ? LIMIT 1',
            [$id]
        );
    }

    public function findPublishedById(int $id): ?array
    {
        return $this->db->fetch(
            'SELECT p.id FROM posts p WHERE p.id = ? AND ' . $this->publishedWhere('p') . ' LIMIT 1',
            [$id]
        );
    }

    /**
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function adminPaginate(string $status, string $search, int $page, int $perPage): array
    {
        $where = ['1 = 1'];
        $params = [];
        if (in_array($status, ['draft', 'published', 'scheduled'], true)) {
            $where[] = 'p.status = ?';
            $params[] = $status;
        }
        if ($search !== '') {
            $where[] = '(p.title LIKE ? OR p.slug LIKE ?)';
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
        }
        $whereSql = implode(' AND ', $where);
        $total = $this->db->count('SELECT COUNT(*) FROM posts p WHERE ' . $whereSql, $params);
        $offset = max(0, ($page - 1) * $perPage);
        $sql = "SELECT p.*, u.display_name AS author_name,
                (SELECT GROUP_CONCAT(c.name ORDER BY c.name SEPARATOR '、')
                 FROM post_categories pc INNER JOIN categories c ON c.id = pc.category_id
                 WHERE pc.post_id = p.id) AS category_names
             FROM posts p LEFT JOIN users u ON u.id = p.author_id
             WHERE " . $whereSql . " ORDER BY p.created_at DESC, p.id DESC
             LIMIT " . max(1, $perPage) . " OFFSET " . $offset;
        $items = $this->db->fetchAll($sql, $params);

        return ['items' => $items, 'total' => $total];
    }

    /** @param array<string, mixed> $data @param list<int> $categoryIds @param list<string> $tagNames */
    public function create(array $data, array $categoryIds, array $tagNames): int
    {
        $now = date('Y-m-d H:i:s');
        $data['created_at'] = $data['created_at'] ?? $now;
        $data['updated_at'] = $data['updated_at'] ?? $now;
        $data['slug'] = $this->uniqueSlug((string) ($data['slug'] ?? ''), (string) $data['title']);
        $id = $this->db->insert('posts', $data);
        $this->syncCategories($id, $categoryIds);
        $this->syncTags($id, $tagNames);
        $this->createRevision($id);
        $this->clearCache();

        return $id;
    }

    /** @param array<string, mixed> $data @param list<int> $categoryIds @param list<string> $tagNames */
    public function update(int $id, array $data, array $categoryIds, array $tagNames): void
    {
        $this->createRevision($id);
        $data['updated_at'] = $data['updated_at'] ?? date('Y-m-d H:i:s');
        $data['slug'] = $this->uniqueSlug((string) ($data['slug'] ?? ''), (string) $data['title'], $id);
        $this->db->update('posts', $data, ['id' => $id]);
        $this->syncCategories($id, $categoryIds);
        $this->syncTags($id, $tagNames);
        $this->clearCache();
    }

    public function delete(int $id): void
    {
        $this->db->transaction(function () use ($id): void {
            $this->db->delete('comments', ['post_id' => $id]);
            $this->db->delete('post_categories', ['post_id' => $id]);
            $this->db->delete('post_tags', ['post_id' => $id]);
            $this->db->delete('post_revisions', ['post_id' => $id]);
            $this->db->delete('posts', ['id' => $id]);
        });
        $this->clearCache();
    }

    public function incrementViews(int $id): void
    {
        $this->db->query('UPDATE posts SET views = views + 1 WHERE id = ?', [$id]);
    }

    public function uniqueSlug(string $slug, string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($slug !== '' ? $slug : $title);
        if ($base === '') {
            $base = 'post-' . date('YmdHis');
        }
        $candidate = $base;
        $counter = 2;
        if ((string) $this->app->options->get('permalink_style', 'post') === 'slug') {
            $reserved = ['admin', 'api', 'post', 'category', 'tag', 'page', 'search', 'archives', 'install', 'rss', 'feed', 'sitemap-xml', 'robots-txt', 'uploads', 'assets', 'themes', 'plugins', 'comments'];
            while (in_array($candidate, $reserved, true)
                || $this->db->fetch('SELECT id FROM pages WHERE slug = ? LIMIT 1', [$candidate]) !== null) {
                $candidate = $base . '-' . $counter++;
            }
        }
        while (true) {
            $params = [$candidate];
            $sql = 'SELECT id FROM posts WHERE slug = ?';
            if ($ignoreId !== null) {
                $sql .= ' AND id <> ?';
                $params[] = $ignoreId;
            }
            if ($this->db->fetch($sql . ' LIMIT 1', $params) === null) {
                return $candidate;
            }
            $candidate = $base . '-' . $counter++;
        }
    }

    /** @return list<int> */
    public function categoryIds(int $postId): array
    {
        $rows = $this->db->fetchAll('SELECT category_id FROM post_categories WHERE post_id = ?', [$postId]);

        return array_map(static fn (array $row): int => (int) $row['category_id'], $rows);
    }

    /** @return list<array<string, mixed>> */
    public function tags(int $postId): array
    {
        return $this->db->fetchAll(
            'SELECT t.* FROM tags t INNER JOIN post_tags pt ON pt.tag_id = t.id WHERE pt.post_id = ? ORDER BY t.name',
            [$postId]
        );
    }

    /** @return list<array<string, mixed>> */
    public function categories(int $postId): array
    {
        return $this->db->fetchAll(
            'SELECT c.* FROM categories c INNER JOIN post_categories pc ON pc.category_id = c.id WHERE pc.post_id = ? ORDER BY c.name',
            [$postId]
        );
    }

    /** @return list<array<string, mixed>> */
    public function revisions(int $postId): array
    {
        return $this->db->fetchAll(
            'SELECT r.*, u.display_name AS author_name FROM post_revisions r
             LEFT JOIN users u ON u.id = r.author_id
             WHERE r.post_id = ? ORDER BY r.created_at DESC, r.id DESC',
            [$postId]
        );
    }

    public function findRevision(int $postId, int $revisionId): ?array
    {
        return $this->db->fetch(
            'SELECT * FROM post_revisions WHERE id = ? AND post_id = ? LIMIT 1',
            [$revisionId, $postId]
        );
    }

    public function createRevision(int $postId): void
    {
        $post = $this->find($postId);
        if ($post === null) {
            return;
        }
        $this->db->insert('post_revisions', [
            'post_id'    => $postId,
            'title'      => $post['title'],
            'excerpt'    => $post['excerpt'],
            'content'    => $post['content'],
            'author_id'  => $post['author_id'],
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $this->db->query(
            'DELETE FROM post_revisions WHERE post_id = ? AND id NOT IN
             (SELECT id FROM (SELECT id FROM post_revisions WHERE post_id = ? ORDER BY id DESC LIMIT 30) keep)',
            [$postId, $postId]
        );
    }

    public function restoreRevision(int $postId, int $revisionId): bool
    {
        $revision = $this->findRevision($postId, $revisionId);
        if ($revision === null) {
            return false;
        }
        $this->createRevision($postId);
        $this->db->update('posts', [
            'title'        => $revision['title'],
            'excerpt'      => $revision['excerpt'],
            'content'      => $revision['content'],
            'updated_at'   => date('Y-m-d H:i:s'),
        ], ['id' => $postId]);
        $this->clearCache();

        return true;
    }

    /** @return array{previous: ?array, next: ?array} */
    public function adjacent(array $post): array
    {
        $date = (string) $post['published_at'];
        $previous = $this->db->fetch(
            'SELECT title, slug, published_at FROM posts p WHERE ' . $this->publishedWhere('p')
            . ' AND p.published_at < ? AND p.id <> ? ORDER BY p.published_at DESC LIMIT 1',
            [$date, (int) $post['id']]
        );
        $next = $this->db->fetch(
            'SELECT title, slug, published_at FROM posts p WHERE ' . $this->publishedWhere('p')
            . ' AND p.published_at > ? AND p.id <> ? ORDER BY p.published_at ASC LIMIT 1',
            [$date, (int) $post['id']]
        );

        return ['previous' => $previous, 'next' => $next];
    }

    /** @return list<array<string, mixed>> */
    public function related(array $post, int $limit = 3): array
    {
        $rows = $this->db->fetchAll(
            'SELECT DISTINCT p.*, u.display_name AS author_name FROM posts p
             INNER JOIN post_categories pc ON pc.post_id = p.id
             LEFT JOIN users u ON u.id = p.author_id
             WHERE pc.category_id IN (SELECT category_id FROM post_categories WHERE post_id = ?)
             AND p.id <> ? AND ' . $this->publishedWhere('p') . '
             ORDER BY p.published_at DESC LIMIT ' . max(1, $limit),
            [(int) $post['id'], (int) $post['id']]
        );
        if ($rows !== []) {
            return $rows;
        }

        return $this->db->fetchAll(
            'SELECT p.*, u.display_name AS author_name FROM posts p
             LEFT JOIN users u ON u.id = p.author_id
             WHERE p.id <> ? AND ' . $this->publishedWhere('p') . '
             ORDER BY p.published_at DESC LIMIT ' . max(1, $limit),
            [(int) $post['id']]
        );
    }

    /** @return list<array{year: int, month: int, count: int}> */
    public function archiveCounts(): array
    {
        return $this->db->fetchAll(
            'SELECT YEAR(p.published_at) AS year, MONTH(p.published_at) AS month, COUNT(*) AS count
             FROM posts p WHERE ' . $this->publishedWhere('p') . '
             GROUP BY YEAR(p.published_at), MONTH(p.published_at) ORDER BY year DESC, month DESC'
        );
    }

    /** @return array<string, int> */
    public function counts(): array
    {
        return [
            'total'     => $this->db->count('SELECT COUNT(*) FROM posts'),
            'published' => $this->db->count("SELECT COUNT(*) FROM posts WHERE status = 'published'"),
            'draft'     => $this->db->count("SELECT COUNT(*) FROM posts WHERE status = 'draft'"),
            'scheduled' => $this->db->count("SELECT COUNT(*) FROM posts WHERE status = 'scheduled'"),
            'views'     => (int) $this->db->value('SELECT COALESCE(SUM(views), 0) FROM posts'),
            'comments'  => $this->db->count("SELECT COUNT(*) FROM comments WHERE status = 'pending'"),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function latest(int $limit = 5): array
    {
        return $this->db->fetchAll(
            'SELECT p.id, p.title, p.slug, p.status, p.views, p.published_at, p.created_at
             FROM posts p WHERE ' . $this->publishedWhere('p') . '
             ORDER BY p.published_at DESC, p.id DESC LIMIT ' . max(1, $limit)
        );
    }

    /** @param list<int> $ids */
    private function syncCategories(int $postId, array $ids): void
    {
        $this->db->delete('post_categories', ['post_id' => $postId]);
        foreach (array_unique(array_filter(array_map('intval', $ids))) as $categoryId) {
            $this->db->query('INSERT IGNORE INTO post_categories (post_id, category_id) VALUES (?, ?)', [$postId, $categoryId]);
        }
    }

    /** @param list<string> $names */
    private function syncTags(int $postId, array $names): void
    {
        $this->db->delete('post_tags', ['post_id' => $postId]);
        $clean = [];
        foreach ($names as $name) {
            $name = trim($name);
            if ($name !== '' && !in_array($name, $clean, true)) {
                $clean[] = mb_substr($name, 0, 100, 'UTF-8');
            }
        }
        foreach ($clean as $name) {
            $tag = $this->db->fetch('SELECT id FROM tags WHERE name = ? LIMIT 1', [$name]);
            if ($tag === null) {
                $slug = $this->uniqueTagSlug($name);
                $tagId = $this->db->insert('tags', [
                    'name'       => $name,
                    'slug'       => $slug,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            } else {
                $tagId = (int) $tag['id'];
            }
            $this->db->query('INSERT IGNORE INTO post_tags (post_id, tag_id) VALUES (?, ?)', [$postId, $tagId]);
        }
    }

    private function uniqueTagSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'tag-' . Str::random(4);
        $candidate = $base;
        $counter = 2;
        while ($this->db->fetch('SELECT id FROM tags WHERE slug = ? LIMIT 1', [$candidate]) !== null) {
            $candidate = $base . '-' . $counter++;
        }

        return $candidate;
    }

    private function clearCache(): void
    {
        $this->app->cache->clear();
    }
}