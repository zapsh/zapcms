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

// 更新 page_title 以便布局渲染面包屑
if (!empty($catalogPaths)) {
    $last = array_pop($catalogPaths);
    $page_title = $last['title'] . ' - ' . $page_title;
}

// ── 列头排序：保留现有筛选参数，切换排序方向 ──
$sortParams = $_GET;
unset($sortParams['page'], $sortParams['sort']);
$currentSort = (string)($_GET['sort'] ?? '');
$sortUrl = function (string $sort) use ($sortParams): string {
    $sortParams['sort'] = $sort;
    return '?' . http_build_query($sortParams);
};

// ── 批量移动：仅同模型栏目（树序 DFS，与发布表单一致） ──
$moveCatalogOptions = [];
if (isset($menu) && $menu instanceof \zapcms\services\Catalog) {
    $flat = [];
    $walkMove = function (array $items) use (&$walkMove, &$flat) {
        foreach ($items as $it) {
            $flat[] = $it;
            if (!empty($it['children']) && is_array($it['children'])) {
                $walkMove($it['children']);
            }
        }
    };
    $walkMove($menu->getTreeArray([], ['columns' => ['id', 'title', 'level', 'node_type']]));
    foreach ($flat as $it) {
        if (($it['node_type'] ?? '') === ($node_type ?? '')) {
            $moveCatalogOptions[] = $it;
        }
    }
}
?>

<div class="container-fluid p-0">
    <div class="row g-3">
        <!-- 左侧栏目导航 -->
        <div class="col-lg-3">
            <div class="card card-outline card-primary">
                <div class="card-header p-2">
                    <h6 class="card-title mb-0">
                        <i class="fa fa-sitemap me-1 text-primary"></i>栏目导航
                    </h6>
                    <div class="card-tools">
                        <a href="<?php echo Url::action("Node"); ?>"
                           class="btn btn-tool <?php echo !$catalogId ? 'text-primary' : ''; ?>" title="全部内容">
                            <i class="fa fa-home"></i>
                        </a>
                    </div>
                </div>
                <div class="card-body p-0" style="max-height:calc(100vh - 260px);overflow-y:auto;">
                    <?php include __DIR__ . '/sidebar.php'; ?>
                </div>
            </div>
    
             <?php echo $this->partial('_left_navs'); ?>
        </div>
        <!-- /左侧栏目导航 -->

        <!-- 右侧内容区 -->
        <div class="col-lg-9">
            <div class="card card-outline card-primary">
                <!-- 工具栏 -->
                <div class="card-header p-2">
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <!-- 面包屑路径 -->
                        <div class="d-flex align-items-center me-auto">
                            <i class="fa fa-map-marker-alt text-muted me-1"></i>
                            <?php if (!empty($catalogPaths)): ?>
                                <?php
                                $breadcrumbParts = [];
                                foreach ($catalogPaths as $cat):
                                    $breadcrumbParts[] = '<a href="' . Url::action("Node@{$_controller}", ['cid' => $cat['id']]) . '" class="text-muted text-decoration-none">' . htmlspecialchars($cat['title']) . '</a>';
                                endforeach;
                                $breadcrumbParts[] = '<span class="text-muted">' . htmlspecialchars($last['title'] ?? '') . '</span>';
                                echo implode(' <span class="text-muted mx-1">/</span> ', $breadcrumbParts);
                                ?>
                            <?php else: ?>
                                <span class="text-muted">全部内容</span>
                            <?php endif; ?>
                        </div>

                        <!-- 搜索 + 状态筛选 -->
                        <form method="get" class="d-flex align-items-center gap-1 me-1">
                            <?php if ($catalogId): ?>
                            <input type="hidden" name="cid" value="<?php echo e($catalogId); ?>">
                            <?php endif; ?>
                            <div class="input-group input-group-sm" style="max-width:200px">
                                <input type="search" name="s" class="form-control" placeholder="搜索标题/别名..."
                                       value="<?php echo htmlspecialchars($_GET['s'] ?? ''); ?>">
                                <button class="btn btn-outline-secondary" type="submit">
                                    <i class="fa fa-search"></i>
                                </button>
                            </div>
                            <select name="status" class="form-select form-select-sm" style="width:auto"
                                    onchange="this.form.submit()">
                                <option value="">全部状态</option>
                                <?php $curStatus = (string)($_GET['status'] ?? ''); ?>
                                <option value="publish" <?php echo $curStatus === 'publish' ? 'selected' : ''; ?>>已发布</option>
                                <option value="draft" <?php echo $curStatus === 'draft' ? 'selected' : ''; ?>>草稿</option>
                            </select>
                        </form>

                        <!-- 添加按钮 -->
                        <a href="<?php echo Url::action("Node@{$_controller}/add", ['cid' => $catalogId]); ?>"
                           class="btn btn-primary btn-sm">
                            <i class="fa fa-plus me-1"></i>添加
                        </a>
                    </div>

                    <!-- 批量操作栏（勾选后显示） -->
                    <div id="nodeBatchBar" class="d-flex flex-wrap align-items-center gap-2 pt-2 border-top" style="display:none">
                        <span class="badge bg-primary"><span id="nodeBatchCount">0</span> 项已选</span>
                        <button type="button" class="btn btn-success btn-sm" onclick="nodeBatch('publish')">
                            <i class="fa fa-check me-1"></i>发布
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="nodeBatch('draft')">
                            <i class="fa fa-cloud-download me-1"></i>下架
                        </button>
                        <div class="input-group input-group-sm" style="max-width:240px">
                            <select id="nodeBatchCatalog" class="form-select form-select-sm">
                                <option value="0">移动到栏目…</option>
                                <?php foreach (($moveCatalogOptions ?? []) as $mc): ?>
                                <option value="<?php echo e($mc['id']); ?>">
                                    <?php echo str_repeat('— ', max(0, (int)$mc['level'] - 1)) . htmlspecialchars($mc['title']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" class="btn btn-outline-primary" onclick="nodeBatchMove()">移动</button>
                        </div>
                        <button type="button" class="btn btn-outline-danger btn-sm" onclick="nodeBatch('delete')">
                            <i class="fa fa-trash me-1"></i>删除
                        </button>
                    </div>
                </div>
                <!-- /工具栏 -->

                <!-- 内容表格 -->
                <div class="card-body p-0">
                    <?php if (!empty($data)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover table-striped mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:32px">
                                        <input type="checkbox" id="nodeCheckAll" title="全选/反选">
                                    </th>
                                    <th style="width:60px">
                                        <a href="<?php echo e($sortUrl($currentSort === 'id_asc' ? 'id' : 'id_asc')); ?>"
                                           class="text-decoration-none">ID
                                            <i class="fa fa-sort<?php
                                                echo $currentSort === 'id' ? '-desc' : ($currentSort === 'id_asc' ? '-asc' : '');
                                            ?>"></i>
                                        </a>
                                    </th>
                                    <th>标题</th>
                                    <th style="width:120px">类型</th>
                                    <th style="width:100px">状态</th>
                                    <th style="width:140px">
                                        <a href="<?php echo e($sortUrl($currentSort === 'pub_time_asc' ? 'pub_time' : 'pub_time_asc')); ?>"
                                           class="text-decoration-none">发布时间
                                            <i class="fa fa-sort<?php
                                                echo $currentSort === 'pub_time' ? '-desc' : ($currentSort === 'pub_time_asc' ? '-asc' : '');
                                            ?>"></i>
                                        </a>
                                    </th>
                                    <?php foreach (($list_columns ?? []) as $lc): ?>
                                    <th><?php echo htmlspecialchars($lc['field_label']); ?></th>
                                    <?php endforeach; ?>
                                    <th style="width:130px">操作</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($data as $row): ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" class="node-row-check" value="<?php echo e($row['id']); ?>"
                                               onchange="nodeBatchUpdateBar()">
                                    </td>
                                    <td class="text-muted small"><?php echo e($row['id']); ?></td>
                                    <td>
                                        <a href="<?php echo Url::action("Node@{$row['node_type']}/edit/{$row['id']}", ['cid' => $catalogId]); ?>"
                                           class="text-decoration-none fw-semibold">
                                            <?php echo htmlspecialchars($row['title']); ?>
                                        </a>
                                        <?php if (!empty($row['slug'])): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($row['slug']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-info text-dark"><?php echo \zapcms\models\Node::getTypeTitle($row['node_type']); ?></span>
                                    </td>
                                    <td>
                                        <?php $status = $row['status'] ?? 'draft'; ?>
                                        <span class="badge bg-<?php echo $status === 'publish' ? 'success' : 'secondary'; ?>">
                                            <?php echo \zapcms\models\Node::getStatusTitle($status); ?>
                                        </span>
                                    </td>
                                    <td class="text-muted small">
                                        <?php echo date('Y-m-d H:i', $row['pub_time'] ?? $row['add_time'] ?? 0); ?>
                                    </td>
                                    <?php foreach (($list_columns ?? []) as $lc): ?>
                                    <?php
                                        $mv = $row['meta'][$lc['field_name']] ?? '';
                                        $decoded = json_decode((string)$mv, true);
                                        if (is_array($decoded)) { $mv = implode(', ', $decoded); }
                                    ?>
                                    <td class="text-muted small">
                                        <?php echo $mv !== '' && $mv !== null ? htmlspecialchars((string)$mv) : '<span class="text-light-emphasis">-</span>'; ?>
                                    </td>
                                    <?php endforeach; ?>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <?php if (!empty($row['slug'])): ?>
                                            <a href="<?php echo smart_node_url($row); ?>"
                                               target="_blank" class="btn btn-outline-secondary" title="前台查看">
                                                <i class="fa fa-eye"></i>
                                            </a>
                                            <?php endif; ?>
                                            <a href="<?php echo Url::action("Node@{$row['node_type']}/edit/{$row['id']}", ['cid' => $catalogId]); ?>"
                                               class="btn btn-outline-primary" title="编辑">
                                                <i class="fa fa-edit"></i>
                                            </a>
                                            <button type="button" class="btn btn-outline-danger"
                                                    onclick="deleteNode(<?php echo e($row['id']); ?>,'<?php echo htmlspecialchars(addslashes($row['title'])); ?>')"
                                                    title="删除">
                                                <i class="fa fa-trash"></i>
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
                    <div class="text-center py-5">
                        <i class="fa fa-inbox fa-3x text-muted mb-3 d-block"></i>
                        <p class="text-muted mb-3">暂无内容数据</p>
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
                    <small class="text-muted">
                        显示第 <?php echo $page->getOffset() + 1; ?>-<?php echo min($page->getOffset() + $page->getLimit(), $page->total()); ?> 条，共 <?php echo $page->total(); ?> 条
                    </small>
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
    if (bar) { bar.style.display = ids.length ? 'flex' : 'none'; }
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
