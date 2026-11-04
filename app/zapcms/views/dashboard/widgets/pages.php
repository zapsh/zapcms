<div class="card mb-4">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fa fa-file card-header-icon text-primary"></i> 单页管理
        </h3>
        <div class="card-tools">
            <span class="badge text-bg-secondary"><?php echo count($pages); ?> 个页面</span>
        </div>
    </div>
    <div class="card-body p-0">
        <?php if (empty($pages)): ?>
            <div class="p-4 text-center text-muted">
                <i class="fa fa-inbox fs-1 d-block mb-2"></i>
                暂无单页内容
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0">
                    <thead class="table-light">
                    <tr>
                        <th class="ps-3">标题</th>
                        <th>状态</th>
                        <th class="text-end pe-3">操作</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($pages as $page): ?>
                        <tr>
                            <td class="ps-3">
                                <a href="<?php echo url_action("Node@page/edit/{$page['id']}"); ?>"
                                   class="text-decoration-none">
                                    <i class="fa fa-file-alt text-success me-1"></i>
                                    <?php echo e($page['title']); ?>
                                </a>
                            </td>
                            <td>
                                <?php if (!empty($page['status'])): ?>
                                    <span class="badge text-bg-success">已发布</span>
                                <?php else: ?>
                                    <span class="badge text-bg-warning">草稿</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end pe-3">
                                <a href="<?php echo url_action("Node@page/edit/{$page['id']}"); ?>"
                                   class="btn btn-sm btn-outline-primary" title="编辑">
                                    <i class="fa fa-edit"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
