<?php
use zap\facades\Url;

$this->layout('layouts/common');

// 统计数据
$totalUsers  = $totalAll ?? count($users);
$activeCount = $activeCount ?? 0;
$latestUser  = $latestUser ?? '-';
?>

<!--begin::Stats Row-->
<div class="row">
    <div class="col-lg-4 col-6">
        <div class="small-box text-bg-primary">
            <div class="inner">
                <h3><?php echo e($totalUsers); ?></h3>
                <p>用户总数</p>
            </div>
            <i class="fa fa-users small-box-icon fs-1"></i>
        </div>
    </div>
    <div class="col-lg-4 col-6">
        <div class="small-box text-bg-success">
            <div class="inner">
                <h3><?php echo e($activeCount); ?></h3>
                <p>已激活用户</p>
            </div>
            <i class="fa fa-user-check small-box-icon fs-1"></i>
        </div>
    </div>
    <div class="col-lg-4 col-6">
        <div class="small-box text-bg-info">
            <div class="inner">
                <h3><?php echo e($latestUser); ?></h3>
                <p>最新注册用户</p>
            </div>
            <i class="fa fa-user-plus small-box-icon fs-1"></i>
        </div>
    </div>
</div>
<!--end::Stats Row-->

<!--begin::User Table Card-->
<div class="row">
    <div class="col-12">
        <div class="card mb-4">
            <div class="card-header d-flex align-items-center flex-wrap gap-2">
                <h3 class="card-title flex-grow-1">
                    <i class="fa fa-users card-header-icon text-primary"></i> 用户列表
                    <span class="badge text-bg-primary ms-2"><?php echo e($totalUsers); ?></span>
                </h3>
                <div class="d-flex gap-2">
                    <a href="<?php echo url_action('User@roles') ?>" class="btn btn-sm btn-outline-secondary">
                        <i class="fa fa-user-gear me-1"></i>角色
                    </a>
                    <a href="<?php echo url_action('User@permissions') ?>" class="btn btn-sm btn-outline-secondary">
                        <i class="fa fa-shield-halved me-1"></i>权限
                    </a>
                    <button type="button" class="btn btn-sm btn-success" onclick="addOrEdit(0)">
                        <i class="fa fa-plus me-1"></i>添加
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
                                        <input class="form-check-input" type="checkbox" onclick="Zap.CheckBox_CheckAll(this,'.users_list')"/>
                                    </th>
                                    <th style="width: 60px;">ID</th>
                                    <th>用户</th>
                                    <th class="d-none d-md-table-cell">姓名</th>
                                    <th class="d-none d-lg-table-cell">邮箱</th>
                                    <th class="d-none d-xl-table-cell">手机</th>
                                    <th class="text-center" style="width: 70px;">状态</th>
                                    <th class="text-center" style="width: 60px;">操作</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (empty($users)): ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">
                                    <i class="fa fa-inbox fs-1 d-block mb-2"></i>暂无用户数据
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($users as $user): ?>
                            <tr>
                                <td class="text-center">
                                    <?php if($user['id'] != 1): ?>
                                    <input name="admin[<?php echo e($user['id']); ?>][id]"
                                           value="<?php echo e($user['id']); ?>"
                                           class="form-check-input users_list" type="checkbox"/>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge text-bg-light"><?php echo e($user['id']); ?></span></td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="avatar avatar-sm bg-success text-white rounded-circle d-inline-flex align-items-center justify-content-center" style="width:32px;height:32px;font-size:0.85rem;flex-shrink:0;">
                                            <?php echo mb_substr($user['username'], 0, 1); ?>
                                        </span>
                                        <span class="fw-semibold"><?php echo htmlspecialchars($user['username']); ?></span>
                                    </div>
                                </td>
                                <td class="d-none d-md-table-cell"><?php echo htmlspecialchars($user['full_name']) ?: '<span class="text-muted">-</span>'; ?></td>
                                <td class="d-none d-lg-table-cell"><small><?php echo htmlspecialchars($user['email']) ?: '<span class="text-muted">-</span>'; ?></small></td>
                                <td class="d-none d-xl-table-cell"><small><?php echo htmlspecialchars($user['phone_number']) ?: '<span class="text-muted">-</span>'; ?></small></td>
                                <td class="text-center">
                                    <?php if($user['status'] == 'activated'): ?>
                                    <span class="badge text-bg-success rounded-pill"><i class="fa fa-check"></i></span>
                                    <?php else: ?>
                                    <span class="badge text-bg-secondary rounded-pill"><i class="fa fa-times"></i></span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="addOrEdit(<?php echo e($user['id']); ?>)" title="编辑">
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
<!--end::User Table Card-->

<script>
    $(function (){
        Zap.EnableToolTip();
    })

    function remove(){
        const checkedList = $('.users_list:checked').serialize();
        if(checkedList.length === 0){
            ZapToast.alert('请选择需要删除的用户',{bgColor:bgWarning,position:Toast_Pos_Center});
            return;
        }
        $.ajax({
            url:'<?php echo Url::action("User@remove");?>',
            method:'post',
            data:checkedList,
            success:function (data){
                ZapToast.alert(data.msg,{bgColor:data.code===0?bgSuccess:bgDanger,position:Toast_Pos_Center});
                Zap.reload();
            }
        })
    }

    function addOrEdit(id){
        m = ZapModal.create({
            id:'addOrEditUser',
            title: (id ? '修改' : '添加') + '系统管理员',
            content:ZapModal.loadding(),
            backdrop:false,
            url:'<?php echo Url::action("User@form");?>?id='+id,
            buttons:[{close:true,title:"关闭"},{title:"保存",class:'btn-success'}],
            btn2:function (){
                $.ajax({
                    url:'<?php echo Url::action("User@saveUser");?>',
                    method:'post',
                    data:$('#addOrEditUser form').serialize(),
                    success:function (data){
                        m.hide();
                        ZapToast.alert(data.msg,{bgColor:data.code===0?bgSuccess:bgDanger,position:Toast_Pos_Center});
                        if(data.code===0){
                            setTimeout(function(){
                                Zap.reload();
                            },800);
                        }
                    }
                })
            }
        },true)
        m.show();
    }
</script>
