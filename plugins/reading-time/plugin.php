<?php
/**
 * Reading progress plugin.
 *
 * Adds a slim progress bar to article pages. The element is inert on pages
 * without a `.post-single .prose` block.
 */

declare(strict_types=1);

use App\Core\Hook;

Hook::add('theme.footer', static function (): void {
    $app = app();
    if ($app === null) {
        return;
    }
    $config = $app->plugins->config('reading-time');
    $showPercentage = (string) ($config['show_percentage'] ?? '0') === '1';
    ?>
    <div class="wb-reading-bar" data-reading-bar hidden aria-hidden="true"><span></span></div>
    <?php if ($showPercentage): ?>
        <div class="wb-reading-percent" data-reading-percent hidden aria-hidden="true">0%</div>
    <?php endif; ?>
    <style>
        .wb-reading-bar{position:fixed;inset:0 0 auto;height:3px;z-index:200;pointer-events:none;background:transparent}
        .wb-reading-bar span{display:block;height:100%;width:0;background:var(--accent,#4f46e5);transition:width .08s linear}
        .wb-reading-percent{position:fixed;left:18px;bottom:18px;z-index:200;background:var(--text,#1c1c1a);color:var(--bg,#fff);font:600 11px/1 ui-sans-serif,system-ui,sans-serif;padding:7px 9px;border-radius:999px;opacity:.82;pointer-events:none}
    </style>
    <script>
        (function () {
            var article = document.querySelector('.post-single .prose');
            var bar = document.querySelector('[data-reading-bar]');
            if (!article || !bar) return;
            var fill = bar.querySelector('span');
            var label = document.querySelector('[data-reading-percent]');
            var ticking = false;
            bar.removeAttribute('hidden');
            if (label) label.removeAttribute('hidden');

            function update() {
                ticking = false;
                var rect = article.getBoundingClientRect();
                var start = rect.top + window.scrollY;
                var distance = article.offsetHeight - window.innerHeight;
                var ratio = distance <= 0 ? 1 : (window.scrollY - start) / distance;
                ratio = Math.max(0, Math.min(1, ratio));
                fill.style.width = (ratio * 100).toFixed(2) + '%';
                if (label) label.textContent = Math.round(ratio * 100) + '%';
            }

            function onScroll() {
                if (ticking) return;
                ticking = true;
                window.requestAnimationFrame(update);
            }

            window.addEventListener('scroll', onScroll, { passive: true });
            window.addEventListener('resize', onScroll);
            update();
        })();
    </script>
    <?php
});