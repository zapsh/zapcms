<?php
defined('IN_ZAPCMS') or die('No permission to access');
$this->extend('layout/default');
$this->beginBlock('content');
/**
 * @var array $node
 */
// 主图：原图存在生成对应尺寸缩略图（原名+尺寸），不存在则显示占位图原名，不产生重复缩略图
// 尺寸从内容模型显示配置读取（默认 750x480）
$nodeType = $node['node_type'] ?? 'product';
$detailW  = (int)\zapcms\services\NodeType::getConfig($nodeType, 'detail_image_width', 750);
$detailH  = (int)\zapcms\services\NodeType::getConfig($nodeType, 'detail_image_height', 480);
$imageUrl = \zapcms\helpers\ThumbHelper::thumb($node['image'] ?? '', $detailW, $detailH);
?>
    <?php echo $this->partial('partials/_breadcrumb'); ?>
    <div class="container">
        <div class="row">
            <?php echo $this->partial('partials/_sidebar'); ?>
            <div class="col-sm-9">
                <div class="content-wrap">
                    <div class="product-detail">
                        <div class="row">
                            <div class="col-sm-6">
                                <div class="product-gallery text-center">
                                    <img src="<?php echo $imageUrl; ?>" class="product-detail-img img-fluid" alt="<?php echo htmlspecialchars($node['title'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <h2 class="product-title"><?php echo htmlspecialchars($node['title'] ?? ''); ?></h2>
                                <?php if (!empty($node['meta']['price'])): ?>
                                <div class="product-price">
                                    <span class="product-price-label">价格</span>
                                    <span class="product-price-currency">¥</span>
                                    <span class="product-price-value"><?php echo number_format(floatval($node['meta']['price']), 2); ?></span>
                                </div>
                                <?php endif; ?>
                                <?php if (!empty($node['excerpt'])): ?>
                                <p class="product-excerpt text-muted"><?php echo htmlspecialchars($node['excerpt']); ?></p>
                                <?php endif; ?>
                                <div class="product-meta">
                                    <?php if (!empty($node['pub_time'])): ?>
                                    <span class="text-muted"><i class="fa fa-calendar"></i> <?php echo date('Y-m-d', is_numeric($node['pub_time']) ? intval($node['pub_time']) : strtotime($node['pub_time'])); ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($node['hits'])): ?>
                                    <span class="text-muted">&nbsp;&nbsp;<i class="fa fa-eye"></i> <?php echo intval($node['hits']); ?> 次浏览</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php if (!empty($node['content'])): ?>
                        <div class="product-content">
                            <h3 class="section-title">产品详情</h3>
                            <div class="post-content">
                                <?php echo $node['content']; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php $this->endBlock(); ?>
