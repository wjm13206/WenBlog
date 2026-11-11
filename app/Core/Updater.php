<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * Checks for and installs WenBlog core updates.
 *
 * The updater only touches the core files (app/, assets/, install/, the front
 * controller and project metadata). User content in config/, storage/,
 * uploads/, themes/ and plugins/ is never overwritten.
 */
final class Updater
{
    public const DEFAULT_REPOSITORY = 'wjm13206/WenBlog';

    private const MAX_ARCHIVE_BYTES = 40 * 1024 * 1024;
    private const MAX_EXTRACTED_BYTES = 120 * 1024 * 1024;
    private const MAX_ENTRIES = 8000;

    /** Core paths that an update package may replace. */
    private const CORE_PATHS = [
        'app',
        'assets',
        'install',
        'index.php',
        '.htaccess',
        'nginx.conf',
        'README.md',
        'LICENSE',
        'version.json',
    ];

    private string $directory;

    public function __construct(private readonly App $app)
    {
        $this->directory = BASE_PATH . '/storage/updates';
        if (!is_dir($this->directory)) {
            @mkdir($this->directory, 0775, true);
        }
    }

    public function currentVersion(): string
    {
        $version = trim((string) $this->app->options->get('app_version', ''));
        if ($version === '') {
            $version = (string) $this->app->config('app.version', '1.0.0');
        }

        return $this->normalizeVersion($version);
    }

    public function repository(): string
    {
        $repository = trim((string) $this->app->options->get('update_repository', ''));
        if ($repository === '') {
            $repository = trim((string) $this->app->config('app.update_repo', self::DEFAULT_REPOSITORY));
        }

        return preg_match('#^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$#', $repository) === 1
            ? $repository
            : self::DEFAULT_REPOSITORY;
    }

    public function manifestUrl(): string
    {
        $url = trim((string) $this->app->options->get('update_manifest', ''));
        if ($url === '') {
            $url = trim((string) $this->app->config('app.update_manifest', ''));
        }

        return $url;
    }

    public function zipSupported(): bool
    {
        return class_exists(ZipArchive::class);
    }

    /**
     * Contact the update source and compare versions.
     *
     * @return array{
     *     checked_at: string,
     *     current: string,
     *     latest: string,
     *     available: bool,
     *     download: string,
     *     changelog: string,
     *     published_at: string,
     *     source: string,
     *     message: string,
     *     error: string
     * }
     */
    public function check(): array
    {
        $current = $this->currentVersion();
        $result = [
            'checked_at'   => date('Y-m-d H:i:s'),
            'current'      => $current,
            'latest'       => $current,
            'available'    => false,
            'download'     => '',
            'changelog'    => '',
            'published_at' => '',
            'source'       => '',
            'message'      => '',
            'error'        => '',
        ];

        try {
            $manifest = $this->manifestUrl();
            if ($manifest !== '') {
                $data = $this->fetchJson($manifest);
                $latest = $this->normalizeVersion((string) ($data['version'] ?? ''));
                $result['latest'] = $latest;
                $result['download'] = trim((string) ($data['download'] ?? $data['download_url'] ?? ''));
                $result['changelog'] = (string) ($data['changelog'] ?? $data['notes'] ?? $data['body'] ?? '');
                $result['published_at'] = (string) ($data['published_at'] ?? $data['date'] ?? '');
                $result['source'] = 'manifest';
            } else {
                $release = $this->githubLatest();
                if ($release === null) {
                    $result['source'] = 'none';
                    $result['message'] = '更新源暂时没有可用的发布包。你仍可以上传更新包手动升级。';

                    return $result;
                }
                $result['latest'] = $release['version'];
                $result['download'] = $release['download'];
                $result['changelog'] = $release['changelog'];
                $result['published_at'] = $release['published_at'];
                $result['source'] = 'github';
            }

            if ($result['latest'] === '') {
                throw new RuntimeException('更新信息中缺少版本号。');
            }
            $result['available'] = version_compare($result['latest'], $current, '>');
            $result['message'] = $result['available']
                ? '发现新版本 ' . $result['latest'] . '。'
                : '当前已是最新版本。';
        } catch (Throwable $e) {
            $result['error'] = $e->getMessage();
            $result['message'] = '检查更新失败：' . $e->getMessage();
            Logger::warning('Update check failed: ' . $e->getMessage());
        }

        return $result;
    }

    /** @param array<string, mixed> $result */
    public function saveCheck(array $result): void
    {
        $this->app->options->set('update_check', (string) json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /** @return array<string, mixed>|null */
    public function lastCheck(): ?array
    {
        $raw = (string) $this->app->options->get('update_check', '');
        if ($raw === '') {
            return null;
        }
        $data = json_decode($raw, true);

        return is_array($data) ? $data : null;
    }

    /** @return list<array<string, mixed>> */
    public function history(): array
    {
        $file = $this->directory . '/history.json';
        if (!is_file($file)) {
            return [];
        }
        $data = json_decode((string) @file_get_contents($file), true);

        return is_array($data) ? array_values(array_filter($data, 'is_array')) : [];
    }

    /**
     * Save the update source settings.
     */
    public function saveSource(string $repository, string $manifest): void
    {
        $repository = trim($repository);
        if ($repository !== '' && preg_match('#^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$#', $repository) !== 1) {
            throw new RuntimeException('GitHub 仓库格式应为 owner/repo。');
        }
        $manifest = trim($manifest);
        if ($manifest !== '') {
            $parts = parse_url($manifest);
            $scheme = strtolower((string) ($parts['scheme'] ?? ''));
            if (!in_array($scheme, ['http', 'https'], true) || (string) ($parts['host'] ?? '') === '') {
                throw new RuntimeException('更新清单地址必须是有效的 http/https URL。');
            }
        }

        $this->app->options->setMany([
            'update_repository' => $repository,
            'update_manifest'   => $manifest,
            'update_check'      => '',
        ]);
        $this->app->cache->clear();
    }

    /**
     * Download an update archive to storage/updates and verify it is a ZIP.
     */
    public function download(string $url): string
    {
        $response = $this->httpRequest($url);
        if ($response['status'] !== 200) {
            throw new RuntimeException('下载更新包失败（HTTP ' . $response['status'] . '）。');
        }
        if (strlen($response['body']) > self::MAX_ARCHIVE_BYTES) {
            throw new RuntimeException('更新包超过大小限制。');
        }

        $file = $this->directory . '/download-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.zip';
        if (@file_put_contents($file, $response['body'], LOCK_EX) === false) {
            throw new RuntimeException('无法写入更新包，请检查 storage/updates 目录权限。');
        }
        $this->assertZip($file);

        return $file;
    }

    /**
     * Install an uploaded archive.
     *
     * @param array<string, mixed> $file
     * @return array{version: string, backup: string, files: list<string>, migrations: list<string>}
     */
    public function installUpload(array $file, string $versionHint = ''): array
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            if (in_array($error, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
                throw new RuntimeException('更新包超过服务器上传限制，请调整 upload_max_filesize / post_max_size，或改用在线更新。');
            }
            if ($error === UPLOAD_ERR_NO_FILE) {
                throw new RuntimeException('请选择更新包 ZIP 文件。');
            }
            throw new RuntimeException('更新包上传失败，请重试。');
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            throw new RuntimeException('无效的上传文件。');
        }
        if ((int) ($file['size'] ?? 0) > self::MAX_ARCHIVE_BYTES) {
            throw new RuntimeException('更新包超过大小限制。');
        }
        $this->assertZip($tmp);

        $target = $this->directory . '/upload-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.zip';
        if (!@move_uploaded_file($tmp, $target)) {
            throw new RuntimeException('无法保存上传的更新包。');
        }
        try {
            return $this->applyFromZip($target, $versionHint, 'upload');
        } finally {
            @unlink($target);
        }
    }

    /**
     * Extract and install an update archive.
     *
     * @return array{version: string, backup: string, files: list<string>, migrations: list<string>}
     */
    public function applyFromZip(string $zipPath, string $versionHint = '', string $source = 'upload'): array
    {
        if (!$this->zipSupported()) {
            throw new RuntimeException('服务器未启用 ZipArchive 扩展，无法在线更新。');
        }
        if (!is_file($zipPath)) {
            throw new RuntimeException('更新包不存在。');
        }

        $lock = $this->acquireLock();
        $work = $this->directory . '/work-' . bin2hex(random_bytes(6));
        try {
            if (!@mkdir($work, 0775, true) && !is_dir($work)) {
                throw new RuntimeException('无法创建临时目录，请检查 storage/updates 权限。');
            }
            $root = $this->extract($zipPath, $work);
            $version = $this->detectVersion($root, $versionHint);
            if ($version === '') {
                throw new RuntimeException('更新包中没有找到版本号，请手动填写后重试。');
            }
            $current = $this->currentVersion();
            if (version_compare($version, $current, '<')) {
                throw new RuntimeException('更新包版本 ' . $version . ' 低于当前版本 ' . $current . '。');
            }

            $this->assertWritableTargets($root);
            $backup = $this->createBackup($version);
            $before = $this->snapshotCore();
            try {
                $files = $this->copyCore($root);
                $migrations = $this->runMigrations($root);
            } catch (Throwable $e) {
                $this->restoreBackup($backup, $before);
                throw new RuntimeException('更新失败，已尝试恢复备份：' . $e->getMessage(), 0, $e);
            }

            $this->app->options->setMany([
                'app_version'          => $version,
                'update_check'         => '',
                'update_last_applied'  => date('Y-m-d H:i:s'),
            ]);
            $this->app->cache->clear();
            $this->appendHistory([
                'version'     => $version,
                'previous'    => $current,
                'applied_at'  => date('Y-m-d H:i:s'),
                'backup'      => basename($backup),
                'files'       => count($files),
                'migrations'  => $migrations,
                'source'      => $source,
            ]);
            Logger::info('WenBlog core updated to ' . $version . ' from ' . $source, [
                'files'      => count($files),
                'migrations' => $migrations,
            ]);

            return [
                'version'    => $version,
                'backup'     => $backup,
                'files'      => $files,
                'migrations' => $migrations,
            ];
        } finally {
            $this->deleteDirectory($work);
            $this->releaseLock($lock);
        }
    }

    /**
     * Report whether the web server can write every core path.
     *
     * @return array{ok: bool, items: list<array{path: string, exists: bool, writable: bool}>}
     */
    public function writableStatus(): array
    {
        $items = [];
        $ok = true;
        foreach (self::CORE_PATHS as $path) {
            $target = BASE_PATH . '/' . $path;
            $exists = file_exists($target);
            $writable = $exists ? is_writable($target) : is_writable(dirname($target));
            if (!$writable && !($path === 'version.json' && !$exists)) {
                $ok = false;
            }
            $items[] = ['path' => $path, 'exists' => $exists, 'writable' => $writable];
        }
        if (!is_writable($this->directory)) {
            $ok = false;
        }

        return ['ok' => $ok, 'items' => $items];
    }

    /** @return list<array{version: string, published_at: string, download: string, changelog: string}>|null */
    private function githubLatest(): ?array
    {
        $repository = $this->repository();
        $release = $this->fetchJson(
            'https://api.github.com/repos/' . $repository . '/releases/latest',
            true
        );
        if (is_array($release)) {
            $download = '';
            foreach ((array) ($release['assets'] ?? []) as $asset) {
                $name = (string) ($asset['name'] ?? '');
                $url = (string) ($asset['browser_download_url'] ?? '');
                if ($url !== '' && preg_match('/\.zip$/i', $name) === 1) {
                    $download = $url;
                    break;
                }
            }
            if ($download === '') {
                $download = (string) ($release['zipball_url'] ?? '');
            }

            $version = $this->normalizeVersion((string) ($release['tag_name'] ?? $release['name'] ?? ''));
            if (preg_match('/^\d+\.\d+(?:\.\d+)*$/', $version) === 1) {
                return [
                    'version'      => $version,
                    'published_at' => (string) ($release['published_at'] ?? ''),
                    'download'     => $download,
                    'changelog'    => (string) ($release['body'] ?? ''),
                ];
            }
        }

        $tags = $this->fetchJson(
            'https://api.github.com/repos/' . $repository . '/tags?per_page=30',
            true
        );
        if (!is_array($tags) || $tags === []) {
            return null;
        }

        $best = '';
        $bestTag = '';
        foreach ($tags as $tag) {
            $name = (string) ($tag['name'] ?? '');
            $version = $this->normalizeVersion($name);
            if (preg_match('/^\d+\.\d+(?:\.\d+)*$/', $version) !== 1) {
                continue;
            }
            if ($best === '' || version_compare($version, $best, '>')) {
                $best = $version;
                $bestTag = $name;
            }
        }
        if ($best === '' || $bestTag === '') {
            return null;
        }

        return [
            'version'      => $best,
            'published_at' => '',
            'download'     => 'https://github.com/' . $repository . '/archive/refs/tags/' . rawurlencode($bestTag) . '.zip',
            'changelog'    => '来自标签 ' . $bestTag . ' 的源码包。',
        ];
    }

    /** @param array<string, mixed> $data */
    private function appendHistory(array $data): void
    {
        $history = $this->history();
        array_unshift($history, $data);
        $history = array_slice($history, 0, 20);
        @file_put_contents(
            $this->directory . '/history.json',
            (string) json_encode($history, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
            LOCK_EX
        );
    }

    private function detectVersion(string $root, string $hint): string
    {
        if ($hint !== '') {
            return $this->normalizeVersion($hint);
        }

        $manifest = $root . '/version.json';
        if (is_file($manifest)) {
            $data = json_decode((string) @file_get_contents($manifest), true);
            if (is_array($data) && isset($data['version'])) {
                $version = $this->normalizeVersion((string) $data['version']);
                if ($version !== '') {
                    return $version;
                }
            }
        }

        $defaults = $root . '/app/defaults.php';
        if (is_file($defaults)) {
            $content = (string) @file_get_contents($defaults);
            if (preg_match("/'app_version'\s*=>\s*'([^']+)'/", $content, $match) === 1) {
                return $this->normalizeVersion($match[1]);
            }
        }

        return '';
    }

    private function normalizeVersion(string $version): string
    {
        $version = trim($version);
        $version = (string) preg_replace('/^[vV]\s*/', '', $version);
        if (preg_match('/\d+(?:\.\d+){1,3}/', $version, $match) === 1) {
            return $match[0];
        }

        return $version;
    }

    /**
     * @return list<string>
     */
    private function copyCore(string $root): array
    {
        $copied = [];
        foreach (self::CORE_PATHS as $path) {
            $source = $root . '/' . $path;
            if (!file_exists($source)) {
                continue;
            }
            $target = BASE_PATH . '/' . $path;
            if (is_dir($source)) {
                $copied = array_merge($copied, $this->copyTree($source, $target, $path));
            } else {
                $this->copyFile($source, $target);
                $copied[] = $path;
            }
        }

        if (!in_array('index.php', $copied, true) || !in_array('app/Core/App.php', $copied, true)) {
            throw new RuntimeException('更新包缺少 index.php 或 app/Core/App.php。');
        }

        return $copied;
    }

    /** @return list<string> */
    private function copyTree(string $source, string $target, string $relative): array
    {
        if (!is_dir($target) && !@mkdir($target, 0775, true) && !is_dir($target)) {
            throw new RuntimeException('无法创建目录 ' . $relative . '。');
        }
        $copied = [];
        foreach (scandir($source) ?: [] as $item) {
            if ($item === '.' || $item === '..' || $item === '.git') {
                continue;
            }
            $from = $source . '/' . $item;
            $to = $target . '/' . $item;
            $path = $relative . '/' . $item;
            if (is_dir($from)) {
                $copied = array_merge($copied, $this->copyTree($from, $to, $path));
            } else {
                $this->copyFile($from, $to);
                $copied[] = $path;
            }
        }

        return $copied;
    }

    private function copyFile(string $source, string $target): void
    {
        $parent = dirname($target);
        if (!is_dir($parent) || !is_writable($parent)) {
            throw new RuntimeException('目录不可写：' . $parent);
        }
        if (is_file($target) && !is_writable($target)) {
            throw new RuntimeException('文件不可写：' . $target);
        }
        if (!@copy($source, $target)) {
            throw new RuntimeException('复制文件失败：' . $target);
        }
    }

    /**
     * @return list<string>
     */
    private function runMigrations(string $root): array
    {
        $directory = $root . '/install/migrations';
        if (!is_dir($directory)) {
            return [];
        }
        $files = glob($directory . '/*.sql') ?: [];
        sort($files, SORT_STRING);
        if ($files === []) {
            return [];
        }

        $raw = (string) $this->app->options->get('app_migrations', '[]');
        $applied = json_decode($raw, true);
        $applied = is_array($applied) ? array_map('strval', $applied) : [];
        $executed = [];
        $pdo = $this->app->db->connection();

        foreach ($files as $file) {
            $name = basename($file);
            if (in_array($name, $applied, true)) {
                continue;
            }
            $sql = trim((string) @file_get_contents($file));
            if ($sql !== '') {
                $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
                foreach (explode(';', $sql) as $statement) {
                    $statement = trim($statement);
                    if ($statement !== '') {
                        $pdo->exec($statement);
                    }
                }
            }
            $applied[] = $name;
            $executed[] = $name;
            $this->app->options->set('app_migrations', (string) json_encode(array_values($applied)));
        }

        return $executed;
    }

    private function createBackup(string $version): string
    {
        if (!$this->zipSupported()) {
            return '';
        }
        $file = $this->directory . '/backup-' . preg_replace('/[^0-9A-Za-z_.-]/', '-', $version)
            . '-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.zip';
        $zip = new ZipArchive();
        if ($zip->open($file, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('无法创建备份文件，请检查 storage/updates 权限。');
        }
        foreach (self::CORE_PATHS as $path) {
            $this->addToZip($zip, BASE_PATH . '/' . $path, $path);
        }
        $zip->close();

        return is_file($file) ? $file : '';
    }

    private function addToZip(ZipArchive $zip, string $source, string $relative): void
    {
        if (is_file($source)) {
            $zip->addFile($source, $relative);

            return;
        }
        if (!is_dir($source)) {
            return;
        }
        foreach (scandir($source) ?: [] as $item) {
            if ($item === '.' || $item === '..' || $item === '.git') {
                continue;
            }
            $this->addToZip($zip, $source . '/' . $item, $relative . '/' . $item);
        }
    }

    private function restoreBackup(string $backup, array $before = []): void
    {
        if ($backup === '' || !is_file($backup) || !$this->zipSupported()) {
            return;
        }

        // Remove core files that did not exist before the update so a failed
        // migration script cannot linger and run again on the next attempt.
        if ($before !== []) {
            $current = [];
            foreach (self::CORE_PATHS as $path) {
                $this->snapshotPath(BASE_PATH . '/' . $path, $path, $current);
            }
            foreach (array_keys($current) as $relative) {
                if (!isset($before[$relative])) {
                    @unlink(BASE_PATH . '/' . $relative);
                }
            }
        }

        $zip = new ZipArchive();
        if ($zip->open($backup) !== true) {
            return;
        }
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = (string) $zip->getNameIndex($index);
            if ($name === '' || !$this->isCorePath($name)) {
                continue;
            }
            $this->assertZipPath($name);
            $zip->extractTo(BASE_PATH, $name);
        }
        $zip->close();
    }

    /**
     * Record every core file that exists before an update.
     *
     * @return array<string, true>
     */
    private function snapshotCore(): array
    {
        $files = [];
        foreach (self::CORE_PATHS as $path) {
            $this->snapshotPath(BASE_PATH . '/' . $path, $path, $files);
        }

        return $files;
    }

    /** @param array<string, true> $files */
    private function snapshotPath(string $source, string $relative, array &$files): void
    {
        if (is_file($source)) {
            $files[$relative] = true;

            return;
        }
        if (!is_dir($source)) {
            return;
        }
        foreach (scandir($source) ?: [] as $item) {
            if ($item === '.' || $item === '..' || $item === '.git') {
                continue;
            }
            $this->snapshotPath($source . '/' . $item, $relative . '/' . $item, $files);
        }
    }

    private function isCorePath(string $name): bool
    {
        foreach (self::CORE_PATHS as $path) {
            if ($name === $path || str_starts_with($name, $path . '/')) {
                return true;
            }
        }

        return false;
    }

    private function extract(string $zipPath, string $work): string
    {
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('无法打开更新包 ZIP 文件。');
        }

        try {
            $total = 0;
            if ($zip->numFiles > self::MAX_ENTRIES) {
                throw new RuntimeException('更新包文件数量过多。');
            }
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $stat = $zip->statIndex($index);
                $name = (string) ($stat['name'] ?? '');
                $this->assertZipPath($name);

                $operations = 0;
                $attributes = 0;
                if ($zip->getExternalAttributesIndex($index, $operations, $attributes)
                    && (($attributes >> 16) & 0xF000) === 0xA000) {
                    throw new RuntimeException('更新包包含符号链接，已拒绝安装。');
                }
                $total += (int) ($stat['size'] ?? 0);
                if ($total > self::MAX_EXTRACTED_BYTES) {
                    throw new RuntimeException('更新包解压后体积过大。');
                }
            }
            if (!$zip->extractTo($work)) {
                throw new RuntimeException('解压更新包失败。');
            }
        } finally {
            $zip->close();
        }

        $candidates = [$work];
        foreach (glob($work . '/*', GLOB_ONLYDIR) ?: [] as $child) {
            $candidates[] = $child;
            foreach (glob($child . '/*', GLOB_ONLYDIR) ?: [] as $grandchild) {
                $candidates[] = $grandchild;
            }
        }
        foreach ($candidates as $candidate) {
            if (is_file($candidate . '/index.php') && is_file($candidate . '/app/Core/App.php')) {
                return $candidate;
            }
        }

        throw new RuntimeException('更新包中没有找到 WenBlog 程序目录。');
    }

    private function assertZip(string $file): void
    {
        if (!$this->zipSupported()) {
            throw new RuntimeException('服务器未启用 ZipArchive 扩展。');
        }
        if (@filesize($file) > self::MAX_ARCHIVE_BYTES) {
            throw new RuntimeException('更新包超过大小限制。');
        }
        $zip = new ZipArchive();
        if ($zip->open($file) !== true) {
            throw new RuntimeException('上传的文件不是有效的 ZIP 压缩包。');
        }
        $zip->close();
    }

    private function assertZipPath(string $name): void
    {
        if ($name === ''
            || str_contains($name, "\0")
            || str_contains($name, '..')
            || str_starts_with($name, '/')
            || str_contains($name, '\\')
            || preg_match('#^[A-Za-z]:#', $name) === 1) {
            throw new RuntimeException('更新包包含不安全的路径。');
        }
    }

    private function assertWritableTargets(string $root): void
    {
        if (!is_writable(BASE_PATH)) {
            throw new RuntimeException('站点根目录不可写，无法更新。');
        }
        foreach (self::CORE_PATHS as $path) {
            if (!file_exists($root . '/' . $path)) {
                continue;
            }
            $target = BASE_PATH . '/' . $path;
            if (file_exists($target)) {
                if (!is_writable($target)) {
                    throw new RuntimeException('文件不可写：' . $path);
                }
                continue;
            }
            $parent = dirname($target);
            if (!is_dir($parent) || !is_writable($parent)) {
                throw new RuntimeException('目录不可写：' . $parent);
            }
        }
        if (!is_writable($this->directory)) {
            throw new RuntimeException('目录不可写：storage/updates');
        }
    }

    /**
     * @return array{status: int, body: string, headers: array<string, string>}
     */
    private function httpRequest(string $url, int $redirects = 0): array
    {
        $this->assertSafeUrl($url);
        $headers = [];

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            if ($ch === false) {
                throw new RuntimeException('无法初始化网络请求。');
            }
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_TIMEOUT        => 30,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_MAXFILESIZE    => self::MAX_ARCHIVE_BYTES,
                CURLOPT_USERAGENT      => 'WenBlog-Updater/' . $this->currentVersion(),
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_HTTPHEADER     => [
                    'Accept: application/vnd.github+json, application/json',
                    'X-GitHub-Api-Version: 2022-11-28',
                ],
                CURLOPT_HEADERFUNCTION => static function ($handle, string $line) use (&$headers): int {
                    $trimmed = trim($line);
                    if ($trimmed !== '' && !str_starts_with($trimmed, 'HTTP/')) {
                        $parts = explode(':', $trimmed, 2);
                        if (count($parts) === 2) {
                            $headers[strtolower(trim($parts[0]))] = trim($parts[1]);
                        }
                    }

                    return strlen($line);
                },
            ]);
            $body = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            if ($body === false) {
                throw new RuntimeException('网络请求失败：' . ($error !== '' ? $error : '未知错误'));
            }
        } else {
            $context = stream_context_create([
                'http' => [
                    'method'          => 'GET',
                    'timeout'         => 30,
                    'ignore_errors'   => true,
                    'follow_location' => 0,
                    'user_agent'      => 'WenBlog-Updater/' . $this->currentVersion(),
                    'header'          => "Accept: application/vnd.github+json, application/json\r\n",
                ],
                'ssl' => [
                    'verify_peer'      => true,
                    'verify_peer_name' => true,
                ],
            ]);
            $body = @file_get_contents($url, false, $context);
            $rawHeaders = $http_response_header ?? [];
            $status = 0;
            foreach ($rawHeaders as $line) {
                if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $match) === 1) {
                    $status = (int) $match[1];
                } elseif (str_contains($line, ':')) {
                    [$name, $value] = explode(':', $line, 2);
                    $headers[strtolower(trim($name))] = trim($value);
                }
            }
            if ($body === false) {
                throw new RuntimeException('网络请求失败，请检查服务器是否允许访问外网。');
            }
        }

        $body = (string) $body;
        if (strlen($body) > self::MAX_ARCHIVE_BYTES + 4 * 1024 * 1024) {
            throw new RuntimeException('更新服务器返回的数据过大。');
        }

        if (in_array($status, [301, 302, 303, 307, 308], true)) {
            $location = $headers['location'] ?? '';
            if ($location === '' || $redirects >= 5) {
                throw new RuntimeException('更新服务器重定向异常。');
            }

            return $this->httpRequest($this->resolveUrl($url, $location), $redirects + 1);
        }

        return ['status' => $status, 'body' => $body, 'headers' => $headers];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchJson(string $url, bool $allow404 = false): ?array
    {
        $response = $this->httpRequest($url);
        if ($response['status'] === 404 && $allow404) {
            return null;
        }
        if ($response['status'] < 200 || $response['status'] >= 300) {
            $data = json_decode($response['body'], true);
            $message = is_array($data) ? (string) ($data['message'] ?? '') : '';
            throw new RuntimeException('更新服务器返回 HTTP ' . $response['status'] . ($message !== '' ? '：' . $message : ''));
        }

        $data = json_decode($response['body'], true);
        if (!is_array($data)) {
            throw new RuntimeException('更新服务器返回了无效的 JSON。');
        }

        return $data;
    }

    private function resolveUrl(string $base, string $location): string
    {
        if (preg_match('#^https?://#i', $location) === 1) {
            return $location;
        }
        $parts = parse_url($base);
        $scheme = (string) ($parts['scheme'] ?? 'https');
        $host = (string) ($parts['host'] ?? '');
        $port = isset($parts['port']) ? ':' . (int) $parts['port'] : '';
        if (str_starts_with($location, '//')) {
            return $scheme . ':' . $location;
        }
        if (str_starts_with($location, '/')) {
            return $scheme . '://' . $host . $port . $location;
        }
        $path = (string) ($parts['path'] ?? '/');
        $dir = substr($path, 0, (int) strrpos($path, '/') + 1);

        return $scheme . '://' . $host . $port . $dir . $location;
    }

    private function assertSafeUrl(string $url): void
    {
        $parts = parse_url($url);
        if (!is_array($parts)) {
            throw new RuntimeException('更新地址格式无效。');
        }
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = (string) ($parts['host'] ?? '');
        if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
            throw new RuntimeException('更新地址必须是有效的 http/https URL。');
        }
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            if (!$this->isPublicIp($host)) {
                throw new RuntimeException('更新地址不能指向内网或保留地址。');
            }

            return;
        }
        $ips = gethostbynamel($host);
        if (!is_array($ips) || $ips === []) {
            $records = @dns_get_record($host, DNS_A | DNS_AAAA);
            $ips = [];
            foreach (is_array($records) ? $records : [] as $record) {
                if (isset($record['ip'])) {
                    $ips[] = (string) $record['ip'];
                } elseif (isset($record['ipv6'])) {
                    $ips[] = (string) $record['ipv6'];
                }
            }
        }
        if ($ips === []) {
            throw new RuntimeException('无法解析更新服务器域名：' . $host);
        }
        foreach ($ips as $ip) {
            if (!$this->isPublicIp($ip)) {
                throw new RuntimeException('更新地址不能指向内网或保留地址。');
            }
        }
    }

    private function isPublicIp(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return false;
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return false;
        }
        $long = ip2long($ip);
        if ($long !== false && $long >= ip2long('100.64.0.0') && $long <= ip2long('100.127.255.255')) {
            return false;
        }

        return true;
    }

    /**
     * @return resource
     */
    private function acquireLock()
    {
        $handle = @fopen($this->directory . '/update.lock', 'c');
        if ($handle === false || !flock($handle, LOCK_EX | LOCK_NB)) {
            throw new RuntimeException('另一个更新正在进行，请稍后再试。');
        }

        return $handle;
    }

    /** @param resource $handle */
    private function releaseLock($handle): void
    {
        @flock($handle, LOCK_UN);
        @fclose($handle);
    }

    private function deleteDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }
        foreach (scandir($directory) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $directory . '/' . $item;
            is_dir($path) ? $this->deleteDirectory($path) : @unlink($path);
        }
        @rmdir($directory);
    }
}
