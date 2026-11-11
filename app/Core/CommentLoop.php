<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Typecho-style comment cursor:
 *
 *     <?php $this->comments()->to($comments); ?>
 *     <?php while ($comments->next()): ?>
 *         <?php $comments->author(); ?>
 *     <?php endwhile; ?>
 *
 * Templates that use Typecho's threaded list call `$comments->listComments()`
 * and provide a global `threadedComments($comment, $options)` renderer; each
 * row handed to that renderer is a CommentNode.
 */
final class CommentLoop
{
    /** @var list<array<string, mixed>> Flat list used by next()/rewind(). */
    private array $items = [];
    /** @var list<CommentNode> Threaded tree used by listComments(). */
    private array $roots = [];
    private int $index = -1;

    /** @param list<array<string, mixed>> $comments */
    public function __construct(array $comments, private readonly ThemeApi $api)
    {
        foreach ($comments as $comment) {
            if (!is_array($comment)) {
                continue;
            }
            $root = new CommentNode($comment, 0, $this);
            $replies = [];
            foreach ((array) ($comment['replies'] ?? []) as $reply) {
                if (!is_array($reply)) {
                    continue;
                }
                $root->addChild(new CommentNode($reply, 1, $this));
                $replies[] = $reply;
            }
            $this->items[] = $comment;
            foreach ($replies as $reply) {
                $this->items[] = $reply;
            }
            $this->roots[] = $root;
        }
    }

    /**
     * Typecho's `to()` assigns the loop object to the variable passed by the
     * theme. Keeping the parameter by reference makes that exact pattern work.
     */
    public function to(mixed &$target = null): self
    {
        $target = $this;

        return $this;
    }

    public function have(): bool
    {
        return $this->items !== [];
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function next(): bool
    {
        if ($this->index + 1 < count($this->items)) {
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
    public function currentItem(): ?array
    {
        return $this->index >= 0 ? ($this->items[$this->index] ?? null) : null;
    }

    public function id(): void
    {
        echo (string) (int) ($this->currentItem()['id'] ?? 0);
    }

    public function author(): void
    {
        echo e((string) ($this->currentItem()['author_name'] ?? ''));
    }

    public function mail(): void
    {
        echo e((string) ($this->currentItem()['author_email'] ?? ''));
    }

    public function url(): void
    {
        echo e((string) ($this->currentItem()['author_url'] ?? ''));
    }

    public function content(): void
    {
        echo nl2br(e((string) ($this->currentItem()['content'] ?? '')));
    }

    public function date(?string $format = null): void
    {
        echo e(format_date((string) ($this->currentItem()['created_at'] ?? ''), $format));
    }

    public function permalink(): void
    {
        $id = (int) ($this->currentItem()['id'] ?? 0);
        if ($id > 0) {
            echo e($this->api->currentPostUrl() . '#comment-' . $id);
        }
    }

    public function parent(): void
    {
        echo (string) (int) ($this->currentItem()['parent_id'] ?? 0);
    }

    /** Permalink anchor for a single comment of the current post. */
    public function postUrlForComment(int $id): string
    {
        return $this->api->currentPostUrl() . '#comment-' . $id;
    }

    /**
     * Output the threaded comment list, delegating each row to the theme's
     * global `threadedComments()` renderer when it exists.
     */
    public function listComments(mixed $options = null): void
    {
        if ($this->roots === []) {
            return;
        }

        echo '<ol class="comment-list">';
        foreach ($this->roots as $node) {
            if (function_exists('threadedComments')) {
                \threadedComments($node, $options);
            } else {
                $node->render($options);
            }
        }
        echo '</ol>';
    }

    /** Reply-form cancel link expected by Typecho's comment JavaScript. */
    public function cancelReply(string $text = '取消'): void
    {
        echo '<a id="cancel-comment-reply-link" href="#respond" style="display:none"'
            . ' onclick="return TypechoComment.cancelReply();">' . e($text) . '</a>';
    }

    /**
     * Typecho's comment pagination signature is
     * `pageNav($prev, $next, $splitPage, $splitWord, $template)`; the template
     * array only controls markup, which WenBlog renders itself.
     */
    public function pageNav(
        string $prev = '上一页',
        string $next = '下一页',
        int $splitPage = 2,
        string $splitWord = '...',
        mixed $template = null
    ): string {
        return $this->api->commentsPageNav($prev, $next, max(1, $splitPage), true);
    }
}

/**
 * One comment row in the threaded tree. Property access mirrors Typecho's
 * comment object closely enough for stock themes.
 */
final class CommentNode
{
    /** @param array<string, mixed> $row */
    public function __construct(
        private readonly array $row,
        private readonly int $level,
        private readonly CommentLoop $loop
    ) {
    }

    public function addChild(self $child): void
    {
        $this->children[] = $child;
    }

    /** @var list<CommentNode> */
    private array $children = [];

    public function __get(string $name): mixed
    {
        return match ($name) {
            'levels'    => $this->level,
            'authorId'  => (int) ($this->row['user_id'] ?? 0),
            'author'    => e((string) ($this->row['author_name'] ?? '')),
            'url'       => e((string) ($this->row['author_url'] ?? '')),
            'mail'      => (string) ($this->row['author_email'] ?? ''),
            'content'   => nl2br(e((string) ($this->row['content'] ?? ''))),
            'status'    => (string) ($this->row['status'] ?? ''),
            'children'  => $this->children,
            'id'        => (int) ($this->row['id'] ?? 0),
            'parent'    => (int) ($this->row['parent_id'] ?? 0),
            'permalink' => $this->permalink(),
            'date'      => $this->dateValue(),
            default     => $this->row[$name] ?? null,
        };
    }

    public function __isset(string $name): bool
    {
        return $this->__get($name) !== null;
    }

    public function id(): int
    {
        return (int) ($this->row['id'] ?? 0);
    }

    /** Echo `comment-<id>`; Typecho templates prefix it with `li-`. */
    public function theId(): void
    {
        echo 'comment-' . $this->id();
    }

    public function reply(string $text = '回复'): void
    {
        $id = $this->id();
        echo '<a href="#respond" onclick="return TypechoComment.reply(\'comment-' . $id . '\', ' . $id . ');">'
            . e($text) . '</a>';
    }

    public function date(?string $format = null): void
    {
        echo e($this->dateValue($format));
    }

    public function content(): void
    {
        echo $this->__get('content');
    }

    public function author(): void
    {
        echo $this->__get('author');
    }

    public function mail(): void
    {
        echo e((string) ($this->row['author_email'] ?? ''));
    }

    public function url(): void
    {
        echo $this->__get('url');
    }

    /** Render children recursively with the theme's renderer. */
    public function threadedComments(mixed $options = null): void
    {
        foreach ($this->children as $child) {
            if (function_exists('threadedComments')) {
                \threadedComments($child, $options);
            } else {
                $child->render($options);
            }
        }
    }

    /** Minimal fallback when the theme has no threadedComments() helper. */
    public function render(mixed $options = null): void
    {
        echo '<li id="li-comment-' . $this->id() . '" class="' . ($this->level > 0 ? 'c_c' : 'c_p') . '">'
            . '<div id="comment-' . $this->id() . '">'
            . '<div class="cp">' . $this->__get('content') . '<div class="cm">'
            . '<span class="ca">' . $this->__get('author') . '</span> '
            . '<span class="cr">';
        $this->reply();
        echo '</span></div></div></div>';
        if ($this->children !== []) {
            echo '<div class="children"><ol class="children-list">';
            $this->threadedComments($options);
            echo '</ol></div>';
        }
        echo '</li>';
    }

    private function dateValue(?string $format = null): string
    {
        return format_date((string) ($this->row['created_at'] ?? ''), $format);
    }

    private function permalink(): string
    {
        $id = $this->id();

        return $id > 0 ? $this->loop->postUrlForComment($id) : '';
    }
}