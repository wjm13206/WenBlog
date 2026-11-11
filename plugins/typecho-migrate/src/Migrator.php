<?php

declare(strict_types=1);

namespace WenBlog\Plugins\TypechoMigrate;

use App\Core\App;
use App\Core\Str;
use App\Models\Category;
use App\Models\Page;
use App\Models\Post;
use App\Models\Tag;
use RuntimeException;

/**
 * Detects Typecho tables in the current database and copies their content into
 * the WenBlog schema.
 *
 * Every migrated source row is recorded in `typecho_migrate_map`, which makes
 * repeated runs idempotent and lets the importer resolve relationships and
 * comment parents created in different passes.
 */
final class Migrator
{
    private const MAP_TABLE = 'typecho_migrate_map';

    private const CONTENT_COLUMNS = [
        'cid', 'title', 'slug', 'created', 'modified', 'text', 'authorId',
        'type', 'status', 'commentsNum', 'allowComment',
    ];

    private const META_COLUMNS = ['mid', 'name', 'slug', 'type', 'parent', 'description', 'order'];

    private const RELATION_COLUMNS = ['cid', 'mid'];

    private string $prefix;

    /** @var array<string, array<int, int>> entity => source id => target id */
    private array $map = [];

    /** @var array<int, int> Typecho uid => WenBlog user id */
    private array $userMap = [];

    /** @var array<int, string> Typecho uid => display name */
    private array $userNames = [];

    private int $defaultAuthor = 0;

    /**
     * @var array<string, int>
     */
    private array $stats = [];

    public function __construct(private readonly App $app, string $prefix = '')
    {
        $this->prefix = self::normalizePrefix($prefix);
    }

    /**
     * Inspect the database without writing anything.
     *
     * @return array<string, mixed>
     */
    public function detect(): array
    {
        $candidates = $this->candidatePrefixes();
        $prefix = $this->prefix;
        if ($prefix === '') {
            $prefix = in_array('typecho_', $candidates, true) ? 'typecho_' : ($candidates[0] ?? '');
        }

        $tables = [];
        foreach (['contents', 'metas', 'relationships', 'comments', 'users', 'fields', 'options'] as $suffix) {
            $tables[$suffix] = $prefix !== '' && $this->tableExists($prefix . $suffix);
        }

        $missing = [];
        if ($prefix !== '') {
            if ($tables['contents']) {
                $missing['contents'] = array_values(array_diff(self::CONTENT_COLUMNS, $this->columns($prefix . 'contents')));
            }
            if ($tables['metas']) {
                $missing['metas'] = array_values(array_diff(self::META_COLUMNS, $this->columns($prefix . 'metas')));
            }
            if ($tables['relationships']) {
                $missing['relationships'] = array_values(array_diff(self::RELATION_COLUMNS, $this->columns($prefix . 'relationships')));
            }
        }
        $missing = array_filter($missing, static fn (array $columns): bool => $columns !== []);

        $available = $prefix !== ''
            && $tables['contents']
            && $tables['metas']
            && $tables['relationships']
            && $missing === [];

        return [
            'candidates'  => $candidates,
            'prefix'      => $prefix,
            'available'   => $available,
            'tables'      => $tables,
            'missing'     => $missing,
            'counts'      => $this->counts($prefix, $tables),
            'map'         => $this->mapStats($prefix),
        ];
    }

    /**
     * Copy Typecho rows into WenBlog.
     *
     * @param array{import_pages?: bool, import_drafts?: bool, import_comments?: bool, default_author?: int} $options
     * @return array<string, int>
     */
    public function run(array $options): array
    {
        $detection = $this->detect();
        if (($detection['available'] ?? false) !== true) {
            throw new RuntimeException('没有检测到结构完整的 Typecho 数据表，请确认前缀或先把 Typecho 数据库导入当前数据库。');
        }

        $this->prefix = (string) $detection['prefix'];
        $this->defaultAuthor = $this->resolveDefaultAuthor((int) ($options['default_author'] ?? 0));
        $this->stats = array_fill_keys([
            'posts', 'pages', 'drafts', 'categories', 'tags', 'relations', 'comments',
            'skipped_contents', 'skipped_comments', 'skipped_metas',
            'users_matched', 'users_fallback', 'attachments', 'fields',
        ], 0);

        $this->ensureMapTable();
        $this->loadMap();

        $this->app->db->transaction(function () use ($options): void {
            $this->importUsers();
            $this->importMetas();
            $this->importContents($options);
            $this->importRelationships();
            if (!empty($options['import_comments'])) {
                $this->importComments();
            }
            $this->refreshCommentCounts();
        });

        $this->app->cache->clear();

        return $this->stats;
    }

    /** @return list<string> */
    private function candidatePrefixes(): array
    {
        $rows = $this->app->db->fetchAll(
            'SELECT TABLE_NAME AS table_name FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()'
        );
        $actual = [];
        foreach ($rows as $row) {
            $name = (string) ($row['table_name'] ?? '');
            if ($name !== '') {
                $actual[strtolower($name)] = $name;
            }
        }

        $prefixes = [];
        foreach ($actual as $lower => $name) {
            if (!str_ends_with($lower, 'contents')) {
                continue;
            }
            $prefix = substr($name, 0, -strlen('contents'));
            $prefixLower = strtolower($prefix);
            if ($prefix !== '' && !str_ends_with($prefix, '_')) {
                continue;
            }
            if (isset($actual[$prefixLower . 'metas'], $actual[$prefixLower . 'relationships'])) {
                $prefixes[] = $prefix;
            }
        }

        usort($prefixes, static function (string $a, string $b): int {
            if ($a === 'typecho_') {
                return -1;
            }
            if ($b === 'typecho_') {
                return 1;
            }

            return strlen($a) <=> strlen($b);
        });

        return array_values(array_unique($prefixes));
    }

    /**
     * @param array<string, bool> $tables
     * @return array<string, int>
     */
    private function counts(string $prefix, array $tables): array
    {
        $counts = [
            'posts' => 0, 'pages' => 0, 'drafts' => 0, 'categories' => 0, 'tags' => 0,
            'comments' => 0, 'comments_approved' => 0, 'comments_pending' => 0, 'comments_spam' => 0,
            'users' => 0, 'fields' => 0, 'attachments' => 0, 'revisions' => 0,
        ];
        if ($prefix === '') {
            return $counts;
        }

        if ($tables['contents'] ?? false) {
            $rows = $this->app->db->fetchAll(
                'SELECT `type`, `status`, COUNT(*) AS total FROM `' . $prefix . 'contents` GROUP BY `type`, `status`'
            );
            foreach ($rows as $row) {
                $type = (string) $row['type'];
                $status = (string) $row['status'];
                $total = (int) $row['total'];
                if ($type === 'post' && $status === 'publish') {
                    $counts['posts'] += $total;
                } elseif ($type === 'page' && $status === 'publish') {
                    $counts['pages'] += $total;
                } elseif ($type === 'attachment') {
                    $counts['attachments'] += $total;
                } elseif ($type === 'revision') {
                    $counts['revisions'] += $total;
                } elseif (in_array($type, ['post', 'page', 'post_draft', 'page_draft'], true)) {
                    $counts['drafts'] += $total;
                }
            }
        }

        if ($tables['metas'] ?? false) {
            $rows = $this->app->db->fetchAll(
                'SELECT `type`, COUNT(*) AS total FROM `' . $prefix . 'metas` GROUP BY `type`'
            );
            foreach ($rows as $row) {
                if ((string) $row['type'] === 'category') {
                    $counts['categories'] = (int) $row['total'];
                } elseif ((string) $row['type'] === 'tag') {
                    $counts['tags'] = (int) $row['total'];
                }
            }
        }

        if ($tables['comments'] ?? false) {
            $rows = $this->app->db->fetchAll(
                'SELECT `status`, COUNT(*) AS total FROM `' . $prefix . 'comments` GROUP BY `status`'
            );
            foreach ($rows as $row) {
                $total = (int) $row['total'];
                $counts['comments'] += $total;
                if ((string) $row['status'] === 'approved') {
                    $counts['comments_approved'] += $total;
                } elseif ((string) $row['status'] === 'spam') {
                    $counts['comments_spam'] += $total;
                } else {
                    $counts['comments_pending'] += $total;
                }
            }
        }

        if ($tables['users'] ?? false) {
            $counts['users'] = $this->app->db->count('SELECT COUNT(*) FROM `' . $prefix . 'users`');
        }
        if ($tables['fields'] ?? false) {
            $counts['fields'] = $this->app->db->count('SELECT COUNT(*) FROM `' . $prefix . 'fields`');
        }

        return $counts;
    }

    /** @return array{total: int, entities: array<string, int>} */
    private function mapStats(string $prefix): array
    {
        if ($prefix === '' || !$this->tableExists(self::MAP_TABLE)) {
            return ['total' => 0, 'entities' => []];
        }
        $rows = $this->app->db->fetchAll(
            'SELECT `entity`, COUNT(*) AS total FROM `' . self::MAP_TABLE . '` WHERE `source_prefix` = ? GROUP BY `entity`',
            [$prefix]
        );
        $entities = [];
        $total = 0;
        foreach ($rows as $row) {
            $entities[(string) $row['entity']] = (int) $row['total'];
            $total += (int) $row['total'];
        }

        return ['total' => $total, 'entities' => $entities];
    }

    private function resolveDefaultAuthor(int $userId): int
    {
        if ($userId > 0 && $this->app->db->fetch('SELECT id FROM users WHERE id = ? LIMIT 1', [$userId]) !== null) {
            return $userId;
        }
        $current = $this->app->auth->user();

        return (int) ($current['id'] ?? 0);
    }

    private function importUsers(): void
    {
        if (!$this->tableExists($this->prefix . 'users')) {
            return;
        }

        $rows = $this->app->db->fetchAll(
            'SELECT `uid`, `name`, `mail`, `screenName` FROM `' . $this->prefix . 'users` ORDER BY `uid` ASC'
        );
        foreach ($rows as $row) {
            $uid = (int) $row['uid'];
            $name = trim((string) $row['name']);
            $mail = trim((string) $row['mail']);
            $this->userNames[$uid] = trim((string) $row['screenName']) ?: $name;

            $local = null;
            if ($mail !== '') {
                $local = $this->app->db->fetch('SELECT id FROM users WHERE email = ? LIMIT 1', [$mail]);
            }
            if ($local === null && $name !== '') {
                $local = $this->app->db->fetch('SELECT id FROM users WHERE username = ? LIMIT 1', [$name]);
            }

            if ($local !== null) {
                $this->userMap[$uid] = (int) $local['id'];
                $this->stats['users_matched']++;
            } else {
                $this->userMap[$uid] = $this->defaultAuthor;
                $this->stats['users_fallback']++;
            }
        }
    }

    private function importMetas(): void
    {
        $rows = $this->app->db->fetchAll(
            'SELECT * FROM `' . $this->prefix . 'metas` WHERE `type` IN (\'category\', \'tag\')
             ORDER BY `order` ASC, `mid` ASC'
        );

        $categories = [];
        foreach ($rows as $row) {
            if ((string) $row['type'] === 'category') {
                $categories[] = $row;
            } else {
                $this->importTag($row);
            }
        }

        // Categories can reference a parent that appears later in the result set.
        $pending = $categories;
        $guard = 0;
        while ($pending !== [] && $guard++ < 50) {
            $progress = false;
            foreach ($pending as $key => $row) {
                $parent = (int) $row['parent'];
                if ($parent > 0 && !isset($this->map['category'][$parent])) {
                    continue;
                }
                $this->importCategory($row, $parent > 0 ? $this->map['category'][$parent] : null);
                unset($pending[$key]);
                $progress = true;
            }
            if (!$progress) {
                foreach ($pending as $row) {
                    $this->importCategory($row, null);
                }
                $pending = [];
            }
        }
    }

    /** @param array<string, mixed> $row */
    private function importCategory(array $row, ?int $parentId): void
    {
        $mid = (int) $row['mid'];
        if (isset($this->map['category'][$mid])) {
            $this->stats['skipped_metas']++;

            return;
        }

        $name = trim((string) $row['name']) ?: '未命名分类';
        $slug = $this->cleanSlug((string) $row['slug'], $name);
        $existing = $this->app->db->fetch(
            'SELECT id FROM categories WHERE slug = ? OR name = ? LIMIT 1',
            [$slug, $name]
        );
        if ($existing !== null) {
            $id = (int) $existing['id'];
        } else {
            $id = (new Category($this->app))->create(
                $name,
                $slug,
                $parentId,
                mb_substr(trim((string) $row['description']), 0, 255, 'UTF-8'),
                (int) $row['order']
            );
        }

        $this->map['category'][$mid] = $id;
        $this->recordMap('category', $mid, $id);
        $this->stats['categories']++;
    }

    /** @param array<string, mixed> $row */
    private function importTag(array $row): void
    {
        $mid = (int) $row['mid'];
        if (isset($this->map['tag'][$mid])) {
            $this->stats['skipped_metas']++;

            return;
        }

        $name = mb_substr(trim((string) $row['name']) ?: '未命名标签', 0, 100, 'UTF-8');
        $slug = $this->cleanSlug((string) $row['slug'], $name);
        $existing = $this->app->db->fetch(
            'SELECT id FROM tags WHERE name = ? OR slug = ? LIMIT 1',
            [$name, $slug]
        );
        if ($existing !== null) {
            $id = (int) $existing['id'];
        } else {
            $id = (new Tag($this->app))->create($name, $slug);
        }

        $this->map['tag'][$mid] = $id;
        $this->recordMap('tag', $mid, $id);
        $this->stats['tags']++;
    }

    /** @param array<string, mixed> $options */
    private function importContents(array $options): void
    {
        $types = ['post', 'page'];
        if (!empty($options['import_drafts'])) {
            $types[] = 'post_draft';
            $types[] = 'page_draft';
        }
        $placeholders = implode(', ', array_fill(0, count($types), '?'));
        $rows = $this->app->db->fetchAll(
            'SELECT * FROM `' . $this->prefix . 'contents` WHERE `type` IN (' . $placeholders . ')
             ORDER BY `created` ASC, `cid` ASC',
            $types
        );

        foreach ($rows as $row) {
            $cid = (int) $row['cid'];
            $type = (string) $row['type'];
            $isPage = in_array($type, ['page', 'page_draft'], true);
            $entity = $isPage ? 'page' : 'post';

            if (isset($this->map[$entity][$cid])) {
                $this->stats['skipped_contents']++;

                continue;
            }

            $isDraftType = str_ends_with($type, '_draft');
            $published = !$isDraftType && (string) $row['status'] === 'publish';
            $status = $published ? 'published' : 'draft';
            if (!$published && empty($options['import_drafts'])) {
                $this->stats['skipped_contents']++;

                continue;
            }
            if ($isPage && empty($options['import_pages'])) {
                $this->stats['skipped_contents']++;

                continue;
            }

            $rawText = (string) $row['text'];
            $title = trim((string) $row['title']);
            $title = $title !== '' ? $title : ($isPage ? '未命名页面' : '未命名文章');
            $created = (int) $row['created'];
            $modified = (int) $row['modified'];
            $createdAt = $this->date($created);
            $updatedAt = $this->date($modified > 0 ? $modified : $created);
            $authorId = $this->authorFor((int) $row['authorId']);
            $excerpt = Content::excerptFrom($rawText);
            $content = Content::toMarkdown($rawText);

            if ($isPage) {
                $pageModel = new Page($this->app);
                $id = $this->app->db->insert('pages', [
                    'title'      => mb_substr($title, 0, 255, 'UTF-8'),
                    'slug'       => $pageModel->uniqueSlug($this->cleanSlug((string) $row['slug'], $title), $title),
                    'excerpt'    => $excerpt,
                    'content'    => $content,
                    'template'   => '',
                    'status'     => $status,
                    'author_id'  => $authorId,
                    'created_at' => $createdAt,
                    'updated_at' => $updatedAt,
                ]);
                $this->stats[$published ? 'pages' : 'drafts']++;
            } else {
                $postModel = new Post($this->app);
                $id = $this->app->db->insert('posts', [
                    'title'          => mb_substr($title, 0, 255, 'UTF-8'),
                    'slug'           => $postModel->uniqueSlug($this->cleanSlug((string) $row['slug'], $title), $title),
                    'excerpt'        => $excerpt,
                    'content'        => $content,
                    'cover'          => '',
                    'status'         => $status,
                    'author_id'      => $authorId,
                    'allow_comments' => (string) $row['allowComment'] === '1' ? 1 : 0,
                    'pinned'         => 0,
                    'views'          => 0,
                    'comment_count'  => 0,
                    'published_at'   => $published ? $createdAt : null,
                    'created_at'     => $createdAt,
                    'updated_at'     => $updatedAt,
                ]);
                $this->stats[$published ? 'posts' : 'drafts']++;
            }

            $this->map[$entity][$cid] = $id;
            $this->recordMap($entity, $cid, $id);
        }

        if ($this->tableExists($this->prefix . 'contents')) {
            $this->stats['attachments'] = $this->app->db->count(
                'SELECT COUNT(*) FROM `' . $this->prefix . 'contents` WHERE `type` = \'attachment\''
            );
        }
        if ($this->tableExists($this->prefix . 'fields')) {
            $this->stats['fields'] = $this->app->db->count('SELECT COUNT(*) FROM `' . $this->prefix . 'fields`');
        }
    }

    private function importRelationships(): void
    {
        $rows = $this->app->db->fetchAll('SELECT `cid`, `mid` FROM `' . $this->prefix . 'relationships`');
        foreach ($rows as $row) {
            $cid = (int) $row['cid'];
            $mid = (int) $row['mid'];
            $postId = $this->map['post'][$cid] ?? null;
            if ($postId === null) {
                continue;
            }

            if (isset($this->map['category'][$mid])) {
                $statement = $this->app->db->query(
                    'INSERT IGNORE INTO post_categories (post_id, category_id) VALUES (?, ?)',
                    [$postId, $this->map['category'][$mid]]
                );
                $this->stats['relations'] += $statement->rowCount();
            } elseif (isset($this->map['tag'][$mid])) {
                $statement = $this->app->db->query(
                    'INSERT IGNORE INTO post_tags (post_id, tag_id) VALUES (?, ?)',
                    [$postId, $this->map['tag'][$mid]]
                );
                $this->stats['relations'] += $statement->rowCount();
            }
        }
    }

    private function importComments(): void
    {
        if (!$this->tableExists($this->prefix . 'comments')) {
            return;
        }

        $rows = $this->app->db->fetchAll(
            'SELECT * FROM `' . $this->prefix . 'comments` ORDER BY `coid` ASC'
        );
        $parents = [];

        foreach ($rows as $row) {
            $coid = (int) $row['coid'];
            if (isset($this->map['comment'][$coid])) {
                $this->stats['skipped_comments']++;

                continue;
            }
            if ((string) $row['type'] !== 'comment') {
                $this->stats['skipped_comments']++;

                continue;
            }

            $postId = $this->map['post'][(int) $row['cid']] ?? null;
            if ($postId === null) {
                $this->stats['skipped_comments']++;

                continue;
            }

            $authorId = (int) $row['authorId'];
            $status = match ((string) $row['status']) {
                'approved' => 'approved',
                'spam'     => 'spam',
                default    => 'pending',
            };
            $authorName = trim((string) $row['author']);
            if ($authorName === '') {
                $authorName = $this->userNames[$authorId] ?? '访客';
            }
            $url = trim((string) ($row['url'] ?? ''));
            $createdAt = $this->date((int) $row['created']);

            $id = $this->app->db->insert('comments', [
                'post_id'      => $postId,
                'parent_id'    => null,
                'user_id'      => $authorId > 0 && isset($this->userMap[$authorId]) ? $this->userMap[$authorId] : null,
                'author_name'  => mb_substr($authorName, 0, 50, 'UTF-8'),
                'author_email' => mb_substr(trim((string) ($row['mail'] ?? '')), 0, 190, 'UTF-8'),
                'author_url'   => $url !== '' ? mb_substr($url, 0, 255, 'UTF-8') : null,
                'content'      => (string) $row['text'],
                'status'       => $status,
                'ip'           => mb_substr(trim((string) ($row['ip'] ?? '')), 0, 45, 'UTF-8'),
                'user_agent'   => mb_substr(trim((string) ($row['agent'] ?? '')), 0, 255, 'UTF-8'),
                'created_at'   => $createdAt,
                'updated_at'   => $createdAt,
            ]);

            $this->map['comment'][$coid] = $id;
            $this->recordMap('comment', $coid, $id);
            $this->stats['comments']++;

            if ((int) $row['parent'] > 0) {
                $parents[$id] = (int) $row['parent'];
            }
        }

        foreach ($parents as $id => $parentCoid) {
            $parentId = $this->map['comment'][$parentCoid] ?? null;
            if ($parentId !== null) {
                $this->app->db->update('comments', ['parent_id' => $parentId], ['id' => $id]);
            }
        }
    }

    private function refreshCommentCounts(): void
    {
        $this->app->db->query(
            "UPDATE posts SET comment_count = (
                SELECT COUNT(*) FROM comments c WHERE c.post_id = posts.id AND c.status = 'approved'
             )"
        );
    }

    private function authorFor(int $uid): int
    {
        if ($uid > 0 && isset($this->userMap[$uid])) {
            return $this->userMap[$uid];
        }

        return $this->defaultAuthor;
    }

    private function cleanSlug(string $slug, string $fallback): string
    {
        $slug = trim(rawurldecode($slug));

        return Str::slug($slug !== '' ? $slug : $fallback);
    }

    private function date(int $timestamp): string
    {
        if ($timestamp <= 0) {
            return date('Y-m-d H:i:s');
        }

        return date('Y-m-d H:i:s', $timestamp);
    }

    private function ensureMapTable(): void
    {
        $this->app->db->query(
            'CREATE TABLE IF NOT EXISTS `' . self::MAP_TABLE . '` (
                `source_prefix` VARCHAR(64) NOT NULL DEFAULT \'\',
                `entity` VARCHAR(20) NOT NULL,
                `source_id` INT UNSIGNED NOT NULL,
                `target_id` INT UNSIGNED NOT NULL,
                `created_at` DATETIME NOT NULL,
                PRIMARY KEY (`source_prefix`, `entity`, `source_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    private function loadMap(): void
    {
        $this->map = [];
        $rows = $this->app->db->fetchAll(
            'SELECT `entity`, `source_id`, `target_id` FROM `' . self::MAP_TABLE . '` WHERE `source_prefix` = ?',
            [$this->prefix]
        );
        foreach ($rows as $row) {
            $this->map[(string) $row['entity']][(int) $row['source_id']] = (int) $row['target_id'];
        }
    }

    private function recordMap(string $entity, int $sourceId, int $targetId): void
    {
        $this->app->db->query(
            'INSERT INTO `' . self::MAP_TABLE . '` (`source_prefix`, `entity`, `source_id`, `target_id`, `created_at`)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE `target_id` = VALUES(`target_id`)',
            [$this->prefix, $entity, $sourceId, $targetId, date('Y-m-d H:i:s')]
        );
    }

    private function tableExists(string $table): bool
    {
        return (int) $this->app->db->value(
            'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            [$table]
        ) > 0;
    }

    /** @return list<string> */
    private function columns(string $table): array
    {
        $rows = $this->app->db->fetchAll('SHOW COLUMNS FROM `' . $this->quoteTable($table) . '`');

        return array_map(static fn (array $row): string => (string) $row['Field'], $rows);
    }

    private function quoteTable(string $table): string
    {
        if (preg_match('/^[A-Za-z0-9_]+$/', $table) !== 1) {
            throw new RuntimeException('数据表名无效。');
        }

        return $table;
    }

    private static function normalizePrefix(string $prefix): string
    {
        $prefix = trim($prefix);
        if ($prefix === '') {
            return '';
        }
        if (preg_match('/^[A-Za-z0-9_]+$/', $prefix) !== 1) {
            throw new RuntimeException('数据表前缀只能包含字母、数字和下划线。');
        }

        return str_ends_with($prefix, '_') ? $prefix : $prefix . '_';
    }
}
