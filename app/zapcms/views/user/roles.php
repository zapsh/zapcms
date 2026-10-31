<?php
use zap\facades\Url;

$this->layout('layouts/common');
$roleCount = count($data);
?>

<!--begin::Role Table Card-->
<div class="row">
    <div class="col-12">
        <div class="card mb-4">
            <div class="card-header d-flex align-items-center flex-wrap gap-2">
                <h3 class="card-title flex-grow-1">
                    <i class="fa fa-user-gear card-header-icon text-warning"></i> 角色列表
                    <span class="badge text-bg-warning ms-2"><?php echo e($roleCount); ?></span>
                </h3>
                <div class="d-flex gap-2">
                    <a href="<?php echo url_action('User@permissions') ?>" class="btn btn-sm btn-outline-secondary">
                        <i class="fa fa-shield-halved me-1"></i>权限管理
                    </a>
                    <button type="button" class="btn btn-sm btn-success" onclick="addOrEdit(0)">
                        <i class="fa fa-plus me-1"></i>添加角色
                    </button>
                </div>
            </div>
            <div class="card-body p-0">
                <form id="reqForm">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="text-center" style="width: 40px;">
                                        <input class="form-check-input" type="checkbox" onclick="Zap.CheckBox_CheckAll(this,'.roles_list')"/>
                                    </th>
                                    <th>角色名称</th>
                                    <th class="d-none d-md-table-cell">简介</th>
                                    <th class="d-none d-lg-table-cell">修改时间</th>
                                    <th class="d-none d-lg-table-cell">创建时间</th>
                                    <th class="text-center" style="width: 80px;">操作</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (empty($data)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">
                                    <i class="fa fa-inbox fs-1 d-block mb-2"></i>暂无角色
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($data as $item): ?>
                            <tr>
                                <td class="text-center">
                                    <?php if ($item['role_id'] != 1): ?>
                                    <input name="data[<?php echo e($item['role_id']); ?>][role_id]"
                                           value="<?php echo e($item['role_id']); ?>"
                                           class="form-check-input roles_list" type="checkbox"/>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="fw-semibold"><?php echo htmlspecialchars($item['name']); ?></span>
                                    <small class="text-muted ms-1">ID:<?php echo e($item['role_id']); ?></small>
                                </td>
                                <td class="d-none d-md-table-cell text-muted">
                                    <?php echo htmlspecialchars($item['description']) ?: '-'; ?>
                                </td>
                                <td class="d-none d-lg-table-cell text-muted">
                                    <?php echo date('Y-m-d H:i', $item['updated_at']); ?>
                                </td>
                                <td class="d-none d-lg-table-cell text-muted">
                                    <?php echo date('Y-m-d H:i', $item['created_at']); ?>
                                </td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="addOrEdit(<?php echo e($item['role_id']); ?>)" title="编辑">
                                        <i class="fa fa-edit"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="d-flex justify-content-between align-items-center p-3 flex-wrap gap-2">
                        <button type="button" class="btn btn-outline-danger btn-sm" onclick="remove()">
                            <i class="fa fa-trash me-1"></i>删除选中
                        </button>
                        <?php echo $pageHelper->render(7,'pagination justify-content-center justify-content-sm-end mb-0','page-item','page-link'); ?>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<!--end::Role Table Card-->

<script>
    $(function (){
        Zap.EnableToolTip();
    })

    function remove(){
        const checkedCatalog = $('.roles_list:checked').serialize();
        if(checkedCatalog.length === 0){
            ZapToast.alert('请选择需要删除的角色',{bgColor:bgWarning,position:Toast_Pos_Center});
            return;
        }
        $.ajax({
            url:'<?php echo Url::action("User@removeRole");?>',
            method:'post',
            data:checkedCatalog,
            success:function (data){
                ZapToast.alert(data.msg,{bgColor:data.code===0?bgSuccess:bgDanger,position:Toast_Pos_Center});
                Zap.reload();
            }
        })
    }

    function addOrEdit(id){
        m = ZapModal.create({
            id:'addRole',
            title: (id ? '修改' : '添加') + '角色',
            content:ZapModal.loadding(),
            backdrop:false,
            url:'<?php echo Url::action("User@formRole");?>?id='+id,
            buttons:[{close:true,title:"关闭"},{title:"保存",class:'btn-success'}],
            btn2:function (){
                $.ajax({
                    url:'<?php echo Url::action("User@saveRole");?>',
                    method:'post',
                    data:$('#addRole form').serialize(),
                    success:function (data){
                        ZapToast.alert(data.msg,{bgColor:data.code===0?bgSuccess:bgDanger,position:Toast_Pos_Center});
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
