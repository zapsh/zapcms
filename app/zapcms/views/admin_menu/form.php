<?php

use zapcms\services\AdminMenu;

?>
<form class="row g-2">
    <input name="menu_id" value="<?php echo e($menu['id'] ?? ''); ?>" type="hidden"/>
    <div class="col-md-6">
        <label for="data_title" class="form-label small">菜单名称</label>
        <input type="text" class="form-control form-control-sm" name="zap_data[title]" id="data_title"
               value="<?php echo e($menu['title'] ?? ''); ?>" placeholder="菜单名称" autofocus/>
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
        <label class="form-label small d-block">显示位置</label>
        <?php
        $positions = explode(',', $menu['show_position'] ?? '');
        foreach (AdminMenu::getPositions() as $id => $title):
            ?>
            <div class="form-check form-check-inline">
                <input class="form-check-input" type="checkbox" name="zap_data[show_position][]"
                    <?php echo in_array($id, $positions) ? 'checked' : ''; ?>
                       id="data_show_position<?php echo e($id); ?>" value="<?php echo e($id); ?>">
                <label class="form-check-label small"
                       for="data_show_position<?php echo e($id); ?>"><?php echo e($title); ?></label>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="col-md-6">
        <label for="data_link_to" class="form-label small">链接地址</label>
        <input type="text" class="form-control form-control-sm" name="zap_data[link_to]" id="data_link_to"
               value="<?php echo e($menu['link_to'] ?? ''); ?>" placeholder="例: System@settings"/>
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
    </div>
    <div class="col-md-6">
        <label for="data_sort_order" class="form-label small">排序</label>
        <input type="text" class="form-control form-control-sm" name="zap_data[sort_order]" id="data_sort_order"
               value="<?php echo e($menu['sort_order'] ?? 0); ?>"/>
    </div>
</form>
