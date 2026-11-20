<?php 
use zap\facades\Url; 


?>
<?php $this->layout('layouts/common'); ?>
<div class="card">
    <div class="card-header d-flex flex-wrap gap-2 align-items-center justify-content-between">
        <form class="row g-2" method="get" action="<?= Url::action('Node@types')?>">
            <div class="col-auto">
                <input type="text" name="search" class="form-control form-control-sm"
                       placeholder="搜索标识 / 标题" value="<?=e($search ?? '')?>">
            </div>
            <div class="col-auto">
                <select name="status" class="form-select form-select-sm">
                    <option value="">全部状态</option>
                    <option value="1" <?=($status ?? '') === '1' ? 'selected' : ''?>>启用</option>
                    <option value="0" <?=($status ?? '') === '0' ? 'selected' : ''?>>禁用</option>
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-search"></i> 搜索
                </button>
            </div>
            <div class="col-auto">
                <a href="<?= Url::action('Node/types')?>" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-repeat"></i> 重置
                </a>
            </div>
        </form>
        <div class="d-flex gap-2 ms-auto">
            <a href="<?= Url::action('Node/typesConfig')?>" class="btn btn-outline-secondary btn-sm">
                <i class="fa fa-sliders-h"></i> 显示设置
            </a>
            <a href="<?= Url::action('Node/typesForm')?>" class="btn btn-primary btn-sm">
                <i class="fa fa-plus"></i> 添加模型
            </a>
            <a href="<?= Url::action('Node')?>" class="btn btn-primary btn-sm">
                <i class="fa fa-arrow-left"></i> 内容管理
            </a>
        </div>
    </div>

    <?php if (empty($data)): ?>
    <div class="card-body text-center py-5 text-muted">
        <i class="bi bi-inbox" style="font-size:3rem;display:block;"></i>
        <p class="mt-2">暂无内容模型数据，点击「添加模型」开始创建。</p>
    </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover table-sm mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th style="width:60px">ID</th>
                    <th>类型标识</th>
                    <th>标题</th>
                    <th>描述</th>
                    <th>处理类</th>
                    <th class="text-center" style="width:80px">字段</th>
                    <th class="text-center">排序</th>
                    <th class="text-center" style="width:80px">状态</th>
                    <th class="text-end" style="width:150px">操作</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($data as $row): ?>
                <tr>
                    <td><?=$row['type_id']?></td>
                    <td><code><?=e($row['type_name'])?></code></td>
                    <td><strong><?=e($row['title'])?></strong></td>
                    <td class="text-muted small"><?=e(mb_strlen($row['description'] ?? '') > 30 ? mb_substr($row['description'], 0, 30) . '…' : ($row['description'] ?? ''))?></td>
                    <td><small class="text-monospace text-muted"><?php
                        $handler = trim((string)($row['node_type'] ?? ''));
                        echo e($handler !== '' ? $handler : '默认（AbstractNodeType）');
                    ?></small></td>
                    <td class="text-center">
                        <a href="<?= Url::action('Node/typesForm', ['id' => $row['type_id']])?>#fields"
                           class="badge text-decoration-none bg-<?=($fieldCount[$row['type_id']] ?? 0) ? 'info' : 'light text-dark border'?>"
                           title="配置该模型的自定义字段">
                            <?=$fieldCount[$row['type_id']] ?? 0?> 项
                        </a>
                    </td>
                    <td class="text-center"><?=$row['sort_order']?></td>
                    <td class="text-center">
                        <span class="badge bg-<?=$row['status'] ? 'success' : 'secondary'?> type-status"
                              style="cursor:pointer;" title="点击切换状态"
                              data-id="<?=$row['type_id']?>" data-status="<?=$row['status'] ? 0 : 1?>">
                            <?=$row['status'] ? '启用' : '禁用'?>
                        </span>
                    </td>
                    <td class="text-end">
                        <a href="<?= Url::action('Node/typesForm', ['id' => $row['type_id']])?>"
                           class="btn btn-outline-primary btn-sm" title="编辑">
                            <i class="fa fa-pencil"></i> 编辑
                        </a>
                        <button type="button" class="btn btn-outline-danger btn-sm"
                                onclick="deleteType(<?=$row['type_id']?>, '<?=e($row['title'])?>')" title="删除">
                            <i class="fa fa-trash"></i> 删除
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<script>
function deleteType(id, title) {
    Swal.fire({
        title: '确认删除模型？',
        text: '「' + title + '」及其字段配置将被删除，此操作不可恢复。',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: '确认删除',
        cancelButtonText: '取消'
    }).then(function(result) {
        if (!result.isConfirmed) return;

        var formData = new FormData();
        formData.append('id', id);

        fetch('<?= Url::action('Node/typesDelete')?>', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            if (res.code === 0) {
                location.reload();
            } else {
                Swal.fire({ icon: 'error', title: '删除失败', text: res.msg || '未知错误' });
            }
        })
        .catch(function() {
            Swal.fire({ icon: 'error', title: '网络错误', text: '请重试' });
        });
    });
}

// 点击状态徽标快速切换启用/禁用
document.querySelectorAll('.type-status').forEach(function(el) {
    el.addEventListener('click', function() {
        var id = this.dataset.id;
        var status = this.dataset.status;
        var formData = new FormData();
        formData.append('id', id);
        formData.append('status', status);

        fetch('<?= Url::action('Node/typesStatus')?>', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            if (res.code === 0) {
                Swal.fire({ icon: 'success', title: res.msg || '操作成功', showConfirmButton: false, timer: 1000, toast: true, position: 'top-end' });
                location.reload();
            } else {
                Swal.fire({ icon: 'error', title: '操作失败', text: res.msg || '未知错误' });
            }
        })
        .catch(function() {
            Swal.fire({ icon: 'error', title: '网络错误', text: '请重试' });
        });
    });
});
</script>
