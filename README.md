# WenBlog

WenBlog 是一个从零实现的轻量级博客 CMS

它的目标是接近 Typecho 的轻量与部署体验，同时提供现代化的响应式后台、主题系统和插件钩子机制。


## 环境要求

- PHP 8.2 或更高版本
- 扩展：`pdo_mysql`、`mbstring`、`json`、`fileinfo`、`session`
- 可选：`ZipArchive`（后台上传主题 / 插件 ZIP 时需要）
- MySQL 5.7 或更高版本（MariaDB 10.2+ 亦可）
- Apache（启用 `mod_rewrite`）或 Nginx + PHP-FPM

## 安装

1. 将项目文件放入网站目录。站点根目录就是 `index.php` 所在目录，不需要把 `public/` 作为根目录。
2. 确保以下目录可写：
   - `config/`
   - `storage/` 及其子目录 `cache/`、`logs/`
   - `uploads/`
3. 浏览器访问 `https://你的域名/install`。
4. 按向导完成环境检查、数据库配置、站点信息和管理员账户创建。
5. 登录 `/admin`。




## Web 服务器

### Apache

根目录已附带 `.htaccess`，要求 `AllowOverride` 至少包含 `FileInfo` 和 `Options`。如果虚拟主机禁止 `.htaccess`，请把重写规则写入站点配置：

```apache
<VirtualHost *:80>
    ServerName example.com
    DocumentRoot /var/www/wenblog

    <Directory /var/www/wenblog>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

### Nginx

参考根目录的 `nginx.conf`，核心规则：

```nginx
root /var/www/wenblog;
index index.php;

location ~ ^/(app|config|storage)/ {
    deny all;
}

location ~* ^/(uploads|themes|plugins)/.*\.(php|phtml|phar)$ {
    deny all;
}

location /install {
    rewrite ^ /index.php last;
}

location / {
    try_files $uri $uri/ /index.php?$query_string;
}

location ~ \.php$ {
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    fastcgi_pass unix:/run/php/php8.2-fpm.sock;
}
```


## 固定链接

后台“设置 → 阅读”可选：

- `post`：`/post/文章别名`
- `slug`：`/文章别名`

切到 `slug` 时，文章、页面和保留前缀会做冲突检查。修改固定链接后会自动清理页面缓存。

## 页面模板

主题中只有 `page.php` 以及带有模板声明头的 PHP 文件可作为页面模板。例如在主题根目录新增 `about.php`：

```php
<?php
/**
 * Template Name: 关于页面
 */
```

然后在后台页面编辑页选择“关于页面”。模板内部可以使用 `$page`、`$pageTitle`、`$pageDescription`、`$isPage` 以及主题公共片段。

## 主题开发

主题目录：

```text
themes/my-theme/
├── theme.json
├── functions.php        # 可选，主题启动时加载
├── index.php            # 必需
├── page.php
├── single.php
├── category.php
├── tag.php
├── search.php
├── archive.php
├── 404.php
├── assets/
│   ├── style.css
│   └── theme.js
└── partials/
    ├── header.php
    ├── footer.php
    ├── sidebar.php
    ├── post-card.php
    ├── pagination.php
    └── comments.php
```

`theme.json` 示例：

```json
{
    "name": "My Theme",
    "slug": "my-theme",
    "version": "1.0.0",
    "description": "主题说明",
    "author": "Your Name",
    "config": {
        "accent_color": {
            "type": "color",
            "label": "强调色",
            "description": "按钮、链接等元素的主题色。",
            "group": "appearance",
            "default": "#4f46e5"
        },
        "show_sidebar": {
            "type": "radio",
            "label": "显示侧边栏",
            "description": "关闭后正文区域会占满页面宽度。",
            "group": "layout",
            "default": "1",
            "options": {
                "1": "显示",
                "0": "隐藏"
            }
        }
    },
    "config_groups": {
        "layout": {
            "label": "布局",
            "description": "控制页面结构与侧边栏模块。"
        },
        "appearance": {
            "label": "外观配色",
            "description": "整站主色调。"
        }
    }
}
```

配置字段支持：

- `type`：`text`、`textarea`、`checkbox`、`radio`、`select`、`color`、`number`、`url`、`email`、`password`。
- `label` / `description`：后台设置页显示的中文名称与功能说明。
- `options`：`radio` / `select` 的可选项，键为保存值，值为显示文字。
- `group`：所属分组，配合顶层 `config_groups` 使用；未声明时归入 `general`。
- `placeholder`、`rows`、`wide`：占位文字、多行文本框高度、是否独占一行。

后台「主题 → 主题设置」会按分组展示这些选项，保存通过 AJAX 完成，无需刷新页面；浏览器不支持时自动回退为普通表单提交。

模板中可用：

- `$theme->partial('header')`
- `theme_config('accent_color', '#4f46e5')`
- `theme_asset('assets/style.css')`
- `url()`、`post_url()`、`page_url()`、`category_url()`、`tag_url()`
- `e()` 输出转义、`markdown()` 渲染 Markdown
- `Hook::do()` / `Hook::filter()`

主题 ZIP 包的根目录或第一层子目录中需包含 `theme.json` 和 `index.php`。

### Typecho 风格模板 API

模板里的 `$this` 就是 `App\Core\Theme` 实例，它会把未定义的方法和属性委托给 `ThemeApi`，因此可以直接使用接近 Typecho 的写法：

```php
<?php $this->need('header.php'); ?>

<h1><?php $this->options->title(); ?></h1>

<?php while ($this->next()): ?>
    <article>
        <h2><a href="<?php $this->permalink(); ?>"><?php $this->title(); ?></a></h2>
        <p><?php $this->date('Y-m-d'); ?> · <?php $this->author(); ?></p>
        <div><?php $this->content(); ?></div>
        <p><?php $this->category(', '); ?> <?php $this->tags(', '); ?></p>
    </article>
<?php endwhile; ?>

<?php $this->pageNav('上一页', '下一页'); ?>
<?php $this->need('footer.php'); ?>
```

`$this->need()` 会依次查找主题根目录与 `partials/`，文件名带不带 `.php` 都可以，所以 Typecho 主题的 `header.php` / `footer.php` 结构可以直接沿用。

常用方法（都会直接输出，`get*` 版本返回字符串）：

| 分类 | 方法 |
| --- | --- |
| 循环 | `next()`、`rewind()`、`id()`、`title()`、`permalink()`、`content()`、`excerpt()`、`date($format)`、`author()`、`category($split, $link)`、`tags($split, $link)`、`commentsNum()`、`viewsNum()`、`cover()` |
| 上下文 | `is('index' / 'post' / 'page' / 'category' / 'tag' / 'archive' / 'search' / 'single')` |
| 归档 | `archiveTitle($before, $after)`、`getArchiveTitle()`、`description()`、`keywords()` |
| 分页 | `pageNav($prev, $next, $mid)`、`pageLink('prev' / 'next')` |
| 评论 | `comments()`、`commentsOpen()`、`commentCount()`、`comments()->next()`、`comments()->pageNav()` |
| 模板 | `need($file)`、`header()`、`footer()`、`partial($name)` |
| 返回值版 | `getTitle()`、`getPermalink()`、`getContent()`、`getExcerpt()`、`getDate()` 等 |

站点配置通过 `$this->options` 读取，属性与调用两种写法都支持：

```php
<?php $this->options->title(); ?>
<?php $this->options->siteUrl(); ?>
<?php $this->options->themeUrl('style.css'); ?>
<?= e($this->options->description) ?>
<?= e($this->options->postsPerPage) ?>
```

`$this->user` 是当前登录用户，`$this->author` 是当前文章作者：

```php
<?php if ($this->user->hasLogin()): ?>
    <a href="<?= e(admin_url()) ?>"><?php $this->user->screenName(); ?></a>
<?php endif; ?>

<?php $this->author->screenName(); ?>
<?php $this->author->avatar(32); ?>
```

迁移 Typecho 主题时的差异：

- 文章标题、摘要会做 HTML 转义；`content()` 输出渲染后的 Markdown HTML。
- 暂不支持每篇文章的自定义字段，`$this->fields->xxx` 会安全返回 `null`。
- 暂无公开的作者归档页，`$this->author->permalink()` 会回退到文章归档地址。
- `pageNav()` 输出 Typecho 风格的 `<ol class="page-navigator">` 结构，主题原有的分页 CSS 通常可以直接复用。

### 原生 Typecho 主题兼容层

主题的入口文件或 `functions.php` 中出现 `__TYPECHO_ROOT_DIR__` 时，WenBlog 会自动在加载主题函数前启用兼容层（`app/typecho.php`、`app/Core/TypechoCompat.php`），无需修改主题源码。

兼容内容包括：

- 常量：`__TYPECHO_ROOT_DIR__`、`__TYPECHO_THEME_DIR__`、`__TYPECHO_VERSION__` 等。
- 全局函数：`_t()`、`_e()`。
- 类入口：`Helper::options()`、`Typecho_Widget::widget()`、`Typecho_Db::get()`。
- 常用 Widget：`Widget_Options`、`Widget_Security`、`Widget_Contents_Page_List`、`Widget_Metas_Category_List`、`Widget_Metas_Tag_Cloud`、`Widget_Contents_Post_Recent`，均支持 `to($x)`、`have()`、`next()` 与行属性。
- 常用查询：`select()->from('table.contents')`、`table.options`、`table.metas`，以及 `fetchRow()` / `fetchAll()` / `fetchObject()`，会自动映射到 WenBlog 的表和字段。
- 模板名映射：`single` → `post.php`，`category` / `tag` / `search` → `archive.php`，其余回退 `index.php`。

评论提交接口允许不带 CSRF 令牌（与 Typecho 主题表单一致），改由蜜罐字段、频率限制和审核队列保护。

## 插件开发

插件目录：

```text
plugins/my-plugin/
├── plugin.json
└── plugin.php
```

`plugin.json`：

```json
{
    "name": "我的插件",
    "slug": "my-plugin",
    "version": "1.0.0",
    "description": "插件说明",
    "author": "Your Name",
    "main": "plugin.php",
    "config": {
        "enabled_note": {
            "type": "checkbox",
            "label": "启用提示",
            "default": "1"
        }
    }
}
```

`plugin.php` 在插件启用后自动加载，可直接注册钩子：

```php
<?php
use App\Core\Hook;

Hook::add('theme.footer', static function (): void {
    echo '<div class="my-plugin">Hello</div>';
});

Hook::add('post.html', static function (string $html, array $post): string {
    return $html . '<p>阅读完成。</p>';
}, 20);
```

内置插件 `reading-time`（阅读进度条）和 `copyright`（版权声明）可作为参考。

## REST API

默认关闭。后台“设置 → 高级”启用后生成 API Key。

请求方式：`X-API-Key` 请求头，或 `?api_key=...` 查询参数。

| 方法 | 地址 | 说明 |
| --- | --- | --- |
| GET | `/api/posts?page=1&per_page=10` | 已发布文章列表 |
| GET | `/api/posts/{slug}` | 文章详情（含 Markdown 与 HTML） |
| GET | `/api/categories` | 分类列表 |
| GET | `/api/tags` | 标签列表 |
| GET | `/api/comments?post_id=1` | 某篇文章的已审核评论 |

响应为 JSON；未启用返回 404，密钥错误返回 401。

## 缓存

后台启用页面缓存后，访客 GET 页面会写入 `storage/cache/pages/`。缓存不会命中：

- 已登录用户
- 后台与 API
- RSS、Sitemap、robots.txt
- 带 `preview` 查询参数的请求
- 存在 flash、表单错误或旧输入的回调页面

缓存 HTML 中的 CSRF Token 会替换为占位符，再按访问者会话动态替换，避免把其他用户的令牌发给访客。发布、修改、删除内容、评论审核、主题切换和设置保存都会清理缓存。

## 目录结构

```text
app/
├── Core/          # App、Router、Database、Auth、Theme、PluginManager 等
├── Models/        # Post、Page、Category、Tag、Comment、Media、User、Link
├── Controllers/   # 前台、后台、安装、API 控制器
├── Views/         # 后台与安装器视图
├── defaults.php   # 默认站点选项
├── helpers.php    # 全局辅助函数
└── icons.php      # 内联 SVG 图标
assets/admin/      # 后台 CSS / JS
config/            # 配置样例与安装后生成的配置
install/           # 安装器入口与数据库 schema
plugins/           # 插件目录
storage/           # 缓存与日志（需可写）
themes/            # 主题目录
uploads/           # 上传文件（需可写）
index.php          # 唯一前台入口
```

## 安全说明

- 所有数据库访问使用 PDO 预处理；动态表名 / 条件均来自内部白名单。
- 后台与安装器 POST 默认校验 CSRF Token。
- 文章 Markdown 中的原始 HTML 会被转义；链接协议经过白名单检查。
- 密码使用 `password_hash()`（bcrypt/argon 由 PHP 默认算法决定）。
- 登录失败按 IP 与账号限流，记录在 `login_attempts`。
- 上传文件同时校验扩展名、真实 MIME 与图片尺寸，并拒绝可执行类型。
- `app/`、`config/`、`storage/`、主题与插件 PHP 文件均有服务器层保护。
- 生产环境请使用 HTTPS，并保持 `app.debug` 为 `false`。

## 许可

Apache 2.0