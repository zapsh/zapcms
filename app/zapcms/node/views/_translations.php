<?php
/**
 * 多语言翻译区块（Node 表单公用 partial）
 *
 * @var \zapcms\models\Node $node
 * @var array               $translations locale => 译文行
 */

use zapcms\services\Locale;

$translations = $translations ?? [];
$nodeId       = (int) ($node->id ?? 0);
$enabled      = Locale::enabled();
$locales      = array_values(array_filter(Locale::all(), static function ($item) {
    return !$item['is_default'];
}));
?>
<?php if (!$enabled): ?>

    <div class="card card-outline card-secondary mt-3">
        <div class="card-header">
            <h3 class="card-title"><i class="fa fa-language me-1"></i>多语言翻译</h3>
        </div>
        <div class="card-body p-3">
            <p class="text-muted mb-0">
                当前站点只启用了单一语言（<?php echo e(Locale::name(Locale::defaultLocale())); ?>）。
                如需翻译，请先在「设置 → 多语言」里添加语言。
            </p>
        </div>
    </div>

<?php else: ?>

<div class="card card-outline card-info mt-3" id="zap-translation-box">
    <div class="card-header d-flex align-items-center">
        <h3 class="card-title mb-0"><i class="fa fa-language me-1"></i>多语言翻译</h3>
        <div class="card-tools ms-auto">
            <span class="text-muted small">留空的字段前台会自动回退到默认语言内容</span>
        </div>
    </div>
    <div class="card-body p-3">
        <?php if ($nodeId <= 0): ?>
            <p class="text-muted small mb-2">
                <i class="fa fa-info-circle me-1"></i>当前为新建模式：可直接同时填写其它语言的翻译，与内容一起保存。
            </p>
        <?php endif; ?>
        <div class="row g-3">
                <div class="col-md-3">
                    <div class="nav flex-column nav-pills" id="zap-translation-nav" role="tablist" aria-orientation="vertical">
                        <?php foreach ($locales as $index => $item):
                            $code   = $item['code'];
                            $anchor = 'trn-' . str_replace('-', '_', $code);
                            $row    = $translations[$code] ?? [];
                            $filled = ($row['title'] ?? '') !== '' || ($row['content'] ?? '') !== '';
                        ?>
                            <button class="nav-link <?php echo $index === 0 ? 'active' : ''; ?>"
                                    id="btn-<?php echo $anchor; ?>"
                                    data-bs-toggle="pill" data-bs-target="#<?php echo $anchor; ?>"
                                    type="button" role="tab">
                                <?php echo e($item['title']); ?>
                                <small class="text-muted"><?php echo e($code); ?></small>
                                <?php if ($filled): ?>
                                    <i class="fa fa-check text-success ms-1" title="已有译文"></i>
                                <?php endif; ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="col-md-9">
                    <div class="tab-content">
                        <?php foreach ($locales as $index => $item):
                            $code   = $item['code'];
                            $anchor = 'trn-' . str_replace('-', '_', $code);
                            $row    = $translations[$code] ?? [];
                            $field  = 'translation[' . $code . ']';
                        ?>
                            <div class="tab-pane fade <?php echo $index === 0 ? 'show active' : ''; ?>"
                                 id="<?php echo $anchor; ?>" role="tabpanel">
                                <div class="mb-2">
                                    <label class="form-label" for="<?php echo $anchor; ?>-title">标题</label>
                                    <input type="text" class="form-control form-control-sm"
                                           id="<?php echo $anchor; ?>-title"
                                           name="<?php echo $field; ?>[title]"
                                           value="<?php echo e($row['title'] ?? ''); ?>"
                                           placeholder="<?php echo e($item['title']); ?>标题，留空则回退默认语言">
                                </div>

                                <div class="mb-2">
                                    <label class="form-label" for="<?php echo $anchor; ?>-slug">
                                        URL 别名 <span class="text-muted small">(slug)</span>
                                    </label>
                                    <input type="text" class="form-control form-control-sm font-monospace"
                                           id="<?php echo $anchor; ?>-slug"
                                           name="<?php echo $field; ?>[slug]"
                                           value="<?php echo e($row['slug'] ?? ''); ?>"
                                           placeholder="留空则沿用默认语言的别名">
                                    <div class="form-text small">
                                        <?php echo htmlspecialchars(Locale::isDefault($code) ? '' : home_url($code) . '/'); ?>
                                    </div>
                                </div>

                                <div class="mb-2">
                                    <label class="form-label" for="<?php echo $anchor; ?>-content">正文</label>
                                    <textarea class="form-control form-control-sm summernote" rows="8"
                                              id="<?php echo $anchor; ?>-content"
                                              name="<?php echo $field; ?>[content]"
                                              placeholder="支持 HTML"><?php echo e($row['content'] ?? ''); ?></textarea>
                                </div>

                                <div class="mb-2">
                                    <label class="form-label" for="<?php echo $anchor; ?>-excerpt">摘要</label>
                                    <textarea class="form-control form-control-sm" rows="3"
                                              id="<?php echo $anchor; ?>-excerpt"
                                              name="<?php echo $field; ?>[excerpt]"><?php echo e($row['excerpt'] ?? ''); ?></textarea>
                                </div>

                                <div class="row g-2">
                                    <div class="col-md-6">
                                        <label class="form-label" for="<?php echo $anchor; ?>-keywords">SEO 关键词</label>
                                        <input type="text" class="form-control form-control-sm"
                                               id="<?php echo $anchor; ?>-keywords"
                                               name="<?php echo $field; ?>[keywords]"
                                               value="<?php echo e($row['keywords'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label" for="<?php echo $anchor; ?>-description">SEO 描述</label>
                                        <textarea class="form-control form-control-sm" rows="3"
                                                  id="<?php echo $anchor; ?>-description"
                                                  name="<?php echo $field; ?>[description]"><?php echo e($row['description'] ?? ''); ?></textarea>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
    </div>
</div>
<?php endif; ?>
