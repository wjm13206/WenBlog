<?php
/**
 * Theme settings page, styled after Typecho's "主题设置" screen.
 *
 * @var array<string, mixed>       $theme
 * @var list<array<string, mixed>> $groups
 * @var array<string, mixed>       $values
 * @var int                        $fieldCount
 */
$groupCount = count($groups);
?>
<div class="page-toolbar theme-settings-toolbar">
    <div>
        <a class="back-link" href="<?= e(admin_url('themes')) ?>"><?= icon('arrow-left', 15) ?> 返回主题列表</a>
        <h2 class="page-subtitle"><?= e($theme['name']) ?> · 主题设置</h2>
        <p class="muted">这里的选项由主题提供，保存后立即在前台生效。</p>
    </div>
    <div class="theme-settings-meta">
        <span class="pill">v<?= e($theme['version']) ?></span>
        <span class="pill"><?= $fieldCount ?> 项设置</span>
    </div>
</div>

<section class="card theme-hero">
    <div class="card-body theme-hero-body">
        <div class="theme-hero-mark"><?= icon('palette', 22) ?></div>
        <div class="theme-hero-text">
            <div class="theme-hero-title">
                <h3><?= e($theme['name']) ?></h3>
                <code>themes/<?= e($theme['slug']) ?></code>
            </div>
            <p class="muted"><?= e($theme['description'] ?: '主题作者还没有填写描述。') ?></p>
            <div class="theme-hero-foot">
                <span>作者：<?= e($theme['author'] ?: '未知') ?></span>
                <span>当前主题</span>
            </div>
        </div>
    </div>
</section>

<?php if ($fieldCount === 0): ?>
    <section class="card">
        <div class="card-body empty-state">
            <p>这个主题没有提供可配置的选项。</p>
            <p class="muted">主题作者可以在 <code>theme.json</code> 的 <code>config</code> 中声明设置项。</p>
        </div>
    </section>
<?php else: ?>
    <form method="post" action="<?= e(admin_url('themes/config')) ?>" class="theme-config-form" data-theme-config>
        <?= csrf_field() ?>
        <input type="hidden" name="slug" value="<?= e($theme['slug']) ?>">

        <div class="settings-shell<?= $groupCount === 1 ? ' is-single' : '' ?>">
            <?php if ($groupCount > 1): ?>
                <nav class="settings-nav" aria-label="设置分组">
                    <?php foreach ($groups as $index => $group): ?>
                        <button class="settings-nav-item<?= $index === 0 ? ' active' : '' ?>" type="button" data-settings-tab="<?= e($group['key']) ?>"><?= e($group['label']) ?></button>
                    <?php endforeach; ?>
                </nav>
            <?php endif; ?>

            <div class="settings-panels">
                <?php foreach ($groups as $index => $group): ?>
                    <section class="card settings-panel" data-settings-panel="<?= e($group['key']) ?>"<?= $index === 0 ? '' : ' hidden' ?>>
                        <div class="card-head">
                            <div>
                                <h3 class="card-title"><?= e($group['label']) ?></h3>
                                <?php if ($group['description'] !== ''): ?>
                                    <p class="muted"><?= e($group['description']) ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="card-body config-list">
                            <?php foreach ($group['fields'] as $field): ?>
                                <?php
                                $key = (string) $field['key'];
                                $name = 'config[' . $key . ']';
                                $type = (string) $field['type'];
                                $value = (string) ($values[$key] ?? $field['default']);
                                $labelId = 'config-label-' . $key;
                                ?>
                                <div class="config-field<?= $field['wide'] ? ' is-wide' : '' ?>">
                                    <div class="config-field-head">
                                        <label class="config-field-label" for="config-<?= e($key) ?>" id="<?= e($labelId) ?>"><?= e($field['label']) ?></label>
                                        <?php if ($field['description'] !== ''): ?>
                                            <p class="config-field-desc"><?= e($field['description']) ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="config-field-control">
                                        <?php if ($type === 'checkbox'): ?>
                                            <label class="check-row config-check">
                                                <input type="checkbox" id="config-<?= e($key) ?>" name="<?= e($name) ?>" value="1"<?= checked($value) ?>>
                                                <span>开启</span>
                                            </label>
                                        <?php elseif ($type === 'textarea'): ?>
                                            <textarea class="textarea" id="config-<?= e($key) ?>" name="<?= e($name) ?>" rows="<?= (int) $field['rows'] ?>" placeholder="<?= e((string) $field['placeholder']) ?>"><?= e($value) ?></textarea>
                                        <?php elseif ($type === 'radio' && $field['options'] !== []): ?>
                                            <div class="radio-group" role="radiogroup" aria-labelledby="<?= e($labelId) ?>">
                                                <?php foreach ($field['options'] as $optionValue => $optionLabel): ?>
                                                    <label class="radio-pill">
                                                        <input type="radio" name="<?= e($name) ?>" value="<?= e((string) $optionValue) ?>"<?= selected($value, (string) $optionValue) ?>>
                                                        <span><?= e((string) $optionLabel) ?></span>
                                                    </label>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php elseif ($type === 'select' && $field['options'] !== []): ?>
                                            <select class="select" id="config-<?= e($key) ?>" name="<?= e($name) ?>">
                                                <?php foreach ($field['options'] as $optionValue => $optionLabel): ?>
                                                    <option value="<?= e((string) $optionValue) ?>"<?= selected($value, (string) $optionValue) ?>><?= e((string) $optionLabel) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        <?php elseif ($type === 'color'): ?>
                                            <div class="color-input">
                                                <input class="input input-color" type="color" id="config-<?= e($key) ?>" name="<?= e($name) ?>" value="<?= e($value !== '' ? $value : '#4f46e5') ?>">
                                                <span class="color-value"><?= e($value !== '' ? $value : '默认') ?></span>
                                            </div>
                                        <?php else: ?>
                                            <?php
                                            $inputType = in_array($type, ['url', 'email', 'number', 'password'], true) ? $type : 'text';
                                            ?>
                                            <input class="input" type="<?= e($inputType) ?>" id="config-<?= e($key) ?>" name="<?= e($name) ?>" value="<?= e($value) ?>" placeholder="<?= e((string) $field['placeholder']) ?>"<?= $inputType === 'password' ? ' autocomplete="new-password"' : '' ?>>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="config-savebar">
            <span class="config-status" data-config-status role="status" aria-live="polite"></span>
            <div class="config-savebar-actions">
                <button class="btn btn-ghost" type="button" data-config-reset>撤销修改</button>
                <button class="btn btn-primary" type="submit" data-config-submit><?= icon('save', 16) ?> 保存设置</button>
            </div>
        </div>
    </form>
<?php endif; ?>
