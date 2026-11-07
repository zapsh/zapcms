<?php defined('IN_ZAPCMS') or die('No permission to access');

// 优先使用传入参数，否则从 PageState 获取
$state = pageState();
$title = htmlspecialchars(
    ($node['title'] ?? '') ?: ($state->node['title'] ?? '')
);

$catalogs = !empty($subCatalogList) ? $subCatalogList : ($state->subCatalogList ?? []);

// 获取 catlogPaths 第一个的title（搜索页等未设置栏目路径时安全降级）
$catalogPaths = $state->catalogPaths ?? [];
$firstCatalog = $catalogPaths[0] ?? null;
$firstCatalogTitle = $firstCatalog['title'] ?? '';

$lastCatalog = !empty($catalogPaths) ? end($catalogPaths) : null;
$lastCatalogId = (int)($lastCatalog['id'] ?? 0);

/**
 * 判断菜单项或其子孙是否处于激活链路
 *
 * MenuItem 结构带 active 标记（PageState::markActivePath 已标记自身及全部祖先）；
 * 旧栏目树结构无该标记，用「当前栏目 id」兜底比对。
 */
if (!function_exists('_zap_sidebar_item_active')) {
    function _zap_sidebar_item_active(array $item, int $lastCatalogId): bool
    {
        if (!empty($item['active'])) {
            return true;
        }
        if ($lastCatalogId > 0 && (int)($item['id'] ?? 0) === $lastCatalogId) {
            return true;
        }
        foreach (($item['children'] ?? []) as $child) {
            if (is_array($child) && _zap_sidebar_item_active($child, $lastCatalogId)) {
                return true;
            }
        }
        return false;
    }
}

/**
 * 递归渲染侧边栏菜单
 *
 * 数据源统一是 menu_items 菜单树（含 url/target/active/children）；
 * 仍保留对旧栏目行（slug/node_type/link_*）的渲染兼容，便于第三方传入。
 * 选中子项时父级追加 current-ancestor 类同步高亮。
 */
if (!function_exists('_zap_sidebar_render_menu')) {
    function _zap_sidebar_render_menu(array $items, int $lastCatalogId, int $level = 0): string
    {
        $html = '';
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $hasChildren = !empty($item['children']);

            $hasActiveChild = false;
            foreach (($item['children'] ?? []) as $child) {
                if (is_array($child) && _zap_sidebar_item_active($child, $lastCatalogId)) {
                    $hasActiveChild = true;
                    break;
                }
            }
            $selfActive = _zap_sidebar_item_active($item, $lastCatalogId) && !$hasActiveChild;

            $classes = [];
            if ($selfActive) {
                $classes[] = 'current';
            }
            if ($hasActiveChild) {
                $classes[] = 'current-ancestor';
            }

            // MenuItem 项已解析好 url/target；旧栏目行走 smart_node_url
            $isLinkUrl  = ($item['node_type'] ?? '') === 'link-url';
            $href       = $item['url'] ?? smart_node_url($item);
            $linkTarget = $item['target'] ?? ($isLinkUrl ? ($item['link_target'] ?? '') : '');

            $html .= '<li' . ($classes ? ' class="' . implode(' ', $classes) . '"' : '') . '>';
            $html .= '<a href="' . htmlspecialchars($href, ENT_QUOTES) . '"'
                . ' title="' . htmlspecialchars($item['title'] ?? '', ENT_QUOTES) . '"'
                . (($linkTarget === '_blank') ? ' target="_blank" rel="noopener"' : '') . '>';
            $html .= htmlspecialchars($item['title'] ?? '', ENT_QUOTES);
            $html .= '</a>';

            if ($hasChildren) {
                $html .= '<ul class="sub-menu">';
                $html .= _zap_sidebar_render_menu($item['children'], $lastCatalogId, $level + 1);
                $html .= '</ul>';
            }
            $html .= '</li>';
        }
        return $html;
    }
}

?>
<aside class="sidebar col-sm-3">
    <?php
    // 小工具区域：按当前内容模型取（首页/搜索页等无模型时回退 sidebar）
    echo widget((string) (pageState()->nodeMimeType ?: 'sidebar'));
    ?>
    <?php if (!empty($catalogs)): ?>
    <div class="widget">
        <h4><?php echo e(!empty($firstCatalogTitle) ? $firstCatalogTitle : $title); ?></h4>
        <ul>
            <?php echo _zap_sidebar_render_menu((array) $catalogs, $lastCatalogId); ?>
        </ul>
    </div>
    <?php endif; ?>
</aside>
