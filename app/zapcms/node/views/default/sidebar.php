<?php
use zap\facades\Url;
use zapcms\services\Catalog;
use zapcms\models\Node;

/**
 * @var \zapcms\services\Catalog $menu
 * @var int $catalogId
 */
$menu = $menu ?? Catalog::instance();
$catalogId = $catalogId ?? 0;

$tree = $menu->getTreeArray();

/**
 * 递归收集选中栏目的所有祖先 id，用于展开路径
 */
function collectAncestorIds($tree, $activeId) {
    $path = [];
    foreach ($tree as $item) {
        if (isset($item['id']) && $item['id'] == $activeId) {
            return [$item['id']];
        }
        if (!empty($item['children'])) {
            $childPath = collectAncestorIds($item['children'], $activeId);
            if (!empty($childPath)) {
                $path[] = $item['id'];
                $path = array_merge($path, $childPath);
                return $path;
            }
        }
    }
    return $path;
}

// 获取要展开的节点 id 集合
$expandIds = [];
if ($catalogId > 0) {
    $expandIds = collectAncestorIds($tree, $catalogId);
}
$expandIdsMap = array_flip($expandIds);

/**
 * 解析 link-url 栏目的目标链接
 * link-url 是导航链接，不存实际内容，需要解析到真正的目标
 */
function resolveLinkUrlTarget($item, $menu) {
    $linkType = $item['link_type'] ?? '';
    $linkObject = intval($item['link_object'] ?? 0);
    $linkTo = $item['link_to'] ?? '';

    if ($linkType === 'catalog' && $linkObject > 0) {
        // 链接到另一个栏目 → 打开目标栏目的内容列表
        $targetCatalog = $menu->get($linkObject);
        if ($targetCatalog) {
            $targetNodeType = $targetCatalog['node_type'] ?? 'page';
            return Url::action("Node@{$targetNodeType}", ['cid' => $linkObject]);
        }
    } elseif ($linkType === 'node' && $linkObject > 0) {
        // 链接到具体内容节点 → 打开该节点的编辑页
        $targetNode = Node::findById($linkObject);
        if ($targetNode) {
            $targetNodeType = $targetNode->node_type ?? 'page';
            return Url::action("Node@{$targetNodeType}/edit/{$linkObject}");
        }
    } elseif ($linkType === 'external') {
        // 外部链接 → 直接用 link_to 作为 URL，_blank 打开
        return $linkTo ?: '#';
    }
    // fallback: 无法解析时使用 link_to
    return $linkTo ?: Url::action("Node@page", ['cid' => $item['id']]);
}

/**
 * 递归渲染树节点
 */
function renderTree($items, $catalogId, $expandIdsMap, $level = 0) {
    // 每层级缩进：base + level * step（用 inline style 更精确）
    $indentMap = [0 => 0.5, 1 => 2.5, 2 => 4.5, 3 => 6.5];
    foreach ($items as $item):
        $hasChildren = !empty($item['children']);
        $isActive = $catalogId == $item['id'];
        $isExpanded = $isActive || isset($expandIdsMap[$item['id']]);
        $paddingLeft = $indentMap[$level] ?? (2.5 + ($level - 2) * 2);
        // catalog 表中 node_type 存的是真实内容类型（page/article/product 等）
        $nodeType = $item['node_type'] ?? 'default';
        $isLinkUrl = $nodeType === 'link-url';

        // link-url 类型：解析目标链接，不生成 Node@link-url 这种无效地址
        if ($isLinkUrl) {
            $nodeUrl = resolveLinkUrlTarget($item, Catalog::instance());
        } else {
            $nodeUrl = Url::action("Node@{$nodeType}", ['cid' => $item['id']]);
        }
?>
    <div class="tree-node">
        <a href="<?php echo e($nodeUrl); ?>"
           class="list-group-item list-group-item-action border-0 d-flex align-items-center <?php echo $isActive ? 'active' : ''; ?>"
           data-catalog-id="<?php echo e($item['id']); ?>"
           style="padding-left: <?php echo e($paddingLeft); ?>rem;">
            <?php if ($hasChildren): ?>
            <span class="tree-toggle me-2" onclick="toggleTreeNode(event, this)" style="cursor:pointer;width:16px;text-align:center;flex-shrink:0;">
                <i class="fa fa-caret-<?php echo $isExpanded ? 'down' : 'right'; ?>"></i>
            </span>
            <?php else: ?>
            <span style="width:16px" class="me-2 flex-shrink-0"></span>
            <?php endif; ?>
            <?php if ($isLinkUrl): ?>
            <i class="fa fa-link me-2 text-primary" style="width:14px;flex-shrink:0;"></i>
            <?php elseif ($nodeType === 'page'): ?>
            <i class="far fa-file me-2 text-info" style="width:14px;flex-shrink:0;"></i>
            <?php else: ?>
            <i class="far fa-folder me-2 text-warning" style="width:14px;flex-shrink:0;"></i>
            <?php endif; ?>
            <span class="flex-grow-1 text-truncate"><?php echo htmlspecialchars($item['title']); ?></span>
        </a>
        <?php if ($hasChildren): ?>
        <div class="tree-children<?php echo $isExpanded ? '' : ' d-none'; ?>">
            <?php renderTree($item['children'], $catalogId, $expandIdsMap, $level + 1); ?>
        </div>
        <?php endif; ?>
    </div>
<?php
    endforeach;
}
?>

<div class="list-group list-group-flush">
    <?php if (!empty($tree)): ?>
        <?php renderTree($tree, $catalogId, $expandIdsMap); ?>
    <?php else: ?>
    <div class="text-center text-muted py-4">
        <i class="fa fa-inbox fa-2x d-block mb-2"></i>
        <small>暂无栏目</small>
    </div>
    <?php endif; ?>
</div>

<script>
function toggleTreeNode(e, el) {
    e.preventDefault();
    e.stopPropagation();
    var children = el.closest('.tree-node').querySelector('.tree-children');
    var icon = el.querySelector('i');
    if (children) {
        children.classList.toggle('d-none');
        if (icon) {
            if (icon.classList.contains('d-none')) {
                icon.classList.remove('fa-caret-down');
                icon.classList.add('fa-caret-right');
            } else {
                icon.classList.remove('fa-caret-right');
                icon.classList.add('fa-caret-down');
            }
        }
    }
}
</script>
