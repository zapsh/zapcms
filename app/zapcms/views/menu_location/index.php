<?php

use zap\facades\Url;

IS_AJAX !== true && $this->layout('layouts/common');
?>

<div class="card card-outline card-success">
    <div class="card-header p-2 d-flex align-items-center">
        <h6 class="card-title mb-0">
            <i class="fa fa-bars me-1 text-success"></i>菜单位置
        </h6>
        <div class="card-tools ms-auto">
            <div class="d-flex gap-2 align-items-center">
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="location.href='<?php echo Url::action('Catalog'); ?>'">
                    <i class="fa fa-arrow-left me-1"></i>栏目管理
                </button>
                <button type="button" class="btn btn-success btn-sm" onclick="addOrEdit(0)">
                    <i class="fa fa-plus me-1"></i>添加位置
                </button>
            </div>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr class="table-light">
                        <th>名称</th>
                        <th>标识 (key)</th>
                        <th class="d-none d-md-table-cell">描述</th>
                        <th style="width:70px">排序</th>
                        <th style="width:90px">类型</th>
                        <th style="width:140px">操作</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($locations as $loc): ?>
                    <tr>
                        <td><?php echo e($loc['title']); ?></td>
                        <td><code><?php echo e($loc['key']); ?></code></td>
                        <td class="text-muted small d-none d-md-table-cell"><?php echo e($loc['description'] ?? ''); ?></td>
                        <td><?php echo e($loc['sort_order']); ?></td>
                        <td>
                            <?php if (!empty($loc['is_system'])): ?>
                                <span class="badge text-bg-secondary">系统</span>
                            <?php else: ?>
                                <span class="badge text-bg-light">自定义</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button type="button" class="btn btn-outline-info btn-sm me-1" onclick="addOrEdit(<?php echo e($loc['id']); ?>)">
                                <i class="fa fa-pen"></i>
                            </button>
                            <?php if (empty($loc['is_system'])): ?>
                                <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeRow(<?php echo e($loc['id']); ?>)">
                                    <i class="fa fa-trash"></i>
                                </button>
                            <?php else: ?>
                                <button type="button" class="btn btn-outline-danger btn-sm" disabled title="系统内置位置不可删除">
                                    <i class="fa fa-trash"></i>
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer small text-muted">
        新增自定义位置后，可在「栏目管理」的显示位置中勾选，并通过模板函数
        <code>catalog_menu('key')</code> 在前台任意区域输出该位置的导航。
    </div>
</div>

<script>
    function addOrEdit(id){
        var url = '<?php echo Url::action("MenuLocation@form"); ?>' + (id ? '?id=' + id : '');
        var m = ZapModal.create({
            id:'menuLocationModal',
            title: id ? '编辑位置' : '添加位置',
            url: url,
            size:'modal-md',
            buttons:[
                {close:true,title:'取消'},
                {title:'保存',class:'btn-success'}
            ],
            btn2:function(){ saveLocation(m); }
        }, true);
        m.show();
    }

    var saveLocation = function (m) {
        var $btn = $('#menuLocationModal .btn-success');
        var title = $.trim($('#menuLocationForm [name="title"]').val());
        if(!title){
            Swal.fire({icon:'warning',title:'请填写位置名称'});
            return;
        }
        $btn.prop('disabled', true).text('保存中...');
        $.ajax({
            url:'<?php echo Url::action("MenuLocation@save"); ?>',
            method:'post',
            data:$('#menuLocationForm').serialize(),
            success:function (data){
                $btn.prop('disabled', false).text('保存');
                if(data.code !== 0){
                    Swal.fire({icon:'error',title:'保存失败',text:data.msg});
                    return;
                }
                ZapToast.alert(data.msg,{bgColor:bgSuccess,position:Toast_Pos_Center,delay:1200,callback:function(){ location.reload();}});
                m.hide();
            },
            error:function(xhr){
                $btn.prop('disabled', false).text('保存');
                Swal.fire({icon:'error',title:'保存失败',text:xhr.statusText});
            }
        });
    };

    function removeRow(id){
        Swal.fire({
            title:'确定删除该位置？',
            text:'已勾选该位置的栏目不会被删除，仅不再归属此位置。',
            icon:'warning',
            showCancelButton:true,
            confirmButtonText:'删除',
            cancelButtonText:'取消',
            confirmButtonColor:'#d33'
        }).then(function (result){
            if(!result.isConfirmed) return;
            $.ajax({
                url:'<?php echo Url::action("MenuLocation@remove"); ?>',
                method:'post',
                data:{id:id},
                success:function (data){
                    ZapToast.alert(data.msg,{bgColor:data.code===0?bgSuccess:bgDanger,position:Toast_Pos_Center});
                    if(data.code===0){ location.reload(); }
                }
            });
        });
    }
</script>
