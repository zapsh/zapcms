<?php

use zapcms\support\Asset;
use zap\facades\Url;

Asset::library('summernote');
Asset::library('datetimepicker');
Asset::library('jqueryvalidation');
register_scripts(base_url('/assets/plugins/zapuploader.js'));

!IS_AJAX && $this->extend('layouts/common');

$this->view->page_title = !empty($sub_title) ? $sub_title : ($title ?? '编辑');
// $this->view->page_subtitle = $title ?? '';

/**
 * @var \zapcms\models\Node $node
 * @var array $catalogList
 * @var array $node_relations
 * @var string $_controller
 */

// 按字段分组拆分为多个 Tab（'' 为默认分组）
$metaGroups = \zapcms\services\NodeType::getFieldsGrouped((string)($node_type ?? $_controller ?? 'default'));
$metaDefaultFields = $metaGroups[''] ?? [];
unset($metaGroups['']);

// 附加图片（node_meta 中的 gallery，JSON 数组）
$galleryArr = [];
$galleryRaw = $node->get_node_meta('gallery');
if ($galleryRaw !== '' && $galleryRaw !== null) {
    $decoded = json_decode((string)$galleryRaw, true);
    if (is_array($decoded)) {
        $galleryArr = array_values(array_filter($decoded, fn($v) => $v !== ''));
    } elseif ((string)$galleryRaw !== '') {
        $galleryArr = [(string)$galleryRaw];
    }
}
if (empty($galleryArr)) {
    $galleryArr = [''];
}
?>

<form id="zapForm" method="post">
    <input type="hidden" value="<?php echo e($node->id); ?>" name="node_id">
    <input type="hidden" name="node[pub_time]" value="<?php echo $node->getPubTimeToDate(); ?>" />
    <input name="node[status]" id="node_status" type="hidden" value="<?php echo $node->status ?: \zapcms\models\Node::STATUS_PUBLISH;?>" />
    <input type="hidden" id="node_author_id" name="node[author_id]" value="<?php echo \zapcms\services\Auth::user('id') ?>">

    <div class="row g-3">
        <!-- 左侧栏目导航 -->
        <div class="col-lg-3">
            <div class="card card-outline card-primary">
                <div class="card-header p-2">
                    <h6 class="card-title mb-0">
                        <i class="fa fa-sitemap me-1 text-primary"></i>栏目导航
                    </h6>
                    <div class="card-tools">
                        <a href="<?php echo Url::action("Node"); ?>"
                           class="btn btn-tool <?php echo !$catalogId ? 'text-primary' : ''; ?>" title="全部内容">
                            <i class="fa fa-home"></i>
                        </a>
                    </div>
                </div>
                <div class="card-body p-0" style="max-height:calc(100vh - 260px);overflow-y:auto;">
                    <?php echo $this->partial('default/sidebar');?>
                </div>
            </div>
            <?php echo $this->partial('_left_navs'); ?>
        </div>
        <!-- /左侧栏目导航 -->

        <div class="col-lg-9">
            <div class="card card-outline card-success">
        <div class="card-header d-flex align-items-center ps-3 pt-0 pb-0 pe-0">
            <ul class="nav nav-underline me-auto" role="tablist">
                <li class="nav-item">
                    <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab1" type="button">
                        <i class="fa fa-file-alt me-1"></i>内容
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-attr" type="button">
                        <i class="fa fa-cog me-1"></i>属性
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-image" type="button">
                        <i class="fa fa-image me-1"></i>图片
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab2" type="button">
                        <i class="fa fa-search me-1"></i>SEO
                    </button>
                </li>
                <?php foreach ($metaGroups as $gname => $gfields): ?>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#meta_tab_<?= crc32($gname) ?>" type="button">
                            <i class="fa fa-tags me-1"></i><?= e($gname) ?>
                        </button>
                    </li>
                <?php endforeach; ?>
            </ul>
            <div class="card-tools">
                <a class="btn btn-tool btn-sm" href="<?php echo url_action("Node@{$_controller}", $_GET); ?>">
                    <i class="fa fa-times"></i>
                </a>
            </div>
        </div>
        <div class="card-body p-3">
            <div class="tab-content">
                <div class="tab-pane active" id="tab1">
                    <div class="mb-2">
                        <label for="node_title" class="form-label">产品名称 <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-sm" name="node[title]" id="node_title"
                               placeholder="请输入产品名称" required value="<?php echo e($node->title); ?>">
                    </div>
                    <div class="mb-2 row g-2">
                        <div class="col">
                            <label for="node_slug" class="form-label">
                                URL 别名 <span class="text-muted small">(slug)</span>
                            </label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text text-muted small"><?php echo site_url('/'); ?></span>
                                <input type="text" class="form-control form-control-sm font-monospace" name="node[slug]"
                                       id="node_slug" placeholder="自动生成" value="<?php echo e($node->slug); ?>">
                                <button class="btn btn-outline-secondary btn-sm" type="button" onclick="generateSlug()" title="根据标题自动生成">
                                    <i class="fa fa-magic"></i>
                                </button>
                            </div>
                            <div id="slug_preview" class="form-text small text-success" style="display:none;">
                                <i class="fa fa-link me-1"></i><span></span>
                            </div>
                        </div>
                    </div>
                    <div class="mb-2">
                        <label for="node_excerpt" class="form-label">简介</label>
                        <textarea class="form-control form-control-sm" name="node[excerpt]"
                                  id="node_excerpt" rows="3"
                                  placeholder="产品简介或卖点"><?php echo e($node->excerpt); ?></textarea>
                    </div>
                    <div class="mb-2">
                        <label for="node_content" class="form-label">详情</label>
                        <textarea name="node[content]" id="node_content"
                                  class="form-control summernote"><?php echo e($node->content); ?></textarea>
                    </div>

                    <?php
                    $node_fields = $metaDefaultFields;
                    $meta_section_title = '自定义字段';
                    $meta_show_header = true;
                    include __DIR__ . '/../_meta_fields.php';
                    unset($node_fields, $meta_section_title, $meta_show_header);
                    ?>
                </div>
                <div class="tab-pane" id="tab-attr">
                    <div class="mb-2">
                        <label class="form-label">产品分类</label>
                        <div class="catalog-list">
                            <?php
                            while($catalog = array_shift($catalogList)){
                                $indent = $catalog['level'] * 1;
                                ?>
                                <div class="form-check mb-1" style="padding-left: <?php echo $indent + 1.5; ?>em;">
                                    <input class="form-check-input" type="checkbox" name="catalog[<?php echo e($catalog['id']); ?>]"
                                           <?php echo !empty($node_relations[$catalog['id']]) ? 'checked' : '' ?>
                                           value="<?php echo e($catalog['level']); ?>"
                                           id="catalog-<?php echo e($catalog['id']); ?>">
                                    <label class="form-check-label small" for="catalog-<?php echo e($catalog['id']); ?>">
                                        <?php echo e($catalog['title']); ?>
                                    </label>
                                </div>
                                <?php
                                if(isset($catalog['children'])){
                                    while ($children = array_pop($catalog['children'])){
                                        array_unshift($catalogList,$children);
                                    }
                                }
                            }
                            ?>
                        </div>
                    </div>
                    <div class="row g-2">
                        <div class="col-md-4">
                            <div class="mb-2">
                                <label for="node_status_select" class="form-label">状态</label>
                                <select class="form-select form-select-sm" name="node[status]" id="node_status_select">
                                    <?php foreach($node->getStatus() as $id => $title){
                                        if($id == \zapcms\models\Node::STATUS_SOFT_DELETE or $id == \zapcms\models\Node::STATUS_TRASH){ continue; } ?>
                                        <option value="<?php echo e($id); ?>" <?php echo $node->status==$id?'selected':null ;?> ><?php echo e($title); ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-2">
                                <label for="node_pub_time" class="form-label">发布时间</label>
                                <input type="text" class="form-control form-control-sm datetimepicker" name="node[pub_time]"
                                       id="node_pub_time" value="<?php echo $node->getPubTimeToDate(); ?>" required/>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-2">
                                <label class="form-label">发布人</label>
                                <input type="text" class="form-control form-control-sm form-control-plaintext" readonly value="<?php echo \zapcms\services\Auth::user('full_name') ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-2">
                                <label for="node_hits" class="form-label">点击量</label>
                                <input type="number" class="form-control form-control-sm" name="node[hits]" id="node_hits"
                                       value="<?php echo e($node->hits ?? 0); ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-2">
                                <label for="node_sort_order" class="form-label">排序</label>
                                <input type="number" class="form-control form-control-sm" name="node[sort_order]" id="node_sort_order"
                                       value="<?php echo e($node->sort_order ?? 0); ?>">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="tab-pane" id="tab-image">
                    <div class="mb-3">
                        <label class="form-label">产品主图</label>
                        <div class="d-flex align-items-center gap-3">
                            <img src="<?php echo \zapcms\helpers\ThumbHelper::thumb($node['image'], 136, 136); ?>"
                                 class="img-thumbnail rounded" id="node-image-thumb"
                                 style="width:136px;height:136px;object-fit:cover;" alt=""/>
                            <div class="d-flex flex-column gap-1">
                                <button type="button" class="btn btn-outline-secondary btn-sm"
                                        data-zap-toggle="image" data-zap-target="#node-image|#node-image-thumb">
                                    <i class="fa fa-upload me-1"></i>选择主图
                                </button>
                                <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeImage()">
                                    <i class="fa fa-trash"></i>移除
                                </button>
                                <div class="form-text">产品主图将显示在列表与详情页封面。</div>
                            </div>
                            <input type="hidden" name="node[image]" id="node-image" value="<?php echo e($node['image']); ?>" />
                        </div>
                    </div>
                    <hr>
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <label class="form-label mb-0">附加图片</label>
                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="addGalleryRow()">
                            <i class="fa fa-plus me-1"></i>添加图片
                        </button>
                    </div>
                    <div class="form-text mb-2">可添加 1 张或多张附加图片，存储于 <code>node_meta</code>（meta_name 为 <code>gallery</code>）。</div>
                    <div id="gallery-list">
                        <?php foreach ($galleryArr as $gIdx => $gimg):
                            $gThumb = \zapcms\helpers\ThumbHelper::thumb($gimg, 64, 64);
                        ?>
                        <div class="gallery-item d-flex align-items-center gap-2 mb-2">
                            <img src="<?php echo e($gThumb); ?>"
                                 class="img-thumbnail rounded" id="gallery-thumb-<?php echo e($gIdx); ?>"
                                 style="width:64px;height:64px;object-fit:cover;" alt=""/>
                            <input type="hidden" class="gallery-input" id="gallery-input-<?php echo e($gIdx); ?>"
                                   name="meta[gallery][]" value="<?php echo e($gimg); ?>">
                            <button type="button" class="btn btn-outline-secondary btn-sm"
                                    data-zap-toggle="image" data-zap-target="#gallery-input-<?php echo e($gIdx); ?>|#gallery-thumb-<?php echo e($gIdx); ?>">
                                <i class="fa fa-upload me-1"></i>选择图片
                            </button>
                            <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeGalleryRow(this)">
                                <i class="fa fa-trash"></i>
                            </button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="tab-pane" id="tab2">
                    <div class="mb-2">
                        <label for="node_keywords" class="form-label">关键词</label>
                        <input type="text" class="form-control form-control-sm" name="node[keywords]" id="node_keywords"
                               placeholder="多个用逗号分隔（用于 SEO）" value="<?php echo e($node->keywords); ?>"/>
                    </div>
                    <div class="mb-2">
                        <label for="node_description" class="form-label">页面描述</label>
                        <textarea class="form-control form-control-sm" name="node[description]"
                                  id="node_description" rows="3"
                                  placeholder="建议控制在150字以内，用于搜索引擎摘要"><?php echo e($node->description); ?></textarea>
                    </div>
                </div>
                <?php foreach ($metaGroups as $gname => $gfields): ?>
                    <div class="tab-pane" id="meta_tab_<?= crc32($gname) ?>">
                        <?php
                        $node_fields = $gfields;
                        $meta_show_header = false;
                        include __DIR__ . '/../_meta_fields.php';
                        unset($node_fields, $meta_show_header);
                        ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="card-footer">
            <button type="button" class="btn btn-success btn-sm" onclick="save();">
                <i class="fa fa-save me-1"></i>保存
            </button>
            <span class="float-end text-muted small pt-1">
                <i class="fa fa-clock me-1"></i><span id="node_pub_time_label"><?php echo date('Y-m-d H:i', $node->pub_time ?: time()); ?></span>
                &nbsp;|&nbsp;
                <i class="fa fa-edit me-1"></i><span id="node_update_time_label"><?php echo date('Y-m-d H:i', $node->update_time ?: time()); ?></span>
            </span>
                </div>
            </div>
        </div>
    </div>
</form>

<style>
.nav-tabs .nav-link { padding: 0.5rem 1rem; }
.nav-tabs .nav-link.active { color: #198754; border-bottom-color: #198754; }
.catalog-list { max-height: 200px; overflow-y: auto; border: 1px solid var(--bs-border-color); border-radius: var(--bs-border-radius); padding: 0.5rem; }
.note-editor { border-radius: var(--bs-border-radius); overflow: hidden; }
.note-editor.note-frame { border-color: var(--bs-border-color); }
.note-editor.note-frame .note-editing-area { min-height: 300px; }
.note-editor .note-toolbar { background: #f8f9fa; padding: 0.35rem 0.5rem; }
</style>

<script>
var slugConfig = {
    separator: '<?php echo option('slug.separator', '-'); ?>',
    style: '<?php echo option('slug.style', 'default'); ?>',
    ajaxUrl: '<?php echo url_action('System@ajaxSlug'); ?>'
};

$(function(){
    $.datetimepicker.setLocale('zh');
    $('.datetimepicker').each(function(){
        $(this).datetimepicker({ format: $(this).data('format') || 'Y-m-d H:i:s' });
    });
    $('#zapForm').validate({ignore:'', messages:{"node[title]":"产品名称必须填写"}});
});
function save() {
    // 保存成功后不刷新页面：新建会把地址 replaceState 到 edit/{id}，修改则就地更新时间
    Zap.saveNodeForm({
        isNew: <?php echo $node->id ? 'false' : 'true'; ?>,
        beforeSerialize: function () { cleanGallery(); }
    });
}
function removeImage() {
    $('#node-image').val('');
    $('#node-image-thumb').attr('src','').hide();
}
// ---- 附加图片（node_meta.gallery）----
var galleryIndex = <?php echo count($galleryArr); ?>;
var galleryPlaceholder = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';
function addGalleryRow(src) {
    var list = document.getElementById('gallery-list');
    var i = galleryIndex++;
    var imgSrc = src ? src : galleryPlaceholder;
    var div = document.createElement('div');
    div.className = 'gallery-item d-flex align-items-center gap-2 mb-2';
    div.innerHTML =
        '<img src="' + imgSrc + '" class="img-thumbnail rounded" id="gallery-thumb-' + i + '" style="width:64px;height:64px;object-fit:cover;" alt=""/>' +
        '<input type="hidden" class="gallery-input" id="gallery-input-' + i + '" name="meta[gallery][]" value="' + (src || '') + '">' +
        '<button type="button" class="btn btn-outline-secondary btn-sm" data-zap-toggle="image" data-zap-target="#gallery-input-' + i + '|#gallery-thumb-' + i + '">' +
            '<i class="fa fa-upload me-1"></i>选择图片' +
        '</button>' +
        '<button type="button" class="btn btn-outline-danger btn-sm" onclick="removeGalleryRow(this)"><i class="fa fa-trash"></i></button>';
    list.appendChild(div);
}
function removeGalleryRow(btn) {
    var row = btn.closest('.gallery-item');
    if (row) { row.remove(); }
}
function cleanGallery() {
    document.querySelectorAll('#gallery-list .gallery-item').forEach(function (row) {
        var inp = row.querySelector('.gallery-input');
        if (!inp || !inp.value.trim()) { row.remove(); }
    });
}
function generateSlug() {
    var title = $('#node_title').val().trim();
    if (!title) return;
    var sep = slugConfig.separator;

    if (slugConfig.style === 'pinyin' || slugConfig.style === 'translate') {
        $.getJSON(slugConfig.ajaxUrl, { title: title }, function(res) {
            if (res.code === 0 && res.slug) {
                $('#node_slug').val(res.slug);
                updateSlugPreview();
            }
        });
    } else {
        var slug = title.toLowerCase();
        slug = slug.replace(/[^\w\u4e00-\u9fa5]+/g, sep);
        slug = slug.replace(new RegExp('^' + sep.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '+|' + sep.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '+$', 'g'), '');
        $('#node_slug').val(slug);
        updateSlugPreview();
    }
}
function updateSlugPreview() {
    var slug = $('#node_slug').val().trim();
    var preview = $('#slug_preview');
    if (slug) {
        preview.find('span').text('<?php echo site_url("/"); ?>' + slug + '<?php echo htmlspecialchars((string) config('config.suffix', '')); ?>');
        preview.show();
    } else {
        preview.hide();
    }
}
$(function(){
    $('#node_slug').on('input', updateSlugPreview);
    $('#node_title').on('change', function(){
        if (!$('#node_slug').val()) generateSlug();
    });
    updateSlugPreview();
});
</script>
<?php
\zapcms\services\Editor::instance()->create('.summernote', [
    'image_upload' => 'zapSendFile',
    'upload_url' => url_action('Upload@image')
]);
