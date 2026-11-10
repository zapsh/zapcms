<?php

use zapcms\services\AdminMenu;

?>
<form class="row g-2">
    <input name="menu_id" value="<?php echo e($menu['id'] ?? ''); ?>" type="hidden"/>
    <div class="col-md-6">
        <label for="data_title" class="form-label small">菜单名称 <span class="text-danger">*</span></label>
        <input type="text" class="form-control form-control-sm" name="zap_data[title]" id="data_title"
               value="<?php echo e($menu['title'] ?? ''); ?>" placeholder="菜单名称" autofocus
               oninput="$('#data_title').removeClass('is-invalid')"/>
    </div>
    <div class="col-md-6">
        <label for="data_icon" class="form-label small">图标</label>
        <div class="input-group input-group-sm">
            <input type="text" class="form-control" placeholder="点击选择图标"
                   name="zap_data[icon]" id="data_icon"
                   value="<?php echo e($menu['icon'] ?? 'fa fa-circle-notch'); ?>"
                   onclick="ZapFaIcons(['#preview-icon-i','#data_icon']);"/>
            <span class="input-group-text" id="preview-icon">
                <i class="<?php echo e($menu['icon'] ?? 'fa fa-circle-notch'); ?>" id="preview-icon-i"></i>
            </span>
        </div>
    </div>
    <div class="col-md-6">
        <label for="data_pid" class="form-label small">上级菜单</label>
        <select name="zap_data[pid]" id="data_pid" class="form-select form-select-sm">
            <option value="0">- 顶级菜单 -</option>
            <?php
            // 新增时 $parent 有值 → 自动选中父级；编辑时用 $menu['pid'] 选中
            $selectedPid = isset($parent['id']) ? intval($parent['id']) : intval($menu['pid'] ?? 0);
            AdminMenu::instance()->forEachAll(function ($row) use ($menu, $selectedPid) {
                ?>
                <option value="<?php echo e($row['id']); ?>"
                    <?php echo $selectedPid === intval($row['id']) ? 'selected' : ''; ?>
                    <?php echo !empty($menu['path']) && \zap\util\Str::startsWith($row['path'], $menu['path']) ? 'disabled' : ''; ?>>
                    <?php echo str_repeat('&nbsp;&nbsp;', max(0, intval($row['level']) - 1)) . $row['title']; ?>
                </option>
                <?php
            });
            ?>
        </select>
    </div>
    <div class="col-md-6">
        <label for="data_link_to" class="form-label small">链接地址</label>
        <input type="text" class="form-control form-control-sm" name="zap_data[link_to]" id="data_link_to"
               value="<?php echo e($menu['link_to'] ?? ''); ?>" placeholder="例: System@settings"/>
        <div class="form-text small text-muted" id="link_to_help"></div>
    </div>
    <div class="col-md-6">
        <label for="data_link_type" class="form-label small">链接类型</label>
        <select name="zap_data[link_type]" class="form-select form-select-sm" id="data_link_type">
            <option value="action" <?php echo ($menu['link_type'] ?? '') == 'action' ? 'selected' : ''; ?>>Action</option>
            <option value="route" <?php echo ($menu['link_type'] ?? '') == 'route' ? 'selected' : ''; ?>>Route</option>
            <option value="link" <?php echo ($menu['link_type'] ?? '') == 'link' ? 'selected' : ''; ?>>自定义链接</option>
        </select>
    </div>
    <div class="col-md-6">
        <label for="data_link_target" class="form-label small">链接目标</label>
        <select name="zap_data[link_target]" class="form-select form-select-sm" id="data_link_target">
            <option value="_self" <?php echo ($menu['link_target'] ?? '') == '_self' ? 'selected' : ''; ?>>当前页面</option>
            <option value="_blank" <?php echo ($menu['link_target'] ?? '') == '_blank' ? 'selected' : ''; ?>>新页面</option>
            <option value="_top" <?php echo ($menu['link_target'] ?? '') == '_top' ? 'selected' : ''; ?>>Top</option>
        </select>
    </div>
    <div class="col-md-6">
        <label for="data_active_rule" class="form-label small">选中规则</label>
        <input type="text" class="form-control form-control-sm" name="zap_data[active_rule]" id="data_active_rule"
               value="<?php echo e($menu['active_rule'] ?? ''); ?>" placeholder="匹配的路由规则"/>
        <div class="form-text small text-muted">访问该规则对应的页面时，此菜单高亮。可填控制器名（如 System）或路由。</div>
    </div>
    <div class="col-md-6">
        <label for="data_sort_order" class="form-label small">排序</label>
        <input type="text" class="form-control form-control-sm" name="zap_data[sort_order]" id="data_sort_order"
               value="<?php echo e($menu['sort_order'] ?? 0); ?>"/>
    </div>
    <div class="col-md-6">
        <label for="data_i18n_key" class="form-label small">翻译 Key（可选）</label>
        <input type="text" class="form-control form-control-sm" name="zap_data[i18n_key]" id="data_i18n_key"
               value="<?php echo e($menu['i18n_key'] ?? ''); ?>" placeholder="如 media"/>
        <div class="form-text small text-muted">
            填写后菜单标题会按语言文件 <code>languages/{语言}/menu.php</code> 中的同名 key 翻译；
            留空则始终显示「菜单名称」原文。仅字母、数字与下划线。
        </div>
    </div>
</form>
<script>
    (function () {
        var helps = {
            action: '控制器动作，例：System@settings 或 System@index',
            route:  '路由别名，例：admin/dashboard',
            link:   '完整 URL 或站点相对路径，例：https://example.com 或 /about'
        };
        function syncLinkHelp() {
            var type = $('#data_link_type').val();
            $('#data_link_to').attr('placeholder', helps[type] || '');
            $('#link_to_help').text(helps[type] || '');
        }
        $('#data_link_type').on('change', syncLinkHelp);
        syncLinkHelp();
    })();
</script>
