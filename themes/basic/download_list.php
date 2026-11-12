<?php
defined('IN_ZAPCMS') or die('No permission to access');
$this->extend('layout/default');
$this->beginBlock('content');
/**
 * 文件下载模型列表页（封面 + 格式/大小）
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
                            $imgW = (int)\zapcms\services\NodeType::getConfig($node['node_type'] ?? 'download', 'list_image_width', 270);
                            $imgH = (int)\zapcms\services\NodeType::getConfig($node['node_type'] ?? 'download', 'list_image_height', 210);
                            $meta    = $node['meta'] ?? [];
                            $fileExt  = $meta['file_ext'] ?? '';
                            $fileSize = $meta['file_size'] ?? '';
                        ?>
                        <div class="col-sm-4 mb-3">
                            <div class="card h-100">
                                <a href="<?php echo smart_node_url($node); ?>">
                                    <img class="card-img-top" src="<?php echo \zapcms\helpers\ThumbHelper::thumb($node['image'], $imgW, $imgH); ?>"
                                         alt="<?php echo htmlspecialchars($node['title']); ?>"
                                         style="object-fit:cover;height:160px;width:100%;" />
                                </a>
                                <div class="card-body p-2">
                                    <h4 class="h6 mb-1"><a href="<?php echo smart_node_url($node); ?>"><?php echo htmlspecialchars($node['title']); ?></a></h4>
                                    <div class="text-muted small">
                                        <?php if ($fileExt !== ''): ?><i class="fa fa-file me-1"></i><?php echo htmlspecialchars($fileExt); ?><?php endif; ?>
                                        <?php if ($fileSize !== ''): ?>&nbsp;<?php echo htmlspecialchars($fileSize); ?><?php endif; ?>
                                    </div>
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
