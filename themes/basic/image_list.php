<?php
defined('IN_ZAPCMS') or die('No permission to access');
$this->extend('layout/default');
$this->beginBlock('content');
/**
 * 图片模型列表页
 */
?>
    <?php echo $this->partial('partials/_breadcrumb'); ?>
    <div class="container">
        <div class="row">
            <?php echo $this->partial('partials/_sidebar'); ?>
            <div class="col-sm-9">
                <div class="content-wrap">
                    <div class="row">
                        <?php if (empty($nodes)): ?>
                        <div class="col-sm-12"><p>暂无内容</p></div>
                        <?php else: foreach ($nodes as $node):
                            $imgW = (int)\zapcms\services\NodeType::getConfig($node['node_type'] ?? 'image', 'list_image_width', 270);
                            $imgH = (int)\zapcms\services\NodeType::getConfig($node['node_type'] ?? 'image', 'list_image_height', 210);
                        ?>
                        <div class="col-sm-4 mb-3">
                            <div class="post-content">
                                <a href="<?php echo smart_node_url($node); ?>">
                                    <img class="img-fluid rounded" src="<?php echo \zapcms\helpers\ThumbHelper::thumb($node['image'], $imgW, $imgH); ?>"
                                         alt="<?php echo htmlspecialchars($node['title']); ?>"
                                         style="object-fit:cover;height:180px;width:100%;" />
                                </a>
                                <div class="content-wrap">
                                    <h4><a href="<?php echo smart_node_url($node); ?>"><?php echo htmlspecialchars($node['title']); ?></a></h4>
                                    <ul class="list-inline post-meta">
                                        <li><i class="fa fa-calendar"></i> <?php echo date('Y-m-d', intval($node['pub_time'] ?: 0)); ?></li>
                                        <?php if (!empty($node['hits'])): ?><li><i class="fa fa-eye"></i> <?php echo intval($node['hits']); ?></li><?php endif; ?>
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
<?php $this->endBlock(); ?>
