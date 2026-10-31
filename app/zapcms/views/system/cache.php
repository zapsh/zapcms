<?php
use zap\facades\Url;
use zapcms\services\CacheManager;

$this->layout('layouts/common');

$relativePath = static function (string $path): string {
    $base = rtrim(str_replace('\\', '/', base_path()), '/') . '/';
    $path = str_replace('\\', '/', $path);

    return strpos($path, $base) === 0 ? substr($path, strlen($base)) : $path;
};
?>

<!--begin::Main Row-->
<div class="row">
    <!--begin::当前状态-->
    <div class="col-12">
        <div class="card mb-4">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fa fa-circle-info card-header-icon text-primary"></i> 当前状态
                </h3>
            </div>
            <div class="card-body">
                <div class="row g-4">
                    <div class="col-md-4">
                        <div class="text-secondary small mb-1">缓存驱动</div>
                        <div class="fw-semibold">
                            <?php echo $cache_driver === 'redis' ? 'Redis' : 'File（文件缓存）'; ?>
                            <?php if ($cache_enabled): ?>
                                <span class="badge text-bg-success ms-1">已开启</span>
                            <?php else: ?>
                                <span class="badge text-bg-secondary ms-1">已关闭</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-secondary small mb-1">缓存目录</div>
                        <div class="fw-semibold"><code><?php echo $relativePath(var_path('cache')); ?></code></div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-secondary small mb-1">模板缓存目录</div>
                        <div class="fw-semibold"><code><?php echo $relativePath(var_path('cache/twig')); ?></code></div>
                    </div>
                </div>
                <?php if ($cache_driver === 'redis'): ?>
                    <div class="alert alert-info mt-3 mb-0 py-2 small">
                        <i class="fa fa-circle-info me-1"></i>
                        Redis 驱动下只会删除带 <code><?php echo htmlspecialchars((string)config('cache.redis_prefix', 'zapcms:')); ?></code>
                        前缀的键，不会影响同一数据库中其它应用的数据。
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <!--end::当前状态-->

    <!--begin::清理项目-->
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fa fa-broom card-header-icon text-primary"></i> 清理项目
                </h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-tool" data-zui-toggle="card-collapse" aria-label="折叠">
                        <i data-zui-icon="expand" class="fa fa-plus"></i>
                        <i data-zui-icon="collapse" class="fa fa-minus"></i>
                    </button>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                    <tr>
                        <th>项目</th>
                        <th class="text-end" style="width:110px;">文件数</th>
                        <th class="text-end" style="width:110px;">占用空间</th>
                        <th class="text-end" style="width:100px;">操作</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($cache_targets as $key => $target): ?>
                        <?php $stat = $cache_stats[$key]; ?>
                        <tr>
                            <td>
                                <div class="fw-semibold">
                                    <i class="fa <?php echo e($target['icon']); ?> me-2 text-primary"></i><?php echo e($target['label']); ?>
                                </div>
                                <div class="text-secondary small"><?php echo e($target['desc']); ?></div>
                                <div class="text-secondary small">
                                    <code><?php echo $relativePath($target['path']); ?></code>
                                </div>
                            </td>
                            <td class="text-end">
                                <?php echo $stat['files'] === null ? '—' : number_format($stat['files']); ?>
                            </td>
                            <td class="text-end"><?php echo CacheManager::formatBytes($stat['bytes']); ?></td>
                            <td class="text-end">
                                <button type="button" class="btn btn-outline-danger btn-sm"
                                        onclick="zapCacheClear('<?php echo e($key); ?>', '<?php echo e($target['label']); ?>')">
                                    清理
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer d-flex flex-wrap align-items-center gap-2">
                <button type="button" class="btn btn-warning btn-sm" onclick="zapCacheClear('cache', '全部缓存')">
                    <i class="fa fa-eraser me-1"></i>清空全部缓存
                </button>
                <button type="button" class="btn btn-outline-danger btn-sm" onclick="zapCacheClear('all', '全部缓存与残留')">
                    <i class="fa fa-trash-can me-1"></i>清理全部（含日志与临时文件）
                </button>
                <span class="form-text ms-2 mb-0">日志与临时文件删除后不可恢复，请谨慎操作。</span>
            </div>
        </div>
    </div>
    <!--end::清理项目-->
</div>
<!--end::Main Row-->

<script>
    var cacheClearUrl = '<?php echo Url::action('System@cacheClear'); ?>';

    function zapCacheClear(type, label) {
        Swal.fire({
            title: '确认清理' + label + '？',
            text: '文件删除后不可恢复，缓存类数据会在下次访问时自动重建。',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: '立即清理',
            cancelButtonText: '取消',
            confirmButtonColor: '#dc3545'
        }).then(function (result) {
            if (!result.isConfirmed) {
                return;
            }

            const load = Zap.loading('正在清理，请稍后');

            $.ajax({
                url: cacheClearUrl,
                method: 'post',
                data: {type: type},
                dataType: 'json',
                success: function (data) {
                    if (data.code === 0) {
                        ZapToast.alert(data.msg, {bgColor: bgSuccess, position: Toast_Pos_Center});
                        setTimeout(function () {
                            location.reload();
                        }, 1200);
                    } else {
                        ZapToast.alert(data.msg, {bgColor: bgDanger, position: Toast_Pos_Center});
                    }
                },
                error: function (xhr) {
                    ZapToast.alert('请求失败：' + xhr.status, {bgColor: bgDanger, position: Toast_Pos_Center});
                }
            }).always(function () {
                load.dispose();
            });
        });
    }
</script>
