<?php
defined('IN_ZAPCMS') or die('No permission to access');
$this->extend('layout/default');
$this->beginBlock('content');
/**
 * 文件下载模型详情页：文件信息 + 下载按钮 + 付费预留
 * 注意：付费下载的真实拦截（购买/会员校验、下载计数）将在「会员系统」接入阶段实现；
 *       当前仅展示付费标识与价格，下载链接仍可直接点击（用于预览）。
 * @var array $node
 */
$nodeType   = $node['node_type'] ?? 'download';
$detailW    = (int)\zapcms\services\NodeType::getConfig($nodeType, 'detail_image_width', 750);
$detailH    = (int)\zapcms\services\NodeType::getConfig($nodeType, 'detail_image_height', 480);
$fileUrl    = $node['meta']['file_url'] ?? '';
$fileSize   = $node['meta']['file_size'] ?? '';
$fileExt    = $node['meta']['file_ext'] ?? '';
$isPaid     = ((string) ($node['meta']['is_paid'] ?? '0')) === '1';
$price      = floatval($node['meta']['price'] ?? 0);
$vipFree    = ((string) ($node['meta']['vip_free'] ?? '0')) === '1';
$coverThumb = \zapcms\helpers\ThumbHelper::thumb($node['image'] ?? '', $detailW, $detailH);
?>
    <?php echo $this->partial('partials/_breadcrumb'); ?>
    <div class="container">
        <div class="row">
            <?php echo $this->partial('partials/_sidebar'); ?>
            <div class="col-sm-9">
                <div class="content-wrap">
                    <div class="download-detail">
                        <div class="row">
                            <div class="col-sm-4">
                                <img src="<?php echo htmlspecialchars($coverThumb, ENT_QUOTES); ?>" class="img-fluid rounded w-100"
                                     style="object-fit:cover;height:200px;" alt="<?php echo htmlspecialchars($node['title'] ?? '', ENT_QUOTES); ?>">
                            </div>
                            <div class="col-sm-8">
                                <h2 class="download-title"><?php echo htmlspecialchars($node['title'] ?? ''); ?></h2>
                                <?php if (!empty($node['excerpt'])): ?>
                                <p class="download-excerpt text-muted"><?php echo htmlspecialchars($node['excerpt']); ?></p>
                                <?php endif; ?>
                                <ul class="list-inline download-meta text-muted small mb-3">
                                    <?php if ($fileExt !== ''): ?><li class="list-inline-item"><i class="fa fa-file me-1"></i><?php echo htmlspecialchars($fileExt); ?></li><?php endif; ?>
                                    <?php if ($fileSize !== ''): ?><li class="list-inline-item"><i class="fa fa-hdd me-1"></i><?php echo htmlspecialchars($fileSize); ?></li><?php endif; ?>
                                    <?php if (!empty($node['pub_time'])): ?><li class="list-inline-item"><i class="fa fa-calendar me-1"></i><?php echo date('Y-m-d', is_numeric($node['pub_time']) ? intval($node['pub_time']) : strtotime($node['pub_time'])); ?></li><?php endif; ?>
                                    <?php if (!empty($node['hits'])): ?><li class="list-inline-item"><i class="fa fa-download me-1"></i><?php echo intval($node['hits']); ?> 次下载</li><?php endif; ?>
                                </ul>
                                <?php if ($isPaid): ?>
                                <div class="alert alert-warning d-flex align-items-center justify-content-between mb-3" role="alert">
                                    <div>
                                        <i class="fa fa-lock me-1"></i> 付费下载
                                        <?php if ($price > 0): ?><strong class="ms-1">¥<?php echo number_format($price, 2); ?></strong><?php endif; ?>
                                        <?php if ($vipFree): ?><span class="badge text-bg-success ms-2">会员免费</span><?php endif; ?>
                                    </div>
                                    <button type="button" class="btn btn-warning btn-sm" disabled title="接入会员系统后开放购买/下载">
                                        <i class="fa fa-shopping-cart me-1"></i>购买下载
                                    </button>
                                </div>
                                <?php endif; ?>
                                <?php if ($fileUrl !== ''): ?>
                                <a href="<?php echo htmlspecialchars($fileUrl, ENT_QUOTES); ?>" class="btn btn-primary btn-lg"<?php if (!$isPaid): ?> download<?php endif; ?>>
                                    <i class="fa fa-download me-1"></i><?php echo $isPaid ? '购买后下载' : '立即下载'; ?>
                                </a>
                                <?php else: ?>
                                <span class="text-muted">暂未提供下载文件</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if (!empty($node['content'])): ?>
                        <div class="download-content mt-3">
                            <h3 class="section-title">资源详情</h3>
                            <div class="post-content"><?php echo $node['content']; ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php $this->endBlock(); ?>
