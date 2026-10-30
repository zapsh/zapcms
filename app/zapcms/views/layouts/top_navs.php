<aside class="app-sidebar bg-dark shadow" data-bs-theme="dark">
    <style>
      .docs-btn-text {
  overflow: hidden;           /* 防止文字溢出 */
  white-space: nowrap;        /* 强制不换行，配合max-width实现折叠 */
  opacity: 0;
  max-width: 0;
  transition: 
    opacity 0.3s ease-in-out,
    max-width 0.4s ease-in-out; /* 宽度稍慢，制造“展开”感 */
}

body.sidebar-mini.sidebar-open .docs-btn-text {
  opacity: 1;
  max-width: 150px;           /* 过渡到目标宽度 */
}
    </style>    <!-- 侧边栏品牌区域 -->
    <div class="sidebar-brand">
        <a href="<?php echo \zap\facades\Url::action('Index'); ?>" class="brand-link text-decoration-none">
            <img src="<?php echo base_url();?>/assets/admin/img/zap_logo_white.svg" alt="ZAP" class="brand-image opacity-75 shadow" width="26" height="26" onerror="this.style.display='none'">
            <span class="brand-text fw-light ms-2">CMS</span>
        </a>
    </div>

    <!-- 侧边栏菜单滚动区 -->
    <div class="sidebar-wrapper">
        <nav class="mt-2">
            <ul class="nav sidebar-menu flex-column <?php \zapcms\AdminHook::echo('admin_menu_class'); ?>" data-zui-toggle="treeview" role="menu" data-accordion="false">
                <?php \zapcms\AdminHook::echo('admin_menu_before'); ?>
                <li class="nav-item">
                    <a href="<?php echo \zap\facades\Url::action('Index'); ?>" class="nav-link">
                            <i class="nav-icon fa fa-dashboard"></i>
                            <p>控制面板</p>
                        </a>
                </li>
                <?php
                use zap\facades\Url;
                use zapcms\services\AdminMenu;

                $menuTree = AdminMenu::instance()->getTreeArray();

                // 根据用户权限过滤菜单（约定：admin_menu_{id} 对应菜单项权限 key）
                $rbac = app()->rbac;
                if ($rbac !== null && !$rbac->isSuperAdmin()) {
                    $visibleTree = [];
                    foreach ($menuTree as $group) {
                        $groupId = $group['id'] ?? 0;
                        if (!$rbac->check("admin_menu_{$groupId}")) {
                            continue;
                        }

                        $rawChildren = $group['children'] ?? [];
                        $visibleChildren = [];
                        foreach ($rawChildren as $child) {
                            $childId = $child['id'] ?? 0;
                            if ($rbac->check("admin_menu_{$childId}")) {
                                $visibleChildren[] = $child;
                            }
                        }
                        $group['children'] = $visibleChildren;

                        // 分组没有 link_to 且所有子项都被过滤掉 → 隐藏该分组
                        if (empty($visibleChildren) && empty($group['link_to'])) {
                            continue;
                        }

                        $visibleTree[] = $group;
                    }
                    $menuTree = $visibleTree;
                }

                foreach ($menuTree as $group):
                    $groupTitle = $group['title'] ?? $group['name'] ?? '';
                    if (empty($groupTitle)) continue;

                    $children = $group['children'] ?? [];
                    $hasChildren = !empty($children);
                    $groupAction = Url::action($group['link_to'] ?? '');

                    // 判断当前 group 自身或任意子节点是否激活
                    // active_rule 优先 → 正则匹配 controller/method；否则用 URL 匹配
                    $groupActive = false;
                    if (!$hasChildren) {
                        if ($group['active_rule'] ?? '') {
                            $groupActive = Url::active($group['active_rule']);
                        } elseif ($groupAction && $groupAction !== '#') {
                            $groupActive = Url::active($groupAction);
                        }
                    }
                    echo "\n<!-- Menu Group: " . htmlspecialchars($groupTitle) . " -->\n";
                    echo "<!-- Group Active: " . ($groupActive ? 'true' : 'false') . " -->\n";

                    // 检查子节点激活状态，并决定父级是否展开
                    $childActiveAny = false;
                    if ($hasChildren):
                        foreach ($children as $child):
                            $childActive = false;
                            if ($child['active_rule'] ?? '') {
                                $childActive = Url::active($child['active_rule']);
                            } else {
                                $childAction = Url::action($child['link_to'] ?? '');
                                $childActive = ($childAction && $childAction !== '#') ? Url::active($childAction) : false;
                            }
                            if ($childActive) {
                                $childActiveAny = true;
                                $groupActive = true; // 父级也标记为激活
                                break;
                            }
                        endforeach;
                    endif;

                    // 无 action 的菜单项用 # 占位，确保可点击
                    if (empty($groupAction)) {
                        $groupAction = '#';
                    }

                    // 父级 li 的 class
                    $liClass = 'nav-item';
                    if ($hasChildren) {
                        $liClass .= $childActiveAny ? ' menu-open' : '';
                    }

                    // 父级链接 class
                    $linkClass = 'nav-link';
                    if (!$hasChildren && $groupActive) {
                        $linkClass .= ' active';
                    } elseif ($hasChildren && $groupActive) {
                        $linkClass .= ' active';
                    }

                    // 图标
                    $icon = $group['icon'] ?? 'fas fa-circle';
                    if (strpos($icon, 'fa ') !== 0 && !strpos($icon, 'fas ') && !strpos($icon, 'far ') && !strpos($icon, 'fab ')) {
                        $icon = 'fa ' . $icon;
                    }
                ?>
                    <li class="<?php echo $liClass; ?>">
                        <?php if ($hasChildren): ?>
                        <a href="#" class="<?php echo $linkClass; ?>" role="button">
                            <i class="nav-icon <?php echo htmlspecialchars($icon); ?>"></i>
                            <p>
                                <?php echo htmlspecialchars($groupTitle); ?>
                                <i class="nav-arrow fas fa-angle-left right"></i>
                            </p>
                        </a>
                        <ul class="nav nav-treeview">
                            <?php foreach ($children as $child):
                                $childTitle = $child['title'] ?? $child['name'] ?? '';
                                $childAction = Url::action($child['link_to'] ?? '#');
                                $childActive = Url::active($child['active_rule'] ?: $childAction) ? ' active' : '';
                                $childIcon = $child['icon'] ?? 'far fa-circle';
                            ?>
                            <li class="nav-item">
                                <a href="<?php echo $childAction === '#' ? '#' : $childAction; ?>" class="nav-link<?php echo $childActive; ?>">
                                    <i class="nav-icon <?php echo htmlspecialchars($childIcon); ?>"></i>
                                    <p><?php echo htmlspecialchars($childTitle); ?></p>
                                </a>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                        <?php else: ?>
                        <a href="<?php echo $groupAction === '#' ? '#' : $groupAction; ?>" class="<?php echo $linkClass; ?>">
                            <i class="nav-icon <?php echo htmlspecialchars($icon); ?>"></i>
                            <p><?php echo htmlspecialchars($groupTitle); ?></p>
                        </a>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
                <?php \zapcms\AdminHook::echo('admin_menu_after'); ?>
            </ul>
            <?php \zapcms\AdminHook::echo('admin_menu_footer'); ?>
            <div class="p-3 mt-3 border-top border-secondary border-opacity-25 docs-block">
              <a href="https://zap.cn/docs/zapcms" target="_blank" class="btn btn-sm btn-outline-light w-100 d-flex align-items-center justify-content-center docs-btn" title="View documentation">
                <i class="fa fa-book ms-2 me-2" aria-hidden="true"></i>
                <span class="docs-btn-text">Documentation</span>
              </a>
            </div>
        </nav>
    </div>
</aside>
<script>
(function() {
    var body = document.body;
    if (!body.classList.contains('sidebar-mini')) return;
    var sidebar = document.querySelector('.app-sidebar');
    if (sidebar) {
        sidebar.addEventListener('mouseenter', function() {
            body.classList.add('sidebar-open');
        });
        sidebar.addEventListener('mouseleave', function() {
            body.classList.remove('sidebar-open');
        });
    }
})();
</script>
