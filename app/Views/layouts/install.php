<?php
/** @var string $title */
/** @var string $content */
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($title ?? '安装 WenBlog') ?> · WenBlog</title>
    <style>
        :root {
            --bg: #f6f6f3;
            --panel: #ffffff;
            --ink: #17171a;
            --muted: #6d6d74;
            --faint: #9a9aa2;
            --line: #e4e4df;
            --line-strong: #d2d2ca;
            --accent: #4f46e5;
            --accent-dark: #4338ca;
            --accent-soft: #eef2ff;
            --ok: #15803d;
            --ok-soft: #ecfdf3;
            --err: #b91c1c;
            --err-soft: #fef2f2;
            --radius: 14px;
            --shadow: 0 24px 70px rgba(20, 20, 24, .10);
        }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; }
        body {
            background: var(--bg);
            color: var(--ink);
            font-family: ui-sans-serif, -apple-system, BlinkMacSystemFont, "Segoe UI", "PingFang SC", "Hiragino Sans GB", "Microsoft YaHei", sans-serif;
            font-size: 14px;
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
        }
        a { color: var(--accent-dark); }
        h1, h2, h3, p { margin-top: 0; }
        code { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: .92em; }
        .install-shell { min-height: 100vh; display: grid; grid-template-columns: 360px minmax(0, 1fr); }
        .install-brand {
            background: #151518;
            color: #fff;
            padding: 44px 40px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 40px;
        }
        .install-logo { display: inline-flex; align-items: center; gap: 12px; color: #fff; text-decoration: none; font-weight: 700; font-size: 20px; letter-spacing: -.02em; }
        .install-logo span { width: 38px; height: 38px; border-radius: 10px; background: #fff; color: #151518; display: grid; place-items: center; font-size: 19px; font-weight: 800; }
        .install-brand h2 { color: #fff; font-size: 28px; line-height: 1.3; letter-spacing: -.03em; margin: 0 0 14px; }
        .install-brand p { color: #a9a9b2; font-size: 13px; margin: 0; max-width: 27em; }
        .install-points { list-style: none; margin: 30px 0 0; padding: 0; display: grid; gap: 13px; }
        .install-points li { display: flex; gap: 10px; align-items: flex-start; color: #c9c9d0; font-size: 13px; }
        .install-points li::before { content: ""; width: 6px; height: 6px; border-radius: 50%; background: #8b8b98; margin-top: 8px; flex: 0 0 auto; }
        .install-brand-foot { color: #6f6f7a; font-size: 11.5px; }
        .install-main { padding: 48px 40px 64px; display: flex; align-items: flex-start; justify-content: center; }
        .install-panel { width: 100%; max-width: 720px; background: var(--panel); border: 1px solid var(--line); border-radius: var(--radius); box-shadow: var(--shadow); padding: 36px 40px 40px; }
        .install-steps { display: flex; align-items: center; gap: 8px; margin: 0 0 30px; padding: 0; list-style: none; }
        .install-steps li { display: flex; align-items: center; gap: 8px; color: var(--faint); font-size: 12.5px; white-space: nowrap; }
        .install-steps li + li::before { content: ""; width: 26px; height: 1px; background: var(--line-strong); display: inline-block; margin-right: 2px; }
        .install-steps b { width: 24px; height: 24px; border-radius: 50%; display: grid; place-items: center; border: 1px solid var(--line-strong); font-size: 11.5px; font-weight: 600; color: var(--muted); }
        .install-steps .is-done b { background: var(--ok-soft); border-color: #bbf7d0; color: var(--ok); }
        .install-steps .is-current { color: var(--ink); font-weight: 600; }
        .install-steps .is-current b { background: var(--ink); border-color: var(--ink); color: #fff; }
        .install-kicker { color: var(--accent-dark); font-size: 11.5px; font-weight: 700; letter-spacing: .10em; text-transform: uppercase; margin-bottom: 8px; }
        .install-panel h1 { font-size: 26px; letter-spacing: -.035em; line-height: 1.25; margin: 0 0 8px; }
        .install-lead { color: var(--muted); margin: 0 0 26px; font-size: 13.5px; }
        .install-panel h2 { font-size: 16px; letter-spacing: -.02em; margin: 26px 0 12px; }
        .alert { border: 1px solid; border-radius: 10px; padding: 11px 13px; font-size: 13px; margin-bottom: 18px; }
        .alert-error { color: var(--err); background: var(--err-soft); border-color: #fecaca; }
        .alert-info { color: #1e40af; background: #eff6ff; border-color: #bfdbfe; }
        .alert-warn { color: #92400e; background: #fffbeb; border-color: #fde68a; }
        .check-list { border: 1px solid var(--line); border-radius: 11px; overflow: hidden; margin: 0 0 24px; }
        .check-row { display: flex; align-items: center; gap: 12px; padding: 11px 14px; border-bottom: 1px solid var(--line); font-size: 13px; }
        .check-row:last-child { border-bottom: 0; }
        .check-state { width: 20px; height: 20px; border-radius: 50%; display: grid; place-items: center; flex: 0 0 auto; font-size: 11px; font-weight: 700; }
        .check-state.ok { background: var(--ok-soft); color: var(--ok); }
        .check-state.bad { background: var(--err-soft); color: var(--err); }
        .check-name { min-width: 0; flex: 1; }
        .check-detail { color: var(--faint); font-size: 11.5px; }
        .check-tag { font-size: 10.5px; letter-spacing: .04em; border: 1px solid var(--line); border-radius: 999px; padding: 2px 7px; color: var(--muted); flex: 0 0 auto; }
        .check-tag.optional { color: var(--faint); }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .field { display: flex; flex-direction: column; gap: 6px; margin-bottom: 16px; min-width: 0; }
        .field.full { grid-column: 1 / -1; }
        .field label { font-size: 12.5px; font-weight: 600; color: #35353b; }
        .field .hint { color: var(--faint); font-size: 11.5px; }
        .field input, .field select {
            width: 100%; border: 1px solid var(--line-strong); border-radius: 9px; background: #fff;
            padding: 10px 12px; font: inherit; font-size: 13.5px; color: var(--ink); transition: border-color .15s, box-shadow .15s;
        }
        .field input:focus, .field select:focus { outline: 0; border-color: var(--accent); box-shadow: 0 0 0 3px rgba(79, 70, 229, .12); }
        .field.has-error input { border-color: var(--err); }
        .field-error { color: var(--err); font-size: 11.5px; }
        .actions { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-top: 28px; padding-top: 22px; border-top: 1px solid var(--line); }
        .actions .spacer { flex: 1; }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; border: 1px solid transparent; border-radius: 9px; padding: 10px 18px; font: inherit; font-size: 13px; font-weight: 600; cursor: pointer; text-decoration: none; }
        .btn-primary { background: var(--ink); color: #fff; }
        .btn-primary:hover { background: #29292f; }
        .btn-primary:disabled { opacity: .4; cursor: not-allowed; }
        .btn-ghost { background: #fff; color: var(--muted); border-color: var(--line-strong); }
        .btn-ghost:hover { border-color: var(--ink); color: var(--ink); }
        .summary { border: 1px solid var(--line); border-radius: 11px; overflow: hidden; margin-bottom: 22px; }
        .summary-row { display: flex; justify-content: space-between; gap: 18px; padding: 11px 14px; border-bottom: 1px solid var(--line); font-size: 13px; }
        .summary-row:last-child { border-bottom: 0; }
        .summary-row span { color: var(--faint); }
        .summary-row strong { font-weight: 600; text-align: right; overflow-wrap: anywhere; }
        .finish-note { display: flex; gap: 12px; padding: 13px 15px; border-radius: 10px; background: #fafaf8; border: 1px solid var(--line); color: var(--muted); font-size: 12.5px; margin-bottom: 22px; }
        .finish-mark { width: 22px; height: 22px; border-radius: 50%; background: var(--ok-soft); color: var(--ok); display: grid; place-items: center; font-weight: 700; flex: 0 0 auto; }
        .success-mark { width: 58px; height: 58px; border-radius: 50%; background: var(--ok-soft); color: var(--ok); display: grid; place-items: center; font-size: 26px; font-weight: 700; margin-bottom: 18px; }
        .success-links { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 24px; }
        .success-link { display: block; padding: 16px; border: 1px solid var(--line); border-radius: 10px; text-decoration: none; color: var(--ink); }
        .success-link:hover { border-color: var(--ink); }
        .success-link strong { display: block; font-size: 13.5px; margin-bottom: 2px; }
        .success-link span { color: var(--muted); font-size: 12px; }
        .install-foot { text-align: center; color: var(--faint); font-size: 11.5px; margin-top: 18px; }
        @media (max-width: 900px) {
            .install-shell { grid-template-columns: 1fr; }
            .install-brand { padding: 26px 26px 22px; }
            .install-brand h2 { font-size: 22px; }
            .install-brand p, .install-points { display: none; }
            .install-brand-foot { display: none; }
            .install-main { padding: 22px 16px 42px; }
            .install-panel { padding: 26px 20px 30px; }
        }
        @media (max-width: 560px) {
            .form-grid { grid-template-columns: 1fr; }
            .install-steps li span { display: none; }
            .install-steps li + li::before { width: 16px; }
            .success-links { grid-template-columns: 1fr; }
            .install-panel h1 { font-size: 22px; }
        }
        @media (prefers-color-scheme: dark) {
            :root { --bg: #101012; --panel: #18181b; --ink: #f2f2f0; --muted: #a1a1aa; --faint: #71717a; --line: #29292e; --line-strong: #3a3a41; --accent-soft: #1e1b4b; --shadow: 0 24px 70px rgba(0, 0, 0, .4); }
            .field label { color: #d4d4d8; }
            .field input, .field select, .btn-ghost { background: #131316; color: var(--ink); }
            .finish-note { background: #141417; }
            .success-link:hover { border-color: #71717a; }
            .alert-warn { background: #1c1708; border-color: #4a3a10; color: #fcd34d; }
        }
    </style>
</head>
<body>
<div class="install-shell">
    <aside class="install-brand">
        <div>
            <a class="install-logo" href="<?= e(($basePath ?? '') . '/install') ?>"><span>W</span>WenBlog</a>
            <div style="height:52px"></div>
            <h2>一个安静、轻快的<br>写作空间。</h2>
            <p>WenBlog 是一个不依赖大型框架的轻量博客系统。几分钟即可完成安装，随后专注于内容本身。</p>
            <ul class="install-points">
                <li>纯 PHP + MySQL，低资源占用</li>
                <li>现代化后台，支持浅色与深色主题</li>
                <li>主题与插件扩展，按需启用</li>
                <li>内置安全、缓存与 SEO 基础能力</li>
            </ul>
        </div>
        <div class="install-brand-foot">WenBlog <?= e(option('app_version', '1.0.0')) ?> · 安装向导</div>
    </aside>
    <main class="install-main">
        <div style="width:100%;max-width:720px">
            <div class="install-panel">
                <?= $content ?>
            </div>
            <div class="install-foot">安装过程中不会向外部服务发送任何数据。</div>
        </div>
    </main>
</div>
</body>
</html>