-- WenBlog database schema
-- MySQL 5.7+ / utf8mb4

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    username VARCHAR(60) NOT NULL,
    email VARCHAR(190) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    display_name VARCHAR(100) NOT NULL,
    role ENUM('admin', 'editor') NOT NULL DEFAULT 'editor',
    status ENUM('active', 'disabled') NOT NULL DEFAULT 'active',
    avatar VARCHAR(255) NOT NULL DEFAULT '',
    bio TEXT NULL,
    last_login_at DATETIME NULL,
    last_login_ip VARCHAR(45) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY users_username_unique (username),
    UNIQUE KEY users_email_unique (email),
    KEY users_role_status_index (role, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS categories (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(190) NOT NULL,
    parent_id INT UNSIGNED NULL,
    description VARCHAR(255) NOT NULL DEFAULT '',
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY categories_slug_unique (slug),
    KEY categories_parent_index (parent_id),
    KEY categories_sort_index (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS posts (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(190) NOT NULL,
    excerpt TEXT NULL,
    content MEDIUMTEXT NOT NULL,
    cover VARCHAR(255) NOT NULL DEFAULT '',
    status ENUM('draft', 'published', 'scheduled') NOT NULL DEFAULT 'draft',
    author_id INT UNSIGNED NOT NULL,
    allow_comments TINYINT(1) NOT NULL DEFAULT 1,
    pinned TINYINT(1) NOT NULL DEFAULT 0,
    views INT UNSIGNED NOT NULL DEFAULT 0,
    comment_count INT UNSIGNED NOT NULL DEFAULT 0,
    published_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY posts_slug_unique (slug),
    KEY posts_status_published_index (status, published_at),
    KEY posts_author_index (author_id),
    KEY posts_pinned_index (pinned),
    KEY posts_created_index (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The FULLTEXT index (posts_fulltext) is created by the installer so it can
-- use WITH PARSER ngram on MySQL and fall back to a plain index on MariaDB.

CREATE TABLE IF NOT EXISTS tags (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(190) NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY tags_slug_unique (slug),
    UNIQUE KEY tags_name_unique (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS post_categories (
    post_id INT UNSIGNED NOT NULL,
    category_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (post_id, category_id),
    KEY post_categories_category_index (category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS post_tags (
    post_id INT UNSIGNED NOT NULL,
    tag_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (post_id, tag_id),
    KEY post_tags_tag_index (tag_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS post_revisions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    post_id INT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    excerpt TEXT NULL,
    content MEDIUMTEXT NOT NULL,
    author_id INT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY revisions_post_index (post_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pages (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(190) NOT NULL,
    excerpt TEXT NULL,
    content MEDIUMTEXT NOT NULL,
    template VARCHAR(100) NOT NULL DEFAULT '',
    status ENUM('draft', 'published') NOT NULL DEFAULT 'draft',
    author_id INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY pages_slug_unique (slug),
    KEY pages_status_index (status),
    KEY pages_author_index (author_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS comments (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    post_id INT UNSIGNED NOT NULL,
    parent_id INT UNSIGNED NULL,
    user_id INT UNSIGNED NULL,
    author_name VARCHAR(50) NOT NULL,
    author_email VARCHAR(190) NOT NULL,
    author_url VARCHAR(255) NULL,
    content TEXT NOT NULL,
    status ENUM('pending', 'approved', 'spam') NOT NULL DEFAULT 'pending',
    ip VARCHAR(45) NOT NULL DEFAULT '',
    user_agent VARCHAR(255) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY comments_post_status_index (post_id, status, created_at),
    KEY comments_parent_index (parent_id),
    KEY comments_status_index (status),
    KEY comments_ip_created_index (ip, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS media (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    filename VARCHAR(255) NOT NULL,
    original_name VARCHAR(190) NOT NULL,
    mime VARCHAR(100) NOT NULL,
    size INT UNSIGNED NOT NULL DEFAULT 0,
    width INT UNSIGNED NULL,
    height INT UNSIGNED NULL,
    path VARCHAR(255) NOT NULL,
    alt VARCHAR(190) NOT NULL DEFAULT '',
    uploaded_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY media_created_index (created_at),
    KEY media_uploader_index (uploaded_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS links (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    url VARCHAR(255) NOT NULL,
    description VARCHAR(255) NOT NULL DEFAULT '',
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY links_sort_index (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS options (
    option_name VARCHAR(100) NOT NULL,
    option_value LONGTEXT NULL,
    PRIMARY KEY (option_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS plugins (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug VARCHAR(100) NOT NULL,
    name VARCHAR(190) NOT NULL,
    description TEXT NULL,
    version VARCHAR(30) NOT NULL DEFAULT '1.0.0',
    author VARCHAR(190) NOT NULL DEFAULT '',
    main VARCHAR(100) NOT NULL DEFAULT 'plugin.php',
    active TINYINT(1) NOT NULL DEFAULT 0,
    installed_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY plugins_slug_unique (slug),
    KEY plugins_active_index (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    login VARCHAR(190) NOT NULL,
    ip VARCHAR(45) NOT NULL,
    attempted_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY login_attempts_login_index (login, attempted_at),
    KEY login_attempts_ip_index (ip, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;