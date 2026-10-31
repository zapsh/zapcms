<?php
use zap\facades\Url;
use zapcms\models\Comment;

$this->layout('layouts/common');

// 统计
$totalAll    = $totalAll ?? 0;
$pendingCnt  = $pendingCnt ?? 0;
$approvedCnt = $approvedCnt ?? 0;
$spamCnt     = $spamCnt ?? 0;
?>

<!--begin::Stats Row-->
<div class="row">
    <div class="col-lg-3 col-6">
        <div class="small-box text-bg-primary">
            <div class="inner">
                <h3><?php echo e($totalAll); ?></h3>
                <p>评论总数</p>
            </div>
            <i class="fa fa-comments small-box-icon fs-1"></i>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <a class="small-box text-bg-warning text-decoration-none" href="<?php echo url_action('Comment@index', ['status' => Comment::APPROVED_PENDING]); ?>" style="cursor:pointer">
            <div class="inner">
                <h3><?php echo e($pendingCnt); ?></h3>
                <p>待审核</p>
            </div>
            <i class="fa fa-hourglass-half small-box-icon fs-1"></i>
        </a>
    </div>
    <div class="col-lg-3 col-6">
        <a class="small-box text-bg-success text-decoration-none" href="<?php echo url_action('Comment@index', ['status' => Comment::APPROVED_YES]); ?>" style="cursor:pointer">
            <div class="inner">
                <h3><?php echo e($approvedCnt); ?></h3>
                <p>已通过</p>
            </div>
            <i class="fa fa-circle-check small-box-icon fs-1"></i>
        </a>
    </div>
    <div class="col-lg-3 col-6">
        <a class="small-box text-bg-danger text-decoration-none" href="<?php echo url_action('Comment@index', ['status' => Comment::APPROVED_SPAM]); ?>" style="cursor:pointer">
            <div class="inner">
                <h3><?php echo e($spamCnt); ?></h3>
                <p>垃圾评论</p>
            </div>
            <i class="fa fa-biohazard small-box-icon fs-1"></i>
        </a>
    </div>
</div>
<!--end::Stats Row-->

<!--begin::Comment Table Card-->
<div class="row">
    <div class="col-12">
        <div class="card mb-4">
            <div class="card-header d-flex align-items-center flex-wrap gap-2">
                <h3 class="card-title flex-grow-1">
                    <i class="fa fa-comments card-header-icon text-primary"></i> 评论列表
                    <span class="badge text-bg-primary ms-2"><?php echo e($total); ?></span>
                </h3>
                <!-- 搜索表单 -->
                <form class="d-flex flex-wrap gap-2" method="get" action="<?php echo url_action('Comment@index'); ?>">
                    <input type="text" name="keyword" value="<?php echo htmlspecialchars($keyword); ?>"
                           class="form-control form-control-sm" placeholder="搜索内容 / 作者" style="width:180px"/>
                    <select name="status" class="form-select form-select-sm" style="width:120px">
                        <option value="">全部状态</option>
                        <?php foreach ($statusOptions as $val => $label): ?>
                        <option value="<?php echo e($val); ?>" <?php echo (string)$status === (string)$val ? 'selected' : ''; ?>><?php echo e($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="object_type" class="form-select form-select-sm" style="width:130px">
                        <option value="">全部模块</option>
                        <?php foreach ($objectTypes as $val => $label): ?>
                        <option value="<?php echo e($val); ?>" <?php echo $objectType === $val ? 'selected' : ''; ?>><?php echo e($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-sm btn-outline-primary">
                        <i class="fa fa-magnifying-glass"></i>
                    </button>
                </form>
            </div>
            <div class="card-body p-0">
                <form id="reqForm">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="text-center" style="width: 40px;">
                                        <input class="form-check-input" type="checkbox" onclick="Zap.CheckBox_CheckAll(this,'.comments_list')"/>
                                    </th>
                                    <th style="width: 60px;">ID</th>
                                    <th>评论内容</th>
                                    <th style="width: 200px;">关联内容</th>
                                    <th class="text-center" style="width: 90px;">状态</th>
                                    <th style="width: 140px;">时间</th>
                                    <th class="text-center" style="width: 130px;">操作</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (empty($comments)): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">
                                    <i class="fa fa-inbox fs-1 d-block mb-2"></i>暂无评论数据
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($comments as $comment):
                                $objKey = $comment['object_type'] . ':' . $comment['object_id'];
                                $obj    = $objectInfos[$objKey] ?? null;
                                $isReply = (int)$comment['parent'] > 0;
                            ?>
                            <tr>
                                <td class="text-center">
                                    <input name="ids[]" value="<?php echo e($comment['comment_id']); ?>"
                                           class="form-check-input comments_list" type="checkbox"/>
                                </td>
                                <td><span class="badge text-bg-light"><?php echo e($comment['comment_id']); ?></span></td>
                                <td>
                                    <div class="d-flex align-items-start gap-2">
                                        <span class="avatar avatar-sm bg-info text-white rounded-circle d-inline-flex align-items-center justify-content-center" style="width:32px;height:32px;font-size:0.85rem;flex-shrink:0;margin-top:2px;">
                                            <?php echo mb_substr($comment['author'] ?: '客', 0, 1); ?>
                                        </span>
                                        <div class="min-w-0">
                                            <?php if ($isReply): ?><span class="badge text-bg-secondary me-1">回复</span><?php endif; ?>
                                            <span class="fw-semibold"><?php echo htmlspecialchars($comment['author'] ?: '游客'); ?></span>
                                            <small class="text-muted ms-2">
                                                <?php echo $comment['author_email'] ? '<i class="fa fa-envelope me-1"></i>' . htmlspecialchars($comment['author_email']) : ''; ?>
                                            </small>
                                            <?php if ($comment['author_ip']): ?>
                                            <small class="text-muted ms-2"><i class="fa fa-location-dot me-1"></i><?php echo htmlspecialchars($comment['author_ip']); ?></small>
                                            <?php endif; ?>
                                            <div class="text-break mt-1">
                                                <?php echo nl2br(htmlspecialchars($comment['content'])); ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge text-bg-light me-1"><?php echo $obj['type_title'] ?? $comment['object_type']; ?></span>
                                    <div class="text-truncate" style="max-width:150px;">
                                        <?php if ($obj['exists'] ?? true): ?>
                                        <?php echo htmlspecialchars($obj['title'] ?? "#{$comment['object_id']}"); ?>
                                        <?php else: ?>
                                        <span class="text-danger"><?php echo htmlspecialchars($obj['title'] ?? '已删除'); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <small class="text-muted">#<?php echo e($comment['object_id']); ?></small>
                                </td>
                                <td class="text-center">
                                    <span class="badge text-bg-<?php echo Comment::getStatusBadge($comment['approved']); ?> rounded-pill">
                                        <?php echo Comment::getStatusTitle($comment['approved']); ?>
                                    </span>
                                </td>
                                <td><small><?php echo date('Y-m-d H:i', (int)$comment['created_at']); ?></small></td>
                                <td class="text-center text-nowrap">
                                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="addOrEdit(<?php echo e($comment['comment_id']); ?>)" title="编辑">
                                        <i class="fa fa-edit"></i>
                                    </button>
                                    <?php if ((int)$comment['approved'] !== Comment::APPROVED_YES): ?>
                                    <button type="button" class="btn btn-sm btn-outline-success" onclick="setStatus([<?php echo e($comment['comment_id']); ?>], <?php echo Comment::APPROVED_YES; ?>)" title="通过">
                                        <i class="fa fa-check"></i>
                                    </button>
                                    <?php endif; ?>
                                    <?php if ((int)$comment['approved'] !== Comment::APPROVED_SPAM): ?>
                                    <button type="button" class="btn btn-sm btn-outline-warning" onclick="setStatus([<?php echo e($comment['comment_id']); ?>], <?php echo Comment::APPROVED_SPAM; ?>)" title="标记垃圾">
                                        <i class="fa fa-ban"></i>
                                    </button>
                                    <?php endif; ?>
                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeOne(<?php echo e($comment['comment_id']); ?>)" title="删除">
                                        <i class="fa fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="d-flex justify-content-between align-items-center p-3 flex-wrap gap-2">
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-outline-success btn-sm" onclick="setCheckedStatus(<?php echo Comment::APPROVED_YES; ?>)">
                                <i class="fa fa-check me-1"></i>通过选中
                            </button>
                            <button type="button" class="btn btn-outline-warning btn-sm" onclick="setCheckedStatus(<?php echo Comment::APPROVED_SPAM; ?>)">
                                <i class="fa fa-ban me-1"></i>标记垃圾
                            </button>
                            <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeChecked()">
                                <i class="fa fa-trash me-1"></i>删除选中
                            </button>
                        </div>
                        <?php echo $pageHelper->render(7,'pagination justify-content-center justify-content-sm-end mb-0','page-item','page-link'); ?>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<!--end::Comment Table Card-->

<script>
    $(function (){
        Zap.EnableToolTip();
    })

    /** 收集选中的评论 ID */
    function getCheckedIds(){
        return $('.comments_list:checked').map(function(){ return $(this).val(); }).get();
    }

    /** 批量设置状态 */
    function setCheckedStatus(status){
        const ids = getCheckedIds();
        if(ids.length === 0){
            ZapToast.alert('请选择评论',{bgColor:bgWarning,position:Toast_Pos_Center});
            return;
        }
        setStatus(ids, status);
    }

    /** 单条/批量设置状态 */
    function setStatus(ids, status){
        $.ajax({
            url:'<?php echo Url::action("Comment@setStatus");?>',
            method:'post',
            data:{ids:ids, status:status},
            success:function (data){
                ZapToast.alert(data.msg,{bgColor:data.code===0?bgSuccess:bgDanger,position:Toast_Pos_Center});
                if(data.code===0){
                    setTimeout(function(){ Zap.reload(); }, 600);
                }
            }
        })
    }

    /** 删除选中 */
    function removeChecked(){
        const ids = getCheckedIds();
        if(ids.length === 0){
            ZapToast.alert('请选择评论',{bgColor:bgWarning,position:Toast_Pos_Center});
            return;
        }
        if(!confirm('确定删除选中的 ' + ids.length + ' 条评论吗？')){
            return;
        }
        $.ajax({
            url:'<?php echo Url::action("Comment@remove");?>',
            method:'post',
            data:{ids:ids},
            success:function (data){
                ZapToast.alert(data.msg,{bgColor:data.code===0?bgSuccess:bgDanger,position:Toast_Pos_Center});
                if(data.code===0){
                    setTimeout(function(){ Zap.reload(); }, 600);
                }
            }
        })
    }

    /** 删除单条 */
    function removeOne(id){
        if(!confirm('确定删除这条评论吗？')){
            return;
        }
        $.ajax({
            url:'<?php echo Url::action("Comment@remove");?>',
            method:'post',
            data:{ids:[id]},
            success:function (data){
                ZapToast.alert(data.msg,{bgColor:data.code===0?bgSuccess:bgDanger,position:Toast_Pos_Center});
                if(data.code===0){
                    setTimeout(function(){ Zap.reload(); }, 600);
                }
            }
        })
    }

    /** 编辑评论弹窗 */
    function addOrEdit(id){
        const m = ZapModal.create({
            id:'editComment',
            title:'编辑评论',
            content:ZapModal.loadding(),
            backdrop:false,
            url:'<?php echo Url::action("Comment@form");?>?id='+id,
            buttons:[{close:true,title:"关闭"},{title:"保存",class:'btn-success'}],
            btn2:function (){
                $.ajax({
                    url:'<?php echo Url::action("Comment@save");?>',
                    method:'post',
                    data:$('#editComment form').serialize(),
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
