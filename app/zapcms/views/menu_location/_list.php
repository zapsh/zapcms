<?php
/**
 * 菜单位置列表片段（用于「栏目管理」页右侧抽屉）
 *
 * @var array $locations
 */
use zap\facades\Url;
?>
<div class="p-2">
    <div class="d-flex align-items-center mb-2">
        <span class="small text-muted me-auto">管理导航显示位置</span>
        <button type="button" class="btn btn-success btn-sm" onclick="mlAddOrEdit(0)">
            <i class="fa fa-plus me-1"></i>添加位置
        </button>
    </div>
    <div class="border rounded">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead>
                <tr class="table-light">
                    <th>名称</th>
                    <th>标识 (key)</th>
                    <th style="width:80px">类型</th>
                    <th style="width:90px">操作</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($locations)): ?>
                <tr>
                    <td colspan="4" class="text-center text-muted small py-3">暂无位置</td>
                </tr>
            <?php else: ?>
                <?php foreach ($locations as $loc): ?>
                    <tr>
                        <td class="small align-middle"><?php echo e($loc['title']); ?></td>
                        <td class="align-middle"><code class="small"><?php echo e($loc['key']); ?></code></td>
                        <td class="align-middle">
                            <?php if (!empty($loc['is_system'])): ?>
                                <span class="badge text-bg-secondary">系统</span>
                            <?php else: ?>
                                <span class="badge text-bg-light">自定义</span>
                            <?php endif; ?>
                        </td>
                        <td class="align-middle">
                            <button type="button" class="btn btn-outline-success btn-sm py-0 px-1" onclick="mlOpenBuilder('<?php echo e($loc['key']); ?>')" title="管理菜单项">
                                <i class="fa fa-list"></i>
                            </button>
                            <button type="button" class="btn btn-outline-info btn-sm py-0 px-1" onclick="mlAddOrEdit(<?php echo e($loc['id']); ?>)" title="编辑">
                                <i class="fa fa-pen"></i>
                            </button>
                            <?php if (empty($loc['is_system'])): ?>
                                <button type="button" class="btn btn-outline-danger btn-sm py-0 px-1" onclick="mlRemove(<?php echo e($loc['id']); ?>)" title="删除">
                                    <i class="fa fa-trash"></i>
                                </button>
                            <?php else: ?>
                                <button type="button" class="btn btn-outline-danger btn-sm py-0 px-1" disabled title="系统内置位置不可删除">
                                    <i class="fa fa-trash"></i>
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
