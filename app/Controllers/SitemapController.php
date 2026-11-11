<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Category;
use App\Models\Page;
use App\Models\Post;
use App\Models\Tag;

final class SitemapController extends FrontController
{
    public function index(): void
    {
        $posts = (new Post($this->app))->paginate([], 1, 1000)['items'];
        $pages = (new Page($this->app))->published();
        $categories = (new Category($this->app))->all();
        $tags = (new Tag($this->app))->allWithCounts();

        header('Content-Type: application/xml; charset=UTF-8');
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        $this->url(url('/'), null, '1.0', 'daily');
        foreach ($posts as $post) {
            $this->url(post_url($post), $post['updated_at'] ?? $post['published_at'], '0.8', 'weekly');
        }
        foreach ($pages as $page) {
            $this->url(page_url($page), $page['updated_at'] ?? null, '0.6', 'monthly');
        }
        foreach ($categories as $category) {
            $this->url(category_url($category), null, '0.5', 'weekly');
        }
        foreach ($tags as $tag) {
            if ((int) $tag['post_count'] > 0) {
                $this->url(tag_url($tag), null, '0.4', 'weekly');
            }
        }
        echo '</urlset>';
    }

    public function robots(): void
    {
        header('Content-Type: text/plain; charset=UTF-8');
        $base = rtrim((string) (parse_url(url('/'), PHP_URL_PATH) ?? '/'), '/');
        echo "User-agent: *\n";
        echo 'Disallow: ' . $base . "/admin/\n";
        echo 'Disallow: ' . $base . "/install/\n";
        echo 'Disallow: ' . $base . "/api/\n";
        echo 'Allow: ' . ($base !== '' ? $base . '/' : '/') . "\n\n";
        echo 'Sitemap: ' . url('sitemap.xml') . "\n";
    }

    private function url(string $location, ?string $lastmod, string $priority, string $changefreq): void
    {
        echo '  <url><loc>' . htmlspecialchars($location, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</loc>';
        if ($lastmod !== null && $lastmod !== '') {
            $timestamp = strtotime($lastmod);
            if ($timestamp !== false) {
                echo '<lastmod>' . date('c', $timestamp) . '</lastmod>';
            }
        }
        echo '<changefreq>' . $changefreq . '</changefreq><priority>' . $priority . '</priority></url>' . "\n";
    }
}