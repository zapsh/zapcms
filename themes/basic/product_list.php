<?php
defined('IN_ZAPCMS') or die('No permission to access');
$this->extend('layout/default');
$this->beginBlock('content');
?>
<style>
.product-thumb { width: 100%; height: 210px; object-fit: cover; display: block; }
</style>
    <?php echo $this->partial('partials/_breadcrumb'); ?>
    <div class="container">
        <div class="row">
            <?php echo $this->partial('partials/_sidebar'); ?>
            <div class="col-sm-9">
                <div class="content-wrap">
                    <div class="row">
                        <?php if (empty($nodes)): ?>
                        <div class="col-sm-12">
                            <p>暂无内容</p>
                        </div>
                        <?php else: foreach ($nodes as $node): $imgW = \zapcms\services\NodeType::getConfig($node['node_type'] ?? 'article', 'list_image_width', 270); $imgH = \zapcms\services\NodeType::getConfig($node['node_type'] ?? 'article', 'list_image_height', 210); ?>
                        <div class="col-sm-4">
                            <div class="post-content">
                                <a href="<?php echo node_url($node['id'],$node['node_type']); ?>">
                                    <img class="img-fluid product-thumb" src="<?php echo \zapcms\helpers\ThumbHelper::thumb($node['image'], $imgW, $imgH); ?>" alt="<?php echo htmlspecialchars($node['title']); ?>" />
                                </a>
                                <div class="content-wrap">
                                    <h5><a href="<?php echo node_url($node['id'],$node['node_type']); ?>"><?php echo htmlspecialchars($node['title']); ?></a></h5>
                                    <?php if (!empty($node['meta']['price'])): ?>
                                    <div class="product-price">
                                        <span class="product-price-currency">¥</span>
                                        <span class="product-price-value"><?php echo number_format(floatval($node['meta']['price']), 2); ?></span>
                                    </div>
                                    <?php endif; ?>
                                    <!-- <ul class="list-inline post-meta">
                                        <li><i class="fa fa-calendar"></i> <?php echo date('Y-m-d', intval($node['pub_time'] ?: 0)); ?></li>
                                    </ul> -->
                                </div>
                            </div>
                        </div>
                        <?php endforeach; endif; ?>
                    </div>
                    <?php echo $this->partial('partials/_pagination', ['page' => $page ?? null]); ?>
                </div>
            </div>
        </div>
    </div>
<?php $this->endBlock(); ?>
