<?php
defined('IN_ZAPCMS_ADMIN') or die('No permission');

/**
 * 图片设置 - 列表主图尺寸配置
 *
 * 可为产品列表、新闻列表等不同内容类型单独设置缩略图尺寸。
 * 配置项保存在 theme.json 中，所属分组为 "imageSizes"。
 */

if (req()->isPost()) {
    $settings = themeSettings();

    $settings->set('basic.productThumbWidth', intval(req()->post('productThumbWidth', 400)));
    $settings->set('basic.productThumbHeight', intval(req()->post('productThumbHeight', 300)));
    $settings->set('basic.newsThumbWidth', intval(req()->post('newsThumbWidth', 400)));
    $settings->set('basic.newsThumbHeight', intval(req()->post('newsThumbHeight', 300)));
    $settings->set('basic.articleThumbWidth', intval(req()->post('articleThumbWidth', 400)));
    $settings->set('basic.articleThumbHeight', intval(req()->post('articleThumbHeight', 300)));
    $settings->set('basic.faqThumbWidth', intval(req()->post('faqThumbWidth', 120)));
    $settings->set('basic.faqThumbHeight', intval(req()->post('faqThumbHeight', 120)));
    $settings->set('basic.catalogThumbWidth', intval(req()->post('catalogThumbWidth', 400)));
    $settings->set('basic.catalogThumbHeight', intval(req()->post('catalogThumbHeight', 300)));

    $settings->save();
    \zap\http\Response::redirect(url_action('theme@settings', ['page' => 'image']))->with('success', '图片尺寸设置已保存');

}

// ---- 读取当前值 ----
$productThumbW  = themeSettings()->get('basic.productThumbWidth', 400);
$productThumbH  = themeSettings()->get('basic.productThumbHeight', 300);
$newsThumbW     = themeSettings()->get('basic.newsThumbWidth', 400);
$newsThumbH     = themeSettings()->get('basic.newsThumbHeight', 300);
$articleThumbW  = themeSettings()->get('basic.articleThumbWidth', 400);
$articleThumbH  = themeSettings()->get('basic.articleThumbHeight', 300);
$faqThumbW      = themeSettings()->get('basic.faqThumbWidth', 120);
$faqThumbH      = themeSettings()->get('basic.faqThumbHeight', 120);
$catalogThumbW  = themeSettings()->get('basic.catalogThumbWidth', 400);
$catalogThumbH  = themeSettings()->get('basic.catalogThumbHeight', 300);
?>

<form method="post" action="<?php echo url_action('theme@settings', ['page' => 'image']); ?>">

    <!-- 产品列表主图 -->
    <div class="card card-outline card-success">
        <div class="card-header">
            <h5 class="card-title mb-0">
                <i class="fa-solid fa-box me-2"></i>产品列表主图
            </h5>
            <div class="card-tools">
                <button type="button" class="btn btn-tool" data-card-widget="collapse">
                    <i class="fa-solid fa-minus"></i>
                </button>
            </div>
        </div>
        <div class="card-body">
            <p class="text-muted small">控制产品列表页中主图（缩略图）的显示尺寸。</p>
            <div class="row g-3 align-items-end">
                <div class="col-sm-3">
                    <label class="form-label">图片宽度</label>
                    <div class="input-group">
                        <input type="number" class="form-control" name="productThumbWidth"
                               value="<?php echo $productThumbW; ?>" min="1" max="4096">
                        <span class="input-group-text">px</span>
                    </div>
                </div>
                <div class="col-sm-1 text-center d-none d-sm-block" style="align-self: center;">
                    <span class="fs-4 text-muted">&times;</span>
                </div>
                <div class="col-sm-3">
                    <label class="form-label">图片高度</label>
                    <div class="input-group">
                        <input type="number" class="form-control" name="productThumbHeight"
                               value="<?php echo $productThumbH; ?>" min="1" max="4096">
                        <span class="input-group-text">px</span>
                    </div>
                </div>
                <div class="col-sm-5">
                    <span class="badge bg-light text-dark p-2">
                        <i class="fa-solid fa-expand me-1"></i>当前：<?php echo $productThumbW; ?> &times; <?php echo $productThumbH; ?> px
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- 公司新闻主图 -->
    <div class="card card-outline card-secondary">
        <div class="card-header">
            <h5 class="card-title mb-0">
                <i class="fa-solid fa-newspaper me-2"></i>新闻列表主图
            </h5>
            <div class="card-tools">
                <button type="button" class="btn btn-tool" data-card-widget="collapse">
                    <i class="fa-solid fa-minus"></i>
                </button>
            </div>
        </div>
        <div class="card-body">
            <p class="text-muted small">控制新闻列表页中主图（缩略图）的显示尺寸。</p>
            <div class="row g-3 align-items-end">
                <div class="col-sm-3">
                    <label class="form-label">图片宽度</label>
                    <div class="input-group">
                        <input type="number" class="form-control" name="newsThumbWidth"
                               value="<?php echo $newsThumbW; ?>" min="1" max="4096">
                        <span class="input-group-text">px</span>
                    </div>
                </div>
                <div class="col-sm-1 text-center d-none d-sm-block" style="align-self: center;">
                    <span class="fs-4 text-muted">&times;</span>
                </div>
                <div class="col-sm-3">
                    <label class="form-label">图片高度</label>
                    <div class="input-group">
                        <input type="number" class="form-control" name="newsThumbHeight"
                               value="<?php echo $newsThumbH; ?>" min="1" max="4096">
                        <span class="input-group-text">px</span>
                    </div>
                </div>
                <div class="col-sm-5">
                    <span class="badge bg-light text-dark p-2">
                        <i class="fa-solid fa-expand me-1"></i>当前：<?php echo $newsThumbW; ?> &times; <?php echo $newsThumbH; ?> px
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- 文章列表主图 -->
    <div class="card card-outline card-secondary">
        <div class="card-header">
            <h5 class="card-title mb-0">
                <i class="fa-solid fa-file-lines me-2"></i>文章列表主图
            </h5>
            <div class="card-tools">
                <button type="button" class="btn btn-tool" data-card-widget="collapse">
                    <i class="fa-solid fa-minus"></i>
                </button>
            </div>
        </div>
        <div class="card-body">
            <p class="text-muted small">控制文章列表页中主图（缩略图）的显示尺寸。</p>
            <div class="row g-3 align-items-end">
                <div class="col-sm-3">
                    <label class="form-label">图片宽度</label>
                    <div class="input-group">
                        <input type="number" class="form-control" name="articleThumbWidth"
                               value="<?php echo $articleThumbW; ?>" min="1" max="4096">
                        <span class="input-group-text">px</span>
                    </div>
                </div>
                <div class="col-sm-1 text-center d-none d-sm-block" style="align-self: center;">
                    <span class="fs-4 text-muted">&times;</span>
                </div>
                <div class="col-sm-3">
                    <label class="form-label">图片高度</label>
                    <div class="input-group">
                        <input type="number" class="form-control" name="articleThumbHeight"
                               value="<?php echo $articleThumbH; ?>" min="1" max="4096">
                        <span class="input-group-text">px</span>
                    </div>
                </div>
                <div class="col-sm-5">
                    <span class="badge bg-light text-dark p-2">
                        <i class="fa-solid fa-expand me-1"></i>当前：<?php echo $articleThumbW; ?> &times; <?php echo $articleThumbH; ?> px
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- 产品分类通用主图 -->
    <div class="card card-outline card-secondary">
        <div class="card-header">
            <h5 class="card-title mb-0">
                <i class="fa-solid fa-folder-tree me-2"></i>分类列表主图（通用）
            </h5>
            <div class="card-tools">
                <button type="button" class="btn btn-tool" data-card-widget="collapse">
                    <i class="fa-solid fa-minus"></i>
                </button>
            </div>
        </div>
        <div class="card-body">
            <p class="text-muted small">控制其他分类列表页（如新闻中心、产品中心）中主图的显示尺寸。</p>
            <div class="row g-3 align-items-end">
                <div class="col-sm-3">
                    <label class="form-label">图片宽度</label>
                    <div class="input-group">
                        <input type="number" class="form-control" name="catalogThumbWidth"
                               value="<?php echo $catalogThumbW; ?>" min="1" max="4096">
                        <span class="input-group-text">px</span>
                    </div>
                </div>
                <div class="col-sm-1 text-center d-none d-sm-block" style="align-self: center;">
                    <span class="fs-4 text-muted">&times;</span>
                </div>
                <div class="col-sm-3">
                    <label class="form-label">图片高度</label>
                    <div class="input-group">
                        <input type="number" class="form-control" name="catalogThumbHeight"
                               value="<?php echo $catalogThumbH; ?>" min="1" max="4096">
                        <span class="input-group-text">px</span>
                    </div>
                </div>
                <div class="col-sm-5">
                    <span class="badge bg-light text-dark p-2">
                        <i class="fa-solid fa-expand me-1"></i>当前：<?php echo $catalogThumbW; ?> &times; <?php echo $catalogThumbH; ?> px
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- FAQ 主图 -->
    <div class="card card-outline card-secondary">
        <div class="card-header">
            <h5 class="card-title mb-0">
                <i class="fa-solid fa-circle-question me-2"></i>常见问题列表主图
            </h5>
            <div class="card-tools">
                <button type="button" class="btn btn-tool" data-card-widget="collapse">
                    <i class="fa-solid fa-minus"></i>
                </button>
            </div>
        </div>
        <div class="card-body">
            <p class="text-muted small">控制FAQ列表页中主图（图标）的显示尺寸。通常较小，例如 120 &times; 120。</p>
            <div class="row g-3 align-items-end">
                <div class="col-sm-3">
                    <label class="form-label">图片宽度</label>
                    <div class="input-group">
                        <input type="number" class="form-control" name="faqThumbWidth"
                               value="<?php echo $faqThumbW; ?>" min="1" max="4096">
                        <span class="input-group-text">px</span>
                    </div>
                </div>
                <div class="col-sm-1 text-center d-none d-sm-block" style="align-self: center;">
                    <span class="fs-4 text-muted">&times;</span>
                </div>
                <div class="col-sm-3">
                    <label class="form-label">图片高度</label>
                    <div class="input-group">
                        <input type="number" class="form-control" name="faqThumbHeight"
                               value="<?php echo $faqThumbH; ?>" min="1" max="4096">
                        <span class="input-group-text">px</span>
                    </div>
                </div>
                <div class="col-sm-5">
                    <span class="badge bg-light text-dark p-2">
                        <i class="fa-solid fa-expand me-1"></i>当前：<?php echo $faqThumbW; ?> &times; <?php echo $faqThumbH; ?> px
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- 保存按钮 -->
    <div class="text-center mt-3 mb-4">
        <button type="submit" class="btn btn-success btn-lg px-5">
            <i class="fa-solid fa-floppy-disk me-2"></i>保存所有图片设置
        </button>
    </div>

</form>
