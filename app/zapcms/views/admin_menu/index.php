<?php
use zap\facades\Url;

!IS_AJAX && $this->layout('layouts/common');
$this->view->page_title = '系统菜单管理';
?>
<script>
    function checkAll(el) {
        $('.zap_catalog').prop('checked', $(el).prop('checked'));
    }
</script>

<div class="row mb-3">
    <div class="col-12 d-flex align-items-center">
        <h2 class="fs-5 fw-bold mb-0">
            <i class="fa fa-bars me-2 text-info"></i>菜单列表
        </h2>
        <div class="ms-auto">
            <button type="button" class="btn btn-danger btn-sm me-2" onclick="remove()">
                <i class="fa fa-trash me-1"></i>删除选中
            </button>
            <button type="button" class="btn btn-success btn-sm me-2" onclick="save()">
                <i class="fa fa-save me-1"></i>保存
            </button>
            <button type="button" class="btn btn-info btn-sm" onclick="addOrEdit(0)">
                <i class="fa fa-plus me-1"></i>添加顶级菜单
            </button>
        </div>
    </div>
</div>

<form id="reqForm">
    <div class="table-responsive">
        <table class="table table-hover text-nowrap table-sm border">
            <thead>
                <tr class="table-secondary">
                    <th style="width:40px">
                        <input class="form-check-input" type="checkbox" onclick="checkAll(this)" title="全选"/>
                    </th>
                    <th style="width:60px">排序</th>
                    <th class="w-100">菜单名称</th>
                    <th>链接</th>
                    <th style="width:110px">操作</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $menu->forEachAll(function ($admin_menu) {
                $paddingLeft = $admin_menu['level'] <= 1 ? 0 : ($admin_menu['level'] - 1) * 1.5;
                ?>
                <tr>
                    <td>
                        <input name="admin_menu[<?php echo e($admin_menu['id']); ?>][id]"
                               value="<?php echo e($admin_menu['id']); ?>"
                               class="form-check-input zap_catalog" type="checkbox"/>
                    </td>
                    <td>
                        <input name="admin_menu[<?php echo e($admin_menu['id']); ?>][sort_order]"
                               value="<?php echo e($admin_menu['sort_order']); ?>"
                               class="form-control form-control-sm" size="2"
                               data-bs-toggle="tooltip" data-bs-placement="right"
                               data-bs-title="数值越小越靠前"/>
                    </td>
                    <td>
                        <div style="padding-left:<?php echo e($paddingLeft); ?>rem;">
                            <i class="<?php echo e($admin_menu['icon']); ?> me-1"
                               onclick="ZapFaIcons(['#ami-<?php echo e($admin_menu['id']); ?>','#amii-<?php echo e($admin_menu['id']); ?>']);"
                               id="ami-<?php echo e($admin_menu['id']); ?>"></i>
                            <input name="admin_menu[<?php echo e($admin_menu['id']); ?>][icon]" type="hidden"
                                   value="<?php echo e($admin_menu['icon']); ?>"
                                   id="amii-<?php echo e($admin_menu['id']); ?>"/>
                            <input name="admin_menu[<?php echo e($admin_menu['id']); ?>][title]"
                                   value="<?php echo e($admin_menu['title']); ?>"
                                   class="d-inline form-control form-control-sm w-auto"/>
                            <small class="text-muted ms-1">ID:<?php echo e($admin_menu['id']); ?></small>
                        </div>
                    </td>
                    <td class="text-muted"><?php echo e($admin_menu['link_to']); ?></td>
                    <td>
                        <button type="button" class="btn btn-outline-success btn-sm me-1"
                                onclick="addOrEdit(<?php echo e($admin_menu['id']); ?>)"
                                data-bs-toggle="tooltip" data-bs-title="添加子菜单">
                            <i class="fa fa-plus"></i>
                        </button>
                        <button type="button" class="btn btn-outline-info btn-sm"
                                onclick="addOrEdit(<?php echo $admin_menu['pid'],',',$admin_menu['id'];?>)"
                                data-bs-toggle="tooltip" data-bs-title="编辑">
                            <i class="fa fa-pen"></i>
                        </button>
                    </td>
                </tr>
                <?php
            });
            ?>
            </tbody>
        </table>
    </div>
</form>

<script>
    $(function (){
        Zap.EnableToolTip();
    })

    function save(){
        $.ajax({
            url:'<?php echo Url::action("AdminMenu@save");?>',
            method:'post',
            data:$('#reqForm').serialize(),
            success:function (data){
                ZapToast.alert(data.msg,{
                    bgColor:data.code===0?bgSuccess:bgDanger,
                    position:Toast_Pos_Center,
                    delay: 2000,
                    callback:function(){ location.reload();}
                });
            }
        })
    }

    function remove(){
        const checkedCatalog = $('.zap_catalog:checked').serialize();
        if(checkedCatalog.length === 0){
            ZapToast.alert('请选择需要删除的菜单',{bgColor:bgWarning,position:Toast_Pos_Center});
            return;
        }
        $.ajax({
            url:'<?php echo Url::action("AdminMenu@remove");?>',
            method:'post',
            data:checkedCatalog,
            success:function (data){
                Zap.reload()
                ZapToast.alert(data.msg,{bgColor:data.code===0?bgSuccess:bgDanger,position:Toast_Pos_Center});
            }
        })
    }

    function addOrEdit(pid,menuId){
        var formUrl = '<?php echo Url::action("AdminMenu@form");?>?modalId=adminMenu&pid='+pid;
        if(menuId!==undefined){
            formUrl += '&id='+menuId;
        }
        const m = ZapModal.create({
            id:'adminMenu',
            title: menuId===undefined ? '添加菜单' : '修改菜单',
            content:ZapModal.loadding(),
            backdrop:false,
            url: formUrl,
            buttons:[{close:true,title:"关闭"},{title:"保存",class:'btn-success'}],
            btn2:function (){
                $.ajax({
                    url:'<?php echo Url::action("AdminMenu@saveAdminMenu");?>',
                    method:'post',
                    data:$('#adminMenu form').serialize(),
                    success:function (data){
                        ZapToast.alert(data.msg,{
                            bgColor:data.code===0?bgSuccess:bgDanger,
                            position:Toast_Pos_Center,
                            delay: 2000,
                            callback:function(){ location.reload();}
                        });
                        document.activeElement && document.activeElement.blur();
                        m.hide();
                    },
                    error:function(xhr){
                        ZapToast.alert('保存失败：' + xhr.statusText,{bgColor:bgDanger,position:Toast_Pos_Center});
                    }
                })
            }
        },true)
        m.show();
    }
</script>
