<?php
defined('IN_ZAPCMS') or die('No permission to access');
$this->extend('layout/default');
$this->beginBlock('content');
/**
 * 视频模型列表页（封面 + 播放图标 + 时长角标）
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
                            $imgW = (int)\zapcms\services\NodeType::getConfig($node['node_type'] ?? 'video', 'list_image_width', 270);
                            $imgH = (int)\zapcms\services\NodeType::getConfig($node['node_type'] ?? 'video', 'list_image_height', 210);
                            $meta     = $node['meta'] ?? [];
                            $vCover   = $meta['video_cover'] ?? ($node['image'] ?? '');
                            $thumb    = \zapcms\helpers\ThumbHelper::thumb($vCover ?: $node['image'], $imgW, $imgH);
                            $duration = $meta['duration'] ?? '';
                        ?>
                        <div class="col-sm-4 mb-3">
                            <div class="post-content position-relative">
                                <a href="<?php echo smart_node_url($node); ?>">
                                    <img class="img-fluid rounded" src="<?php echo htmlspecialchars($thumb, ENT_QUOTES); ?>"
                                         alt="<?php echo htmlspecialchars($node['title']); ?>"
                                         style="object-fit:cover;height:180px;width:100%;" />
                                    <span class="position-absolute top-50 start-50 translate-middle">
                                        <i class="fa fa-play-circle fa-3x text-white" style="text-shadow:0 0 8px rgba(0,0,0,.6);"></i>
                                    </span>
                                    <?php if ($duration !== ''): ?>
                                    <span class="position-absolute bottom-0 end-0 m-1 badge text-bg-dark"><?php echo htmlspecialchars($duration); ?></span>
                                    <?php endif; ?>
                                </a>
                                <div class="content-wrap">
                                    <h4><a href="<?php echo smart_node_url($node); ?>"><?php echo htmlspecialchars($node['title']); ?></a></h4>
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
