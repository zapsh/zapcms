<?php
!IS_AJAX && $this->layout('layouts/common');
$this->view->page_title  = '商城仪表盘';
$this->view->breadcrumbs = [
    ['title' => '控制台', 'url' => url_action('Index')],
    ['title' => '商城', 'url' => url_action('mod/shop/product')],
    ['title' => '仪表盘'],
];
?>
<div class="container-fluid p-3">
    <h4 class="mb-4">商城仪表盘</h4>

    <div class="row g-3">
        <div class="col-md-3">
            <div class="card border-start border-primary border-3 shadow-sm">
                <div class="card-body">
                    <div class="text-muted small text-uppercase fw-semibold">产品总数</div>
                    <div class="fs-3 fw-bold text-primary"><?= $productCount ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-start border-success border-3 shadow-sm">
                <div class="card-body">
                    <div class="text-muted small text-uppercase fw-semibold">上架中</div>
                    <div class="fs-3 fw-bold text-success"><?= $onlineCount ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-4">
        <a href="<?= url_action('mod/shop/product') ?>" class="btn btn-primary">
            <i class="fa fa-cubes"></i> 管理产品
        </a>
    </div>
</div>
