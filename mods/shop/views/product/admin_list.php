<?php
!IS_AJAX && $this->layout('layouts/common');
$this->view->page_title  = '产品管理';
$this->view->breadcrumbs = [
    ['title' => '控制台', 'url' => url_action('Index')],
    ['title' => '商城', 'url' => url_action('mod/shop/product')],
    ['title' => '产品管理'],
];
?>
<div class="container-fluid p-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">产品管理</h4>
        <a href="<?= url_action('mod/shop/product/edit') ?>" class="btn btn-primary">
            <i class="fa fa-plus"></i> 添加产品
        </a>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:60px">ID</th>
                        <th>产品名称</th>
                        <th style="width:100px">价格</th>
                        <th style="width:80px">库存</th>
                        <th style="width:80px">状态</th>
                        <th style="width:90px">排序</th>
                        <th style="width:150px">操作</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($list)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">暂无产品数据</td></tr>
                <?php else: ?>
                    <?php foreach ($list as $row): ?>
                    <tr>
                        <td><?= $row['id'] ?></td>
                        <td>
                            <div class="d-flex align-items-center">
                                <?php if (!empty($row['image'])): ?>
                                <img src="<?= htmlspecialchars($row['image']) ?>"
                                     class="rounded me-2" style="width:40px;height:40px;object-fit:cover">
                                <?php endif; ?>
                                <span><?= htmlspecialchars($row['title']) ?></span>
                            </div>
                        </td>
                        <td class="text-danger fw-semibold">¥<?= number_format($row['price'], 2) ?></td>
                        <td><?= (int) $row['stock'] ?></td>
                        <td>
                            <span class="badge bg-<?= $row['status'] ? 'success' : 'secondary' ?>">
                                <?= $row['status'] ? '上架' : '下架' ?>
                            </span>
                        </td>
                        <td><?= $row['sort'] ?></td>
                        <td>
                            <a href="<?= url_action('mod/shop/product/edit/' . (int) $row['id']) ?>"
                               class="btn btn-sm btn-outline-primary">编辑</a>
                            <a href="<?= url_action('mod/shop/product/delete/' . (int) $row['id']) ?>"
                               class="btn btn-sm btn-outline-danger"
                               onclick="return confirm('确认删除？')">删除</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($totalPages > 1): ?>
    <nav class="mt-3">
        <ul class="pagination justify-content-center">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
            </li>
            <?php endfor; ?>
        </ul>
    </nav>
    <?php endif; ?>
</div>
