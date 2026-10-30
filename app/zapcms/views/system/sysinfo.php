<?php
/*
 * Copyright (c) 2023.  ZAP.CN  - ZAP CMS - All Rights Reserved
 * @author Allen
 * @email zapcms@zap.cn
 * @date 2023/12/27 下午3:27
 * @lastModified 2023/12/27 下午3:27
 *
 */

use zapcms\support\Asset;
use zap\facades\Url;

$this->layout('layouts/common');

// 辅助函数
$success_status = '<span class="badge text-bg-success">Yes</span>';
$error_status   = '<span class="badge text-bg-danger">No</span>';

// 数据库驱动
try {
    $dbDriver = \zap\DB::connection()->getAttribute(PDO::ATTR_DRIVER_NAME);
} catch (\Throwable $e) {
    $dbDriver = '未知';
}

// 数据库版本
$dbVersion = '';
try {
    if ($dbDriver === 'mysql') {
        $rows = \zap\DB::select("SELECT VERSION()");
        $dbVersion = $rows[0]['VERSION()'] ?? '';
    } elseif ($dbDriver === 'sqlite') {
        $dbh = new \PDO('sqlite::memory:');
        if ($dbh) {
            $dbVersion = $dbh->query('select sqlite_version()')->fetchColumn(0);
            $dbh = null;
        }
    } elseif ($dbDriver === 'pgsql') {
        $rows = \zap\DB::select("SELECT VERSION()");
        $dbVersion = $rows[0]['version'] ?? '';
    }
} catch (\Throwable $e) {
    $dbVersion = '';
}

// 缓存模块状态
$cacheModules = [
    ['name' => 'APC',     'check' => function_exists('apc_add')],
    ['name' => 'APCu',    'check' => function_exists('apcu_add')],
    ['name' => 'OPcache', 'check' => function_exists('opcache_get_configuration')],
    ['name' => 'Memcache','check' => class_exists('Memcache')],
    ['name' => 'Memcached','check' => class_exists('Memcached')],
    ['name' => 'Redis',   'check' => class_exists('Redis')],
];
?>

<!--begin::Main Row-->
<div class="row">
    <!--begin::服务器基本信息-->
    <div class="col-lg-6">
        <div class="card mb-4">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fa fa-server card-header-icon text-primary"></i> 服务器基本信息
                </h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-tool" data-zui-toggle="card-collapse" aria-label="折叠">
                        <i data-zui-icon="expand" class="fa fa-plus"></i>
                        <i data-zui-icon="collapse" class="fa fa-minus"></i>
                    </button>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <span><i class="fa fa-cube me-2 text-primary"></i>ZapCMS</span>
                        <span class="fw-semibold">v<?php echo ZAP_CMS_VERSION . '-' . ZAP_CMS_RELEASE_DATE; ?></span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <span><i class="fa fa-desktop me-2 text-success"></i>主机系统</span>
                        <span class="fw-semibold"><?php echo php_uname(); ?></span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <span><i class="fa fa-globe me-2 text-info"></i>访问地址</span>
                        <span class="fw-semibold"><?php echo $_SERVER['HTTP_HOST']; ?></span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <span><i class="fa fa-server me-2 text-secondary"></i>主机名称</span>
                        <span class="fw-semibold"><?php echo $_SERVER['SERVER_NAME']; ?></span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <span><i class="fa fa-network-wired me-2 text-warning"></i>主机地址</span>
                        <span class="fw-semibold"><?php echo $_SERVER['SERVER_ADDR']; ?></span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <span><i class="fa fa-plug me-2 text-danger"></i>主机端口</span>
                        <span class="fw-semibold"><?php echo $_SERVER['SERVER_PORT']; ?></span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <span><i class="fa fa-window-restore me-2 text-primary"></i>WEB 软件</span>
                        <span class="fw-semibold"><?php echo $_SERVER['SERVER_SOFTWARE']; ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!--end::服务器基本信息-->

    <!--begin::PHP 环境信息-->
    <div class="col-lg-6">
        <div class="card mb-4">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fa fa-code card-header-icon text-success"></i> PHP 环境信息
                </h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-tool" data-zui-toggle="card-collapse" aria-label="折叠">
                        <i data-zui-icon="expand" class="fa fa-plus"></i>
                        <i data-zui-icon="collapse" class="fa fa-minus"></i>
                    </button>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <span><i class="fa fa-code me-2 text-success"></i>PHP 版本</span>
                        <span class="fw-semibold"><?php echo PHP_VERSION . ' (' . php_sapi_name() . ')'; ?></span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <span><i class="fa fa-upload me-2 text-warning"></i>允许上传文件</span>
                        <span><?php if_else_echo(ini_get('file_uploads'), '<span class="badge text-bg-success">Yes</span>', '<span class="badge text-bg-danger">No</span>'); ?></span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <span><i class="fa fa-upload me-2 text-info"></i>文件上传限制</span>
                        <span class="fw-semibold"><?php echo ini_get('upload_max_filesize'); ?></span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <span><i class="fa fa-paper-plane me-2 text-primary"></i>表单提交限制</span>
                        <span class="fw-semibold"><?php echo ini_get('post_max_size'); ?></span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <span><i class="fa fa-copy me-2 text-secondary"></i>最大提交数量</span>
                        <span class="fw-semibold"><?php echo ini_get('max_file_uploads'); ?></span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <span><i class="fa fa-microchip me-2 text-danger"></i>分配内存限制</span>
                        <span class="fw-semibold"><?php echo ini_get('memory_limit'); ?></span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <span><i class="fa fa-image me-2 text-info"></i>GD 库支持</span>
                        <?php if (function_exists('gd_info')): ?>
                        <?php $gdInfo = gd_info(); ?>
                        <span class="fw-semibold"><?php echo current($gdInfo); ?></span>
                        <?php else: ?>
                        <span class="badge text-bg-danger">不支持</span>
                        <?php endif; ?>
                    </div>
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <span><i class="fa fa-sync me-2 text-primary"></i>Curl 支持</span>
                        <span><?php if_else_echo(function_exists('curl_init'), $success_status, $error_status); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!--end::PHP 环境信息-->
</div>
<!--end::Main Row-->

<!--begin::Second Row-->
<div class="row">
    <!--begin::数据库信息-->
    <div class="col-lg-6">
        <div class="card mb-4">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fa fa-database card-header-icon text-warning"></i> 数据库信息
                </h3>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <span><i class="fa fa-database me-2 text-warning"></i>数据库驱动</span>
                        <span class="fw-semibold"><?php echo $dbDriver; ?></span>
                    </div>
                    <?php if (!empty($dbVersion)): ?>
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <span><i class="fa fa-database me-2 text-success"></i>数据库版本</span>
                        <span class="fw-semibold">v<?php echo $dbVersion; ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <span><i class="fa fa-shield-alt me-2 text-info"></i>OpenSSL</span>
                        <span class="fw-semibold"><?php echo defined("OPENSSL_VERSION_TEXT") ? OPENSSL_VERSION_TEXT : '不支持'; ?></span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <span><i class="fa fa-plug me-2 text-secondary"></i>PDO 驱动</span>
                        <span class="fw-semibold"><?php echo join(' / ', PDO::getAvailableDrivers()); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!--end::数据库信息-->

    <!--begin::缓存模块支持-->
    <div class="col-lg-6">
        <div class="card mb-4">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fa fa-bolt card-header-icon text-danger"></i> 缓存模块支持
                </h3>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <?php foreach ($cacheModules as $mod): ?>
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <span>
                            <?php if ($mod['check']): ?>
                            <i class="fa fa-check-circle text-success me-2"></i>
                            <?php else: ?>
                            <i class="fa fa-times-circle text-danger me-2"></i>
                            <?php endif; ?>
                            <?php echo $mod['name']; ?>
                        </span>
                        <?php if ($mod['check']): ?>
                        <span class="badge text-bg-success">已安装</span>
                        <?php else: ?>
                        <span class="badge text-bg-danger">未安装</span>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <!--end::缓存模块支持-->
</div>
<!--end::Second Row-->

<!--begin::已加载PHP模块-->
<div class="row">
    <div class="col-12">
        <div class="card mb-4">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fa fa-puzzle-piece card-header-icon text-primary"></i> 已加载 PHP 模块
                    <?php $extensions = get_loaded_extensions(); ?>
                    <span class="badge text-bg-secondary ms-2">共 <?php echo count($extensions); ?> 个</span>
                </h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-tool" data-zui-toggle="card-collapse" aria-label="折叠">
                        <i data-zui-icon="expand" class="fa fa-plus"></i>
                        <i data-zui-icon="collapse" class="fa fa-minus"></i>
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="d-flex flex-wrap gap-2">
                    <?php foreach ($extensions as $ext): ?>
                    <span class="badge text-bg-light border">
                        <i class="fa fa-puzzle-piece text-muted me-1"></i><?php echo $ext; ?>
                    </span>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<!--end::已加载PHP模块-->
