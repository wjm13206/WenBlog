<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Core\Logger;
use App\Core\Security;
use App\Core\Str;
use PDO;
use PDOException;
use Throwable;

/**
 * Guided web installer. Each step stores its result in the session and the
 * next step is only reachable when the previous ones succeeded.
 */
final class InstallController extends Controller
{
    public function index(): void
    {
        if ($this->app->isInstalled()) {
            \App\Core\Response::redirect('/');
        }
        if (is_file(BASE_PATH . '/config/installed.lock')) {
            \App\Core\Response::error(403, '安装程序已锁定。如需重新安装，请先删除 config/installed.lock 与 config/config.php。');
        }
        $requirements = $this->requirements();
        $step = (int) ($this->app->request->query('step') ?? 1);
        $step = max(1, min(4, $step));

        if ($step >= 2 && !$this->app->session->get('install.requirements_ok', false)) {
            $step = 1;
        }
        if ($step >= 3 && $this->app->session->get('install.db') === null) {
            $step = 2;
        }
        if ($step >= 4 && $this->app->session->get('install.admin') === null) {
            $step = 3;
        }
        if ($step === 1 && $this->allRequirementsMet($requirements)) {
            $step = max(1, min(4, (int) ($this->app->request->query('step') ?? 1)));
        }

        echo $this->app->view->render('install/wizard', [
            'step'         => $step,
            'requirements' => $requirements,
            'allMet'       => $this->allRequirementsMet($requirements),
            'db'           => $this->app->session->get('install.db', []),
            'form'         => $this->app->session->get('install.form', []),
            'site'         => $this->app->session->get('install.site', $this->defaultSite()),
            'admin'        => $this->app->session->get('install.admin', []),
            'title'        => '安装 WenBlog',
            'errors'       => $this->app->session->errors(),
            'basePath'     => $this->basePath(),
        ], 'layouts/install');
    }

    public function check(): void
    {
        $requirements = $this->requirements();
        if (!$this->allRequirementsMet($requirements)) {
            $this->app->session->setErrors(['requirements' => '请先解决未通过的环境检查项。']);
            $this->redirectStep(1);
        }
        $this->app->session->set('install.requirements_ok', true);
        $this->redirectStep(2);
    }

    public function database(): void
    {
        if (!$this->app->session->get('install.requirements_ok', false)) {
            $this->redirectStep(1);
        }
        $host = $this->stringInput('db_host', '127.0.0.1');
        $port = (int) $this->stringInput('db_port', '3306');
        $name = $this->stringInput('db_name');
        $user = $this->stringInput('db_user');
        $pass = (string) ($this->app->request->post('db_pass') ?? '');
        $siteName = $this->stringInput('site_name', 'WenBlog');
        $siteUrl = rtrim($this->stringInput('site_url', $this->defaultSite()['url']), '/');

        $errors = [];
        if ($host === '' || strlen($host) > 190 || preg_match('/^[A-Za-z0-9_.:\-\[\]]+$/', $host) !== 1) {
            $errors['db_host'] = '数据库主机格式无效。';
        }
        if ($port < 1 || $port > 65535) {
            $errors['db_port'] = '端口号无效。';
        }
        if (preg_match('/^[A-Za-z0-9_]+$/', $name) !== 1) {
            $errors['db_name'] = '数据库名只能包含字母、数字和下划线。';
        }
        if ($user === '') {
            $errors['db_user'] = '请填写数据库用户名。';
        }
        if ($siteName === '' || mb_strlen($siteName, 'UTF-8') > 100) {
            $errors['site_name'] = '网站名称不能为空，最多 100 个字符。';
        }
        $siteScheme = strtolower((string) parse_url($siteUrl, PHP_URL_SCHEME));
        if (filter_var($siteUrl, FILTER_VALIDATE_URL) === false || !in_array($siteScheme, ['http', 'https'], true)) {
            $errors['site_url'] = '请填写以 http:// 或 https:// 开头的网站地址。';
        }
        if ($errors !== []) {
            $this->app->session->setErrors($errors);
            $this->app->session->set('install.form', ['db_host' => $host, 'db_port' => $port, 'db_name' => $name, 'db_user' => $user, 'site_name' => $siteName, 'site_url' => $siteUrl]);
            $this->redirectStep(2);
        }

        try {
            $pdo = $this->connect($host, $port, $user, $pass, '');
            $exists = $pdo->prepare('SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?');
            $exists->execute([$name]);
            if ($exists->fetchColumn() === false) {
                $pdo->exec('CREATE DATABASE `' . str_replace('`', '``', $name) . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            }
            $pdo->exec('USE `' . str_replace('`', '``', $name) . '`');
            $this->runSchema($pdo);
            $this->ensureSearchIndex($pdo);
            $tableCount = (int) $pdo->query('SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = ' . $pdo->quote($name))->fetchColumn();
            if ($tableCount < 10) {
                throw new \RuntimeException('数据表创建不完整，请检查数据库权限。');
            }
        } catch (Throwable $e) {
            Logger::error('Installer database step failed: ' . $e->getMessage());
            $this->app->session->setErrors(['database' => '数据库连接或初始化失败：' . $e->getMessage()]);
            $this->app->session->set('install.form', ['db_host' => $host, 'db_port' => $port, 'db_name' => $name, 'db_user' => $user, 'site_name' => $siteName, 'site_url' => $siteUrl]);
            $this->redirectStep(2);
        }

        $this->app->session->set('install.db', [
            'host'    => $host,
            'port'    => $port,
            'name'    => $name,
            'user'    => $user,
            'pass'    => $pass,
            'charset' => 'utf8mb4',
        ]);
        $this->app->session->set('install.site', ['name' => $siteName, 'url' => $siteUrl]);
        $this->app->session->remove('install.form');
        $this->redirectStep(3);
    }

    public function admin(): void
    {
        $db = $this->app->session->get('install.db');
        if (!is_array($db)) {
            $this->redirectStep(2);
        }
        $username = $this->stringInput('username');
        $displayName = $this->stringInput('display_name');
        $email = strtolower($this->stringInput('email'));
        $password = (string) ($this->app->request->post('password') ?? '');
        $confirm = (string) ($this->app->request->post('password_confirm') ?? '');

        $errors = [];
        if (preg_match('/^[A-Za-z0-9_]{3,30}$/', $username) !== 1) {
            $errors['username'] = '用户名需为 3-30 位字母、数字或下划线。';
        }
        if ($displayName === '' || mb_strlen($displayName, 'UTF-8') > 100) {
            $errors['display_name'] = '请填写显示名称。';
        }
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = '请填写有效的邮箱地址。';
        }
        if (strlen($password) < 8) {
            $errors['password'] = '密码至少 8 个字符。';
        }
        if ($password !== $confirm) {
            $errors['password_confirm'] = '两次输入的密码不一致。';
        }
        if ($errors !== []) {
            $this->app->session->setErrors($errors);
            $this->app->session->set('install.form', ['username' => $username, 'display_name' => $displayName, 'email' => $email]);
            $this->redirectStep(3);
        }

        try {
            $pdo = $this->connect($db['host'], (int) $db['port'], (string) $db['user'], (string) $db['pass'], (string) $db['name']);
            $count = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
            if ($count > 0) {
                throw new \RuntimeException('数据库中已经存在用户，无法重复安装。');
            }
        } catch (Throwable $e) {
            $this->app->session->setErrors(['database' => '数据库连接失败：' . $e->getMessage()]);
            $this->redirectStep(2);
        }

        $this->app->session->set('install.admin', [
            'username'      => $username,
            'display_name'  => mb_substr($displayName, 0, 100, 'UTF-8'),
            'email'         => $email,
            'password_hash' => Security::hashPassword($password),
        ]);
        $this->app->session->remove('install.form');
        $this->redirectStep(4);
    }

    public function finish(): void
    {
        $db = $this->app->session->get('install.db');
        $site = $this->app->session->get('install.site');
        $admin = $this->app->session->get('install.admin');
        if (!is_array($db) || !is_array($site) || !is_array($admin)) {
            $this->app->session->setErrors(['install' => '安装会话已失效，请重新开始。']);
            $this->redirectStep(1);
        }
        if (is_file(BASE_PATH . '/config/installed.lock')) {
            \App\Core\Response::error(403, '安装程序已锁定。如需重新安装，请先删除 config/installed.lock 与 config/config.php。');
        }
        if (file_exists(BASE_PATH . '/config/config.php') && !is_writable(BASE_PATH . '/config/config.php')) {
            $this->app->session->setErrors(['config' => 'config/config.php 不可写，请检查文件权限。']);
            $this->redirectStep(4);
        }

        $committed = false;
        try {
            $configWritten = false;
            $pdo = $this->connect($db['host'], (int) $db['port'], (string) $db['user'], (string) $db['pass'], (string) $db['name']);
            $pdo->beginTransaction();
            $now = date('Y-m-d H:i:s');
            $userCount = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
            if ($userCount > 0) {
                throw new \RuntimeException('数据库中已经存在用户，无法重复安装。');
            }
            $insertUser = $pdo->prepare(
                'INSERT INTO users (username, email, password_hash, display_name, role, status, bio, created_at, updated_at)
                 VALUES (?, ?, ?, ?, "admin", "active", "", ?, ?)'
            );
            $insertUser->execute([$admin['username'], $admin['email'], $admin['password_hash'], $admin['display_name'], $now, $now]);

            $defaults = require BASE_PATH . '/app/defaults.php';
            $options = array_merge($defaults, [
                'site_name'        => $site['name'],
                'site_url'         => rtrim((string) $site['url'], '/'),
                'admin_email'      => $admin['email'],
                'active_theme'     => 'default',
                'app_version'      => '1.0.0',
            ]);
            $stmt = $pdo->prepare('INSERT INTO options (option_name, option_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE option_value = VALUES(option_value)');
            foreach ($options as $key => $value) {
                $stmt->execute([$key, is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string) $value]);
            }

            $config = [
                'db'  => $db,
                'app' => [
                    'url'          => rtrim((string) $site['url'], '/'),
                    'key'          => Str::random(64),
                    'debug'        => false,
                    'version'      => '1.0.0',
                    'installed_at' => $now,
                ],
            ];
            $content = "<?php\n\n// Generated by the WenBlog installer. Keep this file private.\nreturn " . var_export($config, true) . ";\n";
            if (file_put_contents(BASE_PATH . '/config/config.php', $content, LOCK_EX) === false) {
                throw new \RuntimeException('无法写入 config/config.php，请检查目录权限。');
            }
            $configWritten = true;
            @chmod(BASE_PATH . '/config/config.php', 0640);
            $pdo->commit();
            $committed = true;
            @file_put_contents(BASE_PATH . '/config/installed.lock', 'Installed at ' . $now . PHP_EOL);
            $this->app->session->remove('install.db');
            $this->app->session->remove('install.site');
            $this->app->session->remove('install.admin');
            $this->app->session->remove('install.requirements_ok');
            $this->app->session->regenerate();

            echo $this->app->view->render('install/success', [
                'title'    => '安装完成',
                'siteUrl'  => rtrim((string) $site['url'], '/'),
                'adminUrl' => rtrim((string) $site['url'], '/') . '/admin/login',
            ], 'layouts/install');
        } catch (Throwable $e) {
            if (!$committed && isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($committed) {
                // The database and config are already installed; never delete
                // the config because a later (non-critical) step failed.
                Logger::error('Installer post-commit failure: ' . $e->getMessage());
                \App\Core\Response::redirect('/admin/login');
            }
            if (isset($configWritten) && $configWritten === true && is_file(BASE_PATH . '/config/config.php')) {
                @unlink(BASE_PATH . '/config/config.php');
            }
            Logger::error('Installer finish failed: ' . $e->getMessage());
            $this->app->session->setErrors(['install' => '安装失败：' . $e->getMessage()]);
            $this->redirectStep(4);
        }
    }

    /** @return list<array{label: string, ok: bool, detail: string, required: bool}> */
    private function requirements(): array
    {
        $configFile = BASE_PATH . '/config/config.php';
        $zip = class_exists(\ZipArchive::class);

        return [
            ['label' => 'PHP 版本 ≥ 8.2', 'ok' => version_compare(PHP_VERSION, '8.2.0', '>='), 'detail' => '当前 ' . PHP_VERSION, 'required' => true],
            ['label' => 'PDO MySQL 扩展', 'ok' => extension_loaded('pdo_mysql'), 'detail' => '用于连接 MySQL', 'required' => true],
            ['label' => 'mbstring 扩展', 'ok' => extension_loaded('mbstring'), 'detail' => '处理中文与多字节文本', 'required' => true],
            ['label' => 'JSON 扩展', 'ok' => extension_loaded('json'), 'detail' => '主题与插件配置', 'required' => true],
            ['label' => 'Fileinfo 扩展', 'ok' => extension_loaded('fileinfo'), 'detail' => '上传文件类型校验', 'required' => true],
            ['label' => 'Session 支持', 'ok' => extension_loaded('session'), 'detail' => '登录与安全令牌', 'required' => true],
            
            ['label' => 'ZipArchive 扩展', 'ok' => $zip, 'detail' => '在线安装主题和插件（可选）', 'required' => false],
            ['label' => 'config 目录可写', 'ok' => is_dir(BASE_PATH . '/config') && is_writable(BASE_PATH . '/config'), 'detail' => '写入配置文件', 'required' => true],
            ['label' => 'config.php 可写', 'ok' => !file_exists($configFile) || is_writable($configFile), 'detail' => '配置文件已存在且不可写', 'required' => true],
            ['label' => 'storage 目录可写', 'ok' => is_dir(BASE_PATH . '/storage') && is_writable(BASE_PATH . '/storage'), 'detail' => '缓存与日志', 'required' => true],
            ['label' => 'uploads 目录可写', 'ok' => is_dir(BASE_PATH . '/uploads') && is_writable(BASE_PATH . '/uploads'), 'detail' => '媒体文件上传', 'required' => true],
        ];
    }

    /** @param list<array{ok: bool, required: bool}> $requirements */
    private function allRequirementsMet(array $requirements): bool
    {
        foreach ($requirements as $item) {
            if ($item['required'] && !$item['ok']) {
                return false;
            }
        }

        return true;
    }

    private function connect(string $host, int $port, string $user, string $pass, string $database): PDO
    {
        $dsn = 'mysql:host=' . $host . ';port=' . $port . ';charset=utf8mb4';
        if ($database !== '') {
            $dsn .= ';dbname=' . $database;
        }
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_TIMEOUT            => 8,
        ]);
        $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");

        return $pdo;
    }

    private function runSchema(PDO $pdo): void
    {
        $sql = (string) file_get_contents(BASE_PATH . '/install/schema.sql');
        $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
        foreach (explode(';', $sql) as $statement) {
            $statement = trim($statement);
            if ($statement !== '') {
                $pdo->exec($statement);
            }
        }
    }

    /**
     * Create the FULLTEXT index with the ngram parser when the server supports
     * it, and fall back to a plain FULLTEXT index on MariaDB.
     */
    private function ensureSearchIndex(PDO $pdo): void
    {
        $exists = $pdo->query("SHOW INDEX FROM posts WHERE Key_name = 'posts_fulltext'");
        if ($exists !== false && $exists->fetch() !== false) {
            return;
        }

        try {
            $pdo->exec('ALTER TABLE posts ADD FULLTEXT INDEX posts_fulltext (title, content, excerpt) WITH PARSER ngram');
        } catch (PDOException) {
            try {
                $pdo->exec('ALTER TABLE posts ADD FULLTEXT INDEX posts_fulltext (title, content, excerpt)');
            } catch (PDOException) {
                // Search still works through the LIKE fallback.
            }
        }
    }

    /** @return array{name: string, url: string} */
    private function defaultSite(): array
    {
        $scheme = $this->app->request->isSecure() ? 'https' : 'http';
        $host = (string) $this->app->request->header('Host');
        if (preg_match('/^[A-Za-z0-9.\-:\[\]]+$/', $host) !== 1) {
            $host = (string) (parse_url((string) config('app.url', ''), PHP_URL_HOST) ?: 'localhost');
        }
        $base = rtrim($this->basePath(), '/');

        return ['name' => 'WenBlog', 'url' => $scheme . '://' . $host . $base];
    }

    private function basePath(): string
    {
        return rtrim($this->app->request->basePath(), '/');
    }

    private function redirectStep(int $step): never
    {
        \App\Core\Response::redirect('/install?step=' . $step);
    }
}