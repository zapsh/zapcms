<?php
use zap\facades\Url;

!IS_AJAX && $this->layout('layouts/common');
$permCount = count($data);
?>

<!--begin::Section Pills-->
<ul class="nav nav-pills zap-section-pills mb-3">
    <li class="nav-item">
        <a class="nav-link" href="<?php echo url_action('User@index'); ?>">
            <i class="fa fa-users me-1"></i>用户管理
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="<?php echo url_action('User@roles'); ?>">
            <i class="fa fa-user-gear me-1"></i>角色
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link active" href="<?php echo url_action('User@permissions'); ?>">
            <i class="fa fa-shield-halved me-1"></i>权限
        </a>
    </li>
</ul>
<!--end::Section Pills-->

<!--begin::Permission Table Card-->
<div class="row">
    <div class="col-12">
        <div class="card mb-4">
            <div class="card-header d-flex align-items-center flex-wrap gap-2">
                <h3 class="card-title flex-grow-1">
                    <i class="fa fa-shield-halved card-header-icon text-danger"></i> 权限列表
                    <span class="badge text-bg-danger ms-2"><?php echo e($permCount); ?></span>
                </h3>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-success" onclick="addOrEdit(0)">
                        <i class="fa fa-plus me-1"></i>添加权限
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
                                        <input class="form-check-input" type="checkbox" onclick="checkAll(this)"/>
                                    </th>
                                    <th>权限名称</th>
                                    <th class="d-none d-md-table-cell">描述</th>
                                    <th class="d-none d-lg-table-cell">修改时间</th>
                                    <th class="text-center" style="width: 140px;">操作</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (empty($data)): ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">
                                    <i class="fa fa-inbox fs-1 d-block mb-2"></i>暂无偿限
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($data as $item):
                                $level = intval($item['level']);
                                // extras：子权限定义（JSON：{key: 标签}），解析失败忽略
                                $extrasList = [];
                                if (!empty($item['extras'])) {
                                    $decoded = json_decode($item['extras'], true);
                                    if (is_array($decoded)) {
                                        $extrasList = $decoded;
                                    }
                                }
                            ?>
                            <tr>
                                <td class="text-center">
                                    <input name="data[<?php echo e($item['perm_id']); ?>][perm_id]"
                                           value="<?php echo e($item['perm_id']); ?>"
                                           class="form-check-input zap_catalog" type="checkbox"/>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center flex-wrap gap-1" style="padding-left:<?php echo $level * 20; ?>px;">
                                        <?php if ($level > 0): ?>
                                        <span class="text-muted me-2">&#9492;</span>
                                        <?php endif; ?>
                                        <span class="fw-semibold"><?php echo htmlspecialchars($item['title']); ?></span>
                                        <small class="text-muted ms-2">ID:<?php echo e($item['perm_id']); ?></small>
                                        <?php foreach ($extrasList as $xKey => $xLabel): ?>
                                        <span class="badge text-bg-light border text-secondary small"
                                              title="子权限 <?php echo e((string)$xKey); ?>"><?php echo e((string)$xLabel); ?></span>
                                        <?php endforeach; ?>
                                        <?php if (!empty($item['perm_key'])): ?>
                                        <code class="ms-1 small"><?php echo htmlspecialchars($item['perm_key']); ?></code>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="d-none d-md-table-cell text-muted">
                                    <?php echo htmlspecialchars($item['description']) ?: '-'; ?>
                                </td>
                                <td class="d-none d-lg-table-cell text-muted">
                                    <?php echo date('Y-m-d H:i', $item['updated_at']); ?>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-outline-primary"
                                                onclick="addOrEdit(<?php echo e($item['perm_id']); ?>,<?php echo e($item['pid']); ?>)" title="编辑">
                                            <i class="fa fa-edit"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-success"
                                                onclick="addOrEdit(0,<?php echo e($item['perm_id']); ?>)" title="添加子权限">
                                            <i class="fa fa-plus"></i>
                                        </button>
                                    </div>
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
<!--end::Permission Table Card-->

<script>
    $(function (){
        Zap.EnableToolTip();
    })

    function remove(){
        const checkedList = $('.zap_catalog:checked').serialize();
        if(checkedList.length === 0){
            ZapToast.alert('请选择需要删除的权限',{bgColor:bgWarning,position:Toast_Pos_Center});
            return;
        }
        Swal.fire({
            title:'确定删除选中的权限？',
            text:'将同时清除各角色对该权限的授权，且不可恢复！',
            icon:'warning',
            showCancelButton:true,
            confirmButtonText:'删除',
            cancelButtonText:'取消',
            confirmButtonColor:'#dc3545',
            reverseButtons:true
        }).then(function (r) {
            if (!r.isConfirmed) { return; }
            $.ajax({
                url:'<?php echo Url::action("User@removePermission");?>',
                method:'post',
                data:checkedList,
                success:function (data){
                    ZapToast.alert(data.msg,{bgColor:data.code===0?bgSuccess:bgDanger,position:Toast_Pos_Center});
                    Zap.reload();
                }
            })
        })
    }

    function addOrEdit(id,pid){
        if(pid===undefined){
            pid = 0;
        }
        m = ZapModal.create({
            id:'addPermission',
            title: (id ? '修改' : '添加') + '权限',
            content:ZapModal.loadding(),
            backdrop:false,
            url:'<?php echo Url::action("User@formPermission");?>?id='+ id + '&pid='+pid,
            buttons:[{close:true,title:"关闭"},{title:"保存",class:'btn-success'}],
            btn2:function (){
                $.ajax({
                    url:'<?php echo Url::action("User@savePermission");?>',
                    method:'post',
                    data:$('#addPermission form').serialize(),
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
