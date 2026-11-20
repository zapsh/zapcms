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

// ── 列头排序：保留现有筛选参数，切换排序方向 ──
$sortParams = $_GET;
unset($sortParams['page'], $sortParams['sort']);
$currentSort = (string)($_GET['sort'] ?? '');
$sortUrl = function (string $sort) use ($sortParams): string {
    $sortParams['sort'] = $sort;
    return '?' . http_build_query($sortParams);
};

?>

<div class="container-fluid p-0">
    <div class="row g-3">
        <div class="col-12">
            <div class="card card-primary zap-node-list">
                <?php echo $this->partial('_list_filters'); ?>

                <div id="nodeBatchBar" class="zap-batch-bar flex-wrap align-items-center gap-2 px-3 py-2 border-bottom" style="display:none">
                    <span class="text-muted small me-2">已选 <strong id="nodeBatchCount">0</strong> 项</span>
                    <select id="nodeBatchAction" class="form-select form-select-sm w-auto">
                        <option value="">批量操作</option>
                        <option value="publish">发布</option>
                        <option value="draft">下架</option>
                        <option value="trash">移到回收站</option>
                        <option value="move">移动到栏目</option>
                        <option value="delete">删除</option>
                    </select>
                    <button type="button" class="btn btn-primary btn-sm" onclick="applyNodeBatchAction()">应用</button>
                    <div id="nodeBatchMoveBox" class="d-none">
                        <?php echo $this->partial('_batch_catalog_picker'); ?>
                    </div>
                </div>

                <!-- 内容表格 -->
                <div class="card-body p-0">
                    <?php if (!empty($data)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:32px">
                                        <input type="checkbox" id="nodeCheckAll" title="全选/反选">
                                    </th>
                                    <th>标题</th>
                                    <th class="d-none d-md-table-cell" style="width:180px">栏目</th>
                                    <?php if (empty($_GET['type'])): ?>
                                    <th class="d-none d-md-table-cell" style="width:120px">类型</th>
                                    <?php endif; ?>
                                    <th class="zap-col-date" style="width:140px">
                                        <a href="<?php echo e($sortUrl($currentSort === 'pub_time_asc' ? 'pub_time' : 'pub_time_asc')); ?>"
                                           class="text-decoration-none">日期
                                            <i class="fa fa-sort<?php
                                                echo $currentSort === 'pub_time' ? '-desc' : ($currentSort === 'pub_time_asc' ? '-asc' : '');
                                            ?>"></i>
                                        </a>
                                    </th>
                                    <?php foreach (($list_columns ?? []) as $lc): ?>
                                    <th class="d-none d-md-table-cell"><?php echo htmlspecialchars($lc['field_label']); ?></th>
                                    <?php endforeach; ?>
                                    <th class="zap-col-actions" style="width:100px">操作</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($data as $row): ?>
                                <tr class="zap-node-row">
                                    <td>
                                        <input type="checkbox" class="node-row-check" value="<?php echo e($row['id']); ?>"
                                               onchange="nodeBatchUpdateBar()">
                                    </td>
                                    <?php
                                        echo zap_node_title_cell($row, [
                                            'controller'     => $_controller,
                                            'edit_controller'=> $row['node_type'],
                                            'catalog_id'     => $catalogId,
                                            'paths'          => $row_catalog_paths[$row['id']] ?? [],
                                            'list_columns'   => $list_columns ?? [],
                                            'cid_param'      => 'cid',
                                        ]);
                                    ?>
                                    <?php
                                        $allPaths = $row_catalog_paths[$row['id']] ?? [];
                                        $listBase = $_controller === 'default'
                                            ? Url::action('Node')
                                            : Url::action('Node@' . ucfirst($_controller));
                                    ?>
                                    <td class="d-none d-md-table-cell text-muted small">
                                        <?php echo zap_catalog_path_links($allPaths, $listBase, 'cid'); ?>
                                    </td>
                                    <?php if (empty($_GET['type'])): ?>
                                    <td class="d-none d-md-table-cell">
                                        <?php echo zap_list_badge(\zapcms\models\Node::getTypeTitle($row['node_type']), 'type'); ?>
                                    </td>
                                    <?php endif; ?>
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
                                            <a href="<?php echo Url::action("Node@{$row['node_type']}/edit/{$row['id']}", ['cid' => $catalogId]); ?>"
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
                        <div class="zap-empty-icon"><i class="fa fa-inbox"></i></div>
                        <div class="zap-empty-title">暂无内容数据</div>
                        <div class="zap-empty-desc">调整筛选条件，或创建第一条内容</div>
                        <a href="<?php echo Url::action("Node@{$_controller}/add", ['cid' => $catalogId]); ?>"
                           class="btn btn-primary btn-sm">
                            <i class="fa fa-plus me-1"></i>添加第一条内容
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
// ============ 批量操作 ============
function nodeBatchCheckedIds() {
    return Array.from(document.querySelectorAll('.node-row-check:checked')).map(function (c) { return c.value; });
}

function nodeBatchUpdateBar() {
    var ids = nodeBatchCheckedIds();
    var bar = document.getElementById('nodeBatchBar');
    if (bar) {
        bar.style.display = ids.length ? 'flex' : 'none';
        bar.classList.toggle('shadow-sm', ids.length > 0);
    }
    var count = document.getElementById('nodeBatchCount');
    if (count) { count.textContent = ids.length; }
    var all = document.getElementById('nodeCheckAll');
    var boxes = document.querySelectorAll('.node-row-check');
    if (all) { all.checked = boxes.length > 0 && ids.length === boxes.length; }
}

document.addEventListener('DOMContentLoaded', function () {
    var all = document.getElementById('nodeCheckAll');
    if (all) {
        all.addEventListener('change', function () {
            document.querySelectorAll('.node-row-check').forEach(function (c) { c.checked = all.checked; });
            nodeBatchUpdateBar();
        });
    }
});

function nodeBatchPost(payload, successMsg) {
    var load = Zap.loading('正在处理...');
    $.ajax({
        url: '<?php echo Url::action("Node@{$_controller}/batch"); ?>',
        method: 'post',
        data: payload,
        traditional: true,
        dataType: 'json',
        success: function (res) {
            if (res.code === 0) {
                Swal.fire({title: '操作成功', text: res.msg || successMsg, icon: 'success', timer: 1500, showConfirmButton: false})
                    .then(function () { location.reload(); });
            } else {
                Swal.fire({title: '操作失败', text: res.msg || '操作失败', icon: 'error'});
            }
        },
        error: function () {
            Swal.fire({title: '操作失败', text: '网络异常，请稍后重试', icon: 'error'});
        }
    }).always(function () { load.dispose(); });
}

function nodeBatch(action) {
    var ids = nodeBatchCheckedIds();
    if (!ids.length) { ZapToast.warning('请先勾选要操作的内容'); return; }
    var doIt = function () { nodeBatchPost({batch_action: action, ids: ids}); };

    if (action === 'delete') {
        Swal.fire({
            title: '确认批量删除',
            html: '确定要删除选中的 <strong>' + ids.length + '</strong> 条内容吗？<br><span class="text-danger small">此操作不可恢复！</span>',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: '<i class="fa fa-trash me-1"></i>确认删除',
            cancelButtonText: '取消',
            confirmButtonColor: '#dc3545',
            reverseButtons: true
        }).then(function (r) { if (r.isConfirmed) { doIt(); } });
        return;
    }
    doIt();
}

function nodeBatchMove() {
    var ids = nodeBatchCheckedIds();
    if (!ids.length) { ZapToast.warning('请先勾选要移动的内容'); return; }
    var cid = parseInt(document.getElementById('nodeBatchCatalog').value, 10);
    if (!cid) { ZapToast.warning('请选择目标栏目'); return; }
    nodeBatchPost({batch_action: 'move', ids: ids, catalog_id: cid});
}

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
