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

$conn_name = config('database.default');
$driver = \zap\DB::connection()->driver;

// 数据库信息
$dbInfoItems = [];
if ($driver === 'sqlite') {
    $dsn = config("database.connections.{$conn_name}.dsn");
    $dbPath = str_replace('sqlite:', '', $dsn);
    $dbInfoItems[] = ['label' => '数据库路径', 'value' => $dbPath, 'icon' => 'fa-folder-open', 'color' => 'secondary'];
    $dbInfoItems[] = ['label' => '数据库大小', 'value' => \zap\util\FileUtils::sizeOf($dbPath, true), 'icon' => 'fa-hdd', 'color' => 'info'];
} elseif ($driver === 'mysql' || $driver === 'mariadb') {
    $host = config("database.connections.{$conn_name}.host");
    $dbname = config("database.connections.{$conn_name}.dbname");
    $dbInfoItems[] = ['label' => '数据库主机', 'value' => $host, 'icon' => 'fa-server', 'color' => 'secondary'];
    $dbInfoItems[] = ['label' => '数据库名称', 'value' => $dbname, 'icon' => 'fa-database', 'color' => 'warning'];
    // 尝试获取数据库大小
    try {
        $sizeRows = \zap\DB::select(
            "SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb FROM information_schema.tables WHERE table_schema = :dbname GROUP BY table_schema",
            ['dbname' => $dbname]
        );
        if (!empty($sizeRows)) {
            $dbInfoItems[] = ['label' => '数据库大小', 'value' => $sizeRows[0]['size_mb'] . ' MB', 'icon' => 'fa-hdd', 'color' => 'info'];
        }
    } catch (\Throwable $e) {}
}

// 表列表（含附加信息）
$tableData = [];
$tablesRaw = \zapcms\support\SysInfo::getDatabaseTableNames();
foreach ($tablesRaw as $t) {
    $name = $t['name'];
    $entry = ['name' => $name, 'rows' => '', 'size' => ''];
    if ($driver !== 'sqlite') {
        // MySQL: 表名在第一个字段
        $name = is_array($t) ? current($t) : $t;
        $entry['name'] = $name;
    }
    // 尝试获取行数
    try {
        $cnt = \zap\DB::select("SELECT COUNT(*) AS cnt FROM `{$name}`");
        if (!empty($cnt)) {
            $entry['rows'] = $cnt[0]['cnt'] ?? '';
        }
    } catch (\Throwable $e) {}
    // MySQL 获取表大小
    if ($driver !== 'sqlite') {
        try {
            $info = \zap\DB::select("SHOW TABLE STATUS LIKE :tname", ['tname' => $name]);
            if (!empty($info)) {
                $size = $info[0]['Data_length'] + $info[0]['Index_length'];
                if ($size > 0) {
                    $entry['size'] = \zap\util\Fmt::ByteToHuman(intval($size));
                }
            }
        } catch (\Throwable $e) {}
    }
    $tableData[] = $entry;
}

$totalTables = count($tableData);
?>

<!--begin::数据库连接信息-->
<div class="row">
    <div class="col-lg-6">
        <div class="card mb-4">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fa fa-database card-header-icon text-warning"></i> 数据库连接信息
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
                        <span><i class="fa fa-plug me-2 text-warning"></i>数据库驱动</span>
                        <span class="badge text-bg-warning text-uppercase"><?php echo e($driver); ?></span>
                    </div>
                    <?php foreach ($dbInfoItems as $item): ?>
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <span>
                            <i class="fa <?php echo e($item['icon']); ?> me-2 text-<?php echo e($item['color']); ?>"></i>
                            <?php echo e($item['label']); ?>
                        </span>
                        <span class="fw-semibold text-break" style="max-width: 60%;"><?php echo e($item['value']); ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!--begin::备份操作-->
    <div class="col-lg-6">
        <div class="card mb-4">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fa fa-cloud-upload-alt card-header-icon text-primary"></i> 备份与还原
                </h3>
            </div>
            <div class="card-body text-center py-5">
                <div class="mb-4">
                    <i class="fa fa-cloud-upload-alt text-primary" style="font-size: 3rem;"></i>
                </div>
                <h5 class="fw-bold">数据库备份与还原</h5>
                <p class="text-muted">将数据库结构和数据导出为 SQL 文件，或从备份文件还原。</p>
                <div class="d-flex justify-content-center gap-3 mt-4">
                    <button onclick="doBackup()" type="button" class="btn btn-primary px-3">
                        <i class="fa fa-cloud-upload-alt me-2"></i>立即备份
                    </button>
                    <button onclick="doBackupData()" type="button" class="btn btn-outline-primary px-3">
                        <i class="fa fa-table me-2"></i>仅备份数据
                    </button>
                    <a href="<?php echo Url::action('System@backupList'); ?>" class="btn btn-outline-secondary px-3">
                        <i class="fa fa-history me-2"></i>备份历史
                    </a>
                </div>
                <p class="text-muted small mt-3 mb-0">
                    <i class="fa fa-info-circle me-1"></i>备份文件存储路径：<code><?php echo var_path('backups/sql'); ?></code>，自动保留最近 <?php echo \zapcms\helpers\Database::MAX_BACKUPS; ?> 个备份
                </p>
            </div>
        </div>
    </div>
    <!--end::备份操作-->
</div>
<!--end::数据库连接信息-->

<!--begin::数据库表列表-->
<div class="row">
    <div class="col-12">
        <div class="card mb-4">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fa fa-table card-header-icon text-success"></i> 数据库表
                    <span class="badge text-bg-success ms-2">共 <?php echo e($totalTables); ?> 张表</span>
                </h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-tool" data-zui-toggle="card-collapse" aria-label="折叠">
                        <i data-zui-icon="expand" class="fa fa-plus"></i>
                        <i data-zui-icon="collapse" class="fa fa-minus"></i>
                    </button>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 60px;">#</th>
                                <th>表名</th>
                                <?php if ($driver !== 'sqlite'): ?>
                                <th class="text-end">行数</th>
                                <th class="text-end">数据大小</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($tableData)): ?>
                            <tr>
                                <td colspan="<?php echo $driver !== 'sqlite' ? 4 : 2; ?>" class="text-center text-muted py-4">
                                    <i class="fa fa-inbox" style="font-size: 2rem; display: block;"></i>
                                    暂无数据表
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($tableData as $idx => $table): ?>
                            <tr>
                                <td class="text-muted"><?php echo $idx + 1; ?></td>
                                <td>
                                    <i class="fa fa-table me-2 text-success"></i>
                                    <code><?php echo e($table['name']); ?></code>
                                </td>
                                <?php if ($driver !== 'sqlite'): ?>
                                <td class="text-end">
                                    <?php if ($table['rows'] !== ''): ?>
                                    <span class="badge text-bg-light"><?php echo number_format($table['rows']); ?> 行</span>
                                    <?php else: ?>
                                    <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <?php if ($table['size'] !== ''): ?>
                                    <span class="fw-semibold"><?php echo e($table['size']); ?></span>
                                    <?php else: ?>
                                    <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<!--end::数据库表列表-->

<script>
function doBackup() {
    const load = Zap.loading('正在备份数据库，请稍后...');
    $.ajax({
        url: '<?php echo Url::action('System@backup'); ?>',
        method: 'post',
        success: function (data) {
            if (data.code === 0) {
                ZapToast.alert(data.msg + ' (仅保留最近 <?php echo \zapcms\helpers\Database::MAX_BACKUPS; ?> 个)', {bgColor: bgSuccess, position: Toast_Pos_Center});
            } else {
                ZapToast.alert(data.msg, {bgColor: bgDanger, position: Toast_Pos_Center});
            }
        },
        error: function () {
            ZapToast.alert('备份请求失败，请稍后重试', {bgColor: bgDanger, position: Toast_Pos_Center});
        }
    }).always(function () {
        load.dispose()
    });
}
function doBackupData() {
    const load = Zap.loading('正在备份数据，请稍后...');
    $.ajax({
        url: '<?php echo Url::action('System@backupData'); ?>',
        method: 'post',
        success: function (data) {
            if (data.code === 0) {
                ZapToast.alert(data.msg, {bgColor: bgSuccess, position: Toast_Pos_Center});
            } else {
                ZapToast.alert(data.msg, {bgColor: bgDanger, position: Toast_Pos_Center});
            }
        },
        error: function () {
            ZapToast.alert('备份请求失败，请稍后重试', {bgColor: bgDanger, position: Toast_Pos_Center});
        }
    }).always(function () {
        load.dispose()
    });
}
</script>
