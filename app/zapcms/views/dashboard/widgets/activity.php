<?php
/**
 * @var array $logs
 * @var array $levelClass
 * @var string $level
 * @var string $badge
 * @var string $level
 * @var string $badge
 * @var string $level
 */
$levelClass = [
    'ERROR' => 'text-bg-danger', 'CRITICAL' => 'text-bg-danger', 'ALERT' => 'text-bg-danger',
    'EMERGENCY' => 'text-bg-danger', 'WARNING' => 'text-bg-warning', 'NOTICE' => 'text-bg-info',
    'INFO' => 'text-bg-secondary', 'DEBUG' => 'text-bg-light',
];
?>
<div class="card mb-4">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fa fa-clock-rotate-left card-header-icon text-primary"></i> <?php echo __('admin.widget_activity'); ?>
        </h3>
        <div class="card-tools">
            <span class="badge text-bg-secondary"><?php echo e(__('admin.log_count', ['count' => count($logs)])); ?></span>
        </div>
    </div>
    <div class="card-body p-0">
        <?php if (empty($logs)): ?>
            <div class="p-4 text-center text-muted">
                <i class="fa fa-inbox fs-1 d-block mb-2"></i>
                <?php echo __('admin.empty_activity'); ?>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0">
                    <thead class="table-light">
                    <tr>
                        <th class="ps-3"><?php echo __('admin.action_label'); ?></th>
                        <th><?php echo __('admin.admin'); ?></th>
                        <th><?php echo __('admin.category'); ?></th>
                        <th><?php echo __('admin.level'); ?></th>
                        <th class="text-end pe-3"><?php echo __('admin.time'); ?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($logs as $log):
                        $level = strtoupper((string)($log['level'] ?? 'INFO'));
                        $badge = $levelClass[$level] ?? 'text-bg-secondary';
                    ?>
                        <tr>
                            <td class="ps-3"><?php echo e($log['title'] ?? ''); ?></td>
                            <td><?php echo e($log['username'] ?? ''); ?></td>
                            <td><span class="badge text-bg-light text-secondary"><?php echo e($log['category'] ?? ''); ?></span></td>
                            <td><span class="badge <?php echo e($badge); ?>"><?php echo e($level); ?></span></td>
                            <td class="text-end pe-3 text-muted small">
                                <?php echo e(date('m-d H:i', (int)($log['request_time'] ?? 0))); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
