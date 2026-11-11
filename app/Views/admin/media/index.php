<?php
/** @var list<array<string, mixed>> $media */
/** @var \App\Core\Paginator $pagination */
/** @var int $total */
$extensions = array_map('trim', explode(',', (string) option('upload_extensions', 'jpg,jpeg,png,gif,webp,pdf')));
$accept = implode(',', array_map(static fn (string $ext): string => '.' . $ext, $extensions));
?>
<div class="page-toolbar">
    <div>
        <h2 class="page-subtitle">媒体库 <span class="badge badge-soft"><?= (int) $total ?></span></h2>
        <p class="muted">上传图片与附件，复制地址后插入文章。</p>
    </div>
</div>

<section class="card">
    <div class="card-head">
        <h3 class="card-title">上传文件</h3>
        <span class="muted">单文件最大 <?= (int) option('upload_max_size', 5) ?> MB · <?= e(implode(' / ', $extensions)) ?></span>
    </div>
    <div class="card-body">
        <form method="post" action="<?= e(admin_url('media/upload')) ?>" enctype="multipart/form-data" class="upload-form">
            <?= csrf_field() ?>
            <label class="dropzone" data-dropzone>
                <input type="file" name="file" accept="<?= e($accept) ?>" required data-file-input>
                <?= icon('upload-cloud', 30) ?>
                <strong data-file-label>点击选择文件，或将文件拖到这里</strong>
                <small class="muted">文件会经过类型与内容双重校验</small>
            </label>
            <div class="upload-side">
                <label class="field">
                    <span class="label">替代文本 <small class="muted">可选，利于 SEO</small></span>
                    <input class="input" type="text" name="alt" placeholder="图片内容描述">
                </label>
                <button class="btn btn-primary" type="submit"><?= icon('upload') ?> 开始上传</button>
            </div>
        </form>
    </div>
</section>

<?php if ($media === []): ?>
    <section class="card">
        <div class="empty">
            <?= icon('image', 34) ?>
            <h3>媒体库是空的</h3>
            <p>上传第一张图片，为文章增添视觉内容。</p>
        </div>
    </section>
<?php else: ?>
    <div class="media-grid">
        <?php foreach ($media as $item): ?>
            <?php $isImage = str_starts_with((string) $item['mime'], 'image/'); ?>
            <figure class="media-card">
                <div class="media-thumb">
                    <?php if ($isImage): ?>
                        <img src="<?= e(upload_url((string) $item['path'])) ?>" alt="<?= e((string) ($item['alt'] ?: $item['original_name'])) ?>" loading="lazy">
                    <?php else: ?>
                        <div class="file-tile"><?= icon('file', 30) ?><span><?= e(strtoupper(pathinfo((string) $item['original_name'], PATHINFO_EXTENSION))) ?></span></div>
                    <?php endif; ?>
                </div>
                <figcaption>
                    <strong class="media-name" title="<?= e((string) $item['original_name']) ?>"><?= e((string) $item['original_name']) ?></strong>
                    <span class="muted">
                        <?= e(format_bytes((int) $item['size'])) ?>
                        <?php if ($isImage && !empty($item['width'])): ?> · <?= (int) $item['width'] ?>×<?= (int) $item['height'] ?><?php endif; ?>
                    </span>
                    <form class="media-alt-form" method="post" action="<?= e(admin_url('media/' . $item['id'] . '/alt')) ?>">
                        <?= csrf_field() ?>
                        <input class="input input-sm" type="text" name="alt" maxlength="190"
                               value="<?= e((string) $item['alt']) ?>" placeholder="替代文本" aria-label="替代文本">
                        <button class="btn btn-sm" type="submit">保存</button>
                    </form>
                    <div class="media-actions">
                        <button class="icon-btn" type="button" data-copy="<?= e(upload_url((string) $item['path'])) ?>" title="复制地址"><?= icon('copy', 16) ?></button>
                        <form method="post" action="<?= e(admin_url('media/' . $item['id'] . '/delete')) ?>" data-confirm="删除后文件不可恢复，确定吗？">
                            <?= csrf_field() ?>
                            <button class="icon-btn danger" type="submit" title="删除"><?= icon('trash', 16) ?></button>
                        </form>
                    </div>
                </figcaption>
            </figure>
        <?php endforeach; ?>
    </div>
    <?= pagination($pagination) ?>
<?php endif; ?>