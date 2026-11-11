<?php
/** @var list<array<string, mixed>> $comments */
/** @var \App\Core\Paginator $commentPagination */
/** @var int $commentTotal */
$formErrors = errors();
$commentsOpen = (string) option('comments_enabled', '1') === '1' && (string) ($post['allow_comments'] ?? '1') === '1';
$moderated = (string) option('comments_moderation', '1') === '1';
?>
<section class="comments" id="comments">
    <div class="comments-head">
        <h2>评论 <span><?= (int) $commentTotal ?></span></h2>
        <?php if ($commentsOpen): ?><a class="text-link" href="#comment-form">写下你的想法</a><?php endif; ?>
    </div>

    <?php if ($comments === []): ?>
        <p class="comments-empty">还没有评论。欢迎成为第一个留言的人。</p>
    <?php else: ?>
        <ol class="comment-list">
            <?php foreach ($comments as $comment): ?>
                <li class="comment" id="comment-<?= (int) $comment['id'] ?>">
                    <div class="comment-avatar" aria-hidden="true"><?= e(initials((string) $comment['author_name'])) ?></div>
                    <div class="comment-main">
                        <div class="comment-meta">
                            <strong>
                                <?php if (!empty($comment['author_url'])): ?>
                                    <a href="<?= e((string) $comment['author_url']) ?>" target="_blank" rel="noopener nofollow ugc"><?= e((string) $comment['author_name']) ?></a>
                                <?php else: ?>
                                    <?= e((string) $comment['author_name']) ?>
                                <?php endif; ?>
                            </strong>
                            <time datetime="<?= e((string) $comment['created_at']) ?>"><?= e(human_time($comment['created_at'])) ?></time>
                        </div>
                        <div class="comment-text"><?= nl2br(e((string) $comment['content'])) ?></div>
                        <button class="comment-reply" type="button" data-reply-toggle="<?= (int) $comment['id'] ?>" data-reply-name="<?= e((string) $comment['author_name']) ?>">回复</button>

                        <?php if (!empty($comment['replies'])): ?>
                            <ol class="comment-replies">
                                <?php foreach ($comment['replies'] as $reply): ?>
                                    <li class="comment comment-reply-item" id="comment-<?= (int) $reply['id'] ?>">
                                        <div class="comment-avatar" aria-hidden="true"><?= e(initials((string) $reply['author_name'])) ?></div>
                                        <div class="comment-main">
                                            <div class="comment-meta">
                                                <strong><?= e((string) $reply['author_name']) ?></strong>
                                                <time datetime="<?= e((string) $reply['created_at']) ?>"><?= e(human_time($reply['created_at'])) ?></time>
                                            </div>
                                            <div class="comment-text"><?= nl2br(e((string) $reply['content'])) ?></div>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ol>
                        <?php endif; ?>
                    </div>
                </li>
            <?php endforeach; ?>
        </ol>
        <?php $pagination = $commentPagination; require __DIR__ . '/pagination.php'; ?>
    <?php endif; ?>

    <?php if ($commentsOpen): ?>
        <div class="comment-respond" id="comment-form">
            <h2>发表评论</h2>
            <p class="comment-note">电子邮件不会公开。带 * 的字段为必填项。<?php if ($moderated): ?>评论将在审核后显示。<?php endif; ?></p>
            <form class="comment-form" method="post" action="<?= e(url('comments')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="post_id" value="<?= (int) $post['id'] ?>">
                <input type="hidden" name="parent_id" value="<?= e((string) old('parent_id', '')) ?>" data-parent-id>
                <div class="reply-notice" data-reply-notice hidden>
                    正在回复 <strong data-reply-name></strong>
                    <button type="button" data-reply-cancel>取消</button>
                </div>
                <div class="form-grid">
                    <label class="field">
                        <span>昵称 *</span>
                        <input type="text" name="author_name" value="<?= e((string) old('author_name', '')) ?>" maxlength="50" required>
                        <?php if (isset($formErrors['author_name'])): ?><em class="field-error"><?= e($formErrors['author_name']) ?></em><?php endif; ?>
                    </label>
                    <label class="field">
                        <span>邮箱 <?= (string) option('comments_require_email', '1') === '1' ? '*' : '（选填）' ?></span>
                        <input type="email" name="author_email" value="<?= e((string) old('author_email', '')) ?>" maxlength="120"<?= (string) option('comments_require_email', '1') === '1' ? ' required' : '' ?>>
                        <?php if (isset($formErrors['author_email'])): ?><em class="field-error"><?= e($formErrors['author_email']) ?></em><?php endif; ?>
                    </label>
                </div>
                <label class="field">
                    <span>网站（选填）</span>
                    <input type="url" name="author_url" value="<?= e((string) old('author_url', '')) ?>" maxlength="255" placeholder="https://">
                    <?php if (isset($formErrors['author_url'])): ?><em class="field-error"><?= e($formErrors['author_url']) ?></em><?php endif; ?>
                </label>
                <label class="field">
                    <span>评论内容 *</span>
                    <textarea name="content" rows="6" maxlength="5000" minlength="2" required placeholder="说点什么…"><?= e((string) old('content', '')) ?></textarea>
                    <?php if (isset($formErrors['content'])): ?><em class="field-error"><?= e($formErrors['content']) ?></em><?php endif; ?>
                </label>
                <div class="hp-field" aria-hidden="true">
                    <label>网站地址<input type="text" name="website" tabindex="-1" autocomplete="off"></label>
                </div>
                <div class="form-actions">
                    <button class="button button-primary" type="submit">发表评论</button>
                </div>
            </form>
        </div>
    <?php else: ?>
        <p class="comments-closed">本文的评论已关闭。</p>
    <?php endif; ?>
</section>