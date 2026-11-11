<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\App;
use App\Core\Database;
use App\Core\Str;

final class Category
{
    protected Database $db;

    public function __construct(protected readonly App $app)
    {
        $this->db = $app->db;
    }

    /** @return list<array<string, mixed>> flat list ordered by parent */
    public function allWithCounts(): array
    {
        $rows = $this->db->fetchAll(
            "SELECT c.*,
                (SELECT COUNT(DISTINCT pc.post_id) FROM post_categories pc
                 INNER JOIN posts p ON p.id = pc.post_id
                 WHERE pc.category_id = c.id AND (p.status = 'published' OR (p.status = 'scheduled' AND p.published_at <= NOW()))
                ) AS post_count
             FROM categories c ORDER BY c.sort_order ASC, c.name ASC"
        );

        // Parent categories represent their whole subtree. Build unique post-id
        // sets per subtree so a post assigned to both a parent and a child is
        // only counted once.
        $children = [];
        foreach ($rows as $row) {
            $parent = $row['parent_id'] !== null ? (int) $row['parent_id'] : 0;
            $children[$parent][] = (int) $row['id'];
        }

        $mappingRows = $this->db->fetchAll(
            "SELECT pc.category_id, pc.post_id FROM post_categories pc
             INNER JOIN posts p ON p.id = pc.post_id
             WHERE p.status = 'published' OR (p.status = 'scheduled' AND p.published_at <= NOW())"
        );
        $directPosts = [];
        foreach ($mappingRows as $mapping) {
            $directPosts[(int) $mapping['category_id']][(int) $mapping['post_id']] = true;
        }

        $memo = [];
        $visiting = [];
        $collect = function (int $id) use (&$collect, &$children, &$directPosts, &$memo, &$visiting): array {
            if (isset($memo[$id])) {
                return $memo[$id];
            }
            if (isset($visiting[$id])) {
                return [];
            }
            $visiting[$id] = true;
            $set = $directPosts[$id] ?? [];
            foreach ($children[$id] ?? [] as $child) {
                $set += $collect($child);
            }
            unset($visiting[$id]);

            return $memo[$id] = $set;
        };

        foreach ($rows as &$row) {
            $row['post_count'] = count($collect((int) $row['id']));
        }
        unset($row);

        return $this->buildTree($rows);
    }

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        return $this->db->fetchAll('SELECT * FROM categories ORDER BY sort_order ASC, name ASC');
    }

    public function findBySlug(string $slug): ?array
    {
        return $this->db->fetch('SELECT * FROM categories WHERE slug = ? LIMIT 1', [$slug]);
    }

    public function find(int $id): ?array
    {
        return $this->db->fetch('SELECT * FROM categories WHERE id = ? LIMIT 1', [$id]);
    }

    /** @return list<array<string, mixed>> */
    public function breadcrumb(?array $category): array
    {
        $trail = [];
        $guard = 0;
        while ($category !== null && $guard < 10) {
            array_unshift($trail, $category);
            $category = $category['parent_id'] !== null ? $this->find((int) $category['parent_id']) : null;
            $guard++;
        }

        return $trail;
    }

    public function create(string $name, string $slug, ?int $parentId, string $description, int $sortOrder): int
    {
        if ($parentId !== null && $this->find($parentId) === null) {
            $parentId = null;
        }
        $id = $this->db->insert('categories', [
            'name'        => mb_substr(trim($name), 0, 100, 'UTF-8'),
            'slug'        => $this->uniqueSlug($slug !== '' ? $slug : $name),
            'parent_id'   => $parentId,
            'description' => $description,
            'sort_order'  => $sortOrder,
            'created_at'  => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);
        $this->app->cache->clear();

        return $id;
    }

    public function update(int $id, string $name, string $slug, ?int $parentId, string $description, int $sortOrder): void
    {
        if ($parentId !== null && ($parentId === $id || $this->find($parentId) === null || $this->isDescendant($parentId, $id))) {
            $parentId = null;
        }
        $this->db->update('categories', [
            'name'        => mb_substr(trim($name), 0, 100, 'UTF-8'),
            'slug'        => $this->uniqueSlug($slug !== '' ? $slug : $name, $id),
            'parent_id'   => $parentId,
            'description' => $description,
            'sort_order'  => $sortOrder,
            'updated_at'  => date('Y-m-d H:i:s'),
        ], ['id' => $id]);
        $this->app->cache->clear();
    }

    public function delete(int $id): void
    {
        $category = $this->find($id);
        if ($category === null) {
            return;
        }
        $this->db->transaction(function () use ($id, $category): void {
            $this->db->update('categories', ['parent_id' => $category['parent_id']], ['parent_id' => $id]);
            $this->db->delete('post_categories', ['category_id' => $id]);
            $this->db->delete('categories', ['id' => $id]);
        });
        $this->app->cache->clear();
    }

    public function uniqueSlug(string $value, ?int $ignoreId = null): string
    {
        $base = Str::slug($value) ?: 'category-' . Str::random(4);
        $candidate = $base;
        $counter = 2;
        while (true) {
            $params = [$candidate];
            $sql = 'SELECT id FROM categories WHERE slug = ?';
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

    /** @param list<array<string, mixed>> $rows */
    private function buildTree(array $rows): array
    {
        $byParent = [];
        foreach ($rows as $row) {
            $byParent[$row['parent_id'] === null ? 0 : (int) $row['parent_id']][] = $row;
        }
        $result = [];
        $walk = static function (int $parentId, int $depth) use (&$walk, &$result, $byParent): void {
            foreach ($byParent[$parentId] ?? [] as $row) {
                $row['depth'] = $depth;
                $result[] = $row;
                $walk((int) $row['id'], $depth + 1);
            }
        };
        $walk(0, 0);

        return $result;
    }

    /**
     * Return a category and all of its descendants.
     *
     * @return list<int>
     */
    public function descendantIds(int $id): array
    {
        $rows = $this->db->fetchAll('SELECT id, parent_id FROM categories');
        $children = [];
        foreach ($rows as $row) {
            $parentId = $row['parent_id'] !== null ? (int) $row['parent_id'] : 0;
            $children[$parentId][] = (int) $row['id'];
        }

        $ids = [$id];
        $queue = [$id];
        while ($queue !== []) {
            $current = array_shift($queue);
            foreach ($children[$current] ?? [] as $child) {
                if (!in_array($child, $ids, true)) {
                    $ids[] = $child;
                    $queue[] = $child;
                }
            }
        }

        return $ids;
    }

    private function isDescendant(int $candidateId, int $ancestorId): bool
    {
        $guard = 0;
        $current = $this->find($candidateId);
        while ($current !== null && $guard < 20) {
            if ((int) $current['parent_id'] === $ancestorId) {
                return true;
            }
            $current = $current['parent_id'] !== null ? $this->find((int) $current['parent_id']) : null;
            $guard++;
        }

        return false;
    }
}