<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\App;
use App\Core\Database;
use App\Core\Security;
use App\Core\Str;
use RuntimeException;

final class Media
{
    protected Database $db;

    public function __construct(protected readonly App $app)
    {
        $this->db = $app->db;
    }

    /** @return array{items: list<array<string, mixed>>, total: int} */
    public function paginate(int $page, int $perPage): array
    {
        $total = $this->db->count('SELECT COUNT(*) FROM media');
        $offset = max(0, ($page - 1) * $perPage);
        $items = $this->db->fetchAll(
            'SELECT m.*, u.display_name AS uploader FROM media m LEFT JOIN users u ON u.id = m.uploaded_by
             ORDER BY m.created_at DESC LIMIT ' . max(1, $perPage) . ' OFFSET ' . $offset
        );

        return ['items' => $items, 'total' => $total];
    }

    public function find(int $id): ?array
    {
        return $this->db->fetch('SELECT * FROM media WHERE id = ? LIMIT 1', [$id]);
    }

    public function updateAlt(int $id, string $alt): void
    {
        $this->db->update('media', [
            'alt' => mb_substr(trim($alt), 0, 190, 'UTF-8'),
        ], ['id' => $id]);
        $this->app->cache->clear();
    }

    /** @return list<array<string, mixed>> */
    public function recent(int $limit = 24): array
    {
        return $this->db->fetchAll('SELECT * FROM media ORDER BY created_at DESC LIMIT ' . max(1, $limit));
    }

    /**
     * Validate and store an uploaded file. Returns the media row.
     *
     * @return array<string, mixed>
     */
    public function upload(array $file, int $userId, string $alt = ''): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException($this->uploadError((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE)));
        }

        $size = (int) ($file['size'] ?? 0);
        $maxBytes = max(1, (int) $this->app->options->get('upload_max_size', 5)) * 1024 * 1024;
        if ($size <= 0 || $size > $maxBytes) {
            throw new RuntimeException('文件大小超过限制（最大 ' . (int) $this->app->options->get('upload_max_size', 5) . ' MB）。');
        }

        $original = Security::safeFilename((string) ($file['name'] ?? 'file'));
        $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        $allowed = array_values(array_unique(array_filter(array_map(
            static fn (string $item): string => strtolower(trim($item)),
            explode(',', (string) $this->app->options->get('upload_extensions', 'jpg,jpeg,png,gif,webp,pdf'))
        ))));
        if ($extension === '' || !in_array($extension, $allowed, true)) {
            throw new RuntimeException('不允许上传该类型的文件。');
        }

        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            throw new RuntimeException('上传文件校验失败。');
        }

        $mimeMap = [
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
            'gif'  => 'image/gif',
            'webp' => 'image/webp',
            'bmp'  => 'image/bmp',
            'avif' => 'image/avif',
            'ico'  => 'image/x-icon',
            'pdf'  => 'application/pdf',
            'txt'  => 'text/plain',
            'md'   => 'text/markdown',
            'csv'  => 'text/csv',
            'zip'  => 'application/zip',
            'mp3'  => 'audio/mpeg',
            'wav'  => 'audio/wav',
            'mp4'  => 'video/mp4',
            'webm' => 'video/webm',
        ];
        $mime = $mimeMap[$extension] ?? 'application/octet-stream';

        $width = null;
        $height = null;
        if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'avif'], true)) {
            $info = @getimagesize($tmp);
            if ($info === false) {
                throw new RuntimeException('无法读取图片信息。');
            }
            $width = (int) $info[0];
            $height = (int) $info[1];
        }

        $relativeDir = 'uploads/' . date('Y/m');
        $directory = BASE_PATH . '/' . $relativeDir;
        if (!is_dir($directory) && !@mkdir($directory, 0775, true)) {
            throw new RuntimeException('无法创建上传目录。');
        }

        $filename = date('Ymd') . '-' . Str::random(6) . '.' . $extension;
        $target = $directory . '/' . $filename;
        if (!move_uploaded_file($tmp, $target)) {
            throw new RuntimeException('文件保存失败。');
        }
        @chmod($target, 0644);

        $relativePath = $relativeDir . '/' . $filename;
        $id = $this->db->insert('media', [
            'filename'      => $filename,
            'original_name' => mb_substr($original, 0, 190, 'UTF-8'),
            'mime'          => $mime,
            'size'          => $size,
            'width'         => $width,
            'height'        => $height,
            'path'          => $relativePath,
            'alt'           => mb_substr(trim($alt), 0, 190, 'UTF-8'),
            'uploaded_by'   => $userId,
            'created_at'    => date('Y-m-d H:i:s'),
        ]);

        $record = $this->find($id);
        if ($record === null) {
            throw new RuntimeException('媒体记录创建失败。');
        }

        return $record;
    }

    public function delete(int $id): bool
    {
        $media = $this->find($id);
        if ($media === null) {
            return false;
        }
        $uploads = realpath(BASE_PATH . '/uploads');
        $file = realpath(BASE_PATH . '/' . ltrim((string) $media['path'], '/'));
        if ($uploads !== false && $file !== false
            && str_starts_with($file, $uploads . DIRECTORY_SEPARATOR)
            && is_file($file)
        ) {
            @unlink($file);
        }
        $this->db->delete('media', ['id' => $id]);

        return true;
    }

    public function count(): int
    {
        return $this->db->count('SELECT COUNT(*) FROM media');
    }

    private function uploadError(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => '文件超过服务器上传限制。',
            UPLOAD_ERR_PARTIAL => '文件只上传了一部分。',
            UPLOAD_ERR_NO_FILE => '没有选择文件。',
            UPLOAD_ERR_NO_TMP_DIR => '服务器缺少临时目录。',
            UPLOAD_ERR_CANT_WRITE => '服务器无法写入文件。',
            UPLOAD_ERR_EXTENSION => '上传被服务器扩展中止。',
            default => '文件上传失败。',
        };
    }
}