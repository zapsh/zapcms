<?php

use zap\facades\Url;

IS_AJAX !== true && $this->layout('layouts/common');

// 栏目树改为前端懒加载（见下方 <script>）：初始只加载根节点，展开时按父级取子级。
// 菜单归属统一在「外观 > 菜单」维护，栏目页只管理目录树结构。
?>

<div class="card card-outline card-success">
    <div class="card-header p-2 d-flex align-items-center">
        <h6 class="card-title mb-0">
            <i class="fa fa-th-list me-1 text-success"></i>栏目列表
            <?php if (!empty($menu)): ?>
            <span class="badge text-bg-success ms-1 rounded-pill"><?php echo e($catalog_count); ?></span>
            <?php endif; ?>
        </h6>
        <div class="card-tools ms-auto">
            <div class="d-flex gap-2 align-items-center">
                <button type="button" class="btn btn-outline-success btn-sm" onclick="save()">
                    <i class="fa fa-save me-1"></i>保存
                </button>
                <button type="button" class="btn btn-success btn-sm" onclick="addOrEdit(0,0)">
                    <i class="fa fa-plus me-1"></i>添加栏目
                </button>
            </div>
        </div>
    </div>
    <div class="card-body p-0">
        <script>
            function checkAll(el) {
                $('.zap_catalog').prop('checked', $(el).prop('checked'));
                updateSelectedCount();
            }
        </script>
        <form action="post" id="reqForm">
            <div class="table-responsive">
                <table class="table table-hover table-striped text-nowrap align-middle mb-0 catalog-table">
                    <thead>
                        <tr class="table-light">
                            <th scope="col" style="width:40px" class="text-center">
                                <input class="form-check-input" type="checkbox" onclick="checkAll(this)"/>
                            </th>
                            <th scope="col" style="width:60px">排序</th>
                            <th scope="col" class="w-100">栏目名称</th>
                            <th scope="col" class="d-none d-md-table-cell">SEO</th>
                            <th scope="col" class="d-none d-lg-table-cell">模型</th>
                            <th scope="col" style="width:120px" class="text-center">操作</th>
                        </tr>
                    </thead>
                    <tbody id="treeBody">
                        <tr id="treeLoading">
                            <td colspan="6" class="text-center py-4 text-muted">
                                <i class="fa fa-spinner fa-spin me-1"></i>加载中…
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="card-footer p-2 bg-white border-top d-flex align-items-center">
                <button type="button" class="btn btn-outline-danger btn-sm" onclick="remove()">
                    <i class="fa fa-trash me-1"></i>删除所选栏目
                </button>
                <span id="selectedCount" class="ms-2 text-muted small">已选 0 项</span>
            </div>
        </form>
    </div>
</div>

<?php // 菜单构建器抽屉已移至「外观 > 菜单」，栏目页不再引入 ?>

<style>
.table {
    font-size: 0.875rem;
}
.table thead th {
    font-weight: 600;
    font-size: 0.8rem;
    padding: 0.75rem 0.5rem;
    white-space: nowrap;
    vertical-align: middle;
}
.table tbody td {
    padding: 0.625rem 0.5rem;
    vertical-align: middle;
}
.table tbody td.title-cell {
    padding-left: 0;
}
.catalog-table tbody tr { transition: background-color .15s ease; }
.catalog-table tbody tr:hover { background-color: #f1fdf6; }
.catalog-title {
    display: flex;
    align-items: center;
    flex-wrap: nowrap;
    gap: 0rem;
}
.catalog-title .title-input {
    max-width: 140px;
    flex-shrink: 0;
}
.catalog-title .menu-icon {
    color: #10b981;
    width: 20px;
    text-align: center;
    flex-shrink: 0;
}
.catalog-title .menu-id {
    color: #9ca3af;
    font-size: 0.75rem;
    flex-shrink: 0;
}
.sort-input {
    width: 50px !important;
    text-align: center;
    padding: 0.25rem 0.25rem !important;
}
.seo-cell {
    max-width: 120px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.btn-group .btn { border-color: #dee2e6; }
.btn-group .btn:hover { z-index: 2; }
@media (max-width: 767px) {
    .table { font-size: 0.8rem; }
    .table th, .table td { padding: 0.5rem 0.35rem; }
    .sort-input { width: 40px !important; font-size: 0.7rem; }
    .catalog-title .title-input { max-width: 100px; font-size: 0.8rem; }
    .catalog-title .menu-icon { width: 16px; font-size: 0.9rem; }
    .seo-cell { display: none; }
}
</style>

<script>
    // HTML 转义（原先由 menu_location._drawer partial 提供，栏目页已不再引入抽屉，本地定义）
    if (typeof escapeHtml !== 'function') {
        window.escapeHtml = function (str) {
            if (str === null || str === undefined) return '';
            return String(str)
                .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
        };
    }

    // ───────────────────── 栏目树（懒加载 / 可展开） ─────────────────────
    var childrenUrl = '<?php echo Url::action("Catalog@children"); ?>';

    // 展开/折叠：点开才 AJAX 取子级
    $(document).on('click', '.tree-toggle', function () {
        toggleBranch(parseInt($(this).data('id'), 10));
    });

    function toggleBranch(id) {
        var $row = $('#treeBody .catalog-row[data-id="' + id + '"]');
        if (!$row.length) return;
        var depth = parseInt($row.data('depth'), 10);
        var $ic = $row.find('.tree-toggle i');
        if ($ic.hasClass('fa-chevron-down')) {
            setDescendantsVisible($row, depth, false);
            $ic.removeClass('fa-chevron-down').addClass('fa-chevron-right');
        } else if ($row.data('loaded')) {
            setDescendantsVisible($row, depth, true);
            $ic.removeClass('fa-chevron-right').addClass('fa-chevron-down');
        } else {
            loadChildren(id, $row);
        }
    }

    // 显示/隐藏某节点下所有后代行
    function setDescendantsVisible($row, depth, visible) {
        var $sib = $row.next();
        while ($sib.length && parseInt($sib.data('depth') || 0, 10) > depth) {
            $sib.toggle(visible);
            $sib = $sib.next();
        }
    }

    // 按父级取子级并插入（pid=0 为根；$afterRow 为 null 时追加到树根）
    function loadChildren(pid, $afterRow) {
        $.getJSON(childrenUrl, {parent_id: pid}, function (res) {
            if (pid === 0) { $('#treeLoading').remove(); }
            if (res.code !== 0 || !res.nodes || !res.nodes.length) {
                if (pid === 0) {
                    $('#treeBody').append(
                        '<tr><td colspan="6" class="text-center py-5 text-muted">' +
                        '<i class="fa fa-inbox" style="font-size:2rem;display:block;"></i>' +
                        '<small>暂无栏目数据</small></td></tr>'
                    );
                }
                if ($afterRow && $afterRow.length) {
                    $afterRow.data('loaded', true);
                    var $ie = $afterRow.find('.tree-toggle i');
                    if ($ie.length) { $ie.removeClass('fa-chevron-right').addClass('fa-chevron-down'); }
                }
                return;
            }
            var baseDepth = ($afterRow && $afterRow.length)
                ? (parseInt($afterRow.data('depth'), 10) + 1)
                : 0;
            var html = '';
            res.nodes.forEach(function (n) { html += renderRow(n, baseDepth); });
            if ($afterRow && $afterRow.length) {
                $afterRow.after(html);
                $afterRow.data('loaded', true);
                $afterRow.find('.tree-toggle i').removeClass('fa-chevron-right').addClass('fa-chevron-down');
            } else {
                $('#treeBody').append(html);
            }
            updateSelectedCount();
        });
    }

    // 渲染单个栏目行（与旧缩进表格列一致）
    function renderRow(node, depth) {
        var paddingLeft = (depth * 1.5).toFixed(2);
        var toggle = node.has_children
            ? '<button type="button" class="btn btn-sm btn-link text-secondary py-0 px-1 tree-toggle" data-id="' + node.id + '">' +
              '<i class="fa fa-chevron-right"></i></button>'
            : '<span class="tree-toggle-spacer d-inline-block" style="width:26px"></span>';
        var icon = node.icon ? '<i class="' + escapeHtml(node.icon) + ' menu-icon"></i>' : '';
        var ext = node.node_type === 'link-url'
            ? '<i class="fa fa-external-link-alt text-muted" title="快捷链接"></i>' : '';
        var seo = node.slug === '--zap-link-url' ? (node.link_to || '') : (node.slug || '');
        return '' +
            '<tr class="catalog-row" data-id="' + node.id + '" data-pid="' + node.pid + '" data-depth="' + depth + '">' +
                '<td class="text-center">' +
                    '<input name="catalog[' + node.id + '][id]" value="' + node.id + '" ' +
                    'class="form-check-input zap_catalog" type="checkbox" onchange="updateSelectedCount()">' +
                '</td>' +
                '<td>' +
                    '<input name="catalog[' + node.id + '][sort_order]" value="' + escapeHtml(node.sort_order) + '" ' +
                    'class="form-control form-control-sm sort-input" data-bs-toggle="tooltip" data-bs-placement="right" ' +
                    'data-bs-title="数值越小越靠前">' +
                '</td>' +
                '<td class="align-middle title-cell">' +
                    '<div class="catalog-title" style="padding-left:' + paddingLeft + 'rem;">' +
                        toggle + icon +
                        '<input name="catalog[' + node.id + '][title]" value="' + escapeHtml(node.title) + '" ' +
                        'class="d-inline form-control form-control-sm title-input">' +
                        '<small class="menu-id d-none d-sm-inline">#' + node.id + '</small>' + ext +
                    '</div>' +
                '</td>' +
                '<td class="align-middle d-none d-md-table-cell">' +
                    '<small class="text-muted seo-cell">' + escapeHtml(seo) + '</small>' +
                '</td>' +
                '<td class="align-middle d-none d-lg-table-cell">' +
                    '<span class="badge text-bg-light">' + escapeHtml(node.node_type_title || node.node_type) + '</span>' +
                '</td>' +
                '<td class="align-middle text-center">' +
                    '<div class="btn-group btn-group-sm" role="group">' +
                        '<button type="button" class="btn btn-outline-secondary" title="编辑" onclick="addOrEdit(' + node.id + ',0)"><i class="fa fa-edit"></i></button>' +
                        '<button type="button" class="btn btn-outline-secondary" title="添加子栏目" onclick="addOrEdit(0,' + node.id + ')"><i class="fa fa-plus"></i></button>' +
                        '<button type="button" class="btn btn-outline-danger" title="删除" onclick="removeOne(' + node.id + ')"><i class="fa fa-trash"></i></button>' +
                    '</div>' +
                '</td>' +
            '</tr>';
    }

    $(function (){
        Zap.EnableToolTip();
        updateSelectedCount();
        loadChildren(0, null); // 初始只加载根栏目，展开时按需取子级
    })

    function save(){
        $.ajax({
            url:'<?php echo Url::action("Catalog@save");?>',
            method:'post',
            data:$('#reqForm').serialize(),
            success:function (data){
                ZapToast.alert(data.msg,{
                    bgColor:data.code===0?bgSuccess:bgDanger,
                    position:Toast_Pos_Center
                });
            }
        })
    }

    function confirmDelete(msg, callback){
        if (typeof Swal === 'undefined') {
            if (window.confirm(msg)) { callback(); }
            return;
        }
        Swal.fire({
            title: '确认删除',
            text: msg,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: '删除',
            cancelButtonText: '取消',
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d'
        }).then(function (result) {
            if (result.isConfirmed) { callback(); }
        });
    }

    function remove(){
        const checked = $('.zap_catalog:checked');
        if(checked.length === 0){
            ZapToast.alert('请选择需要删除的栏目',{bgColor:bgWarning,position:Toast_Pos_Center});
            return;
        }
        confirmDelete('确定要删除选中的 ' + checked.length + ' 个栏目吗？其子栏目将一并删除，且不可恢复。', function(){
            $.ajax({
                url:'<?php echo Url::action("Catalog@remove");?>',
                method:'post',
                data:checked.serialize(),
                success:function (data){
                    ZapToast.alert(data.msg,{bgColor:data.code===0?bgSuccess:bgDanger,position:Toast_Pos_Center});
                    Zap.reload();
                }
            });
        });
    }

    function removeOne(id){
        confirmDelete('确定删除该栏目吗？其子栏目将一并删除，且不可恢复。', function(){
            var payload = {};
            payload['catalog[' + id + '][id]'] = id;
            $.ajax({
                url:'<?php echo Url::action("Catalog@remove");?>',
                method:'post',
                data:payload,
                success:function (data){
                    ZapToast.alert(data.msg,{bgColor:data.code===0?bgSuccess:bgDanger,position:Toast_Pos_Center});
                    Zap.reload();
                }
            });
        });
    }

    function updateSelectedCount(){
        var n = $('.zap_catalog:checked').length;
        $('#selectedCount').text('已选 ' + n + ' 项');
    }

    function addOrEdit(cid, pid){
        cid = cid || 0;
        pid = pid || 0;
        m = ZapModal.create({
            id:'addCatalog',
            title: cid ? '修改栏目' : (pid ? '添加子栏目' : '添加栏目'),
            content:ZapModal.loadding(),
            backdrop:false,
            url:'<?php echo Url::action("Catalog@form");?>?modalId=addCatalog&pid='+pid+'&cid='+cid,
            buttons:[{close:true,title:"关闭"},{title:"保存",class:'btn-success'}],
            btn2:function (){
                $.ajax({
                    url:'<?php echo Url::action("Catalog@saveCatalog");?>',
                    method:'post',
                    data:$('#addCatalog form').serialize(),
                    success:function (data){
                        ZapToast.alert(data.msg,{bgColor:data.code===0?bgSuccess:bgDanger});
                        Zap.reload();
                    }
                }).always(function(){
                    m.hide();
                })
            }
        },true)
        m.show();
    }

</script>
