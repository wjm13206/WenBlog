<?php

declare(strict_types=1);

namespace App\Core;

use Closure;

/**
 * Immutable pagination value object. The URL builder receives a page number
 * and returns the absolute URL for that page.
 */
final class Paginator
{
    private Closure $urlBuilder;
    private int $total;
    private int $perPage;
    private int $current;
    private int $lastPage;

    public function __construct(int $total, int $perPage, int $current, callable $urlBuilder)
    {
        $this->total = max(0, $total);
        $this->perPage = max(1, $perPage);
        $this->lastPage = max(1, (int) ceil($this->total / $this->perPage));
        $this->current = min(max(1, $current), $this->lastPage);
        $this->urlBuilder = Closure::fromCallable($urlBuilder);
    }

    public function total(): int
    {
        return $this->total;
    }

    public function perPage(): int
    {
        return $this->perPage;
    }

    public function current(): int
    {
        return $this->current;
    }

    public function lastPage(): int
    {
        return $this->lastPage;
    }

    public function offset(): int
    {
        return ($this->current - 1) * $this->perPage;
    }

    public function hasPages(): bool
    {
        return $this->lastPage > 1;
    }

    public function hasPrevious(): bool
    {
        return $this->current > 1;
    }

    public function hasNext(): bool
    {
        return $this->current < $this->lastPage;
    }

    public function previousPage(): int
    {
        return max(1, $this->current - 1);
    }

    public function nextPage(): int
    {
        return min($this->lastPage, $this->current + 1);
    }

    public function url(int $page): string
    {
        $page = min(max(1, $page), $this->lastPage);

        return ($this->urlBuilder)($page);
    }

    /**
     * Page numbers with ellipsis markers around the current page.
     *
     * @return list<int|string>
     */
    public function pages(int $radius = 2): array
    {
        if ($this->lastPage <= 1) {
            return [1];
        }

        $pages = [];
        $start = max(2, $this->current - $radius);
        $end = min($this->lastPage - 1, $this->current + $radius);
        $pages[] = 1;

        if ($start > 2) {
            $pages[] = '…';
        }
        for ($page = $start; $page <= $end; $page++) {
            $pages[] = $page;
        }
        if ($end < $this->lastPage - 1) {
            $pages[] = '…';
        }
        $pages[] = $this->lastPage;

        return array_values(array_unique($pages, SORT_REGULAR));
    }
}