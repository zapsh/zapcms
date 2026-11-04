<div class="card mb-4">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fa fa-info-circle card-header-icon text-info"></i> 网站信息
        </h3>
    </div>
    <div class="card-body p-0">
        <div class="list-group list-group-flush">
            <div class="list-group-item d-flex justify-content-between align-items-center">
                <span><i class="fa fa-language me-2 text-muted"></i>系统语言</span>
                <span class="fw-semibold"><?php echo e(req()->language()); ?></span>
            </div>
            <div class="list-group-item d-flex justify-content-between align-items-center">
                <span><i class="fa fa-palette me-2 text-muted"></i>站点模版</span>
                <span class="fw-semibold"><?php echo e(option('website.theme', 'basic')); ?></span>
            </div>
            <div class="list-group-item d-flex justify-content-between align-items-center">
                <span><i class="fa fa-upload me-2 text-muted"></i>上传限制</span>
                <span class="fw-semibold"><?php echo e(ini_get('upload_max_filesize')); ?></span>
            </div>
            <div class="list-group-item d-flex justify-content-between align-items-center">
                <span><i class="fa fa-microchip me-2 text-muted"></i>内存限制</span>
                <span class="fw-semibold"><?php echo e(ini_get('memory_limit')); ?></span>
            </div>
            <div class="list-group-item d-flex justify-content-between align-items-center">
                <span><i class="fa fa-microchip me-2 text-muted"></i>PHP 版本</span>
                <span class="fw-semibold"><?php echo e(PHP_VERSION); ?></span>
            </div>
            <div class="list-group-item d-flex justify-content-between align-items-center">
                <span><i class="fa fa-database me-2 text-muted"></i>数据库</span>
                <span class="fw-semibold"><?php echo e(\zap\DB::connection()->getAttribute(\PDO::ATTR_DRIVER_NAME)); ?></span>
            </div>
        </div>
    </div>
</div>
