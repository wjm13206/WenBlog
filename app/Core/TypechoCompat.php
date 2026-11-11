<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\Category;
use App\Models\Page;
use App\Models\Post;
use App\Models\Tag;

/**
 * Compatibility layer for themes originally written for Typecho.
 *
 * WenBlog is not a Typecho fork, but most Typecho themes only rely on a small,
 * well-known surface: `_t()` / `_e()`, `Helper::options()`, `Widget_*` loops
 * and a tiny subset of `Typecho_Db`. This class translates those calls onto
 * WenBlog's own models and database wrapper.
 */
final class TypechoCompat
{
    public static function options(): ThemeOptions
    {
        $app = self::app();

        return new ThemeOptions($app->theme);
    }

    /**
     * Create the object behind `Typecho_Widget::widget($alias, $params)` or
     * `$this->widget($alias, $params)`.
     */
    public static function widget(string $alias, mixed $params = null): object
    {
        $app = self::app();
        $name = str_contains($alias, '\\') ? substr($alias, (int) strrpos($alias, '\\') + 1) : $alias;
        $params = self::parseParams($params);

        return match ($name) {
            'Widget_Options'               => self::options(),
            'Widget_Security'              => new TypechoCompatSecurity(),
            'Widget_Contents_Page_List'    => new TypechoCompatWidget(self::pageRows($app), $params),
            'Widget_Metas_Category_List'   => new TypechoCompatWidget(self::categoryRows($app), $params),
            'Widget_Metas_Tag_Cloud'       => new TypechoCompatWidget(self::tagRows($app), $params),
            'Widget_Contents_Post_Recent'  => new TypechoCompatWidget(
                self::postRows($app, max(1, (int) ($params['pageSize'] ?? 5))),
                $params
            ),
            default                        => new TypechoCompatWidget([], $params),
        };
    }

    public static function db(): TypechoCompatDb
    {
        return new TypechoCompatDb(self::app()->db);
    }

    private static function app(): App
    {
        $app = \app();
        if (!$app instanceof App) {
            throw new \RuntimeException('WenBlog is not booted.');
        }

        return $app;
    }

    /** @return array<string, string> */
    private static function parseParams(mixed $params): array
    {
        if (is_array($params)) {
            return array_map(static fn (mixed $value): string => (string) $value, $params);
        }
        $result = [];
        if (is_string($params) && trim($params) !== '') {
            foreach (explode('&', $params) as $pair) {
                [$key, $value] = array_pad(explode('=', $pair, 2), 2, '');
                $key = trim($key);
                if ($key !== '') {
                    $result[$key] = urldecode(trim($value));
                }
            }
        }

        return $result;
    }

    /** @return list<array<string, mixed>> */
    private static function pageRows(App $app): array
    {
        $rows = [];
        foreach ((new Page($app))->published() as $row) {
            $row['cid'] = (int) ($row['id'] ?? 0);
            $row['permalink'] = page_url($row);
            $row['date'] = self::dateValue($row['created_at'] ?? null);
            $rows[] = $row;
        }

        return $rows;
    }

    /** @return list<array<string, mixed>> */
    private static function categoryRows(App $app): array
    {
        $rows = [];
        foreach ((new Category($app))->allWithCounts() as $row) {
            $row['mid'] = (int) ($row['id'] ?? 0);
            $row['count'] = (int) ($row['post_count'] ?? 0);
            $row['permalink'] = category_url($row);
            $rows[] = $row;
        }

        return $rows;
    }

    /** @return list<array<string, mixed>> */
    private static function tagRows(App $app): array
    {
        $rows = [];
        foreach ((new Tag($app))->allWithCounts() as $row) {
            $row['mid'] = (int) ($row['id'] ?? 0);
            $row['count'] = (int) ($row['post_count'] ?? 0);
            $row['permalink'] = tag_url($row);
            $rows[] = $row;
        }

        return $rows;
    }

    /** @return list<array<string, mixed>> */
    private static function postRows(App $app, int $limit): array
    {
        $rows = [];
        foreach ((new Post($app))->latest($limit) as $row) {
            $row['cid'] = (int) ($row['id'] ?? 0);
            $row['permalink'] = post_url($row);
            $row['date'] = self::dateValue($row['published_at'] ?? $row['created_at'] ?? null);
            $rows[] = $row;
        }

        return $rows;
    }

    private static function dateValue(mixed $value): ?\DateTimeImmutable
    {
        if (!is_scalar($value) || (string) $value === '') {
            return null;
        }
        try {
            return new \DateTimeImmutable((string) $value);
        } catch (\Throwable) {
            return null;
        }
    }
}

/**
 * Typecho-style widget cursor. Supports the `to() / have() / next()` pattern
 * together with property and method access to the current row.
 */
final class TypechoCompatWidget
{
    private int $index = -1;

    /**
     * @param list<array<string, mixed>> $rows
     * @param array<string, string>      $params
     */
    public function __construct(
        private readonly array $rows,
        private readonly array $params = []
    ) {
    }

    /** @param mixed $target */
    public function to(&$target): self
    {
        $target = $this;

        return $this;
    }

    public function have(): bool
    {
        return $this->rows !== [];
    }

    public function count(): int
    {
        return count($this->rows);
    }

    public function next(): bool
    {
        if ($this->index + 1 < count($this->rows)) {
            $this->index++;

            return true;
        }

        return false;
    }

    public function rewind(): void
    {
        $this->index = -1;
    }

    /** @return array<string, mixed>|null */
    public function currentRow(): ?array
    {
        return $this->index >= 0 ? ($this->rows[$this->index] ?? null) : null;
    }

    public function __get(string $name): mixed
    {
        $row = $this->currentRow();
        if ($row === null || !array_key_exists($name, $row)) {
            return null;
        }
        $value = $row[$name];

        if (in_array($name, ['title', 'name'], true) && is_scalar($value)) {
            return e((string) $value);
        }

        return $value;
    }

    public function __isset(string $name): bool
    {
        return $this->__get($name) !== null;
    }

    public function __call(string $name, array $arguments): void
    {
        $value = $this->__get($name);
        if (is_scalar($value)) {
            echo $name === 'permalink' || $name === 'url' ? e((string) $value) : (string) $value;
        } elseif ($value instanceof \DateTimeInterface) {
            echo e($value->format('Y-m-d'));
        }
    }
}

/**
 * Minimal `Widget_Security` replacement used by encrypted-post forms.
 */
final class TypechoCompatSecurity
{
    public function getTokenUrl(string $url = ''): string
    {
        $separator = str_contains($url, '?') ? '&' : '?';

        return $url . $separator . '_=' . rawurlencode((string) \csrf_token());
    }

    public function getToken(): string
    {
        return (string) \csrf_token();
    }

    public function getSecurityToken(): string
    {
        return $this->getToken();
    }
}

/**
 * `$this->parameter->pageSize` and friends.
 */
final class TypechoCompatParameter
{
    public function __get(string $name): mixed
    {
        return match ($name) {
            'pageSize', 'page_size' => max(1, (int) option('posts_per_page', 8)),
            'commentPageSize', 'comment_page_size' => max(1, (int) option('comments_per_page', 20)),
            default => null,
        };
    }

    public function __isset(string $name): bool
    {
        return $this->__get($name) !== null;
    }

    public function __call(string $name, array $arguments): void
    {
        $value = $this->__get($name);
        if (is_scalar($value)) {
            echo (string) $value;
        }
    }
}

/**
 * `$this->response->setStatus(404)` used by Typecho 404 templates.
 */
final class TypechoCompatResponse
{
    public function setStatus(int $code): void
    {
        if (!headers_sent()) {
            http_response_code($code);
        }
    }

    public function setHeader(string $name, string $value): void
    {
        if (!headers_sent()) {
            header($name . ': ' . $value);
        }
    }

    public function setContentType(string $type): void
    {
        $this->setHeader('Content-Type', $type);
    }

    public function redirect(string $url, int $status = 302): void
    {
        if (!headers_sent()) {
            header('Location: ' . $url, true, $status);
        }
    }
}

/**
 * Request object exposed as `$this->request` by Typecho themes.
 */
final class TypechoCompatRequest
{
    public function isAjax(): bool
    {
        return strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
    }

    public function isPost(): bool
    {
        return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST';
    }

    public function isGet(): bool
    {
        return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'GET';
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $_GET[$key] ?? null;

        return is_scalar($value) ? $value : $default;
    }

    public function getRequestUri(): string
    {
        return (string) ($_SERVER['REQUEST_URI'] ?? '/');
    }

    public function getPathInfo(): string
    {
        $path = parse_url($this->getRequestUri(), PHP_URL_PATH);

        return is_string($path) ? $path : '/';
    }
}

/**
 * Very small subset of the Typecho query builder. It exists so themes can run
 * simple aggregate queries such as "total characters" without knowing about
 * WenBlog's schema.
 */
final class TypechoCompatDb
{
    public function __construct(private readonly Database $db)
    {
    }

    public function select(mixed $fields = '*'): TypechoCompatQuery
    {
        return new TypechoCompatQuery($fields);
    }

    public function fetchRow(mixed $query): ?array
    {
        [$sql, $params] = $this->sql($query);

        return $this->db->fetch($sql, $params);
    }

    /** @return list<array<string, mixed>> */
    public function fetchAll(mixed $query): array
    {
        [$sql, $params] = $this->sql($query);

        return $this->db->fetchAll($sql, $params);
    }

    public function fetchObject(mixed $query): ?object
    {
        $row = $this->fetchRow($query);

        return $row === null ? null : (object) $row;
    }

    /** @return array{0: string, 1: array<string, mixed>} */
    private function sql(mixed $query): array
    {
        if ($query instanceof TypechoCompatQuery) {
            return $query->build();
        }
        if (is_string($query) && trim($query) !== '') {
            return [$query, []];
        }

        return ['SELECT 1', []];
    }
}

/**
 * Fluent query builder backing TypechoCompatDb.
 */
final class TypechoCompatQuery
{
    /** @var list<string> */
    private array $fields;

    private string $table = 'posts';
    /** @var list<array{0: string, 1: list<mixed>}> */
    private array $wheres = [];
    private string $order = '';
    private ?int $limit = null;
    private ?int $offset = null;

    public function __construct(mixed $fields = '*')
    {
        $this->fields = self::splitFields($fields);
    }

    /** @param list<mixed> $fields */
    public static function splitFields(mixed $fields): array
    {
        if (is_array($fields)) {
            return array_values(array_map(static fn (mixed $value): string => (string) $value, $fields));
        }
        $fields = trim((string) $fields);
        if ($fields === '' || $fields === '*') {
            return ['*'];
        }
        $parts = array_map('trim', explode(',', $fields));

        return $parts === [] ? ['*'] : $parts;
    }

    public function from(string $table): self
    {
        $table = trim($table);
        $table = preg_replace('/^table\./i', '', $table) ?? $table;
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table) ?? '';
        $this->table = match ($table) {
            'contents' => 'posts',
            'options' => 'options',
            'metas' => 'categories',
            default => $table !== '' ? $table : 'posts',
        };

        return $this;
    }

    /** @param list<mixed> $arguments */
    public function where(string $condition, mixed ...$arguments): self
    {
        $this->wheres[] = [$condition, array_values($arguments)];

        return $this;
    }

    public function order(string $field, string $direction = 'DESC'): self
    {
        $direction = strtoupper($direction) === 'ASC' ? 'ASC' : 'DESC';
        $field = self::mapColumns($field, false);
        $this->order = $field . ' ' . $direction;

        return $this;
    }

    public function limit(int $limit): self
    {
        $this->limit = max(0, $limit);

        return $this;
    }

    public function offset(int $offset): self
    {
        $this->offset = max(0, $offset);

        return $this;
    }

    /** @return array{0: string, 1: array<string, mixed>} */
    public function build(): array
    {
        $table = $this->table;
        foreach ($this->wheres as [$condition, $arguments]) {
            if (preg_match('/^\s*type\s*=\s*\?/i', $condition) === 1
                && isset($arguments[0])
                && in_array(strtolower((string) $arguments[0]), ['page', 'pages'], true)) {
                $table = 'pages';
            }
        }
        $options = $table === 'options';

        $select = $this->selectSql($options);
        $bindings = [];
        $conditions = [];
        $position = 0;

        foreach ($this->wheres as [$condition, $arguments]) {
            $condition = trim($condition);
            if ($condition === '') {
                continue;
            }
            // The "type" column has no equivalent: posts and pages live in
            // separate tables, so the filter has already done its job.
            if (preg_match('/^\s*type\s*=\s*(?:\?|\'[^\']*\')\s*$/i', $condition) === 1) {
                continue;
            }

            $arguments = array_map(
                static fn (mixed $value): mixed => is_string($value) && strtolower($value) === 'publish'
                    ? 'published'
                    : $value,
                $arguments
            );
            $condition = str_ireplace("'publish'", "'published'", $condition);

            $parts = explode('?', $condition);
            $rewritten = '';
            foreach ($parts as $index => $part) {
                $rewritten .= $part;
                if ($index < count($parts) - 1) {
                    $key = ':wb' . $position;
                    $bindings[$key] = $arguments[$index] ?? null;
                    $position++;
                    $rewritten .= $key;
                }
            }
            $conditions[] = self::mapColumns($rewritten, $options);
        }

        $sql = 'SELECT ' . $select . ' FROM `' . $table . '`';
        if ($conditions !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }
        if ($this->order !== '') {
            $sql .= ' ORDER BY ' . $this->order;
        }
        if ($this->limit !== null) {
            $sql .= ' LIMIT ' . $this->limit;
        }
        if ($this->offset !== null) {
            $sql .= ' OFFSET ' . $this->offset;
        }

        return [$sql, $bindings];
    }

    private function selectSql(bool $options): string
    {
        if ($options) {
            $onlyStar = count($this->fields) === 1 && trim($this->fields[0]) === '*';

            return $onlyStar ? 'option_name AS name, option_value AS value' : self::mapColumns(implode(', ', $this->fields), true);
        }

        $expressions = [];
        foreach ($this->fields as $field) {
            $field = trim($field);
            if ($field === '' || $field === '*') {
                $expressions[] = '*';
                continue;
            }
            $expressions[] = self::mapSelectExpression($field);
        }

        return $expressions === [] ? '*' : implode(', ', $expressions);
    }

    private static function mapSelectExpression(string $expression): string
    {
        $parts = preg_split('/\s+AS\s+/i', $expression, 2) ?: [$expression];
        $body = trim($parts[0]);
        $alias = isset($parts[1]) ? trim($parts[1]) : null;

        $mapped = self::mapColumns($body, false);
        if ($alias === null && strcasecmp($body, 'text') === 0) {
            // Themes read `$row['text']`; keep the alias after mapping to content.
            $alias = 'text';
        }

        return $alias === null ? $mapped : $mapped . ' AS ' . $alias;
    }

    private static function mapColumns(string $sql, bool $options): string
    {
        if ($options) {
            $sql = preg_replace('/\bname\b/i', 'option_name', $sql) ?? $sql;
            $sql = preg_replace('/\bvalue\b/i', 'option_value', $sql) ?? $sql;

            return $sql;
        }

        $sql = preg_replace('/\bmodified\b/i', 'updated_at', $sql) ?? $sql;
        $sql = preg_replace('/\btext\b/i', 'content', $sql) ?? $sql;
        $sql = preg_replace('/\bcreated\b/i', 'created_at', $sql) ?? $sql;
        $sql = preg_replace('/\bcid\b/i', 'id', $sql) ?? $sql;

        return $sql;
    }
}