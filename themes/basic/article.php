<?php
defined('IN_ZAPCMS') or die('No permission to access');
$this->extend('layout/default');
$this->beginBlock('content');
/**
 * @var array $node
 */
$nodeType = $node['node_type'] ?? 'article';
?>
    <?php echo $this->partial('partials/_breadcrumb'); ?>
    <div class="container">
        <div class="row">
            <?php echo $this->partial('partials/_sidebar'); ?>
            <div class="col-sm-9">
                <div class="content-wrap detail-wrap">
                    <div class="row">
                        <div class="col-sm-12">
                            <article class="single-post">
                                <header class="entry-header">
                                    <h1 class="entry-title"><?php echo htmlspecialchars($node['title']); ?></h1>
                                    <ul class="entry-meta">
                                        <li><i class="fa fa-calendar"></i> <?php echo date('Y-m-d', intval($node['pub_time'] ?? 0) ?: time()); ?></li>
                                        <li><i class="fa fa-eye"></i> <?php echo intval($node['hits'] ?? 0); ?> 次浏览</li>
                                    </ul>
                                </header><!-- /.entry-header -->
                                <?php if (!empty($node['image'])): ?>
                                <div class="post-thumbnail">
                                    <img src="<?php echo \zapcms\helpers\ThumbHelper::thumb($node['image'], (int)\zapcms\services\NodeType::getConfig($nodeType, 'detail_image_width', 750), (int)\zapcms\services\NodeType::getConfig($nodeType, 'detail_image_height', 480)); ?>" class="img-fluid" alt="<?php echo htmlspecialchars($node['title']); ?>">
                                </div><!-- /.post-thumbnail -->
                                <?php endif; ?>
                                <div class="entry-content">
                                    <?php echo $node['content']; ?>
                                </div><!-- /.entry-content -->
                            </article><!-- /.single-post -->
                        </div>
                    </div>
                </div><!-- /.content-wrap -->
            </div>
        </div>
    </div>
<?php $this->endBlock(); ?>
