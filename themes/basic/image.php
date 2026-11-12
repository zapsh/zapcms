<?php
defined('IN_ZAPCMS') or die('No permission to access');
$this->extend('layout/default');
$this->beginBlock('content');
/**
 * 图片模型详情页：封面 + 图集画廊 + 简介/正文 + 版权
 * @var array $node
 */
$nodeType = $node['node_type'] ?? 'image';
$detailW  = (int)\zapcms\services\NodeType::getConfig($nodeType, 'detail_image_width', 750);
$detailH  = (int)\zapcms\services\NodeType::getConfig($nodeType, 'detail_image_height', 480);

// 图集：node_meta.gallery，支持 JSON 数组 或 每行一个地址（与后台 textarea 录入一致）
$galleryImgs = [];
if (!empty($node['image'])) {
    $galleryImgs[] = $node['image'];
}
$galleryRaw = $node['meta']['gallery'] ?? '';
if ($galleryRaw !== '' && $galleryRaw !== null) {
    $decoded = json_decode((string) $galleryRaw, true);
    if (is_array($decoded)) {
        foreach (array_filter($decoded, static fn($v) => $v !== '') as $gi) {
            $galleryImgs[] = $gi;
        }
    } else {
        foreach (array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) $galleryRaw)), static fn($v) => $v !== '') as $gi) {
            $galleryImgs[] = $gi;
        }
    }
}
$galleryImgs = array_values(array_unique($galleryImgs));
$cover       = $galleryImgs[0] ?? '';
$coverThumb  = \zapcms\helpers\ThumbHelper::thumb($cover ?: ($node['image'] ?? ''), $detailW, $detailH);
?>
    <?php echo $this->partial('partials/_breadcrumb'); ?>
    <div class="container">
        <div class="row">
            <?php echo $this->partial('partials/_sidebar'); ?>
            <div class="col-sm-9">
                <div class="content-wrap">
                    <div class="image-detail">
                        <h2 class="image-title"><?php echo htmlspecialchars($node['title'] ?? ''); ?></h2>
                        <?php if (!empty($node['excerpt'])): ?>
                        <p class="image-excerpt text-muted"><?php echo htmlspecialchars($node['excerpt']); ?></p>
                        <?php endif; ?>
                        <?php if ($cover !== ''): ?>
                        <div class="image-cover mb-3">
                            <img src="<?php echo htmlspecialchars($coverThumb, ENT_QUOTES); ?>" class="img-fluid w-100 rounded"
                                 alt="<?php echo htmlspecialchars($node['title'] ?? '', ENT_QUOTES); ?>">
                        </div>
                        <?php endif; ?>
                        <?php if (count($galleryImgs) > 1): ?>
                        <div class="row g-2 image-gallery">
                            <?php foreach ($galleryImgs as $gi):
                                $t = \zapcms\helpers\ThumbHelper::thumb($gi, 270, 210);
                            ?>
                            <div class="col-6 col-md-4">
                                <a href="<?php echo htmlspecialchars($gi, ENT_QUOTES); ?>" target="_blank">
                                    <img src="<?php echo htmlspecialchars($t, ENT_QUOTES); ?>" class="img-fluid rounded w-100"
                                         style="object-fit:cover;height:160px;" alt="">
                                </a>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($node['content'])): ?>
                        <div class="image-content mt-3">
                            <div class="post-content"><?php echo $node['content']; ?></div>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($node['meta']['image_copyright'])): ?>
                        <div class="image-copyright text-muted small mt-3">
                            <i class="fa fa-copyright"></i> <?php echo htmlspecialchars($node['meta']['image_copyright']); ?>
                        </div>
                        <?php endif; ?>
                        <div class="image-meta text-muted small mt-2">
                            <?php if (!empty($node['pub_time'])): ?>
                            <span><i class="fa fa-calendar"></i> <?php echo date('Y-m-d', is_numeric($node['pub_time']) ? intval($node['pub_time']) : strtotime($node['pub_time'])); ?></span>
                            <?php endif; ?>
                            <?php if (!empty($node['hits'])): ?>
                            &nbsp;&nbsp;<span><i class="fa fa-eye"></i> <?php echo intval($node['hits']); ?> 次浏览</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php $this->endBlock(); ?>
