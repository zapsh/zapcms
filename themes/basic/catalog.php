<?php
defined('IN_ZAPCMS') or die('No permission to access');
$this->extend('layout/default');
$this->beginBlock('content');
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
                    if (trim($adv) !== '') {
                        echo $adv;
                    } else {
                    ?>
                    <div class="row">
                        <?php if (empty($nodes)): ?>
                        <div class="col-sm-12">
                            <p>暂无内容</p>
                        </div>
                        <?php else: foreach ($nodes as $node): $imgW = \zapcms\services\NodeType::getConfig($node['node_type'] ?? 'article', 'list_image_width', 270); $imgH = \zapcms\services\NodeType::getConfig($node['node_type'] ?? 'article', 'list_image_height', 210); ?>
                        <div class="col-sm-4">
                            <div class="post-content">
                                <a href="<?php echo smart_node_url($node); ?>">
                                    <img class="img-fluid" src="<?php echo \zapcms\helpers\ThumbHelper::thumb($node['image'], $imgW, $imgH); ?>" alt="<?php echo htmlspecialchars($node['title']); ?>" />
                                </a>
                                <div class="content-wrap">
                                    <h4><a href="<?php echo smart_node_url($node); ?>"><?php echo htmlspecialchars($node['title']); ?></a></h4>
                                    <ul class="list-inline post-meta">
                                        <li><i class="fa fa-calendar"></i> <?php echo date('Y-m-d', intval($node['pub_time'] ?: 0)); ?></li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; endif; ?>
                    </div>
                    <?php echo $this->partial('partials/_pagination', ['page' => $page ?? null]); ?>
                    <?php
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
<?php $this->endBlock(); ?>
