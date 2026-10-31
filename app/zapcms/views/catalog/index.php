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
                <button type="button" class="btn btn-success btn-sm" onclick="addOrEdit(0)">
                    <i class="fa fa-plus me-1"></i>添加
                </button>
            </div>
        </div>
    </div>
    <div class="card-body p-0">
        <script>
            function checkAll(el) {
                $('.zap_catalog').prop('checked', $(el).prop('checked'));
            }
        </script>
        <form action="post" id="reqForm">
            <div class="table-responsive">
                <table class="table table-hover table-striped text-nowrap mb-0">
                    <thead>
                        <tr class="table-light">
                            <th scope="col" style="width:40px" class="text-center align-middle">
                                <input class="form-check-input" type="checkbox" onclick="checkAll(this)"/>
                            </th>
                            <th scope="col" style="width:60px">排序</th>
                            <th scope="col" class="w-100">栏目名称</th>
                            <th scope="col" class="d-none d-md-table-cell">SEO</th>
                            <th scope="col" class="d-none d-lg-table-cell">模型</th>
                            <th scope="col" style="width:70px">操作</th>
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
                        <tr class="catalog-row" data-level="<?php echo $level > 0 ? $level : ''; ?>">
                            <td class="text-center align-middle">
                                <input name="catalog[<?php echo e($admin_menu['id']); ?>][id]"
                                       value="<?php echo e($admin_menu['id']); ?>"
                                       class="form-check-input zap_catalog" type="checkbox"/>
                            </td>
                            <td class="align-middle">
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
                            <td class="align-middle">
                                <button type="button" class="btn btn-outline-success btn-sm" onclick="addOrEdit(<?php echo e($admin_menu['id']); ?>)">
                                    <i class="fa fa-cog"></i>
                                </button>
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
                <small class="text-muted ms-2">勾选表格中的栏目后，点击此处删除</small>
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

    function remove(){
        const checkedCatalog = $('.zap_catalog:checked').serialize();
        if(checkedCatalog.length === 0){
            ZapToast.alert('请选择需要删除的栏目',{bgColor:bgWarning,position:Toast_Pos_Center});
            return;
        }
        $.ajax({
            url:'<?php echo Url::action("Catalog@remove");?>',
            method:'post',
            data:checkedCatalog,
            success:function (data){
                ZapToast.alert(data.msg,{bgColor:data.code===0?bgSuccess:bgDanger,position:Toast_Pos_Center});
                Zap.reload();
            }
        })
    }

    function addOrEdit(cid){
        m = ZapModal.create({
            id:'addCatalog',
            title: (cid ? '修改' : '添加') + '栏目',
            content:ZapModal.loadding(),
            backdrop:false,
            url:'<?php echo Url::action("Catalog@form");?>?modalId=addCatalog&pid=0&cid='+cid,
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
