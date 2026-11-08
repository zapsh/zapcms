<?php

use zapcms\services\Dashboard;

$page_title = __('admin.dashboard');
$this->layout('layouts/common');
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <span class="text-muted small"><?php echo e(__('admin.hello', ['name' => \zapcms\services\Auth::user('full_name', '')])); ?></span>
    <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-dashboard-customize"
            data-bs-toggle="modal" data-bs-target="#dashboardCustomizeModal">
        <i class="fa fa-table-columns me-1"></i><?php echo __('admin.customize'); ?>
    </button>
</div>

<?php echo $before_html; ?>

<div class="row" id="dashboardWidgets">
    <?php foreach ($active as $widget): ?>
        <div class="<?php echo e($widget['col']); ?>" data-widget="<?php echo e($widget['id']); ?>">
            <?php echo Dashboard::renderWidget($widget, $admin_id); ?>
        </div>
    <?php endforeach; ?>
    <?php if (empty($active)): ?>
        <div class="col-12">
            <div class="card mb-4">
                <div class="card-body text-center text-muted py-5">
                    <i class="fa fa-table-columns fs-1 d-block mb-2"></i>
                    <?php echo __('admin.empty_dashboard'); ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php echo $after_html; ?>

<!-- 自定义控制台 -->
<div class="modal fade" id="dashboardCustomizeModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title"><i class="fa fa-table-columns me-1"></i><?php echo __('admin.customize_title'); ?></h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php echo __('admin.close'); ?>"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-2">
                    <?php echo __('admin.customize_desc'); ?>
                </p>
                <div id="dashboardWidgetList" class="list-group"></div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-sm btn-outline-secondary me-auto" id="btn-dashboard-reset">
                    <i class="fa fa-rotate-left me-1"></i><?php echo __('admin.restore_default'); ?>
                </button>
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal"><?php echo __('admin.cancel'); ?></button>
                <button type="button" class="btn btn-sm btn-primary" id="btn-dashboard-save"><?php echo __('admin.save'); ?></button>
            </div>
        </div>
    </div>
</div>

<script>
    (function () {
        const Api = {
            list: '<?php echo url_action('Index@dashboardWidgets'); ?>',
            save: '<?php echo url_action('Index@saveDashboardWidgets'); ?>',
            reset: '<?php echo url_action('Index@resetDashboardWidgets'); ?>'
        };
        const $list = $('#dashboardWidgetList');
        let items = [];

        function syncEnabled() {
            $list.children('.list-group-item').each(function (i) {
                if (items[i]) {
                    items[i].enabled = $(this).find('input[type="checkbox"]').is(':checked');
                }
            });
        }

        function render() {
            $list.empty();
            items.forEach(function (item, i) {
                const $row = $('<div class="list-group-item d-flex align-items-center gap-2">')
                    .attr('data-id', item.id);

                $row.append($('<input type="checkbox" class="form-check-input mt-0 flex-shrink-0">')
                    .prop('checked', !!item.enabled));
                $row.append($('<i class="text-secondary" style="width:1.1rem">').addClass(item.icon || 'fa fa-cube'));

                const $text = $('<div class="flex-grow-1 min-w-0">');
                $text.append($('<div class="fw-semibold small">').text(item.title));
                $text.append($('<div class="text-muted" style="font-size:.75rem">').text(item.description));
                $row.append($text);

                $row.append($('<button type="button" class="btn btn-sm btn-light py-0 px-2" data-move="up" title="<?php echo __('admin.move_up'); ?>">')
                    .html('<i class="fa fa-arrow-up"></i>').prop('disabled', i === 0));
                $row.append($('<button type="button" class="btn btn-sm btn-light py-0 px-2" data-move="down" title="<?php echo __('admin.move_down'); ?>">')
                    .html('<i class="fa fa-arrow-down"></i>').prop('disabled', i === items.length - 1));

                $list.append($row);
            });
        }

        function load() {
            $list.html('<div class="list-group-item text-center text-muted py-4"><?php echo __('admin.loading'); ?></div>');
            $.getJSON(Api.list, function (res) {
                const data = res && res.data;
                items = (data && Array.isArray(data.items)) ? data.items : [];
                render();
            }).fail(function () {
                $list.html('<div class="list-group-item text-center text-danger py-4"><?php echo __('admin.load_failed'); ?></div>');
            });
        }

        function collect() {
            syncEnabled();
            const order = [], hidden = [];
            $list.children('.list-group-item').each(function () {
                const id = $(this).attr('data-id');
                order.push(id);
                if (!$(this).find('input[type="checkbox"]').is(':checked')) {
                    hidden.push(id);
                }
            });
            return {order: order, hidden: hidden};
        }

        $('#dashboardCustomizeModal').on('show.bs.modal', load);

        $list.on('click', '[data-move]', function () {
            const idx = $(this).closest('.list-group-item').index();
            const target = idx + ($(this).attr('data-move') === 'up' ? -1 : 1);
            if (target < 0 || target >= items.length) return;
            syncEnabled();
            const tmp = items[idx];
            items[idx] = items[target];
            items[target] = tmp;
            render();
        });

        $('#btn-dashboard-save').on('click', function () {
            const $btn = $(this);
            const payload = collect();
            $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span><?php echo __('admin.saving'); ?>');
            $.ajax({
                url: Api.save,
                method: 'post',
                data: payload,
                dataType: 'json',
                success: function (res) {
                    const ok = res && res.code === 0;
                    try {
                        ZapToast.alert(res && res.msg ? res.msg : (ok ? '<?php echo __('admin.saved'); ?>' : '<?php echo __('admin.save_failed'); ?>'), {
                            delay: 2000, bgColor: ok ? bgSuccess : bgDanger
                        });
                    } catch (err) { /* 提示失败不影响刷新 */ }
                    if (ok) {
                        setTimeout(function () { window.location.reload(); }, 500);
                    }
                },
                error: function () {
                    ZapToast.alert('<?php echo __('admin.request_failed'); ?>', {bgColor: bgDanger});
                },
                complete: function () {
                    $btn.prop('disabled', false).html('<?php echo __('admin.save'); ?>');
                }
            });
        });

        $('#btn-dashboard-reset').on('click', function () {
            $.post(Api.reset, {}, function (res) {
                const ok = res && res.code === 0;
                ZapToast.alert(res && res.msg ? res.msg : (ok ? '<?php echo __('admin.restored'); ?>' : '<?php echo __('admin.operation_failed'); ?>'), {
                    delay: 2000, bgColor: ok ? bgSuccess : bgDanger
                });
                if (ok) {
                    setTimeout(function () { window.location.reload(); }, 500);
                }
            }, 'json');
        });
    })();
</script>
