<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Cursor over the posts of the current page, matching Typecho's
 * `while ($this->next()): … endwhile;` pattern.
 */
final class PostLoop
{
    private int $index = -1;

    /** @param list<array<string, mixed>> $posts */
    public function __construct(
        private readonly ThemeApi $api,
        private readonly array $posts
    ) {
    }

    public function next(): bool
    {
        if ($this->index + 1 < count($this->posts)) {
            $this->index++;

            return true;
        }

        return false;
    }

    public function rewind(): void
    {
        $this->index = -1;
    }

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        return $this->posts;
    }

    public function count(): int
    {
        return count($this->posts);
    }

    /**
     * Current row. Before the loop starts it falls back to the single post or
     * page being rendered, so `$this->title()` works in single.php too.
     *
     * @return array<string, mixed>|null
     */
    public function currentItem(): ?array
    {
        if ($this->index >= 0 && isset($this->posts[$this->index])) {
            return $this->posts[$this->index];
        }

        return $this->api->contextPost();
    }

    public function id(): int
    {
        return (int) ($this->currentItem()['id'] ?? 0);
    }

    public function title(): void
    {
        $row = $this->currentItem();
        if ($row !== null) {
            echo e((string) ($row['title'] ?? ''));
        }
    }

    public function permalink(): void
    {
        $row = $this->currentItem();
        if ($row !== null) {
            echo e($this->api->rowPermalink($row));
        }
    }

    public function content(): void
    {
        $row = $this->currentItem();
        if ($row !== null) {
            echo $this->api->rowContent($row);
        }
    }

    public function excerpt(?int $length = null, string $trim = '…'): void
    {
        echo e($this->getExcerpt($length, $trim));
    }

    public function date(?string $format = null): void
    {
        echo e($this->getDate($format));
    }

    public function getDate(?string $format = null): string
    {
        $row = $this->currentItem();
        if ($row === null) {
            return '';
        }
        $value = $row['published_at'] ?? $row['created_at'] ?? $row['updated_at'] ?? null;

        return format_date(is_scalar($value) ? (string) $value : null, $format);
    }

    public function author(): void
    {
        echo e($this->getAuthor());
    }

    public function getAuthor(): string
    {
        $row = $this->currentItem();

        return (string) ($row['author_name'] ?? $row['author_username'] ?? option('site_name', 'WenBlog'));
    }

    public function category(string $split = ', ', bool $link = true): void
    {
        $row = $this->currentItem();
        if ($row === null) {
            return;
        }
        $items = [];
        foreach ($this->api->rowCategories($row) as $category) {
            $name = e((string) ($category['name'] ?? ''));
            $items[] = $link
                ? '<a href="' . e(category_url($category)) . '">' . $name . '</a>'
                : $name;
        }
        echo implode($split, $items);
    }

    public function tags(string $split = ', ', bool $link = true): void
    {
        $row = $this->currentItem();
        if ($row === null) {
            return;
        }
        $items = [];
        foreach ($this->api->rowTags($row) as $tag) {
            $name = e((string) ($tag['name'] ?? ''));
            $items[] = $link
                ? '<a href="' . e(tag_url($tag)) . '">' . $name . '</a>'
                : $name;
        }
        echo implode($split, $items);
    }

    public function commentsNum(?string $zero = null, ?string $one = null, ?string $more = null): void
    {
        $count = (int) ($this->currentItem()['comment_count'] ?? 0);
        if ($one === null && $more === null) {
            echo $zero === null ? (string) $count : sprintf($zero, $count);

            return;
        }
        if ($count <= 0) {
            echo (string) $zero;

            return;
        }
        if ($count === 1) {
            echo (string) ($one ?? $zero);

            return;
        }
        echo sprintf((string) ($more ?? $one ?? $zero), $count);
    }

    public function viewsNum(): void
    {
        echo (string) (int) ($this->currentItem()['views'] ?? 0);
    }

    public function cover(): void
    {
        $cover = trim((string) ($this->currentItem()['cover'] ?? ''));
        if ($cover !== '') {
            echo e(upload_url($cover));
        }
    }

    public function fields(): ThemeFields
    {
        return new ThemeFields();
    }

    public function getTitle(): string
    {
        return (string) ($this->currentItem()['title'] ?? '');
    }

    public function getPermalink(): string
    {
        $row = $this->currentItem();

        return $row === null ? '' : $this->api->rowPermalink($row);
    }

    public function getContent(): string
    {
        $row = $this->currentItem();

        return $row === null ? '' : $this->api->rowContent($row);
    }

    public function getExcerpt(?int $length = null, string $trim = '…'): string
    {
        $row = $this->currentItem();
        if ($row === null) {
            return '';
        }

        // post_excerpt() always appends an ellipsis; Typecho lets the theme
        // choose the suffix ('' removes it entirely, e.g. PureSuck cards).
        $text = post_excerpt($row, $length);
        if ($trim === '…') {
            return $text;
        }
        if ($trim === '') {
            return (string) preg_replace('/…$/u', '', $text);
        }
        if (str_ends_with($text, '…')) {
            return substr($text, 0, -3) . $trim;
        }

        return $text;
    }

    /** @return list<array<string, mixed>> */
    public function getCategories(): array
    {
        $row = $this->currentItem();

        return $row === null ? [] : $this->api->rowCategories($row);
    }

    /** @return list<array<string, mixed>> */
    public function getTags(): array
    {
        $row = $this->currentItem();

        return $row === null ? [] : $this->api->rowTags($row);
    }
}
