<?php
$pageTitle = $pageTitle ?? '页面不存在';
$message = $message ?? '你访问的页面不存在，或者已经被移动。';
require __DIR__ . '/partials/header.php';
?>
<div class="container error-page">
    <p class="error-code">404</p>
    <h1><?= e($message) ?></h1>
    <p>检查一下地址是否正确，或者回到首页继续浏览。</p>
    <div class="hero-actions">
        <a class="button button-primary" href="<?= e(url('/')) ?>">返回首页</a>
        <a class="button button-ghost" href="<?= e(url('archives')) ?>">浏览归档</a>
    </div>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>