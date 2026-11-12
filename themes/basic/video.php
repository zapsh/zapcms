<?php
defined('IN_ZAPCMS') or die('No permission to access');
$this->extend('layout/default');
$this->beginBlock('content');
/**
 * 视频模型详情页：播放器 + 简介 + 付费预留
 * 注意：付费观看的真实拦截（购买/会员校验）将在「会员系统」接入阶段实现；
 *       当前仅展示付费标识与价格，播放器仍可直接预览。
 * @var array $node
 */
$nodeType   = $node['node_type'] ?? 'video';
$detailW    = (int)\zapcms\services\NodeType::getConfig($nodeType, 'detail_image_width', 750);
$detailH    = (int)\zapcms\services\NodeType::getConfig($nodeType, 'detail_image_height', 480);
$videoUrl   = $node['meta']['video_url'] ?? '';
$videoCover = $node['meta']['video_cover'] ?? ($node['image'] ?? '');
$poster     = \zapcms\helpers\ThumbHelper::thumb($videoCover, $detailW, $detailH);
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
                    <div class="video-detail">
                        <h2 class="video-title"><?php echo htmlspecialchars($node['title'] ?? ''); ?></h2>
                        <?php if (!empty($node['excerpt'])): ?>
                        <p class="video-excerpt text-muted"><?php echo htmlspecialchars($node['excerpt']); ?></p>
                        <?php endif; ?>
                        <div class="video-player mb-3">
                            <?php if ($videoUrl !== ''): ?>
                            <video class="img-fluid w-100 rounded" controls preload="metadata"<?php if ($poster !== ''): ?> poster="<?php echo htmlspecialchars($poster, ENT_QUOTES); ?>"<?php endif; ?>>
                                <source src="<?php echo htmlspecialchars($videoUrl, ENT_QUOTES); ?>">
                                您的浏览器不支持 video 标签。
                            </video>
                            <?php else: ?>
                            <img src="<?php echo htmlspecialchars($coverThumb, ENT_QUOTES); ?>" class="img-fluid w-100 rounded" alt="">
                            <?php endif; ?>
                        </div>
                        <?php if ($isPaid): ?>
                        <div class="alert alert-warning d-flex align-items-center justify-content-between" role="alert">
                            <div>
                                <i class="fa fa-lock me-1"></i> 付费内容
                                <?php if ($price > 0): ?><strong class="ms-1">¥<?php echo number_format($price, 2); ?></strong><?php endif; ?>
                                <?php if ($vipFree): ?><span class="badge text-bg-success ms-2">会员免费</span><?php endif; ?>
                            </div>
                            <button type="button" class="btn btn-warning btn-sm" disabled title="接入会员系统后开放购买/播放">
                                <i class="fa fa-shopping-cart me-1"></i>购买观看
                            </button>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($node['meta']['duration'])): ?>
                        <div class="text-muted small mb-2"><i class="fa fa-clock me-1"></i>时长：<?php echo htmlspecialchars($node['meta']['duration']); ?></div>
                        <?php endif; ?>
                        <?php if (!empty($node['content'])): ?>
                        <div class="video-content mt-3">
                            <h3 class="section-title">视频简介</h3>
                            <div class="post-content"><?php echo $node['content']; ?></div>
                        </div>
                        <?php endif; ?>
                        <div class="video-meta text-muted small mt-2">
                            <?php if (!empty($node['pub_time'])): ?>
                            <span><i class="fa fa-calendar"></i> <?php echo date('Y-m-d', is_numeric($node['pub_time']) ? intval($node['pub_time']) : strtotime($node['pub_time'])); ?></span>
                            <?php endif; ?>
                            <?php if (!empty($node['hits'])): ?>
                            &nbsp;&nbsp;<span><i class="fa fa-eye"></i> <?php echo intval($node['hits']); ?> 次播放</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php $this->endBlock(); ?>
