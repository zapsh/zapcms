<?php
/**
 * 主题卡片网格片段
 *
 * 单独抽出便于「启用主题」后由服务端重新渲染、前端局部替换（无需整页刷新）。
 *
 * @var array $themes
 * @var array $website_options
 */
$currentTheme = $website_options['website.theme'] ?? '';
?>
<div class="row g-3">
    <?php foreach ($themes as $theme): ?>
    <div class="col-6 col-sm-4 col-lg-3">
        <div class="border rounded bg-white h-100 d-flex flex-column">
            <div class="position-relative">
                <img src="<?php echo base_url("/themes/{$theme['name']}/{$theme['screenshot']}"); ?>"
                     class="w-100 rounded-top" alt="<?php echo e($theme['title'] ?? ''); ?>" style="aspect-ratio:16/10;object-fit:cover;">
                <?php if ($currentTheme === $theme['dirname']): ?>
                <span class="badge bg-success position-absolute top-0 end-0 m-1">当前</span>
                <?php endif; ?>
            </div>
            <div class="px-2 pt-2 d-flex flex-column flex-fill">
                <h5 class="fs-6 fw-bold mb-1 text-truncate" title="<?php echo e($theme['title'] ?? ''); ?>"><?php echo e($theme['title'] ?? ''); ?></h5>
                <p class="text-muted small mb-1" style="line-height:1.3;">
                    <?php echo \zap\util\Str::truncate($theme['description'] ?? '', 60); ?>
                </p>
                <small class="text-muted mt-auto mb-2">v<?php echo e($theme['version'] ?? ''); ?></small>
            </div>
            <div class="px-2 pb-2 mt-auto">
                <?php if ($currentTheme === $theme['dirname']): ?>
                <a href="<?php echo url_action('Theme@settings'); ?>"
                   class="btn btn-outline-info btn-sm w-100">
                    <i class="fa fa-cog me-1"></i>设置
                </a>
                <?php else: ?>
                <button class="btn btn-outline-success btn-sm w-100"
                        onclick="activationTheme('<?php echo e($theme['dirname']); ?>')" type="button">
                    <i class="fa fa-check me-1"></i>启用
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
