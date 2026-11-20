<?php
$driver = \zap\DB::connection()->getAttribute(\PDO::ATTR_DRIVER_NAME);
if ($driver === 'mysql') {
    $rows  = \zap\DB::select('SELECT VERSION()');
    $dbVer = $rows[0]['VERSION()'] ?? '';
} elseif ($driver === 'sqlite') {
    $dbh = new \PDO('sqlite::memory:');
    if ($dbh) {
        $dbVer = $dbh->query('select sqlite_version()')->fetchColumn(0);
        $dbh   = null;
    } else {
        $dbVer = __('admin.sqlite_not_supported');
    }
} else {
    $dbVer = '';
}
$gdInfo = function_exists('gd_info') ? gd_info() : [];
?>
<div class="card mb-4">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fa fa-server card-header-icon text-secondary"></i> <?php echo __('admin.widget_system'); ?>
        </h3>
        <div class="card-tools">
            <button type="button" class="btn btn-tool" data-zui-toggle="card-collapse" aria-label="<?php echo __('admin.collapse'); ?>">
                <i data-zui-icon="expand" class="fa fa-plus"></i>
                <i data-zui-icon="collapse" class="fa fa-minus"></i>
            </button>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped mb-0">
                <tbody>
                <tr>
                    <td class="fw-semibold" style="width: 160px;">
                        <i class="fa fa-cube me-2 text-primary"></i><?php echo __('admin.zapcms_version'); ?>
                    </td>
                    <td>v<?php echo e(ZAP_CMS_VERSION . '-' . ZAP_CMS_RELEASE_DATE); ?></td>
                    <td class="fw-semibold" style="width: 120px;">
                        <i class="fa fa-code me-2 text-success"></i><?php echo __('admin.php_version'); ?>
                    </td>
                    <td><?php echo e(PHP_VERSION . ' (' . php_sapi_name() . ')'); ?></td>
                </tr>
                <tr>
                    <td class="fw-semibold">
                        <i class="fa fa-database me-2 text-warning"></i><?php echo e($driver); ?>
                    </td>
                    <td>v<?php echo e($dbVer); ?></td>
                    <td class="fw-semibold">
                        <i class="fa fa-shield-alt me-2 text-info"></i>OpenSSL
                    </td>
                    <td><?php echo e(defined('OPENSSL_VERSION_TEXT') ? OPENSSL_VERSION_TEXT : __('admin.unsupported')); ?></td>
                </tr>
                <tr>
                    <td class="fw-semibold">
                        <i class="fa fa-image me-2 text-danger"></i>PHP GD
                    </td>
                    <td><?php echo e($gdInfo ? (string)current($gdInfo) : __('admin.not_installed')); ?></td>
                    <td class="fw-semibold">
                        <i class="fa fa-plug me-2 text-secondary"></i><?php echo __('admin.pdo_drivers'); ?>
                    </td>
                    <td><?php echo e(join(' / ', \PDO::getAvailableDrivers())); ?></td>
                </tr>
                <tr>
                    <td class="fw-semibold">
                        <i class="fa fa-globe me-2 text-primary"></i>Web Server
                    </td>
                    <td colspan="3"><?php echo e(\zap\http\Request::server('SERVER_SOFTWARE')); ?></td>
                </tr>
                <tr>
                    <td class="fw-semibold">
                        <i class="fa fa-desktop me-2 text-success"></i><?php echo __('admin.os'); ?>
                    </td>
                    <td colspan="3"><?php echo e(php_uname()); ?></td>
                </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
