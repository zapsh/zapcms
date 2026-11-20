<?php
use zap\facades\Url;

/**
 * @var array $data
 * @var \zap\helpers\Pagination $page
 * @var int $catalogId
 * @var array $catalogPaths
 * @var array $breadcrumbs
 * @var string $page_title
 * @var string $_controller
 * @var string $_action
 */

$this->layout('layouts/common');


?>

<div class="container-fluid p-0">
    <div class="row g-3">
        <div class="col-12">
            <div class="card card-success zap-node-list">
                <?php echo $this->partial('_list_filters'); ?>

                <!-- 内容表格 -->
                <div class="card-body p-0">
                    <?php if (!empty($data)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>标题</th>
                                    <th class="d-none d-md-table-cell" style="width:180px">栏目</th>
                                    <th class="zap-col-date" style="width:140px">日期</th>
                                    <?php foreach (($list_columns ?? []) as $lc): ?>
                                    <th class="d-none d-md-table-cell"><?php echo htmlspecialchars($lc['field_label']); ?></th>
                                    <?php endforeach; ?>
                                    <th class="zap-col-actions" style="width:100px">操作</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($data as $row): ?>
                                <tr class="zap-node-row">
                                    <?php
                                        echo zap_node_title_cell($row, [
                                            'controller'   => $_controller,
                                            'catalog_id'   => $catalogId,
                                            'paths'        => $row_catalog_paths[$row['id']] ?? [],
                                            'list_columns' => $list_columns ?? [],
                                            'cid_param'    => 'filter_cid',
                                        ]);
                                    ?>
                                    <?php
                                        $allPaths = $row_catalog_paths[$row['id']] ?? [];
                                        $listBase = Url::action('Node@' . ucfirst($_controller));
                                    ?>
                                    <td class="d-none d-md-table-cell text-muted small">
                                        <?php echo zap_catalog_path_links($allPaths, $listBase, 'filter_cid'); ?>
                                    </td>
                                    <td class="zap-col-date">
                                        <?php $status = $row['status'] ?? 'draft'; ?>
                                        <span class="zap-row-status-badge" data-node-id="<?php echo e($row['id']); ?>"><?php echo zap_list_badge(\zapcms\models\Node::getStatusTitle($status), $status); ?></span>
                                        <div class="text-muted small mt-1 d-none d-md-block">
                                            <?php echo date('Y-m-d H:i', $row['pub_time'] ?? $row['add_time'] ?? 0); ?>
                                        </div>
                                    </td>
                                    <?php foreach (($list_columns ?? []) as $lc): ?>
                                    <?php
                                        $mv = $row['meta'][$lc['field_name']] ?? '';
                                        $decoded = json_decode((string)$mv, true);
                                        if (is_array($decoded)) { $mv = implode(', ', $decoded); }
                                    ?>
                                    <td class="d-none d-md-table-cell text-muted small">
                                        <?php echo $mv !== '' && $mv !== null ? htmlspecialchars((string)$mv) : '<span class="text-light-emphasis">-</span>'; ?>
                                    </td>
                                    <?php endforeach; ?>
                                    <td class="zap-col-actions">
                                        <div class="btn-group btn-group-sm zap-row-actions">
                                            <?php if (!empty($row['slug'])): ?>
                                            <a href="<?php echo smart_node_url($row); ?>"
                                               target="_blank" class="btn" title="前台查看">
                                                <i class="fa fa-eye fa-fw"></i>
                                            </a>
                                            <?php endif; ?>
                                            <a href="<?php echo Url::action("Node@{$_controller}/edit/{$row['id']}"); ?>"
                                               class="btn" title="编辑">
                                                <i class="fa fa-edit fa-fw"></i>
                                            </a>
                                            <button type="button" class="btn zap-top-action<?php echo !empty($row['is_top']) ? ' active' : ''; ?>"
                                                    onclick="zapToggleTop(this)" title="<?php echo !empty($row['is_top']) ? '取消置顶' : '置顶'; ?>">
                                                <i class="fa fa-thumbtack fa-fw"></i>
                                            </button>
                                            <button type="button" class="btn zap-row-action-delete"
                                                    onclick="deleteNode(<?php echo e($row['id']); ?>,'<?php echo htmlspecialchars(addslashes($row['title'])); ?>')"
                                                    title="删除">
                                                <i class="fa fa-trash fa-fw"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <!-- 空状态 -->
                    <div class="text-center zap-empty-state">
                        <div class="zap-empty-icon"><i class="fa fa-file-alt"></i></div>
                        <div class="zap-empty-title">暂无页面数据</div>
                        <div class="zap-empty-desc">调整筛选条件，或创建第一个页面</div>
                        <a href="<?php echo Url::action("Node@{$_controller}/add", ['cid' => $catalogId]); ?>"
                           class="btn btn-primary btn-sm">
                            <i class="fa fa-plus me-1"></i>添加第一个页面
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
                <!-- /内容表格 -->

                <?php if (!empty($data)): ?>
                <!-- 底部分页信息 -->
                <div class="card-footer p-2 d-flex flex-wrap align-items-center justify-content-between">
                    <div class="d-flex align-items-center flex-wrap gap-2">
                        <?php echo zap_per_page_selector((int) $page->getLimit()); ?>
                        <small class="text-muted">
                            显示第 <?php echo $page->getOffset() + 1; ?>-<?php echo min($page->getOffset() + $page->getLimit(), $page->total()); ?> 条，共 <?php echo $page->total(); ?> 条
                        </small>
                    </div>
                    <div>
                        <?php echo $page->render(); ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <!-- /右侧内容区 -->
    </div>
</div>

<script>
function deleteNode(id, title) {
    Swal.fire({
        title: '确认删除',
        html: '确定要删除 <strong>"' + title + '"</strong> 吗？<br><span class="text-danger small">此操作不可恢复！</span>',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: '<i class="fa fa-trash me-1"></i>确认删除',
        cancelButtonText: '取消',
        confirmButtonColor: '#dc3545',
        reverseButtons: true
    }).then((result) => {
        if (!result.isConfirmed) return;
        var load = Zap.loading('正在删除...');
        $.ajax({
            url: '<?php echo Url::action("Node@{$_controller}/remove"); ?>',
            method: 'post',
            data: {id: id},
            dataType: 'json',
            success: function(res) {
                if (res.code === 0) {
                    Swal.fire({title:'已删除',text:res.msg||'删除成功',icon:'success',timer:1500,showConfirmButton:false})
                        .then(function(){ location.reload(); });
                } else {
                    Swal.fire({title:'删除失败',text:res.msg||'操作失败',icon:'error'});
                }
            },
            error: function() {
                Swal.fire({title:'删除失败',text:'网络异常，请稍后重试',icon:'error'});
            }
        }).always(function(){ load.dispose(); });
    });
}
</script>
