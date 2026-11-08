<aside class="app-sidebar bg-dark shadow" data-bs-theme="dark" data-enable-persistence="true">
    <style>
      .docs-btn-text {
  white-space: nowrap;        /* 强制不换行，配合max-width实现折叠 */
  overflow: hidden;           /* 防止文字溢出 */
  opacity: 1;
  max-width: 150px;
  transition:
    opacity 0.3s ease-in-out,
    max-width 0.4s ease-in-out; /* 宽度稍慢，制造“展开”感 */
}

/* 仅在折叠态（sidebar-mini）且未悬停展开（无 sidebar-open）时隐藏文字 */
body.sidebar-mini:not(.sidebar-open) .docs-btn-text {
  opacity: 0;
  max-width: 0;
}

/* 侧栏配色：统一低对比白色叠加，不用深色块/高饱和蓝，层级靠缩进与细线区分 */
.app-sidebar .sidebar-menu > .nav-item > .nav-link.active,
.app-sidebar .sidebar-menu > .nav-item.menu-open > .nav-link {
  background-color: rgba(255, 255, 255, .07);
}
.app-sidebar .sidebar-menu > .nav-item > .nav-link:hover,
.app-sidebar .sidebar-menu > .nav-item > .nav-link:focus {
  background-color: rgba(255, 255, 255, .05);
}

.app-sidebar .sidebar-menu > .nav-item > .nav-treeview {
  background-color: rgba(255, 255, 255, .03);
  border-left: 1px solid rgba(255, 255, 255, .10);
  border-radius: 0 .25rem .25rem 0;
  margin: .1rem 0 .25rem .5rem;
  padding: .2rem 0;
}
.app-sidebar .sidebar-menu > .nav-item > .nav-treeview > .nav-item > .nav-link {
  padding-left: 1.35rem;
  font-size: .88rem;
  color: rgba(255, 255, 255, .62);
}
.app-sidebar .sidebar-menu > .nav-item > .nav-treeview > .nav-item > .nav-link:hover,
.app-sidebar .sidebar-menu > .nav-item > .nav-treeview > .nav-item > .nav-link:focus {
  background-color: rgba(255, 255, 255, .05);
  color: rgba(255, 255, 255, .92);
}
.app-sidebar .sidebar-menu > .nav-item > .nav-treeview > .nav-item > .nav-link.active {
  background-color: rgba(255, 255, 255, .075);
  color: #fff;
  box-shadow: inset 2px 0 0 rgba(255, 255, 255, .28);
}

/* 递归渲染时更深层子菜单同样保持低对比 */
.app-sidebar .nav-treeview .nav-treeview-nested {
  background-color: rgba(255, 255, 255, .025);
  border-left: 1px solid rgba(255, 255, 255, .08);
  margin: .1rem 0 .2rem .5rem;
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
            <ul class="nav sidebar-menu flex-column nav-indent <?php echo \zapcms\Hook::fire('admin_menu_class'); ?>" data-zui-toggle="treeview" role="menu" data-accordion="false">
                <?php echo \zapcms\Hook::fire('admin_menu_before'); ?>
                <li class="nav-item">
                    <a href="<?php echo \zap\facades\Url::action('Index'); ?>" class="nav-link">
                        <i class="nav-icon fa fa-dashboard"></i>
                        <p><?php echo __t('menu.dashboard', '控制面板'); ?></p>
                    </a>
                </li>
                <?php
                use zap\facades\Url;
                use zapcms\services\AdminMenu;

                $menuTree = AdminMenu::instance()->getTreeArray();

                // 按角色权限递归过滤（约定：admin_menu_{id} 为权限 key）
                $rbac       = app()->rbac;
                $needFilter = $rbac !== null && !$rbac->isSuperAdmin();
                $filterMenu = function (array $items) use (&$filterMenu, $rbac, $needFilter): array {
                    $out = [];
                    foreach ($items as $item) {
                        $id = (int) ($item['id'] ?? 0);
                        if ($needFilter && !$rbac->check("admin_menu_{$id}")) {
                            continue;
                        }
                        if (!empty($item['children'])) {
                            $item['children'] = $filterMenu($item['children']);
                            // 纯分组（自身无链接）且子项全被过滤 → 隐藏
                            if (empty($item['children']) && empty($item['link_to'])) {
                                continue;
                            }
                        }
                        $out[] = $item;
                    }
                    return $out;
                };
                $menuTree = $filterMenu($menuTree);

                // 自身或任意子孙命中当前路由 → 该项及其祖先高亮 / 展开
                $menuActive = function (array $item) use (&$menuActive): bool {
                    $rule = (string) ($item['active_rule'] ?? '');
                    if ($rule !== '' && Url::active($rule)) {
                        return true;
                    }
                    $action = Url::action($item['link_to'] ?? '');
                    if ($action && $action !== '#' && Url::active($action)) {
                        return true;
                    }
                    foreach (($item['children'] ?? []) as $child) {
                        if (is_array($child) && $menuActive($child)) {
                            return true;
                        }
                    }
                    return false;
                };

                $iconOf = function ($icon): string {
                    $icon = (string) ($icon ?: 'fas fa-circle');
                    if (strpos($icon, 'fa ') !== 0 && !strpos($icon, 'fas ') && !strpos($icon, 'far ') && !strpos($icon, 'fab ')) {
                        $icon = 'fa ' . $icon;
                    }
                    return $icon;
                };

                /**
                 * 递归渲染任意层级菜单。
                 * 二级及以下的「有子项且自身有 action」父项：主链接仍可进入自身页面，
                 * 展开箭头是独立的 href="#" 按钮（treeview JS 会阻止默认跳转变展开）。
                 */
                $renderMenu = function (array $items, int $depth = 0) use (&$renderMenu, $menuActive, $iconOf): void {
                    foreach ($items as $item) {
                        $title = (string) ($item['title'] ?? $item['name'] ?? '');
                        if ($title === '') {
                            continue;
                        }
                        // 菜单标题翻译：有 i18n_key 时按 key 查译文，查不到回退数据库里的 title
                        $i18nKey = trim((string) ($item['i18n_key'] ?? ''));
                        if ($i18nKey !== '') {
                            $title = __t('menu.' . $i18nKey, $title);
                        }
                        $children    = $item['children'] ?? [];
                        $hasChildren = !empty($children);
                        $action      = Url::action($item['link_to'] ?? '');
                        if (empty($action)) {
                            $action = '#';
                        }
                        $active    = $menuActive($item);
                        $liClass   = 'nav-item' . ($hasChildren && $active ? ' menu-open' : '');
                        $linkExtra = $active ? ' active' : '';
                        $icon      = $iconOf($item['icon'] ?? '');

                        // data-menu-id：侧栏菜单展开状态记忆（localStorage）的定位锚点
                        echo '<li class="' . e($liClass) . '"' . ($hasChildren ? ' data-menu-id="' . e((int) ($item['id'] ?? 0)) . '"' : '') . '>';

                        if ($hasChildren && $depth > 0 && $action !== '#') {
                            echo '<div class="nav-link nav-link-group p-0 d-flex align-items-center' . $linkExtra . '">';
                            echo '<a href="' . e($action) . '" class="nav-link flex-grow-1' . $linkExtra . '">';
                            echo '<i class="nav-icon ' . htmlspecialchars($icon) . '"></i><p>' . htmlspecialchars($title) . '</p>';
                            echo '</a>';
                            echo '<a href="#" class="nav-link nav-link-toggle flex-grow-0 px-2" aria-label="展开子菜单">';
                            echo '<i class="nav-arrow fas fa-angle-left right"></i></a>';
                            echo '</div>';
                        } elseif ($hasChildren) {
                            echo '<a href="#" class="nav-link' . $linkExtra . '" role="button">';
                            echo '<i class="nav-icon ' . htmlspecialchars($icon) . '"></i>';
                            echo '<p>' . htmlspecialchars($title) . '<i class="nav-arrow fas fa-angle-left right"></i></p>';
                            echo '</a>';
                        } else {
                            echo '<a href="' . e($action) . '" class="nav-link' . $linkExtra . '">';
                            echo '<i class="nav-icon ' . htmlspecialchars($icon) . '"></i>';
                            echo '<p>' . htmlspecialchars($title) . '</p>';
                            echo '</a>';
                        }

                        if ($hasChildren) {
                            echo '<ul class="nav nav-treeview' . ($depth >= 1 ? ' nav-treeview-nested' : '') . '">';
                            $renderMenu($children, $depth + 1);
                            echo '</ul>';
                        }
                        echo '</li>';
                    }
                };

                $renderMenu($menuTree, 0);
                ?>
                <?php echo \zapcms\Hook::fire('admin_menu_after'); ?>
            </ul>
            <?php echo \zapcms\Hook::fire('admin_menu_footer'); ?>
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
<script>
(function () {
    // 后台主侧栏菜单展开状态记忆（localStorage，按管理员区分）
    // Treeview 组件（admin.js）开关只切换 .menu-open 类并派发
    // expanded/collapsed.zui.treeview 事件，这里不重写组件、只挂钩持久化。
    var KEY = 'zapcms.adminMenu.state.u<?php echo (int) (\zapcms\services\Auth::user('id') ?? 0); ?>';
    function loadState() {
        try { return JSON.parse(localStorage.getItem(KEY)) || {}; } catch (e) { return {}; }
    }
    function saveState(s) {
        try { localStorage.setItem(KEY, JSON.stringify(s)); } catch (e) {}
    }
    function menuId(item) { return item.getAttribute('data-menu-id'); }

    document.addEventListener('DOMContentLoaded', function () {
        var items = document.querySelectorAll('.app-sidebar .nav-item[data-menu-id]');

        // 应用记忆状态（覆盖服务端默认：默认只展开当前选中路径）
        var state = loadState();
        items.forEach(function (item) {
            var ul = item.querySelector('.nav-treeview');
            if (!ul) { return; }
            var st = state[menuId(item)];
            if (st !== 'open' && st !== 'closed') { return; }
            if (st === 'open') {
                item.classList.add('menu-open');
                ul.style.display = 'block';
            } else {
                item.classList.remove('menu-open');
                ul.style.display = 'none';
            }
        });

        // 持久化用户的展开/折叠操作
        items.forEach(function (item) {
            if (!item.querySelector('.nav-treeview')) { return; }
            item.addEventListener('expanded.zui.treeview', function () {
                var s = loadState();
                s[menuId(item)] = 'open';
                saveState(s);
            });
            item.addEventListener('collapsed.zui.treeview', function () {
                var s = loadState();
                s[menuId(item)] = 'closed';
                saveState(s);
            });
        });
    });
})();
</script>
