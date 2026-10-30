<?php
defined('IN_ZAPCMS') or die('No permission to access');
$this->extend('layout/default');

?>
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
                            <a href="<?php echo node_url($node['id'], $node['node_type']); ?>">
                                <img class="img-fluid" src="<?php echo \zapcms\helpers\ThumbHelper::thumb($node['image'], $imgW, $imgH); ?>" alt="<?php echo htmlspecialchars($node['title']); ?>" />
                            </a>
                            <div class="content-wrap">
                                <h5><a href="<?php echo node_url($node['id'], $node['node_type']); ?>"><?php echo htmlspecialchars($node['title']); ?></a></h5>
                                <?php $excerpt = trim(strip_tags((string)($node['excerpt'] ?? ''))); ?>
                                <?php if ($excerpt !== ''): ?>
                                <p class="card-excerpt"><?php echo htmlspecialchars(mb_substr($excerpt, 0, 80)); ?></p>
                                <?php endif; ?>
                                <ul class="list-inline post-meta">
                                    <li><i class="fa fa-calendar"></i> <?php echo date('Y-m-d', intval($node['pub_time'] ?: 0)); ?></li>
                                </ul>
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

