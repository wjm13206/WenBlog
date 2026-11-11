<?php

declare(strict_types=1);

namespace App\Core;

use App\Controllers\Admin\AuthController;
use App\Controllers\Admin\CategoryController as AdminCategoryController;
use App\Controllers\Admin\CommentController as AdminCommentController;
use App\Controllers\Admin\DashboardController;
use App\Controllers\Admin\LinkController;
use App\Controllers\Admin\MediaController;
use App\Controllers\Admin\PageController as AdminPageController;
use App\Controllers\Admin\PluginController;
use App\Controllers\Admin\PostController as AdminPostController;
use App\Controllers\Admin\ProfileController;
use App\Controllers\Admin\SettingController;
use App\Controllers\Admin\TagController as AdminTagController;
use App\Controllers\Admin\ThemeController;
use App\Controllers\Admin\UpdateController;
use App\Controllers\Admin\UserController;
use App\Controllers\ApiController;
use App\Controllers\ArchiveController;
use App\Controllers\CategoryController;
use App\Controllers\CommentController;
use App\Controllers\FeedController;
use App\Controllers\HomeController;
use App\Controllers\InstallController;
use App\Controllers\PageController;
use App\Controllers\PostController;
use App\Controllers\SearchController;
use App\Controllers\SitemapController;
use App\Controllers\TagController;
use Throwable;

/**
 * Application container and front controller.
 */
final class App
{
    public static ?self $instance = null;

    /** @var array<string, mixed> */
    private array $config;
    /** @var array<string, mixed> */
    private array $defaults;

    public readonly Database $db;
    public readonly Option $options;
    public readonly Session $session;
    public readonly Security $security;
    public readonly Request $request;
    public readonly Auth $auth;
    public readonly View $view;
    public readonly Cache $cache;
    public readonly Theme $theme;
    public readonly PluginManager $plugins;
    public readonly Router $router;
    public readonly bool $installed;

    /** @param array<string, mixed> $config */
    public function __construct(array $config)
    {
        $this->config = $config;
        $this->defaults = require BASE_PATH . '/app/defaults.php';
        $this->installed = trim((string) ($config['app']['installed_at'] ?? '')) !== '';

        $this->request = Request::capture();
        $this->session = new Session();
        $this->session->start($this->request->isSecure());
        $this->security = new Security($this->session);
        $this->db = new Database((array) ($config['db'] ?? []));
        $this->options = new Option($this->db, $this->defaults);
        $timezone = (string) $this->options->get('timezone', 'Asia/Shanghai');
        if (Security::validTimezone($timezone)) {
            date_default_timezone_set($timezone);
            $this->db->syncTimeZone();
        }
        $this->auth = new Auth($this);
        $this->view = new View($this);
        $this->cache = new Cache($this);
        $this->theme = new Theme($this);
        $this->plugins = new PluginManager($this);
        $this->router = new Router($this);

        if ($this->installed) {
            $this->plugins->loadActive();
            $this->theme->boot();
        }

        $this->registerRoutes();
        Hook::do('app.boot', $this);
    }

    public static function boot(array $config): self
    {
        self::$instance = new self($config);
        $GLOBALS['app'] = self::$instance;

        return self::$instance;
    }

    public function config(string $key, mixed $default = null): mixed
    {
        $value = $this->config;
        foreach (explode('.', $key) as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return $default;
            }
            $value = $value[$part];
        }

        return $value;
    }

    public function isInstalled(): bool
    {
        return $this->installed;
    }

    public function run(): void
    {
        $this->sendSecurityHeaders();
        $path = $this->request->path();

        if (!$this->installed && !str_starts_with($path, '/install')) {
            Response::redirect('/install');
        }
        if ($this->installed && str_starts_with($path, '/install')) {
            Response::redirect('/');
        }

        $cacheable = $this->installed
            && $this->request->isGet()
            && (string) $this->options->get('cache_enabled', '0') === '1'
            && !$this->auth->check()
            && !$this->session->hasFlash()
            && !$this->session->has('_errors')
            && !$this->session->has('_old')
            && !str_starts_with($path, '/admin')
            && !str_starts_with($path, '/api')
            && !str_starts_with($path, '/search')
            && !in_array($path, ['/rss', '/feed', '/sitemap.xml', '/robots.txt'], true)
            && $this->request->query('preview') === null;

        if ($this->installed
            && (string) $this->options->get('maintenance_mode', '0') === '1'
            && !$this->auth->check()
            && !str_starts_with($path, '/admin')
            && !str_starts_with($path, '/install')) {
            Response::error(503, '站点正在进行维护，请稍后再来。');
        }

        $key = $path . '?' . $this->cacheQuery();
        if ($cacheable) {
            $cached = $this->cache->get($key);
            if ($cached !== null) {
                if (!headers_sent()) {
                    header('X-WenBlog-Cache: HIT');
                }
                echo str_replace('__WENBLOG_CSRF__', $this->security->csrfToken(), $cached);
                exit;
            }
        }

        if (class_exists(\App\Controllers\InstallController::class) && !$this->installed) {
            // Installer views are rendered by the router below.
        }

        ob_start();
        $this->router->dispatch();
        $html = (string) ob_get_clean();

        if ($cacheable && http_response_code() === 200 && !$this->session->hasFlash() && $html !== '') {
            // CSRF tokens are session-bound. Store a placeholder so every guest
            // receives a token that belongs to their own session.
            $html = str_replace($this->security->csrfToken(), '__WENBLOG_CSRF__', $html);
            $this->cache->put($key, $html);
            if (!headers_sent()) {
                header('X-WenBlog-Cache: MISS');
            }
        }
        echo $html;
    }

    private function sendSecurityHeaders(): void
    {
        if (headers_sent()) {
            return;
        }
        header_remove('X-Powered-By');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
        header("Content-Security-Policy: frame-ancestors 'self'");
    }

    private function cacheQuery(): string
    {
        $params = [];
        foreach (['page', 'cpage'] as $name) {
            $value = $_GET[$name] ?? null;
            if (is_scalar($value) && ctype_digit((string) $value) && strlen((string) $value) <= 9) {
                $number = (int) $value;
                if ($number > 0) {
                    $params[$name] = $number;
                }
            }
        }
        $year = $_GET['year'] ?? null;
        if (is_scalar($year) && ctype_digit((string) $year)) {
            $number = (int) $year;
            if ($number >= 1970 && $number <= 2100) {
                $params['year'] = $number;
            }
        }
        $month = $_GET['month'] ?? null;
        if (is_scalar($month) && ctype_digit((string) $month)) {
            $number = (int) $month;
            if ($number >= 1 && $number <= 12) {
                $params['month'] = $number;
            }
        }
        ksort($params);

        return http_build_query($params);
    }

    private function registerRoutes(): void
    {
        $r = $this->router;

        // Installer
        $r->get('/install', [InstallController::class, 'index']);
        $r->post('/install/check', [InstallController::class, 'check']);
        $r->post('/install/database', [InstallController::class, 'database']);
        $r->post('/install/admin', [InstallController::class, 'admin']);
        $r->post('/install/finish', [InstallController::class, 'finish']);

        // Authentication
        $r->get('/admin/login', [AuthController::class, 'showLogin']);
        $r->post('/admin/login', [AuthController::class, 'login']);
        // 后台侧边栏通过 POST 表单退出；同时接受 GET，方便直接访问
        // /admin/logout 时也能正常退出，而不是返回 405。
        $r->get('/admin/logout', [AuthController::class, 'logout'], ['auth' => true]);
        $r->post('/admin/logout', [AuthController::class, 'logout'], ['auth' => true]);

        // Dashboard
        $r->get('/admin', [DashboardController::class, 'index'], ['auth' => true]);

        // Posts
        $r->get('/admin/posts', [AdminPostController::class, 'index'], ['auth' => true]);
        $r->get('/admin/posts/create', [AdminPostController::class, 'create'], ['auth' => true]);
        $r->post('/admin/posts', [AdminPostController::class, 'store'], ['auth' => true]);
        $r->post('/admin/preview', [AdminPostController::class, 'preview'], ['auth' => true]);
        $r->get('/admin/posts/{id}/edit', [AdminPostController::class, 'edit'], ['auth' => true]);
        $r->post('/admin/posts/{id}', [AdminPostController::class, 'update'], ['auth' => true]);
        $r->post('/admin/posts/{id}/delete', [AdminPostController::class, 'delete'], ['auth' => true]);
        $r->get('/admin/posts/{id}/revisions', [AdminPostController::class, 'revisions'], ['auth' => true]);
        $r->post('/admin/posts/{id}/revisions/{revision}/restore', [AdminPostController::class, 'restore'], ['auth' => true]);

        // Pages
        $r->get('/admin/pages', [AdminPageController::class, 'index'], ['auth' => true]);
        $r->get('/admin/pages/create', [AdminPageController::class, 'create'], ['auth' => true]);
        $r->post('/admin/pages', [AdminPageController::class, 'store'], ['auth' => true]);
        $r->get('/admin/pages/{id}/edit', [AdminPageController::class, 'edit'], ['auth' => true]);
        $r->post('/admin/pages/{id}', [AdminPageController::class, 'update'], ['auth' => true]);
        $r->post('/admin/pages/{id}/delete', [AdminPageController::class, 'delete'], ['auth' => true]);

        // Categories and tags
        $r->get('/admin/categories', [AdminCategoryController::class, 'index'], ['auth' => true]);
        $r->post('/admin/categories', [AdminCategoryController::class, 'store'], ['auth' => true]);
        $r->post('/admin/categories/{id}', [AdminCategoryController::class, 'update'], ['auth' => true]);
        $r->post('/admin/categories/{id}/delete', [AdminCategoryController::class, 'delete'], ['auth' => true]);
        $r->get('/admin/tags', [AdminTagController::class, 'index'], ['auth' => true]);
        $r->post('/admin/tags', [AdminTagController::class, 'store'], ['auth' => true]);
        $r->post('/admin/tags/{id}', [AdminTagController::class, 'update'], ['auth' => true]);
        $r->post('/admin/tags/{id}/delete', [AdminTagController::class, 'delete'], ['auth' => true]);

        // Comments
        $r->get('/admin/comments', [AdminCommentController::class, 'index'], ['auth' => true]);
        $r->post('/admin/comments/{id}/status', [AdminCommentController::class, 'status'], ['auth' => true]);
        $r->post('/admin/comments/{id}/reply', [AdminCommentController::class, 'reply'], ['auth' => true]);
        $r->post('/admin/comments/{id}/delete', [AdminCommentController::class, 'delete'], ['auth' => true]);

        // Media
        $r->get('/admin/media', [MediaController::class, 'index'], ['auth' => true]);
        $r->post('/admin/media', [MediaController::class, 'upload'], ['auth' => true]);
        $r->post('/admin/media/upload', [MediaController::class, 'upload'], ['auth' => true]);
        $r->post('/admin/media/ajax', [MediaController::class, 'ajaxUpload'], ['auth' => true]);
        $r->post('/admin/media/{id}/alt', [MediaController::class, 'updateAlt'], ['auth' => true]);
        $r->post('/admin/media/{id}/delete', [MediaController::class, 'delete'], ['auth' => true]);

        // Users
        $r->get('/admin/users', [UserController::class, 'index'], ['auth' => true, 'admin' => true]);
        $r->get('/admin/users/create', [UserController::class, 'create'], ['auth' => true, 'admin' => true]);
        $r->post('/admin/users', [UserController::class, 'store'], ['auth' => true, 'admin' => true]);
        $r->get('/admin/users/{id}/edit', [UserController::class, 'edit'], ['auth' => true, 'admin' => true]);
        $r->post('/admin/users/{id}', [UserController::class, 'update'], ['auth' => true, 'admin' => true]);
        $r->post('/admin/users/{id}/delete', [UserController::class, 'delete'], ['auth' => true, 'admin' => true]);

        // Themes and plugins
        $r->get('/admin/themes', [ThemeController::class, 'index'], ['auth' => true, 'admin' => true]);
        $r->get('/admin/themes/settings', [ThemeController::class, 'settings'], ['auth' => true, 'admin' => true]);
        $r->post('/admin/themes/activate', [ThemeController::class, 'activate'], ['auth' => true, 'admin' => true]);
        $r->post('/admin/themes/config', [ThemeController::class, 'config'], ['auth' => true, 'admin' => true]);
        $r->post('/admin/themes/upload', [ThemeController::class, 'upload'], ['auth' => true, 'admin' => true]);
        $r->post('/admin/themes/delete', [ThemeController::class, 'delete'], ['auth' => true, 'admin' => true]);
        $r->get('/admin/plugins', [PluginController::class, 'index'], ['auth' => true, 'admin' => true]);
        $r->post('/admin/plugins/toggle', [PluginController::class, 'toggle'], ['auth' => true, 'admin' => true]);
        $r->post('/admin/plugins/config', [PluginController::class, 'config'], ['auth' => true, 'admin' => true]);
        $r->post('/admin/plugins/upload', [PluginController::class, 'upload'], ['auth' => true, 'admin' => true]);
        $r->post('/admin/plugins/delete', [PluginController::class, 'delete'], ['auth' => true, 'admin' => true]);

        // Active plugins may register their own admin routes.
        Hook::do('app.routes', $r);

        // Links
        $r->get('/admin/links', [LinkController::class, 'index'], ['auth' => true, 'admin' => true]);
        $r->post('/admin/links', [LinkController::class, 'store'], ['auth' => true, 'admin' => true]);
        $r->post('/admin/links/{id}', [LinkController::class, 'update'], ['auth' => true, 'admin' => true]);
        $r->post('/admin/links/{id}/delete', [LinkController::class, 'delete'], ['auth' => true, 'admin' => true]);

        // Settings and profile
        $r->get('/admin/settings', [SettingController::class, 'index'], ['auth' => true, 'admin' => true]);
        $r->post('/admin/settings', [SettingController::class, 'save'], ['auth' => true, 'admin' => true]);
        $r->post('/admin/settings/search-index', [SettingController::class, 'rebuildSearchIndex'], ['auth' => true, 'admin' => true]);
        $r->get('/admin/profile', [ProfileController::class, 'index'], ['auth' => true]);
        $r->post('/admin/profile', [ProfileController::class, 'update'], ['auth' => true]);
        $r->post('/admin/cache/clear', [SettingController::class, 'clearCache'], ['auth' => true, 'admin' => true]);

        // System update
        $r->get('/admin/update', [UpdateController::class, 'index'], ['auth' => true, 'admin' => true]);
        $r->post('/admin/update/check', [UpdateController::class, 'check'], ['auth' => true, 'admin' => true]);
        $r->post('/admin/update/apply', [UpdateController::class, 'apply'], ['auth' => true, 'admin' => true]);
        $r->post('/admin/update/upload', [UpdateController::class, 'upload'], ['auth' => true, 'admin' => true]);
        $r->post('/admin/update/source', [UpdateController::class, 'source'], ['auth' => true, 'admin' => true]);

        // Front-end
        $r->get('/', [HomeController::class, 'index']);
        $r->get('/page/{page}', [HomeController::class, 'page']);
        $r->get('/post/{slug}', [PostController::class, 'show']);
        $r->get('/category/{slug}', [CategoryController::class, 'show']);
        $r->get('/category/{slug}/page/{page}', [CategoryController::class, 'show']);
        $r->get('/tag/{slug}', [TagController::class, 'show']);
        $r->get('/tag/{slug}/page/{page}', [TagController::class, 'show']);
        $r->get('/search', [SearchController::class, 'index']);
        $r->get('/archives', [ArchiveController::class, 'index']);
        $r->get('/rss', [FeedController::class, 'rss']);
        $r->get('/feed', [FeedController::class, 'rss']);
        $r->get('/sitemap.xml', [SitemapController::class, 'index']);
        $r->get('/robots.txt', [SitemapController::class, 'robots']);
        // Comments are public and Typecho themes do not emit a CSRF field, so
        // this route relies on the honeypot and the moderation queue instead.
        $r->post('/comments', [CommentController::class, 'store'], ['csrf' => false]);

        // API
        $r->get('/api/posts', [ApiController::class, 'posts'], ['api' => true]);
        $r->get('/api/posts/{slug}', [ApiController::class, 'post'], ['api' => true]);
        $r->get('/api/categories', [ApiController::class, 'categories'], ['api' => true]);
        $r->get('/api/tags', [ApiController::class, 'tags'], ['api' => true]);
        $r->get('/api/comments', [ApiController::class, 'comments'], ['api' => true]);

        // Public pages are matched last so reserved prefixes keep priority.
        $r->get('/{slug}', [PageController::class, 'show']);
    }
}