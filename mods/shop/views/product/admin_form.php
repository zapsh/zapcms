<?php
!IS_AJAX && $this->layout('layouts/common');
$this->view->page_title  = $product ? '编辑产品' : '添加产品';
$this->view->breadcrumbs = [
    ['title' => '控制台', 'url' => url_action('Index')],
    ['title' => '商城', 'url' => url_action('mod/shop/product')],
    ['title' => '产品管理', 'url' => url_action('mod/shop/product')],
    ['title' => $product ? '编辑产品' : '添加产品'],
];
?>
<div class="container-fluid p-3">
    <h4 class="mb-3"><?= $product ? '编辑产品' : '添加产品' ?></h4>

    <form method="post" action="<?= url_action('mod/shop/product/save') ?>">
        <input type="hidden" name="id" value="<?= $product['id'] ?? 0 ?>">

        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">产品名称 *</label>
                <input type="text" name="title" class="form-control"
                       value="<?= htmlspecialchars($product['title'] ?? '') ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">URL 别名</label>
                <input type="text" name="slug" class="form-control"
                       value="<?= htmlspecialchars($product['slug'] ?? '') ?>">
            </div>

            <div class="col-md-4">
                <label class="form-label">价格</label>
                <input type="number" step="0.01" name="price" class="form-control"
                       value="<?= $product['price'] ?? '0.00' ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">原价（划线）</label>
                <input type="number" step="0.01" name="origin_price" class="form-control"
                       value="<?= $product['origin_price'] ?? '0.00' ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">库存</label>
                <input type="number" name="stock" class="form-control"
                       value="<?= $product['stock'] ?? 0 ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">单位</label>
                <input type="text" name="unit" class="form-control"
                       value="<?= htmlspecialchars($product['unit'] ?? '件') ?>">
            </div>

            <div class="col-md-6">
                <label class="form-label">上传主图</label>
                <div class="input-group">
                    <input type="text" name="image" id="productImage" class="form-control"
                           value="<?= htmlspecialchars($product['image'] ?? '') ?>" placeholder="图片地址">
                    <button type="button" class="btn btn-outline-secondary" onclick="selectImage()">选择</button>
                </div>
                <div id="imagePreview" class="mt-2 <?= empty($product['image']) ? 'd-none' : '' ?>">
                    <img src="<?= htmlspecialchars($product['image'] ?? '') ?>"
                         style="max-height:120px" class="rounded border">
                </div>
            </div>

            <div class="col-md-3">
                <label class="form-label">状态</label>
                <select name="status" class="form-select">
                    <option value="1" <?= ($product['status'] ?? 1) == 1 ? 'selected' : '' ?>>上架</option>
                    <option value="0" <?= ($product['status'] ?? 1) == 0 ? 'selected' : '' ?>>下架</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">排序</label>
                <input type="number" name="sort" class="form-control"
                       value="<?= $product['sort'] ?? 0 ?>">
            </div>

            <div class="col-12">
                <label class="form-label">简介</label>
                <textarea name="summary" class="form-control" rows="3"><?= htmlspecialchars($product['summary'] ?? '') ?></textarea>
            </div>
            <div class="col-12">
                <label class="form-label">详情</label>
                <textarea name="content" class="form-control" rows="8"><?= htmlspecialchars($product['content'] ?? '') ?></textarea>
            </div>

            <div class="col-12">
                <button type="submit" class="btn btn-success">保存</button>
                <a href="<?= url_action('mod/shop/product') ?>" class="btn btn-secondary">返回</a>
            </div>
        </div>
    </form>
</div>
