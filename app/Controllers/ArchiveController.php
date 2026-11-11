<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Post;

final class ArchiveController extends FrontController
{
    public function index(): void
    {
        $year = (int) ($this->app->request->query('year') ?? 0);
        $month = (int) ($this->app->request->query('month') ?? 0);
        if ($month < 1 || $month > 12) {
            $month = 0;
        }
        if ($year < 1970 || $year > 2100) {
            $year = 0;
            $month = 0;
        }
        $page = $this->pageNumber();
        $perPage = max(1, (int) option('posts_per_page', 8));
        $posts = new Post($this->app);
        $filters = array_filter(['year' => $year ?: null, 'month' => $month ?: null]);
        $result = $posts->paginate($filters, $page, $perPage);
        if ($page > 1 && $result['items'] === []) {
            $this->notFound();
        }
        $title = '文章归档';
        if ($year > 0) {
            $title = $year . ' 年' . ($month > 0 ? ' ' . $month . ' 月' : '') . '归档';
        }
        $pagination = $this->paginator(
            $result['total'],
            $perPage,
            $page,
            static function (int $number) use ($year, $month): string {
                $params = [];
                if ($year > 0) {
                    $params['year'] = $year;
                }
                if ($month > 0) {
                    $params['month'] = $month;
                }
                if ($number > 1) {
                    $params['page'] = $number;
                }

                return url('archives') . ($params !== [] ? '?' . http_build_query($params) : '');
            }
        );

        $this->theme('archive', $this->pageData([
            'posts'         => $result['items'],
            'pagination'    => $pagination,
            'archives'      => $posts->archiveCounts(),
            'filterYear'    => $year,
            'filterMonth'   => $month,
            'pageTitle'     => $title,
            'noIndex'       => $year === 0,
        ]));
    }
}