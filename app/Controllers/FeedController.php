<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Post;

final class FeedController extends FrontController
{
    public function rss(): void
    {
        $result = (new Post($this->app))->paginate([], 1, 20);
        $siteName = (string) option('site_name', 'WenBlog');
        $description = (string) option('site_description', '');
        $self = url('rss');

        header('Content-Type: application/rss+xml; charset=UTF-8');
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">' . "\n";
        echo '<channel>' . "\n";
        echo '<title>' . $this->xml($siteName) . '</title>' . "\n";
        echo '<link>' . $this->xml(url('/')) . '</link>' . "\n";
        echo '<description>' . $this->xml($description) . '</description>' . "\n";
        echo '<language>zh-CN</language>' . "\n";
        echo '<atom:link href="' . $this->xml($self) . '" rel="self" type="application/rss+xml" />' . "\n";
        if ($result['items'] !== []) {
            $latest = strtotime((string) $result['items'][0]['published_at']);
            if ($latest !== false) {
                echo '<lastBuildDate>' . date(DATE_RSS, $latest) . '</lastBuildDate>' . "\n";
            }
        }
        foreach ($result['items'] as $post) {
            $link = post_url($post);
            $timestamp = strtotime((string) $post['published_at']);
            $categories = (new Post($this->app))->categories((int) $post['id']);
            echo '<item>' . "\n";
            echo '<title>' . $this->xml((string) $post['title']) . '</title>' . "\n";
            echo '<link>' . $this->xml($link) . '</link>' . "\n";
            echo '<guid isPermaLink="true">' . $this->xml($link) . '</guid>' . "\n";
            if ($timestamp !== false) {
                echo '<pubDate>' . date(DATE_RSS, $timestamp) . '</pubDate>' . "\n";
            }
            echo '<description>' . $this->xml(post_excerpt($post, 300)) . '</description>' . "\n";
            foreach ($categories as $category) {
                echo '<category>' . $this->xml((string) $category['name']) . '</category>' . "\n";
            }
            echo '</item>' . "\n";
        }
        echo '</channel>' . "\n";
        echo '</rss>';
    }

    private function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}