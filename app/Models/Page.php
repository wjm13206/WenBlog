<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\App;
use App\Core\Database;
use App\Core\Str;

final class Page
{
    protected Database $db;

    public function __construct(protected readonly App $app)
    {
        $this->db = $app->db;
    }

    public function findPublishedBySlug(string $slug): ?array
    {
        return $this->db->fetch(
            "SELECT p.*, u.display_name AS author_name FROM pages p LEFT JOIN users u ON u.id = p.author_id
             WHERE p.slug = ? AND p.status = 'published' LIMIT 1",
            [$slug]
        );
    }

    public function find(int $id): ?array
    {
        return $this->db->fetch('SELECT * FROM pages WHERE id = ? LIMIT 1', [$id]);
    }

    /** @return array{items: list<array<string, mixed>>, total: int} */
    public function adminPaginate(int $page, int $perPage): array
    {
        $total = $this->db->count('SELECT COUNT(*) FROM pages');
        $offset = max(0, ($page - 1) * $perPage);
        $items = $this->db->fetchAll(
            'SELECT p.*, u.display_name AS author_name FROM pages p LEFT JOIN users u ON u.id = p.author_id
             ORDER BY p.created_at DESC LIMIT ' . max(1, $perPage) . ' OFFSET ' . $offset
        );

        return ['items' => $items, 'total' => $total];
    }

    /** @return list<array<string, mixed>> */
    public function published(): array
    {
        return $this->db->fetchAll("SELECT * FROM pages WHERE status = 'published' ORDER BY created_at ASC");
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $now = date('Y-m-d H:i:s');
        $data['created_at'] = $data['created_at'] ?? $now;
        $data['updated_at'] = $data['updated_at'] ?? $now;
        $data['slug'] = $this->uniqueSlug((string) ($data['slug'] ?? ''), (string) $data['title']);
        $id = $this->db->insert('pages', $data);
        $this->app->cache->clear();

        return $id;
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): void
    {
        $data['updated_at'] = $data['updated_at'] ?? date('Y-m-d H:i:s');
        $data['slug'] = $this->uniqueSlug((string) ($data['slug'] ?? ''), (string) $data['title'], $id);
        $this->db->update('pages', $data, ['id' => $id]);
        $this->app->cache->clear();
    }

    public function delete(int $id): void
    {
        $this->db->delete('pages', ['id' => $id]);
        $this->app->cache->clear();
    }

    public function uniqueSlug(string $slug, string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($slug !== '' ? $slug : $title);
        if ($base === '') {
            $base = 'page-' . date('YmdHis');
        }
        $reserved = ['admin', 'api', 'post', 'category', 'tag', 'page', 'search', 'archives', 'install', 'rss', 'feed', 'sitemap.xml', 'robots.txt', 'uploads', 'assets', 'themes', 'plugins', 'comments'];
        if (in_array($base, $reserved, true)) {
            $base .= '-page';
        }
        if ((string) $this->app->options->get('permalink_style', 'post') === 'slug'
            && $this->db->fetch('SELECT id FROM posts WHERE slug = ? LIMIT 1', [$base]) !== null) {
            $base .= '-page';
        }
        $candidate = $base;
        $counter = 2;
        while (true) {
            $params = [$candidate];
            $sql = 'SELECT id FROM pages WHERE slug = ?';
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

    /** @return list<array{slug: string, title: string}> */
    public function options(): array
    {
        return $this->db->fetchAll("SELECT slug, title FROM pages WHERE status = 'published' ORDER BY title");
    }
}