<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Typecho-compatible theme API, reached from templates through `$this`.
 *
 *     <?php $this->need('header.php'); ?>
 *     <?php while ($this->next()): ?>
 *         <a href="<?php $this->permalink() ?>"><?php $this->title() ?></a>
 *         <?php $this->content(); ?>
 *     <?php endwhile; ?>
 *     <?php $this->pageNav(); ?>
 *     <?php $this->need('footer.php'); ?>
 *
 * Methods follow Typecho semantics and echo their value; every field also has
 * a `get*()` variant that returns a string.
 */
final class ThemeApi
{
    private ?PostLoop $loop = null;
    private ?CommentLoop $comments = null;
    private readonly ThemeOptions $options;
    private readonly ThemeUser $user;
    /** @var array<int, list<array<string, mixed>>> */
    private array $categoryCache = [];
    /** @var array<int, list<array<string, mixed>>> */
    private array $tagCache = [];

    public function __construct(
        private readonly Theme $theme,
        private readonly App $app
    ) {
        $this->options = new ThemeOptions($theme);
        $this->user = new ThemeUser($app->auth->user());
    }

    public function __get(string $name): mixed
    {
        return match ($name) {
            'options'     => $this->options,
            'user'        => $this->user,
            'author'      => $this->authorUser(),
            'fields'      => $this->fieldsObject(),
            'loop'        => $this->loop(),
            'comments'    => $this->comments(),
            'content'     => $this->getContent(),
            'permalink'   => $this->getPermalink(),
            'title'       => $this->getTitle(),
            'excerpt'     => $this->getExcerpt(),
            'text'        => $this->getText(),
            'cid'         => $this->loop()->id(),
            'hidden'      => false,
            'categories'  => $this->loop()->getCategories(),
            'tags'        => $this->loop()->getTags(),
            'parameter'   => new TypechoCompatParameter(),
            '_currentPage' => $this->currentPageNumber(),
            'response'    => new TypechoCompatResponse(),
            'request'     => new TypechoCompatRequest(),
            default       => null,
        };
    }

    public function __isset(string $name): bool
    {
        return $this->__get($name) !== null;
    }

    /** @return array<string, mixed>|null */
    public function data(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            $data = $this->theme->data();

            return is_array($data) ? $data : [];
        }

        return $this->theme->data($key) ?? $default;
    }

    /* ---------------------------------------------------------------------
     * Context detection
     * ------------------------------------------------------------------ */

    public function is(string $type, ?string $slug = null): bool
    {
        $data = $this->theme->data();
        $type = strtolower(trim($type));
        $matches = match ($type) {
            'index', 'home', 'front' => !empty($data['isHome']),
            'single'                 => isset($data['post']) || isset($data['page']),
            'post'                   => isset($data['post']) && !isset($data['page']),
            'page'                   => !empty($data['isPage']) || isset($data['page']),
            'category'               => isset($data['category']),
            'tag'                    => isset($data['tag']),
            'search'                 => isset($data['query']),
            'archive'                => isset($data['archives']) || isset($data['query'])
                || isset($data['category']) || isset($data['tag']),
            '404'                    => isset($data['message']) && !isset($data['post']) && !isset($data['page']),
            default                  => false,
        };

        if (!$matches || $slug === null) {
            return $matches;
        }

        // Typecho's `is('page', $slug)` form used by navigation menus.
        $row = $this->loop()->currentItem();
        $current = (string) ($row['slug'] ?? $data['page']['slug'] ?? $data['category']['slug'] ?? $data['tag']['slug'] ?? '');

        return $current !== '' && $current === $slug;
    }

    public function isHome(): bool
    {
        return $this->is('index');
    }

    public function isPost(): bool
    {
        return $this->is('post');
    }

    public function isPage(): bool
    {
        return $this->is('page');
    }

    public function isCategory(): bool
    {
        return $this->is('category');
    }

    public function isTag(): bool
    {
        return $this->is('tag');
    }

    public function isArchive(): bool
    {
        return $this->is('archive');
    }

    public function isSearch(): bool
    {
        return $this->is('search');
    }

    /** @return array<string, mixed>|null */
    public function contextPost(): ?array
    {
        $data = $this->theme->data();
        $row = $data['post'] ?? $data['page'] ?? null;

        return is_array($row) ? $row : null;
    }

    /* ---------------------------------------------------------------------
     * Post loop
     * ------------------------------------------------------------------ */

    public function loop(): PostLoop
    {
        if ($this->loop === null) {
            $posts = $this->theme->data('posts');

            $this->loop = new PostLoop($this, is_array($posts) ? array_values($posts) : []);
        }

        return $this->loop;
    }

    public function next(): bool
    {
        return $this->loop()->next();
    }

    public function id(): int
    {
        return $this->loop()->id();
    }

    public function rewind(): void
    {
        $this->loop()->rewind();
    }

    /* ---------------------------------------------------------------------
     * Archive / page fields (echo like Typecho, get* returns the value)
     * ------------------------------------------------------------------ */

    public function title(): void
    {
        $row = $this->loop()->currentItem();
        echo e($row !== null ? (string) ($row['title'] ?? '') : $this->getArchiveTitle());
    }

    public function getTitle(): string
    {
        $row = $this->loop()->currentItem();

        return $row !== null ? (string) ($row['title'] ?? '') : $this->getArchiveTitle();
    }

    public function description(): void
    {
        echo e($this->getDescription());
    }

    public function getDescription(): string
    {
        $description = (string) ($this->theme->data('pageDescription') ?? '');

        return $description !== '' ? $description : (string) option('site_description', '');
    }

    public function keywords(): void
    {
        echo e($this->getKeywords());
    }

    public function getKeywords(): string
    {
        $data = $this->theme->data();
        $tag = (string) ($data['tag']['name'] ?? '');
        $category = (string) ($data['category']['name'] ?? '');
        $keywords = (string) option('seo_keywords', '');
        $parts = array_filter([$tag, $category, $keywords]);

        return implode(',', array_unique($parts));
    }

    public function permalink(): void
    {
        echo e($this->getPermalink());
    }

    public function getPermalink(): string
    {
        $row = $this->loop()->currentItem();

        return $row !== null ? $this->rowPermalink($row) : $this->app->request->currentUrl();
    }

    public function content(): void
    {
        $this->loop()->content();
    }

    public function getContent(): string
    {
        return $this->loop()->getContent();
    }

    public function excerpt(?int $length = null, string $trim = '…'): void
    {
        $this->loop()->excerpt($length, $trim);
    }

    public function getExcerpt(): string
    {
        return $this->loop()->getExcerpt();
    }

    public function date(?string $format = null): void
    {
        $this->loop()->date($format);
    }

    public function author(): void
    {
        $this->loop()->author();
    }

    public function category(string $split = ', ', bool $link = true): void
    {
        $this->loop()->category($split, $link);
    }

    public function tags(string $split = ', ', bool $link = true): void
    {
        $this->loop()->tags($split, $link);
    }

    public function commentsNum(?string $zero = null, ?string $one = null, ?string $more = null): void
    {
        $this->loop()->commentsNum($zero, $one, $more);
    }

    public function viewsNum(): void
    {
        $this->loop()->viewsNum();
    }

    public function cover(): void
    {
        $this->loop()->cover();
    }

    public function fields(): ThemeFields
    {
        return $this->fieldsObject();
    }

    /** Custom fields mapped onto WenBlog's post/page columns. */
    public function fieldsObject(): ThemeFields
    {
        $row = $this->loop()->currentItem();
        $values = [];
        if (is_array($row)) {
            $cover = trim((string) ($row['cover'] ?? ''));
            if ($cover !== '') {
                $values['img'] = upload_url($cover);
            }
            $excerpt = trim((string) ($row['excerpt'] ?? ''));
            if ($excerpt !== '') {
                $values['desc'] = e($excerpt);
            }
        }

        return new ThemeFields($values);
    }

    /** Raw Markdown source of the current post, used for word counts. */
    public function getText(): string
    {
        $row = $this->loop()->currentItem();

        return is_array($row) ? (string) ($row['content'] ?? '') : '';
    }

    /** Typecho's `$this->cid()` helper (echoes the current content id). */
    public function cid(): void
    {
        echo (string) $this->loop()->id();
    }

    /* ---------------------------------------------------------------------
     * Widgets and Typecho archive helpers
     * ------------------------------------------------------------------ */

    /** Typecho's `$this->widget('Widget_*')` entry point. */
    public function widget(string $alias, mixed $params = null): object
    {
        return TypechoCompat::widget($alias, $params);
    }

    /** Whether the current archive/listing contains at least one item. */
    public function have(): bool
    {
        return $this->loop()->count() > 0;
    }

    /** Total number of items behind the current pagination. */
    public function getTotal(): int
    {
        $pagination = $this->pagination();

        return $pagination !== null ? $pagination->total() : $this->loop()->count();
    }

    /** Typecho's `$this->allow('comment')` check. */
    public function allow(string $type = 'comment'): bool
    {
        return $type === 'comment' ? $this->commentsOpen() : true;
    }

    public function commentUrl(): void
    {
        // Typecho themes post the comment fields only, so the post id is
        // carried in the action URL for the WenBlog endpoint.
        echo e(url('comments') . '?post_id=' . $this->loop()->id());
    }

    public function respondId(): void
    {
        echo 'respond';
    }

    /**
     * Commenter "remember me" values. WenBlog does not persist these yet, so
     * the method returns an empty string in the requested echo/return mode.
     */
    public function remember(string $key = 'author', bool $return = false): string
    {
        $value = '';
        if ($key === 'author') {
            $value = '';
        }
        if (!$return) {
            echo e($value);
        }

        return $value;
    }

    public function currentPageNumber(): int
    {
        $pagination = $this->pagination();
        if ($pagination !== null) {
            return $pagination->current();
        }
        $data = $this->theme->data();
        if (isset($data['currentPage'])) {
            return max(1, (int) $data['currentPage']);
        }
        $query = $this->app->request->query('page');
        if (is_scalar($query) && ctype_digit((string) $query)) {
            return max(1, (int) $query);
        }

        return 1;
    }

    public function authorUser(): ThemeUser
    {
        $row = $this->loop()->currentItem();
        $name = (string) ($row['author_name'] ?? $row['author_username'] ?? option('site_name', 'WenBlog'));
        $user = is_array($row) ? [
            'id'           => (int) ($row['author_id'] ?? 0),
            'username'     => (string) ($row['author_username'] ?? ''),
            'display_name' => $name,
            'role'         => 'author',
        ] : null;

        return new ThemeUser($user, $name);
    }

    /* ---------------------------------------------------------------------
     * Archive title helper
     * ------------------------------------------------------------------ */

    /**
     * Typecho's archive title helper.
     *
     * New style (used by most Typecho themes):
     *     $this->archiveTitle(['category' => '分类 %s 下的文章'], '', ' - ');
     * Legacy style kept for existing WenBlog themes:
     *     $this->archiveTitle('', ' - ', true);
     */
    public function archiveTitle(array|string $categories = [], string $before = '', string|bool $after = '', ?bool $echo = null): string
    {
        if (is_string($categories)) {
            // Legacy ($before, $after, $echo) signature.
            $legacyEcho = is_bool($after) ? $after : true;
            $text = $this->getArchiveTitle();
            $out = $text === '' ? '' : $categories . e($text) . $before;
            if ($legacyEcho) {
                echo $out;
            }

            return $out;
        }

        $templateAfter = is_bool($after) ? '' : $after;
        $shouldEcho = $echo ?? true;
        $text = $this->archiveTitleText($categories);
        $out = $text === '' ? '' : $before . $text . $templateAfter;
        if ($shouldEcho) {
            echo $out;
        }

        return $out;
    }

    public function getArchiveTitle(?array $categories = null): string
    {
        if ($categories !== null) {
            return $this->archiveTitleText($categories);
        }

        $data = $this->theme->data();
        $category = (string) ($data['category']['name'] ?? '');
        if ($category !== '') {
            return $category;
        }
        $tag = (string) ($data['tag']['name'] ?? '');
        if ($tag !== '') {
            return $tag;
        }
        $pageTitle = trim((string) ($data['pageTitle'] ?? ''));

        return $pageTitle !== '' ? $pageTitle : (string) option('site_name', 'WenBlog');
    }

    /** @param array<string, string> $categories */
    private function archiveTitleText(array $categories): string
    {
        $data = $this->theme->data();
        $defaults = [
            'category' => '分类 %s 下的文章',
            'search'   => '包含关键字 %s 的文章',
            'tag'      => '标签 %s 下的文章',
            'author'   => '%s 发布的文章',
            'date'     => '%s 下的文章',
        ];
        $templates = array_merge($defaults, $categories);

        if (isset($data['category']['name'])) {
            return sprintf($templates['category'], e((string) $data['category']['name']));
        }
        if (isset($data['tag']['name'])) {
            return sprintf($templates['tag'], e((string) $data['tag']['name']));
        }
        if (isset($data['query']) && is_scalar($data['query'])) {
            return sprintf($templates['search'], e((string) $data['query']));
        }
        if (isset($data['post']['title'])) {
            return e((string) $data['post']['title']);
        }
        if (isset($data['page']['title'])) {
            return e((string) $data['page']['title']);
        }

        $year = (int) ($data['filterYear'] ?? 0);
        if ($year > 0) {
            $month = (int) ($data['filterMonth'] ?? 0);
            $date = $year . ' 年' . ($month > 0 ? ' ' . $month . ' 月' : '');

            return sprintf($templates['date'], e($date));
        }

        // Home and other unspecified archives intentionally return nothing so
        // `<title>` falls back to the site name.
        return '';
    }

    /* ---------------------------------------------------------------------
     * Pagination
     * ------------------------------------------------------------------ */

    public function pagination(): ?Paginator
    {
        $pagination = $this->theme->data('pagination');

        return $pagination instanceof Paginator ? $pagination : null;
    }

    /**
     * Output Typecho-style `<ol class="page-navigator">` markup.
     */
    public function pageNav(string $prev = '上一页', string $next = '下一页', int $mid = 2, bool $echo = true): string
    {
        return $this->renderPageNav($this->pagination(), $prev, $next, $mid, $echo);
    }

    /**
     * Comment pagination, used by `$this->comments()->pageNav()`.
     */
    public function commentsPageNav(string $prev = '上一页', string $next = '下一页', int $mid = 2, bool $echo = true): string
    {
        $pagination = $this->theme->data('commentPagination');

        return $this->renderPageNav(
            $pagination instanceof Paginator ? $pagination : null,
            $prev,
            $next,
            $mid,
            $echo
        );
    }

    private function renderPageNav(?Paginator $pagination, string $prev, string $next, int $mid, bool $echo): string
    {
        if ($pagination === null || !$pagination->hasPages()) {
            return '';
        }

        $html = '<ol class="page-navigator">';
        if ($pagination->hasPrevious()) {
            $html .= '<li class="prev"><a href="' . e($pagination->url($pagination->previousPage())) . '">'
                . $prev . '</a></li>';
        }
        foreach ($pagination->pages(max(1, $mid)) as $page) {
            if ($page === '…') {
                $html .= '<li class="ellipsis"><span>…</span></li>';
                continue;
            }
            $number = (int) $page;
            $html .= $number === $pagination->current()
                ? '<li class="current"><a href="' . e($pagination->url($number)) . '">' . $number . '</a></li>'
                : '<li><a href="' . e($pagination->url($number)) . '">' . $number . '</a></li>';
        }
        if ($pagination->hasNext()) {
            $html .= '<li class="next"><a href="' . e($pagination->url($pagination->nextPage())) . '">'
                . $next . '</a></li>';
        }
        $html .= '</ol>';

        if ($echo) {
            echo $html;
        }

        return $html;
    }

    /**
     * Typecho's `$this->pageLink('上一页', 'prev')` outputs an anchor tag.
     * Returns the markup (or null) so callers can reuse it.
     */
    public function pageLink(string $text = '', string $type = 'next', bool $echo = true): ?string
    {
        $pagination = $this->pagination();
        if ($pagination === null) {
            return null;
        }
        $url = match (strtolower($type)) {
            'prev', 'previous' => $pagination->hasPrevious() ? $pagination->url($pagination->previousPage()) : null,
            'next'             => $pagination->hasNext() ? $pagination->url($pagination->nextPage()) : null,
            default            => null,
        };
        if ($url === null) {
            return null;
        }
        if ($text === '') {
            $text = strtolower($type) === 'prev' ? '上一页' : '下一页';
        }
        $link = '<a href="' . e($url) . '">' . $text . '</a>';
        if ($echo) {
            echo $link;
        }

        return $link;
    }

    /* ---------------------------------------------------------------------
     * Template helpers
     * ------------------------------------------------------------------ */

    /** Include a theme file using the same variables as the current template. */
    public function need(string $name): void
    {
        $this->theme->need($name);
    }

    /** Output the theme head hook (Typecho calls this in header.php). */
    public function header(): void
    {
        Hook::do('theme.head');
    }

    /** Output the theme footer hook (Typecho calls this before </body>). */
    public function footer(): void
    {
        Hook::do('theme.footer');
    }

    /* ---------------------------------------------------------------------
     * Comments
     * ------------------------------------------------------------------ */

    public function comments(): CommentLoop
    {
        if ($this->comments === null) {
            $comments = $this->theme->data('comments');

            $this->comments = new CommentLoop(is_array($comments) ? $comments : [], $this);
        }

        return $this->comments;
    }

    public function commentsOpen(): bool
    {
        $row = $this->loop()->currentItem();
        $allow = (string) ($row['allow_comments'] ?? '1') === '1';

        return (string) option('comments_enabled', '1') === '1' && $allow;
    }

    public function commentCount(): void
    {
        $count = $this->theme->data('commentTotal');
        if ($count === null) {
            $count = $this->loop()->currentItem()['comment_count'] ?? 0;
        }
        echo (string) (int) $count;
    }

    /* ---------------------------------------------------------------------
     * Row helpers shared with PostLoop
     * ------------------------------------------------------------------ */

    /** @param array<string, mixed> $row */
    public function rowPermalink(array $row): string
    {
        $data = $this->theme->data();
        if (isset($data['page']['id']) && (int) $data['page']['id'] === (int) ($row['id'] ?? 0)) {
            return page_url($row);
        }

        return post_url($row);
    }

    /** @param array<string, mixed> $row */
    public function rowContent(array $row): string
    {
        $data = $this->theme->data();
        $isPage = isset($data['page']['id']) && (int) $data['page']['id'] === (int) ($row['id'] ?? 0);
        $html = Markdown::html((string) ($row['content'] ?? ''));

        return (string) Hook::filter($isPage ? 'page.html' : 'post.html', $html, $row);
    }

    /**
     * @param array<string, mixed> $row
     * @return list<array<string, mixed>>
     */
    public function rowCategories(array $row): array
    {
        $id = (int) ($row['id'] ?? 0);
        if ($id <= 0) {
            return [];
        }

        $data = $this->theme->data();
        if (isset($data['post']['id'], $data['categories'])
            && (int) $data['post']['id'] === $id && is_array($data['categories'])) {
            return $data['categories'];
        }

        if (!isset($this->categoryCache[$id])) {
            $this->categoryCache[$id] = (new \App\Models\Post($this->app))->categories($id);
        }

        return $this->categoryCache[$id];
    }

    /**
     * @param array<string, mixed> $row
     * @return list<array<string, mixed>>
     */
    public function rowTags(array $row): array
    {
        $id = (int) ($row['id'] ?? 0);
        if ($id <= 0) {
            return [];
        }

        $data = $this->theme->data();
        if (isset($data['post']['id'], $data['tags'])
            && (int) $data['post']['id'] === $id && is_array($data['tags'])) {
            return $data['tags'];
        }

        if (!isset($this->tagCache[$id])) {
            $this->tagCache[$id] = (new \App\Models\Post($this->app))->tags($id);
        }

        return $this->tagCache[$id];
    }

    public function currentPostUrl(): string
    {
        $row = $this->loop()->currentItem();

        return $row !== null ? $this->rowPermalink($row) : url('/');
    }
}
