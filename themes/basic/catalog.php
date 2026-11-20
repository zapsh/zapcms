<?php
defined('IN_ZAPCMS') or die('No permission to access');
$this->extend('layout/default');
$this->beginBlock('content');

// 栏目列表显示样式（存于 node_meta）：card / list / list_image / list_multi_image / mixed
// mixed 另有「混合模式」子选项（listMixedMode）：image（左图右文）/ text（纯文本无图）
$style = strtolower(trim((string) ($listStyle ?? 'card')));
if (!in_array($style, ['card', 'list', 'list_image', 'list_multi_image', 'mixed'], true)) {
    $style = 'card';
}
?>
    <?php echo $this->partial('partials/_breadcrumb'); ?>
    <div class="container">
        <div class="row">
            <?php echo $this->partial('partials/_sidebar'); ?>
            <div class="col-sm-9">
                <div class="content-wrap">
                    <?php
                    // 产品栏目：mall 启用时用高级搜索（规格/价格/库存/分类筛选）替换默认网格
                    $catalogNode = pageState()->node ?: [];
                    $isProductCatalog = (($catalogNode['node_type'] ?? '') === 'catalog' && ($catalogNode['mime_type'] ?? '') === 'product');
                    $adv = $isProductCatalog ? \zapcms\Hook::fire('mall_product_list', (int) ($catalogNode['id'] ?? 0)) : '';
                    ?>
                    <?php if (trim($adv) !== ''): ?>
                        <?php echo $adv; ?>
                    <?php else: ?>
                        <?php if (empty($nodes)): ?>
                            <div class="col-sm-12">
                                <p><?php echo __t('front.no_content', '暂无内容'); ?></p>
                            </div>
                        <?php elseif ($style === 'card'): ?>
                            <div class="row">
                                <?php foreach ($nodes as $node):
                                    $imgW = \zapcms\services\NodeType::getConfig($node['node_type'] ?? 'article', 'list_image_width', 270);
                                    $imgH = \zapcms\services\NodeType::getConfig($node['node_type'] ?? 'article', 'list_image_height', 210);
                                ?>
                                <div class="col-sm-4">
                                    <div class="post-content">
                                        <a href="<?php echo smart_node_url($node); ?>" class="zap-list-img-link">
                                            <img class="zap-list-img" src="<?php echo \zapcms\helpers\ThumbHelper::thumb($node['image'] ?? '', $imgW, $imgH); ?>" alt="<?php echo htmlspecialchars($node['title']); ?>" loading="lazy" />
                                        </a>
                                        <div class="content-wrap">
                                            <h4><a href="<?php echo smart_node_url($node); ?>"><?php echo htmlspecialchars($node['title']); ?></a></h4>
                                            <ul class="list-inline post-meta">
                                                <li><i class="fa fa-calendar"></i> <?php echo date('Y-m-d', intval($node['pub_time'] ?? 0)); ?></li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php elseif ($style === 'list'): ?>
                            <div class="list-group list-style-list">
                                <?php foreach ($nodes as $node): ?>
                                <a href="<?php echo smart_node_url($node); ?>" class="list-group-item list-group-item-action border-0 border-bottom py-2">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="fw-semibold">
                                            <?php if (!empty($node['is_top'])): ?><span class="badge bg-warning text-dark me-1 align-middle"><?php echo __t('front.top', '置顶'); ?></span><?php endif; ?>
                                            <?php echo htmlspecialchars($node['title']); ?>
                                        </span>
                                        <small class="text-muted"><i class="fa fa-calendar me-1"></i><?php echo date('Y-m-d', intval($node['pub_time'] ?? 0)); ?></small>
                                    </div>
                                </a>
                                <?php endforeach; ?>
                            </div>
                        <?php elseif ($style === 'list_image'): ?>
                            <div class="list-style-list-image zap-article-list">
                                <?php foreach ($nodes as $node): ?>
                                    <?php echo $this->partial('partials/_list_item', ['node' => $node]); ?>
                                <?php endforeach; ?>
                            </div>
                        <?php elseif ($style === 'list_multi_image'): ?>
                            <div class="row list-style-multi">
                                <?php foreach ($nodes as $node):
                                    $imgW = \zapcms\services\NodeType::getConfig($node['node_type'] ?? 'article', 'list_image_width', 270);
                                    $imgH = \zapcms\services\NodeType::getConfig($node['node_type'] ?? 'article', 'list_image_height', 210);
                                ?>
                                <div class="col-6 col-md-4 mb-3">
                                    <a href="<?php echo smart_node_url($node); ?>" class="zap-grid-card">
                                        <img class="zap-list-grid-img" src="<?php echo \zapcms\helpers\ThumbHelper::thumb($node['image'] ?? '', $imgW, $imgH); ?>" alt="<?php echo htmlspecialchars($node['title']); ?>" loading="lazy" />
                                        <span class="zap-grid-title"><?php echo htmlspecialchars($node['title']); ?></span>
                                    </a>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php elseif ($style === 'mixed'): ?>
                            <?php
                            $nodes = array_values($nodes);
                            $mixedText = (strtolower(trim((string) ($listMixedMode ?? 'image'))) === 'text');
                            // 首条特推：on 取第一条做大图/特推块；off 不特推，全部进入下方列表
                            $mixedFeature = (strtolower(trim((string) ($listMixedFeature ?? 'on'))) === 'on');
                            $feature = $mixedFeature ? array_shift($nodes) : null;
                            ?>
                            <?php if ($feature): ?>
                            <div class="mb-3<?php echo $mixedText ? ' pb-2 border-bottom' : ''; ?>">
                                <?php if (!$mixedText && !empty($feature['image'])): ?>
                                    <?php
                                    $imgW = \zapcms\services\NodeType::getConfig($feature['node_type'] ?? 'article', 'list_image_width', 720);
                                    $imgH = \zapcms\services\NodeType::getConfig($feature['node_type'] ?? 'article', 'list_image_height', 360);
                                    ?>
                                    <a href="<?php echo smart_node_url($feature); ?>" class="zap-list-feature-link">
                                        <img class="zap-list-feature-img" src="<?php echo \zapcms\helpers\ThumbHelper::thumb($feature['image'], $imgW, $imgH); ?>" alt="<?php echo htmlspecialchars($feature['title']); ?>" loading="lazy" />
                                    </a>
                                <?php endif; ?>
                                <h4 class="mt-2 mb-1">
                                    <a href="<?php echo smart_node_url($feature); ?>">
                                        <?php if (!empty($feature['is_top'])): ?><span class="badge bg-warning text-dark me-1 align-middle"><?php echo __t('front.top', '置顶'); ?></span><?php endif; ?>
                                        <?php echo htmlspecialchars($feature['title']); ?>
                                    </a>
                                </h4>
                                <small class="text-muted"><i class="fa fa-calendar me-1"></i><?php echo date('Y-m-d', intval($feature['pub_time'] ?? 0)); ?></small>
                                <?php if ($mixedText && trim((string) ($feature['description'] ?? '')) !== ''): ?>
                                <p class="text-muted small mt-2 mb-0"><?php echo htmlspecialchars($feature['description']); ?></p>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                            <div class="<?php echo $mixedText ? 'list-style-list' : 'list-style-list-image zap-article-list'; ?>">
                                <?php foreach ($nodes as $node): ?>
                                <?php if ($mixedText): ?>
                                <a href="<?php echo smart_node_url($node); ?>" class="list-group-item list-group-item-action border-0 border-bottom py-2">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="fw-semibold">
                                            <?php if (!empty($node['is_top'])): ?><span class="badge bg-warning text-dark me-1 align-middle"><?php echo __t('front.top', '置顶'); ?></span><?php endif; ?>
                                            <?php echo htmlspecialchars($node['title']); ?>
                                        </span>
                                        <small class="text-muted"><i class="fa fa-calendar me-1"></i><?php echo date('Y-m-d', intval($node['pub_time'] ?? 0)); ?></small>
                                    </div>
                                </a>
                                <?php else: ?>
                                <?php echo $this->partial('partials/_list_item', ['node' => $node]); ?>
                                <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <?php echo $this->partial('partials/_pagination', ['page' => $page ?? null]); ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<?php $this->endBlock(); ?>
