<?php defined('IN_ZAPCMS') or die('No permission to access');

// 优先使用传入参数，否则从 PageState 获取
$state = pageState();
$title = htmlspecialchars(
    ($node['title'] ?? '') ?: ($state->node['title'] ?? '')
);

$catalogs = !empty($subCatalogList) ? $subCatalogList : ($state->subCatalogList ?? []);
$currentId = $nodeId ?? $state->nodeId ?? 0;

// 获取 catlogPaths 第一个的title（搜索页等未设置栏目路径时安全降级）
$catalogPaths = $state->catalogPaths ?? [];
$firstCatalog = $catalogPaths[0] ?? null;
$firstCatalogTitle = $firstCatalog['title'] ?? '';

$lastCatalog = !empty($catalogPaths) ? end($catalogPaths) : null;
$lastCatalogId = $lastCatalog['id'] ?? 0;

?>
<aside class="sidebar col-sm-3">
    <?php
    // 主题小工具区域：sidebar（后台「外观 > 小工具」配置，未配置时输出空）
    echo widget('sidebar');
    ?>
    <?php if (!empty($catalogs)): ?>
    <div class="widget">
        <h4><?php echo e(!empty($firstCatalogTitle) ? $firstCatalogTitle : $title); ?></h4>
        <ul>
            <?php foreach ($catalogs as $catalog):
                // link-url 类型：slug 是占位符 --zap-link-url，smart_node_url 会根据 link_type/link_to/link_object 生成真实 URL
                $isLinkUrl = ($catalog['node_type'] ?? '') === 'link-url';
                $href = smart_node_url($catalog);
                $linkTarget = $isLinkUrl ? ($catalog['link_target'] ?? '') : '';
            ?>
                <li<?php if ($lastCatalogId == $catalog['id']) echo ' class="current"'; ?>>
                    <a href="<?php echo $href; ?>"
                       title="<?php echo htmlspecialchars($catalog['title']); ?>"
                       <?php if ($linkTarget === '_blank'): ?>target="_blank" rel="noopener"<?php endif; ?>>
                        <?php echo htmlspecialchars($catalog['title']); ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>
</aside>
