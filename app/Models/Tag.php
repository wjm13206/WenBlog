<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\App;
use App\Core\Database;
use App\Core\Str;

final class Tag
{
    protected Database $db;

    public function __construct(protected readonly App $app)
    {
        $this->db = $app->db;
    }

    /** @return list<array<string, mixed>> */
    public function allWithCounts(): array
    {
        return $this->db->fetchAll(
            "SELECT t.*,
                (SELECT COUNT(DISTINCT pt.post_id) FROM post_tags pt
                 INNER JOIN posts p ON p.id = pt.post_id
                 WHERE pt.tag_id = t.id AND (p.status = 'published' OR (p.status = 'scheduled' AND p.published_at <= NOW()))
                ) AS post_count
             FROM tags t ORDER BY t.name ASC"
        );
    }

    public function findBySlug(string $slug): ?array
    {
        return $this->db->fetch('SELECT * FROM tags WHERE slug = ? LIMIT 1', [$slug]);
    }

    public function find(int $id): ?array
    {
        return $this->db->fetch('SELECT * FROM tags WHERE id = ? LIMIT 1', [$id]);
    }

    public function create(string $name, string $slug = ''): int
    {
        $name = mb_substr(trim($name), 0, 100, 'UTF-8');
        $id = $this->db->insert('tags', [
            'name'       => $name,
            'slug'       => $this->uniqueSlug($slug !== '' ? $slug : $name),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $this->app->cache->clear();

        return $id;
    }

    public function update(int $id, string $name, string $slug = ''): void
    {
        $this->db->update('tags', [
            'name' => mb_substr(trim($name), 0, 100, 'UTF-8'),
            'slug' => $this->uniqueSlug($slug !== '' ? $slug : $name, $id),
        ], ['id' => $id]);
        $this->app->cache->clear();
    }

    public function delete(int $id): void
    {
        $this->db->transaction(function () use ($id): void {
            $this->db->delete('post_tags', ['tag_id' => $id]);
            $this->db->delete('tags', ['id' => $id]);
        });
        $this->app->cache->clear();
    }

    public function uniqueSlug(string $value, ?int $ignoreId = null): string
    {
        $base = Str::slug($value) ?: 'tag-' . Str::random(4);
        $candidate = $base;
        $counter = 2;
        while (true) {
            $params = [$candidate];
            $sql = 'SELECT id FROM tags WHERE slug = ?';
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
}