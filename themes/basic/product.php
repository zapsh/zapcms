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
                                <?php
                                // 多图画廊：主图 + node_meta.gallery 附加图（与后台 product/form.php 解析保持一致）
                                $galleryImgs = [];
                                if (!empty($node['image'])) {
                                    $galleryImgs[] = $node['image'];
                                }
                                $galleryRaw = $node['meta']['gallery'] ?? '';
                                if ($galleryRaw !== '' && $galleryRaw !== null) {
                                    $galleryDecoded = json_decode((string) $galleryRaw, true);
                                    if (is_array($galleryDecoded)) {
                                        foreach (array_filter($galleryDecoded, static fn($v) => $v !== '') as $gi) {
                                            $galleryImgs[] = $gi;
                                        }
                                    } elseif ((string) $galleryRaw !== '') {
                                        $galleryImgs[] = (string) $galleryRaw;
                                    }
                                }
                                $galleryImgs = array_values(array_unique($galleryImgs));
                                $mainImg   = $galleryImgs[0] ?? '';
                                $mainThumb = \zapcms\helpers\ThumbHelper::thumb($mainImg, $detailW, $detailH);
                                $hasThumbs = count($galleryImgs) > 1;
                                ?>
                                <div class="product-gallery">
                                    <div class="position-relative main-image-wrap">
                                        <img src="<?php echo htmlspecialchars($mainThumb, ENT_QUOTES); ?>" class="product-detail-img img-fluid w-100"
                                             id="productMainImg"
                                             data-original="<?php echo htmlspecialchars($mainImg, ENT_QUOTES); ?>"
                                             alt="<?php echo htmlspecialchars($node['title'] ?? '', ENT_QUOTES); ?>"
                                             style="cursor: zoom-in;">
                                        <button type="button" class="btn btn-light btn-sm shadow-sm position-absolute top-0 end-0 m-2"
                                                id="productZoomBtn" title="<?php echo __t('front.zoom_in', '放大查看'); ?>">
                                            <i class="fa fa-search-plus"></i>
                                        </button>
                                    </div>
                                    <?php if ($hasThumbs): ?>
                                    <div class="product-thumbs d-flex flex-wrap gap-2 mt-3" id="productThumbs">
                                        <?php foreach ($galleryImgs as $gi => $g):
                                            $tUrl = \zapcms\helpers\ThumbHelper::thumb($g, 90, 90);
                                        ?>
                                        <button type="button"
                                                class="btn p-0 product-thumb <?php echo $gi === 0 ? 'border border-primary' : 'border'; ?>"
                                                data-thumb="<?php echo htmlspecialchars($tUrl, ENT_QUOTES); ?>"
                                                data-full="<?php echo htmlspecialchars($g, ENT_QUOTES); ?>"
                                                title="<?php echo __t('front.click_switch', '点击切换'); ?>">
                                            <img src="<?php echo htmlspecialchars($tUrl, ENT_QUOTES); ?>" class="img-fluid"
                                                 style="width:72px;height:72px;object-fit:cover;display:block;" alt="">
                                        </button>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <h2 class="product-title"><?php echo htmlspecialchars($node['title'] ?? ''); ?></h2>
                                <?php
                                $priceHtml = '<span class="product-price-label">' . __t('front.price', '价格') . '</span><span class="product-price-currency">¥</span><span class="product-price-value">' . number_format(floatval($node['meta']['price'] ?? 0), 2) . '</span>';
                                /* mall 有 SKU 时由 sku 组件展示价格区间，隐藏核心单一价 */
                                $priceHtml = \zapcms\Hook::applyFilters('mall_product_price', $priceHtml, $node);
                                ?>
                                <?php if ($priceHtml !== ''): ?>
                                <div class="product-price"><?php echo $priceHtml; ?></div>
                                <?php endif; ?>
                                <?php echo \zapcms\Hook::fire('mall_product_sku', $node); ?>
                                <?php if (!empty($node['excerpt'])): ?>
                                <p class="product-excerpt text-muted"><?php echo htmlspecialchars($node['excerpt']); ?></p>
                                <?php endif; ?>
                                <div class="product-meta">
                                    <?php if (!empty($node['pub_time'])): ?>
                                    <span class="text-muted"><i class="fa fa-calendar"></i> <?php echo date('Y-m-d', is_numeric($node['pub_time']) ? intval($node['pub_time']) : strtotime($node['pub_time'])); ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($node['hits'])): ?>
                                    <span class="text-muted">&nbsp;&nbsp;<i class="fa fa-eye"></i> <?php echo intval($node['hits']); ?> <?php echo __t('front.views', '次浏览'); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php if (!empty($node['content'])): ?>
                        <div class="product-content">
                            <h3 class="section-title"><?php echo __t('front.product_detail', '产品详情'); ?></h3>
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
<!-- 产品图片放大灯箱 -->
<div class="modal fade" id="productZoomModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content bg-transparent border-0 shadow-none">
            <div class="modal-body p-0 text-center position-relative">
                <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-3" data-bs-dismiss="modal" aria-label="<?php echo __t('front.close', '关闭'); ?>"></button>
                <button type="button" class="btn btn-light btn-sm rounded-circle shadow position-absolute top-50 start-0 translate-middle-y ms-3 d-none"
                        id="productZoomPrev" aria-label="<?php echo __t('front.prev_image', '上一张'); ?>">
                    <i class="fa fa-chevron-left"></i>
                </button>
                <button type="button" class="btn btn-light btn-sm rounded-circle shadow position-absolute top-50 end-0 translate-middle-y me-3 d-none"
                        id="productZoomNext" aria-label="<?php echo __t('front.next_image', '下一张'); ?>">
                    <i class="fa fa-chevron-right"></i>
                </button>
                <img id="productZoomImg" src="" alt="" class="img-fluid rounded" style="max-height:85vh;cursor:zoom-in;">
            </div>
        </div>
    </div>
</div>
<script>
(function () {
    var main    = document.getElementById('productMainImg');
    var thumbs  = document.querySelectorAll('#productThumbs .product-thumb');
    var zImg    = document.getElementById('productZoomImg');
    var modal   = document.getElementById('productZoomModal');
    var prevBtn = document.getElementById('productZoomPrev');
    var nextBtn = document.getElementById('productZoomNext');
    var gallery = [];
    var current = 0;

    if (main) {
        var first = main.getAttribute('data-original') || main.getAttribute('src');
        if (first) { gallery.push(first); }
    }
    Array.prototype.forEach.call(thumbs, function (btn) {
        var full = btn.getAttribute('data-full');
        if (full && gallery.indexOf(full) === -1) { gallery.push(full); }
    });

    function resetZoom() {
        if (!zImg) { return; }
        zImg.style.transform = 'scale(1)';
        zImg.style.cursor = 'zoom-in';
        zImg.dataset.zoomed = '0';
    }

    function syncThumb(full) {
        var active = null;
        Array.prototype.forEach.call(thumbs, function (btn) {
            if (btn.getAttribute('data-full') === full) { active = btn; }
        });
        Array.prototype.forEach.call(thumbs, function (btn) {
            btn.classList.remove('border-primary');
            btn.classList.add('border');
        });
        if (active) {
            active.classList.add('border-primary');
            active.classList.remove('border');
        }
        return active;
    }

    function selectImage(index, updateMain) {
        if (!gallery.length) { return; }
        current = (index % gallery.length + gallery.length) % gallery.length;
        var full = gallery[current];
        if (zImg) { zImg.setAttribute('src', full); }
        resetZoom();
        if (updateMain && main) {
            var active = syncThumb(full);
            main.setAttribute('src', active ? active.getAttribute('data-thumb') : full);
            main.setAttribute('data-original', full);
        }
    }

    Array.prototype.forEach.call(thumbs, function (btn) {
        btn.addEventListener('click', function () {
            var full = btn.getAttribute('data-full');
            var idx = gallery.indexOf(full);
            if (idx >= 0) { current = idx; }
            selectImage(idx >= 0 ? idx : current, true);
        });
    });

    if (zImg) {
        zImg.addEventListener('click', function () {
            var zoomed = zImg.dataset.zoomed === '1';
            zImg.style.transform = zoomed ? 'scale(1)' : 'scale(2)';
            zImg.style.cursor = zoomed ? 'zoom-in' : 'zoom-out';
            zImg.dataset.zoomed = zoomed ? '0' : '1';
        });
    }

    if (prevBtn) {
        prevBtn.classList.toggle('d-none', gallery.length <= 1);
        prevBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            selectImage(current - 1, true);
        });
    }
    if (nextBtn) {
        nextBtn.classList.toggle('d-none', gallery.length <= 1);
        nextBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            selectImage(current + 1, true);
        });
    }

    document.addEventListener('keydown', function (e) {
        if (!modal || !modal.classList.contains('show')) { return; }
        if (e.key === 'ArrowLeft') {
            e.preventDefault();
            selectImage(current - 1, true);
        } else if (e.key === 'ArrowRight') {
            e.preventDefault();
            selectImage(current + 1, true);
        }
    });

    function openZoom() {
        if (!main || !modal) { return; }
        var full = main.getAttribute('data-original') || main.getAttribute('src');
        var idx = gallery.indexOf(full);
        if (idx >= 0) { current = idx; }
        selectImage(current, false);
        bootstrap.Modal.getOrCreateInstance(modal).show();
    }
    if (main) { main.addEventListener('click', openZoom); }
    var zoomBtn = document.getElementById('productZoomBtn');
    if (zoomBtn) { zoomBtn.addEventListener('click', openZoom); }
})();
</script>
<?php $this->endBlock(); ?>
