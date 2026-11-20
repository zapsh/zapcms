<?php
/**
 * 多语言翻译（Node 表单公用 partial）
 *
 * mode:
 *  - pane       ：独立 #tab-lang 面板（旧布局，侧栏/顶部 tab 用）
 *  - main_tabs  ：主内容区语言切换标签（默认语言 + 其它语言）
 *  - main_panes ：主内容区其它语言的 tab-pane 列表
 *
 * @var \zapcms\models\Node $node
 * @var array               $translations locale => 译文行
 * @var string              $mode
 */

use zapcms\services\Locale;

$translations = $translations ?? [];
$mode         = $mode ?? 'pane';
$nodeId       = (int) ($node->id ?? 0);
$enabled      = Locale::enabled();
$locales      = array_values(array_filter(Locale::all(), static function ($item) {
    return !$item['is_default'];
}));
if (!$enabled || empty($locales)) {
    return;
}

$langButton = static function (string $title, string $target, bool $active, string $code = '', bool $filled = false): void {
    $anchor = 'btn-' . str_replace('-', '_', $target);
    ?>
    <li class="nav-item" role="presentation">
        <button class="nav-link <?php echo $active ? 'active' : ''; ?>"
                id="<?php echo e($anchor); ?>"
                data-bs-toggle="tab" data-bs-target="#<?php echo e($target); ?>"
                type="button" role="tab">
            <?php echo e($title); ?>
            <?php if ($code !== ''): ?>
                <small class="text-muted ms-1"><?php echo e($code); ?></small>
            <?php endif; ?>
            <?php if ($filled): ?>
                <i class="fa fa-check text-success ms-1" title="已有译文"></i>
            <?php endif; ?>
        </button>
    </li>
    <?php
};

$langPane = static function (array $item, array $row) use ($node): void {
    $code   = (string) $item['code'];
    $anchor = 'trn-' . str_replace('-', '_', $code);
    $field  = 'translation[' . $code . ']';
    ?>
    <div class="tab-pane fade" id="<?php echo e($anchor); ?>" role="tabpanel">
        <div class="alert alert-light border small py-2 px-3 mb-3">
            <i class="fa fa-info-circle me-1"></i>留空的字段前台会自动回退到默认语言内容。
        </div>
        <div class="mb-2">
            <label class="form-label" for="<?php echo e($anchor); ?>-title">标题</label>
            <input type="text" class="form-control form-control-sm"
                   id="<?php echo e($anchor); ?>-title"
                   name="<?php echo e($field); ?>[title]"
                   value="<?php echo e($row['title'] ?? ''); ?>"
                   placeholder="<?php echo e($item['title']); ?>标题，留空则回退默认语言">
        </div>
        <div class="mb-2">
            <label class="form-label" for="<?php echo e($anchor); ?>-slug">
                URL 别名 <span class="text-muted small">(slug)</span>
            </label>
            <input type="text" class="form-control form-control-sm font-monospace"
                   id="<?php echo e($anchor); ?>-slug"
                   name="<?php echo e($field); ?>[slug]"
                   value="<?php echo e($row['slug'] ?? ''); ?>"
                   placeholder="留空则沿用默认语言的别名">
            <div class="form-text small"><?php echo htmlspecialchars(home_url($code) . '/'); ?></div>
        </div>
        <div class="mb-2">
            <label class="form-label" for="<?php echo e($anchor); ?>-content">正文</label>
            <textarea class="form-control form-control-sm summernote" rows="8"
                      id="<?php echo e($anchor); ?>-content"
                      name="<?php echo e($field); ?>[content]"
                      placeholder="支持 HTML"><?php echo e($row['content'] ?? ''); ?></textarea>
        </div>
        <div class="mb-2">
            <label class="form-label" for="<?php echo e($anchor); ?>-excerpt">摘要</label>
            <textarea class="form-control form-control-sm" rows="3"
                      id="<?php echo e($anchor); ?>-excerpt"
                      name="<?php echo e($field); ?>[excerpt]"><?php echo e($row['excerpt'] ?? ''); ?></textarea>
        </div>
        <div class="row g-2">
            <div class="col-md-6">
                <label class="form-label" for="<?php echo e($anchor); ?>-keywords">SEO 关键词</label>
                <input type="text" class="form-control form-control-sm"
                       id="<?php echo e($anchor); ?>-keywords"
                       name="<?php echo e($field); ?>[keywords]"
                       value="<?php echo e($row['keywords'] ?? ''); ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="<?php echo e($anchor); ?>-description">SEO 描述</label>
                <textarea class="form-control form-control-sm" rows="3"
                          id="<?php echo e($anchor); ?>-description"
                          name="<?php echo e($field); ?>[description]"><?php echo e($row['description'] ?? ''); ?></textarea>
            </div>
        </div>
    </div>
    <?php
};

if ($mode === 'main_tabs'): ?>
    <ul class="nav nav-tabs zap-main-lang-tabs mb-3" id="zap-main-lang-nav" role="tablist">
        <?php
        $langButton('默认内容', 'zap-lang-default', true);
        foreach ($locales as $index => $item):
            $code   = (string) $item['code'];
            $anchor = 'trn-' . str_replace('-', '_', $code);
            $row    = $translations[$code] ?? [];
            $filled = ($row['title'] ?? '') !== '' || ($row['content'] ?? '') !== '';
            $langButton((string) $item['title'], $anchor, false, $code, $filled);
        endforeach;
        ?>
    </ul>
<?php elseif ($mode === 'main_panes'): ?>
    <?php foreach ($locales as $item):
        $code = (string) $item['code'];
        $langPane($item, $translations[$code] ?? []);
    endforeach; ?>
<?php else: ?>
    <div class="tab-pane fade" id="tab-lang" role="tabpanel">
        <div class="alert alert-light border small py-2 px-3 mb-3">
            <i class="fa fa-info-circle me-1"></i>
            <?php if ($nodeId <= 0): ?>
                当前为新建模式，可直接填写其它语言内容，与默认语言一起保存。
            <?php else: ?>
                留空的字段前台会自动回退到默认语言内容。
            <?php endif; ?>
        </div>
        <ul class="nav nav-tabs mb-3" id="zap-translation-nav" role="tablist">
            <?php foreach ($locales as $index => $item):
                $code   = (string) $item['code'];
                $anchor = 'trn-' . str_replace('-', '_', $code);
                $row    = $translations[$code] ?? [];
                $filled = ($row['title'] ?? '') !== '' || ($row['content'] ?? '') !== '';
                $langButton((string) $item['title'], $anchor, $index === 0, $code, $filled);
            endforeach; ?>
        </ul>
        <div class="tab-content">
            <?php foreach ($locales as $item):
                $code = (string) $item['code'];
                $langPane($item, $translations[$code] ?? []);
            endforeach; ?>
        </div>
    </div>
<?php endif; ?>
