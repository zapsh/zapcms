<?php

use zap\facades\Url;

IS_AJAX !== true && $this->layout('layouts/common');
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
                    <tbody>
                    <?php if (empty($menu)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="fa fa-inbox" style="font-size:2rem;display:block;"></i>
                                <small>暂无栏目数据</small>
                            </td>
                        </tr>
                    <?php else: ?>
                    <?php
                    $menu->forEachAll(function ($admin_menu) {
                        $level = $admin_menu['level'] -1;
                        $paddingLeft = $level * 1.5;
                        ?>
                        <tr class="catalog-row" data-level="<?php echo $level > 0 ? $level : ''; ?>" data-id="<?php echo e($admin_menu['id']); ?>">
                            <td class="text-center">
                                <input name="catalog[<?php echo e($admin_menu['id']); ?>][id]"
                                       value="<?php echo e($admin_menu['id']); ?>"
                                       class="form-check-input zap_catalog" type="checkbox" onchange="updateSelectedCount()"/>
                            </td>
                            <td>
                                <input name="catalog[<?php echo e($admin_menu['id']); ?>][sort_order]"
                                       value="<?php echo e($admin_menu['sort_order']); ?>"
                                       class="form-control form-control-sm sort-input"
                                       data-bs-toggle="tooltip" data-bs-placement="right"
                                       data-bs-title="数值越小越靠前" />
                            </td>
                            <td class="align-middle title-cell">
                                <div class="catalog-title" style="padding-left:<?php echo e($paddingLeft); ?>rem;">
                                    <i class="<?php echo e($admin_menu['icon']); ?> menu-icon"></i>
                                    <input name="catalog[<?php echo e($admin_menu['id']); ?>][title]"
                                           value="<?php echo e($admin_menu['title']); ?>"
                                           class="d-inline form-control form-control-sm title-input"/>
                                    <small class="menu-id d-none d-sm-inline">#<?php echo e($admin_menu['id']); ?></small>
                                    <?php if($admin_menu['node_type']=='link-url'): ?>
                                    <i class="fa fa-external-link-alt text-muted" title="快捷链接"></i>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="align-middle d-none d-md-table-cell">
                                <small class="text-muted seo-cell"><?php echo $admin_menu['slug'] === '--zap-link-url' ? $admin_menu['link_to'] : $admin_menu['slug']; ?></small>
                            </td>
                            <td class="align-middle d-none d-lg-table-cell">
                                <span class="badge text-bg-light"><?php echo \zapcms\services\NodeType::getTitle($admin_menu['node_type']); ?></span>
                            </td>
                            <td class="align-middle text-center">
                                <div class="btn-group btn-group-sm" role="group">
                                    <button type="button" class="btn btn-outline-secondary" title="编辑" onclick="addOrEdit(<?php echo e($admin_menu['id']); ?>,0)">
                                        <i class="fa fa-edit"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary" title="添加子栏目" onclick="addOrEdit(0,<?php echo e($admin_menu['id']); ?>)">
                                        <i class="fa fa-plus"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-danger" title="删除" onclick="removeOne(<?php echo e($admin_menu['id']); ?>)">
                                        <i class="fa fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php
                    });
                    ?>
                    <?php endif; ?>
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
    $(function (){
        Zap.EnableToolTip();
        updateSelectedCount();
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
                Zap.reload();
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
