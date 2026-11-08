<?php
use zap\facades\Url;
use zapcms\services\Catalog;
use zapcms\services\Locale;
use zapcms\services\MenuItem;
use zapcms\services\Translator;
use zapcms\models\Node;

/**
 * 内容管理页左侧导航。
 *
 * 优先使用「顶部导航（top）」菜单（menu_items，由 外观 > 菜单 构建）组织内容导航；
 * 该菜单为空时回退到栏目树（catalog），保证全新安装也能正常使用。
 *
 * @var \zapcms\services\Catalog $menu
 * @var int $catalogId  当前选中栏目 id
 */
$menu = $menu ?? Catalog::instance();
$catalogId = intval($catalogId ?? 0);

if (!function_exists('contentNavNodeFromCatalog')) {
    /**
     * 多语言：栏目树标题套用当前后台语言译文（一次批量查询，无 N+1）。
     * 未开启多语言或当前为默认语言时原样返回，零额外查询。
     * 栏目译文统一按 node 维度存放（object_id 与栏目 id 相同）。
     */
    function contentNavTranslateTree(array $rows): array
    {
        if ($rows === [] || !Locale::enabled()) {
            return $rows;
        }
        $locale = Locale::current();
        if ($locale === Locale::defaultLocale()) {
            return $rows;
        }

        // 递归收集全部节点 id
        $ids = [];
        $collect = function (array $items) use (&$collect, &$ids): void {
            foreach ($items as $item) {
                $id = (int) ($item['id'] ?? 0);
                if ($id > 0) {
                    $ids[$id] = $id;
                }
                if (!empty($item['children']) && is_array($item['children'])) {
                    $collect($item['children']);
                }
            }
        };
        $collect($rows);

        $map = Translator::getMap(Translator::TYPE_NODE, array_values($ids), $locale);
        if ($map === []) {
            return $rows;
        }

        $apply = function (array $items) use (&$apply, $map, $locale): array {
            foreach ($items as $k => $item) {
                $t = $map[(int) ($item['id'] ?? 0)] ?? null;
                if ($t !== null) {
                    $items[$k] = Translator::applyWith($item, $t, $locale, ['title']);
                }
                if (!empty($item['children']) && is_array($item['children'])) {
                    $items[$k]['children'] = $apply($item['children']);
                }
            }
            return $items;
        };
        return $apply($rows);
    }

    /**
     * 多语言：单行栏目/节点标题套用当前语言译文（菜单引用单条记录的场景）
     */
    function contentNavTranslateRow(array $row): array
    {
        if ($row === [] || !Locale::enabled()) {
            return $row;
        }
        return Translator::apply($row, null, Translator::TYPE_NODE, ['title']);
    }

    /**
     * 把栏目行归一化为导航节点
     */
    function contentNavNodeFromCatalog($menu, array $cat, int $catalogId, array $children = []): array
    {
        $nodeType = $cat['node_type'] ?? 'page';
        $url = Url::action("Node@{$nodeType}", ['cid' => intval($cat['id'])]);
        $icon = $nodeType === 'page' ? 'far fa-file' : 'far fa-folder';
        return [
            'title'       => $cat['title'] ?? '',
            'url'         => $url,
            'icon'        => $icon,
            'active'      => intval($cat['id']) === $catalogId,
            'cid'         => intval($cat['id'] ?? 0),
            'children'    => $children,
        ];
    }

    /**
     * 从「顶部导航」菜单构建归一化导航树
     */
    function contentNavFromMenu($menu, int $catalogId): array
    {
        $items = MenuItem::instance()->all('top');
        if (empty($items)) {
            return [];
        }
        $byParent = [];
        foreach ($items as $it) {
            $byParent[(int) $it['parent_id']][] = $it;
        }
        $catCache = [];

        $build = function (int $pid) use (&$build, $byParent, $menu, &$catCache, $catalogId): array {
            $out = [];
            foreach ($byParent[$pid] ?? [] as $it) {
                $type = $it['item_type'] ?? '';
                $ref  = intval($it['ref_id'] ?? 0);
                $icon = $it['icon'] ?? '';
                $node = null;

                if ($type === 'catalog' && $ref > 0) {
                    if (!array_key_exists($ref, $catCache)) {
                        // 多语言：菜单引用的栏目标题按当前语言取译文（catCache 记忆化，每个栏目只查一次）
                        $catCache[$ref] = contentNavTranslateRow((array) $menu->get($ref));
                    }
                    $cat = $catCache[$ref];
                    if (empty($cat)) {
                        continue; // 栏目已被删除
                    }
                    $node = contentNavNodeFromCatalog($menu, $cat, $catalogId);
                } elseif ($type === 'node' && $ref > 0) {
                    $targetNode = Node::findById($ref);
                    if (empty($targetNode)) {
                        continue;
                    }
                    // 菜单项未自定义标题时取节点标题，并按当前语言取译文
                    $fallbackTitle = contentNavTranslateRow(['id' => $ref, 'title' => $targetNode->title ?? ''])['title'] ?? '';
                    $node = [
                        'title'    => $it['title'] ?: $fallbackTitle,
                        'url'      => Url::action("Node@{$targetNode->node_type}/edit/{$ref}"),
                        'icon'     => $icon ?: 'far fa-file-alt',
                        'active'   => false,
                        'children' => [],
                    ];
                } else {
                    // 自定义链接
                    $url = $it['url'] ?? '';
                    if ($url === '') {
                        continue;
                    }
                    $node = [
                        'title'    => $it['title'] ?: $url,
                        'url'      => $url,
                        'icon'     => $icon ?: 'fa fa-external-link-alt',
                        'active'   => false,
                        'children' => [],
                    ];
                }

                if ($node === null) {
                    continue;
                }
                if ($icon !== '' && $type === 'catalog') {
                    $node['icon'] = $icon; // 菜单项自定义图标优先
                }
                $node['children'] = $build(intval($it['id']));
                $out[] = $node;
            }
            return $out;
        };

        return $build(0);
    }

    /**
     * 从栏目树构建归一化导航树（回退路径）
     */
    function contentNavFromCatalogTree($menu, int $catalogId): array
    {
        $build = function (array $items) use (&$build, $menu, $catalogId): array {
            $out = [];
            foreach ($items as $item) {
                $children = !empty($item['children']) ? $build($item['children']) : [];
                $out[] = contentNavNodeFromCatalog($menu, $item, $catalogId, $children);
            }
            return $out;
        };
        // 多语言：整棵树的标题先批量套用译文（getMap 一次查询，无 N+1）
        return $build(contentNavTranslateTree($menu->getTreeArray()));
    }

    function contentNavHasActive(array $node): bool
    {
        if (!empty($node['active'])) {
            return true;
        }
        foreach ($node['children'] ?? [] as $child) {
            if (contentNavHasActive($child)) {
                return true;
            }
        }
        return false;
    }

    /**
     * 渲染归一化导航树
     */
    function contentNavRender(array $nodes, int $level = 0): void
    {
        $indentMap = [0 => 0.5, 1 => 2.5, 2 => 4.5, 3 => 6.5];
        foreach ($nodes as $node):
            $hasChildren = !empty($node['children']);
            $isActive    = !empty($node['active']);
            $isExpanded  = $isActive || contentNavHasActive($node);
            $paddingLeft = $indentMap[$level] ?? (2.5 + ($level - 2) * 2);
    ?>
    <div class="tree-node"<?php echo ($node['cid'] ?? 0) > 0 ? ' data-cid="' . e($node['cid']) . '"' : ''; ?>>
        <a href="<?php echo e($node['url']); ?>"
           class="list-group-item list-group-item-action border-0 d-flex align-items-center <?php echo $isActive ? 'active' : ''; ?>"
           style="padding-left: <?php echo e($paddingLeft); ?>rem;">
            <?php if ($hasChildren): ?>
            <span class="tree-toggle me-2" onclick="toggleTreeNode(event, this)" style="cursor:pointer;width:16px;text-align:center;flex-shrink:0;">
                <i class="fa fa-caret-<?php echo $isExpanded ? 'down' : 'right'; ?>"></i>
            </span>
            <?php else: ?>
            <span style="width:16px" class="me-2 flex-shrink-0"></span>
            <?php endif; ?>
            <i class="<?php echo e($node['icon']); ?> me-2" style="width:14px;flex-shrink:0;"></i>
            <span class="flex-grow-1 text-truncate"><?php echo htmlspecialchars($node['title']); ?></span>
        </a>
        <?php if ($hasChildren): ?>
        <div class="tree-children<?php echo $isExpanded ? '' : ' d-none'; ?>">
            <?php contentNavRender($node['children'], $level + 1); ?>
        </div>
        <?php endif; ?>
    </div>
    <?php
        endforeach;
    }
}

$navNodes = contentNavFromMenu($menu, $catalogId);
if (empty($navNodes)) {
    $navNodes = contentNavFromCatalogTree($menu, $catalogId); // 菜单为空 → 回退栏目树
}
?>

<div class="list-group list-group-flush">
    <?php if (!empty($navNodes)): ?>
        <?php contentNavRender($navNodes); ?>
    <?php else: ?>
    <div class="text-center text-muted py-4">
        <i class="fa fa-inbox fa-2x d-block mb-2"></i>
        <small>暂无栏目</small>
    </div>
    <?php endif; ?>
</div>

<script>
(function () {
    // 展开状态记忆（localStorage，按栏目 id）
    var KEY = 'zapcms.contentNav.state';
    function loadState() {
        try { return JSON.parse(localStorage.getItem(KEY)) || {}; } catch (e) { return {}; }
    }
    function saveState(s) {
        try { localStorage.setItem(KEY, JSON.stringify(s)); } catch (e) {}
    }
    function applyCollapsed(nodeEl, collapsed) {
        var children = nodeEl.querySelector('.tree-children');
        var icon = nodeEl.querySelector('.tree-toggle i');
        if (!children) { return; }
        children.classList.toggle('d-none', collapsed);
        if (icon) {
            icon.classList.toggle('fa-caret-down', !collapsed);
            icon.classList.toggle('fa-caret-right', collapsed);
        }
    }

    window.toggleTreeNode = function (e, el) {
        e.preventDefault();
        e.stopPropagation();
        var nodeEl = el.closest('.tree-node');
        var children = nodeEl && nodeEl.querySelector('.tree-children');
        if (!children) { return; }
        children.classList.toggle('d-none');
        var collapsed = children.classList.contains('d-none');
        applyCollapsed(nodeEl, collapsed);
        var cid = nodeEl.getAttribute('data-cid');
        if (cid) {
            var s = loadState();
            s[cid] = collapsed ? 'closed' : 'open';
            saveState(s);
        }
    };

    // 页面加载：应用记忆的展开/折叠（覆盖服务端默认——默认只展开当前选中路径）
    var state = loadState();
    document.querySelectorAll('.tree-node[data-cid]').forEach(function (nodeEl) {
        var st = state[nodeEl.getAttribute('data-cid')];
        if (st === 'open' || st === 'closed') {
            applyCollapsed(nodeEl, st === 'closed');
        }
    });
})();
</script>
