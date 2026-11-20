<?php

use zap\facades\Url;

IS_AJAX !== true && $this->layout('layouts/common');

?>
<div class="card card-outline card-info">
    <div class="card-header p-2 d-flex align-items-center">
        <h6 class="card-title mb-0">
            <i class="fa fa-tags me-1 text-info"></i>标签列表
            <span class="badge text-bg-info ms-1 rounded-pill"><?php echo e($total); ?></span>
        </h6>
        <div class="card-tools ms-auto">
            <div class="d-flex gap-2 align-items-center">
                <input type="text" class="form-control form-control-sm" id="tagSearch"
                       placeholder="搜索标签" value="<?php echo e($search); ?>"
                       onkeydown="if(event.key==='Enter'){event.preventDefault();loadTags();}">
                <button type="button" class="btn btn-outline-info btn-sm" onclick="loadTags()">
                    <i class="fa fa-search"></i>
                </button>
                <button type="button" class="btn btn-info btn-sm" onclick="addOrEdit(0)">
                    <i class="fa fa-plus me-1"></i>添加标签
                </button>
            </div>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-striped text-nowrap align-middle mb-0">
                <thead>
                    <tr class="table-light">
                        <th style="width:60px">ID</th>
                        <th>标签名称</th>
                        <th>别名(slug)</th>
                        <th style="width:90px" class="text-center">使用次数</th>
                        <th style="width:140px" class="text-center">操作</th>
                    </tr>
                </thead>
                <tbody id="tagBody">
                    <?php if (empty($rows)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-5 text-muted">
                                <i class="fa fa-inbox me-1"></i>暂无标签
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $row): ?>
                            <tr data-id="<?php echo e($row['id']); ?>">
                                <td class="text-center text-muted"><?php echo e($row['id']); ?></td>
                                <td><?php echo e($row['title']); ?></td>
                                <td><code><?php echo e($row['slug']); ?></code></td>
                                <td class="text-center">
                                    <span class="badge text-bg-light"><?php echo e($row['usage_count']); ?></span>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm" role="group">
                                        <button type="button" class="btn btn-outline-secondary" title="编辑"
                                                onclick="addOrEdit(<?php echo e($row['id']); ?>)">
                                            <i class="fa fa-edit"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-danger" title="删除"
                                                onclick="removeOne(<?php echo e($row['id']); ?>)">
                                            <i class="fa fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="card-footer p-2 bg-white border-top d-flex align-items-center">
            <span class="text-muted small">共 <?php echo e($total); ?> 个标签</span>
        </div>
    </div>
</div>

<script>
    function loadTags() {
        var kw = $('#tagSearch').val().trim();
        var url = '<?php echo url_action('Tag'); ?>';
        if (kw) { url += '?search=' + encodeURIComponent(kw); }
        window.location.href = url;
    }

    function addOrEdit(id) {
        id = id || 0;
        var m = ZapModal.create({
            id: 'tagForm',
            title: id ? '编辑标签' : '添加标签',
            content: ZapModal.loadding(),
            backdrop: false,
            url: '<?php echo url_action('Tag@form'); ?>?id=' + id,
            buttons: [
                {close: true, title: '关闭'},
                {title: '保存', class: 'btn-info'}
            ],
            btn2: function () {
                $.ajax({
                    url: '<?php echo url_action('Tag@save'); ?>',
                    method: 'post',
                    data: $('#tagForm form').serialize(),
                    success: function (res) {
                        ZapToast.alert(res.msg, {bgColor: res.code === 0 ? bgSuccess : bgDanger});
                        if (res.code === 0) { Zap.reload(); }
                    }
                }).always(function () { m.hide(); });
            }
        }, true);
        m.show();
    }

    function removeOne(id) {
        var doRemove = function () {
            $.ajax({
                url: '<?php echo url_action('Tag@remove'); ?>',
                method: 'post',
                data: {tag_id: id},
                success: function (res) {
                    ZapToast.alert(res.msg, {bgColor: res.code === 0 ? bgSuccess : bgDanger});
                    if (res.code === 0) { Zap.reload(); }
                }
            });
        };
        if (typeof Swal === 'undefined') {
            if (window.confirm('确定删除该标签吗？关联内容将不再带有此标签，且不可恢复。')) { doRemove(); }
            return;
        }
        Swal.fire({
            title: '确认删除',
            text: '删除后关联内容将不再带有此标签，且不可恢复。',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: '删除',
            cancelButtonText: '取消',
            confirmButtonColor: '#dc3545'
        }).then(function (result) {
            if (result.isConfirmed) { doRemove(); }
        });
    }
</script>
