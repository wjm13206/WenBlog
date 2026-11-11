<?php
$siteName = (string) option('site_name', 'WenBlog');
$siteDescription = (string) option('site_description', '');
$footerText = (string) option('footer_text', '');
$footerNote = (string) theme_config('footer_note', '');
$year = date('Y');
?>
</main>

<footer class="site-footer">
    <div class="container footer-grid">
        <div class="footer-brand">
            <a href="<?= e(url('/')) ?>"><?= e($siteName) ?></a>
            <?php if ($siteDescription !== ''): ?><p><?= e($siteDescription) ?></p><?php endif; ?>
        </div>
        <nav class="footer-nav" aria-label="页脚导航">
            <a href="<?= e(url('archives')) ?>">归档</a>
            <a href="<?= e(url('rss')) ?>">RSS</a>
            <a href="<?= e(url('sitemap.xml')) ?>">Sitemap</a>
            <?php if (current_user() !== null): ?><a href="<?= e(admin_url()) ?>">管理</a><?php endif; ?>
        </nav>
    </div>
    <div class="container footer-bottom">
        <p>© <?= e($year) ?> <?= e($siteName) ?><?php if ($footerText !== ''): ?> · <?= e($footerText) ?><?php endif; ?></p>
        <p>
            <?php if ($footerNote !== ''): ?><?= e($footerNote) ?> · <?php endif; ?>
            由 <a href="https://github.com/" rel="noopener" target="_blank">WenBlog</a> 驱动
        </p>
    </div>
</footer>

<script src="<?= e(theme_asset('assets/theme.js')) ?>" defer></script>
<?php \App\Core\Hook::do('theme.footer'); ?>
</body>
</html>