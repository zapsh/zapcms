<?php

use zap\facades\Url;

/**
 * 标签新增/编辑表单（模态框内容，无独立布局）
 *
 * @var array $tag
 */

$tagId = (int)($tag['id'] ?? 0);
?>
<form id="tagForm">
    <input type="hidden" name="tag_id" value="<?php echo e($tagId); ?>">

    <div class="mb-3">
        <label for="tag_title" class="form-label small">标签名称 <span class="text-danger">*</span></label>
        <input type="text" class="form-control form-control-sm" id="tag_title" name="title"
               value="<?php echo e($tag['title'] ?? ''); ?>" required>
    </div>

    <div class="mb-2">
        <label for="tag_slug" class="form-label small">别名(slug) <span class="text-muted small">留空自动生成</span></label>
        <input type="text" class="form-control form-control-sm font-monospace" id="tag_slug" name="slug"
               value="<?php echo e($tag['slug'] ?? ''); ?>" placeholder="自动生成">
    </div>
</form>
