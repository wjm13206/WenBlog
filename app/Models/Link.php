<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\App;
use App\Core\Database;

final class Link
{
    protected Database $db;

    public function __construct(protected readonly App $app)
    {
        $this->db = $app->db;
    }

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        return $this->db->fetchAll('SELECT * FROM links ORDER BY sort_order ASC, id ASC');
    }

    public function find(int $id): ?array
    {
        return $this->db->fetch('SELECT * FROM links WHERE id = ? LIMIT 1', [$id]);
    }

    public function create(string $name, string $url, string $description, int $sortOrder): int
    {
        $id = $this->db->insert('links', [
            'name'        => mb_substr(trim($name), 0, 100, 'UTF-8'),
            'url'         => mb_substr(trim($url), 0, 255, 'UTF-8'),
            'description' => mb_substr(trim($description), 0, 255, 'UTF-8'),
            'sort_order'  => $sortOrder,
            'created_at'  => date('Y-m-d H:i:s'),
        ]);
        $this->app->cache->clear();

        return $id;
    }

    public function update(int $id, string $name, string $url, string $description, int $sortOrder): void
    {
        $this->db->update('links', [
            'name'        => mb_substr(trim($name), 0, 100, 'UTF-8'),
            'url'         => mb_substr(trim($url), 0, 255, 'UTF-8'),
            'description' => mb_substr(trim($description), 0, 255, 'UTF-8'),
            'sort_order'  => $sortOrder,
        ], ['id' => $id]);
        $this->app->cache->clear();
    }

    public function delete(int $id): void
    {
        $this->db->delete('links', ['id' => $id]);
        $this->app->cache->clear();
    }
}