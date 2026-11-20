<?php
/**
 * @var array $location
 */
use zap\facades\Url;
?>
<form id="menuLocationForm">
    <input type="hidden" name="id" value="<?php echo e($location['id'] ?? 0); ?>">
    <div class="modal-body">
        <div class="mb-3">
            <label class="form-label small">位置名称 <span class="text-danger">*</span></label>
            <input type="text" class="form-control form-control-sm" name="title"
                   value="<?php echo e($location['title'] ?? ''); ?>" placeholder="如：关于页左侧" autofocus>
        </div>
        <div class="mb-3">
            <label class="form-label small">位置标识 (key) <span class="text-danger">*</span></label>
            <input type="text" class="form-control form-control-sm font-monospace" name="key"
                   value="<?php echo e($location['key'] ?? ''); ?>" placeholder="如：about-left"
                <?php echo !empty($location['id']) ? 'readonly' : ''; ?>>
            <div class="form-text small text-muted">
                只能包含字母、数字、下划线和连字符；模板中通过
                <code>catalog_menu('<?php echo e($location['key'] ?? 'key'); ?>')</code> 调用。
                <?php if (!empty($location['id'])): ?>（系统/已存在位置不可修改标识）<?php endif; ?>
            </div>
        </div>
        <div class="mb-3">
            <label class="form-label small">描述</label>
            <input type="text" class="form-control form-control-sm" name="description"
                   value="<?php echo e($location['description'] ?? ''); ?>" placeholder="选填，便于识别该位置的用途">
        </div>
        <div class="mb-1">
            <label class="form-label small">排序</label>
            <input type="number" class="form-control form-control-sm" name="sort_order"
                   value="<?php echo e($location['sort_order'] ?? 0); ?>" placeholder="数值越小越靠前">
        </div>
    </div>
</form>
