<?php
use zapcms\models\Comment;

$comment      = $comment ?? [];
$objectInfo   = $comment['object_info'] ?? null;
$statusOptions = Comment::getStatusOptions();
?>

<div class="p-3">
    <form>
        <input type="hidden" name="comment_id" value="<?php echo e($comment['comment_id'] ?? 0); ?>"/>

        <!-- 评论信息 -->
        <div class="row mb-2">
            <div class="col-sm-6">
                <div class="info-item">
                    <label class="text-muted small mb-1">评论者</label>
                    <div class="fw-semibold">
                        <?php echo htmlspecialchars($comment['author'] ?? '游客'); ?>
                        <?php if (!empty($comment['author_email'])): ?>
                        <small class="text-muted fw-normal"><?php echo htmlspecialchars($comment['author_email']); ?></small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-sm-6">
                <div class="info-item">
                    <label class="text-muted small mb-1">评论时间</label>
                    <div class="fw-semibold"><?php echo date('Y-m-d H:i:s', (int)($comment['created_at'] ?? 0)); ?></div>
                </div>
            </div>
        </div>

        <?php if (!empty($comment['parent_title'])): ?>
        <div class="mb-3">
            <label class="form-label text-muted small">回复对象</label>
            <div class="border rounded bg-light p-2 small text-muted">
                <i class="fa fa-reply me-1"></i><?php echo htmlspecialchars($comment['parent_title']); ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($objectInfo): ?>
        <div class="mb-3">
            <label class="form-label text-muted small">所属内容</label>
            <div class="border rounded bg-light p-2 small">
                <span class="badge text-bg-light me-1"><?php echo $objectInfo['type_title'] ?? $comment['object_type'] ?? ''; ?></span>
                <span><?php echo htmlspecialchars($objectInfo['title'] ?? '#' . ($comment['object_id'] ?? '')); ?></span>
                <span class="text-muted">#<?php echo e($comment['object_id'] ?? ''); ?></span>
            </div>
        </div>
        <?php endif; ?>

        <div class="mb-3">
            <label for="data_author" class="form-label">评论者 <span class="text-danger">*</span></label>
            <input type="text" name="author" id="data_author" class="form-control"
                   value="<?php echo htmlspecialchars($comment['author'] ?? ''); ?>" placeholder="评论者名称"/>
        </div>

        <div class="mb-3">
            <label for="data_content" class="form-label">评论内容 <span class="text-danger">*</span></label>
            <textarea name="content" id="data_content" class="form-control" rows="4"
                      placeholder="评论内容" required><?php echo htmlspecialchars($comment['content'] ?? ''); ?></textarea>
        </div>

        <div class="mb-3">
            <label class="form-label">审核状态</label>
            <div class="btn-group" role="group">
                <?php foreach ($statusOptions as $val => $label): ?>
                <input type="radio" class="btn-check" name="approved" id="data_approved_<?php echo e($val); ?>"
                       value="<?php echo e($val); ?>" <?php echo (int)($comment['approved'] ?? 0) === (int)$val ? 'checked' : ''; ?>/>
                <label class="btn btn-outline-<?php echo $val === 1 ? 'success' : ($val === 2 ? 'danger' : 'warning'); ?>"
                       for="data_approved_<?php echo e($val); ?>"><?php echo e($label); ?></label>
                <?php endforeach; ?>
            </div>
        </div>
    </form>
</div>
