<?php

use zapcms\support\Asset;
use zap\facades\Url;

Asset::library('summernote');
Asset::library('datetimepicker');
Asset::library('jqueryvalidation');
register_scripts(base_url('/assets/plugins/zapuploader.js'));

!IS_AJAX && $this->extend('layouts/common');

$this->view->page_title = !empty($sub_title) ? $sub_title : ($title ?? '编辑');
$this->view->page_subtitle = $title ?? '';

/**
 * @var \zapcms\models\Node $node
 * @var int $catalogId
 */

// 按字段分组拆分为多个 Tab（'' 为默认分组）
$metaGroups = \zapcms\services\NodeType::getFieldsGrouped((string)($node_type ?? $_controller ?? 'default'));
$metaDefaultFields = $metaGroups[''] ?? [];
unset($metaGroups['']);
?>
<form id="zapForm" method="post">
    <input type="hidden" value="<?php echo e($node->id); ?>" name="node_id">
    <input type="hidden" name="node[pub_time]" value="<?php echo $node->getPubTimeToDate(); ?>" />
    <input name="node[status]" id="node_status" type="hidden" value="<?php echo $node->status ?: \zapcms\models\Node::STATUS_PUBLISH;?>" />
    <input type="hidden" id="node_author_id" name="node[author_id]" value="<?php echo \zapcms\services\Auth::user('id') ?>">

    <div class="row g-3">
        <div class="col-lg-9">
            <div class="card card-outline card-success">
                <div class="card-header d-flex align-items-center ps-3 pt-0 pb-0 pe-0">
                    <ul class="nav nav-underline me-auto" role="tablist">
                        <li class="nav-item">
                            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab1" type="button">
                                <i class="fa fa-file-alt me-1"></i>常规
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
                                <label for="node_title" class="form-label">标题 <span class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-sm" name="node[title]" id="node_title"
                                       placeholder="请输入标题" required value="<?php echo e($node->title); ?>">
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
                                <label for="node_content" class="form-label">内容</label>
                                <textarea name="node[content]" id="node_content"
                                          class="form-control summernote"><?php echo e($node->content); ?></textarea>
                            </div>
                            <div class="row g-2">
                                <div class="col-auto">
                                    <label for="node_hits" class="form-label">点击量</label>
                                    <input type="number" class="form-control form-control-sm" name="node[hits]" id="node_hits"
                                           value="<?php echo e($node->hits ?? 0); ?>" style="width:100px;">
                                </div>
                                <div class="col-auto">
                                    <label for="node_sort_order" class="form-label">排序</label>
                                    <input type="number" class="form-control form-control-sm" name="node[sort_order]" id="node_sort_order"
                                           value="<?php echo e($node->sort_order ?? 0); ?>" style="width:100px;">
                                </div>
                            </div>

                            <?php
                            $node_fields = $metaDefaultFields;
                            $meta_section_title = '自定义字段';
                            $meta_show_header = true;
                            include __DIR__ . '/_meta_fields.php';
                            unset($node_fields, $meta_section_title, $meta_show_header);
                            ?>
                        </div>
                        <div class="tab-pane" id="tab2">
                            <div class="mb-2">
                                <label for="node_keywords" class="form-label">关键词</label>
                                <input type="text" class="form-control form-control-sm" name="node[keywords]" id="node_keywords"
                                       placeholder="多个用逗号分隔" value="<?php echo e($node->keywords); ?>"/>
                            </div>
                            <div class="mb-2">
                                <label for="node_description" class="form-label">页面描述</label>
                                <textarea class="form-control form-control-sm" name="node[description]"
                                          id="node_description" rows="3"
                                          placeholder="建议控制在150字以内"><?php echo e($node->description); ?></textarea>
                            </div>
                        </div>
                        <?php foreach ($metaGroups as $gname => $gfields): ?>
                            <div class="tab-pane" id="meta_tab_<?= crc32($gname) ?>">
                                <?php
                                $node_fields = $gfields;
                                $meta_show_header = false;
                                include __DIR__ . '/_meta_fields.php';
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
                    <?php if($node->update_time){ ?>
                    <span class="float-end text-muted small pt-1">
                        <i class="fa fa-clock me-1"></i><?php echo date('Y-m-d H:i',$node->pub_time); ?>
                        &nbsp;|&nbsp;
                        <i class="fa fa-edit me-1"></i><?php echo date('Y-m-d H:i',$node->update_time); ?>
                    </span>
                    <?php } ?>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card card-outline card-secondary mb-3">
                <div class="card-header">
                    <h3 class="card-title"><i class="fa fa-paper-plane me-2"></i>发布</h3>
                </div>
                <div class="card-body p-2">
                    <div class="mb-2">
                        <label for="node_status_select" class="form-label small mb-1">状态</label>
                        <select class="form-select form-select-sm" name="node[status]" id="node_status_select">
                            <?php foreach($node->getStatus() as $id => $title){
                                if($id == \zapcms\models\Node::STATUS_SOFT_DELETE or $id == \zapcms\models\Node::STATUS_TRASH){ continue; } ?>
                                <option value="<?php echo e($id); ?>" <?php echo $node->status==$id?'selected':null ;?> ><?php echo e($title); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label for="node_pub_time" class="form-label small mb-1">发布时间</label>
                        <input type="text" class="form-control form-control-sm datetimepicker" name="node[pub_time]"
                               id="node_pub_time" value="<?php echo $node->getPubTimeToDate(); ?>" required/>
                    </div>
                    <div class="mb-0">
                        <label class="form-label small mb-1">发布人</label>
                        <input type="text" class="form-control form-control-sm form-control-plaintext" readonly value="<?php echo \zapcms\services\Auth::user('full_name') ?>">
                    </div>
                </div>
            </div>

            <div class="card card-outline card-secondary mb-3">
                <div class="card-header">
                    <h3 class="card-title"><i class="fa fa-folder-open me-2"></i>分类</h3>
                </div>
                <div class="card-body p-2 catalog-list">
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

            <div class="card card-outline card-secondary">
                <div class="card-header">
                    <h3 class="card-title"><i class="fa fa-image me-2"></i>主图</h3>
                </div>
                <div class="card-body text-center p-2">
                    <img src="<?php echo \zapcms\helpers\ThumbHelper::thumb($node['image'],136,136); ?>"
                         class="img-thumbnail rounded" id="node-image-thumb" style="max-height:140px;" alt=""/>
                    <input type="hidden" name="node[image]" id="node-image" value="<?php echo e($node['image']); ?>" />
                </div>
                <div class="card-footer p-2 d-flex gap-1">
                    <button type="button" class="btn btn-outline-secondary btn-sm flex-fill"
                            data-zap-toggle="image" data-zap-target="#node-image|#node-image-thumb">
                        <i class="fa fa-upload me-1"></i>选择
                    </button>
                    <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeImage()">
                        <i class="fa fa-trash"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>

<style>
.nav-tabs .nav-link { padding: 0.5rem 1rem; }
.nav-tabs .nav-link.active { color: #198754; border-bottom-color: #198754; }
.catalog-list { max-height: 220px; overflow-y: auto; }
.note-editor { border-radius: var(--bs-border-radius); overflow: hidden; }
.note-editor.note-frame { border-color: var(--bs-border-color); }
.note-editor.note-frame .note-editing-area { min-height: 300px; }
.note-editor .note-toolbar { background: #f8f9fa; padding: 0.35rem 0.5rem; }
</style>

<script>
    // Slug 配置（从后端获取）
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
    $('#zapForm').validate({ignore:'', messages:{"node[title]":"标题必须填写"}});
});
function save() {
    const f = $('#zapForm');
    if (!f.valid()) { ZapToast.alert('请修改错误项，重新提交', {bgColor: bgDanger}); return; }
    const l = Zap.loading('正在保存，请稍后');
    $.ajax({
        url: '<?php echo Url::currentFull();?>',
        method: 'post', data: f.serialize(), dataType: 'json',
        success: function (d) {
            if (d.code === 0) {
                ZapToast.alert(d.msg, {bgColor: bgSuccess, callback: function(){
                    <?php if($node->id){ ?>location.reload();<?php }else{ ?>location.href = d.redirect_to;<?php } ?>
                }});
            } else { ZapToast.alert(d.msg, {bgColor: bgDanger}); }
        }
    }).always(function(){ l.dispose(); });
}
function removeImage() {
    $('#node-image').val('');
    $('#node-image-thumb').attr('src','').hide();
}
function generateSlug() {
    var title = $('#node_title').val().trim();
    if (!title) return;
    var sep = slugConfig.separator;

    if (slugConfig.style === 'pinyin' || slugConfig.style === 'translate') {
        // 拼音/翻译模式：通过 AJAX 从服务端生成
        $.getJSON(slugConfig.ajaxUrl, { title: title }, function(res) {
            if (res.code === 0 && res.slug) {
                $('#node_slug').val(res.slug);
                updateSlugPreview();
            }
        });
    } else {
        // 默认模式：客户端生成（保留中文，清理特殊字符）
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
