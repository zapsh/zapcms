<?php
/**
 * 通用内容发布 / 编辑表单（node/default 控制器）
 *
 * 新建（node/default/add）：
 *   - 顶部选择「内容模型」，或直接在可搜索多选分类下拉里选择分类（自动确定模型，不允许跨模型混选）
 *   - 切换模型时 AJAX 重载该模型的自定义字段（Node@metaFields）
 *   - 保存时按所选模型入库，成功后地址切换到对应模型的编辑表单
 * 编辑（node/default/edit/{id}）：
 *   - 内容模型固定为节点自身类型，不可切换
 *
 * @var \zapcms\models\Node $node
 * @var array               $node_relations
 */

use zapcms\support\Asset;
use zap\facades\Url;
use zapcms\services\NodeType;
use zap\DB;

Asset::library('summernote');
Asset::library('datetimepicker');
Asset::library('jqueryvalidation');
register_scripts(base_url('/assets/plugins/zapuploader.js'));

!IS_AJAX && $this->extend('layouts/common');

$this->view->page_title = !empty($sub_title) ? $sub_title : ($title ?? '编辑');
$this->view->page_subtitle = $title ?? '';

$node           = $node ?? new \zapcms\models\Node();
$nodeId         = (int) ($node->id ?? 0);
$isEdit         = $nodeId > 0;
$node_relations = $node_relations ?? [];

// ---- 内容模型选项（排除虚拟类型） ----
$types = [];
foreach ((array) DB::table('node_types')
    ->whereNotIn('type_name', NodeType::VIRTUAL_TYPES)
    ->orderBy('sort_order', 'ASC')->orderBy('type_id', 'DESC')
    ->fetchAll() as $t) {
    $types[] = ['name' => (string) $t['type_name'], 'title' => (string) $t['title']];
}
$typeTitleMap = [];
foreach ($types as $t) {
    $typeTitleMap[$t['name']] = $t['title'];
}

// ---- 全量栏目（扁平、带模型标识，前端按模型联动过滤） ----
$allCatalogs = [];
foreach ((array) DB::table('catalog')
    ->orderBy('sort_order', 'ASC')->orderBy('id', 'ASC')
    ->fetchAll() as $c) {
    $allCatalogs[] = [
        'id'    => (int) $c['id'],
        'title' => (string) $c['title'],
        'type'  => (string) ($c['node_type'] ?? ''),
        'level' => (int) ($c['level'] ?? 1),
    ];
}
$catalogTypeMap = [];
foreach ($allCatalogs as $c) {
    $catalogTypeMap[$c['id']] = $c['type'];
}

// ---- 初始内容模型：cid 对应栏目 > 已勾选分类 > 节点自身类型 > 第一个模型 ----
$formType = '';
$cid = (int) ($_GET['cid'] ?? 0);
if ($cid > 0 && isset($catalogTypeMap[$cid])) {
    $formType = $catalogTypeMap[$cid];
}
if ($formType === '' && $node_relations !== []) {
    $formType = (string) ($catalogTypeMap[(int) array_key_first($node_relations)] ?? '');
}
if ($formType === '' && $isEdit) {
    $formType = (string) ($node->node_type ?? '');
}
if ($formType === '' && $types !== []) {
    $formType = $types[0]['name'];
}

// ---- 自定义字段（初始模型；切换模型后 AJAX 重载） ----
$metaGroups = NodeType::getFieldsGrouped((string) $formType);

ob_start();
foreach ($metaGroups as $gname => $gfields) {
    if ($gname !== '') {
        echo '<hr><h6 class="text-muted small mb-2"><i class="fa fa-tags me-1"></i>' . e($gname) . '</h6>';
    }
    $node_fields = $gfields;
    $meta_section_title = '自定义字段';
    $meta_show_header = ($gname === '');
    include __DIR__ . '/../_meta_fields.php';
    unset($node_fields, $meta_section_title, $meta_show_header);
}
$metaFieldsHtml = trim(ob_get_clean());
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
                           class="btn btn-tool <?php echo !$cid ? 'text-primary' : ''; ?>" title="全部内容">
                            <i class="fa fa-home"></i>
                        </a>
                    </div>
                </div>
                <div class="card-body p-0" style="max-height:calc(100vh - 260px);overflow-y:auto;">
                    <?php echo $this->partial('default/sidebar'); ?>
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
                        <li class="nav-item <?php echo $metaFieldsHtml === '' ? 'd-none' : ''; ?>" id="metaTabBtn">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-meta" type="button">
                                <i class="fa fa-list-alt me-1"></i>自定义字段
                            </button>
                        </li>
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
                            <!-- 发布设置：内容模型 + 可搜索多选分类 -->
                            <div class="row g-2 mb-3">
                                <div class="col-md-4">
                                    <label for="node_type_select" class="form-label">内容模型 <span class="text-danger">*</span></label>
                                    <select class="form-select form-select-sm" id="node_type_select" name="node_type"
                                            <?php echo $isEdit ? 'disabled' : ''; ?>>
                                        <?php foreach ($types as $t): ?>
                                            <option value="<?php echo e($t['name']); ?>"
                                                <?php echo $t['name'] === $formType ? 'selected' : ''; ?>>
                                                <?php echo e($t['title']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text small">选择分类后自动匹配，发布后进入对应模型管理</div>
                                </div>
                                <div class="col-md-8">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <label class="form-label mb-1">分类
                                            <span class="text-muted small">（可搜索多选，限同一模型）</span>
                                        </label>
                                        <button type="button" class="btn btn-sm btn-outline-success py-0 mb-1" onclick="openQuickCreateCatalog()">
                                            <i class="fa fa-plus"></i> 新建分类
                                        </button>
                                    </div>
                                    <div id="catalogPicker" class="position-relative">
                                        <div id="cpBox" class="form-control form-control-sm d-flex flex-wrap align-items-center gap-1" style="min-height:34px;cursor:text;">
                                            <span id="cpChips" class="d-flex flex-wrap gap-1"></span>
                                            <input type="text" id="cpSearch" autocomplete="off"
                                                   class="border-0 bg-transparent flex-grow-1" style="outline:none;min-width:110px;"
                                                   placeholder="搜索并选择分类…">
                                        </div>
                                        <div id="cpDropdown" class="position-absolute border rounded bg-white shadow-sm p-1"
                                             style="top:100%;left:0;right:0;z-index:1060;display:none;max-height:280px;overflow-y:auto;">
                                            <div id="cpList"></div>
                                            <div id="cpEmpty" class="text-muted small p-2" style="display:none;">无匹配的分类</div>
                                        </div>
                                        <div id="cpHidden"></div>
                                    </div>
                                </div>
                            </div>

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
                                <label for="node_excerpt" class="form-label">简介</label>
                                <textarea class="form-control form-control-sm" name="node[excerpt]"
                                          id="node_excerpt" rows="2"
                                          placeholder="摘要 / 卖点，列表页展示"><?php echo e($node->excerpt); ?></textarea>
                            </div>
                            <div class="mb-2">
                                <label for="node_content" class="form-label">内容</label>
                                <textarea name="node[content]" id="node_content"
                                          class="form-control summernote"><?php echo e($node->content); ?></textarea>
                            </div>
                        </div>

                        <div class="tab-pane" id="tab-attr">
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
                                <label class="form-label">主图</label>
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
                                        <div class="form-text">主图将显示在列表封面与详情页。</div>
                                    </div>
                                    <input type="hidden" name="node[image]" id="node-image" value="<?php echo e($node['image']); ?>" />
                                </div>
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

                        <div class="tab-pane" id="tab-meta">
                            <div id="metaFieldsPane"><?php echo $metaFieldsHtml; ?></div>
                        </div>
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
<?php echo $this->partial('_translations', ['translations' => $translations ?? [], 'node' => $node ?? null]); ?>
</form>

<style>
.nav-tabs .nav-link { padding: 0.5rem 1rem; }
.nav-tabs .nav-link.active { color: #198754; border-bottom-color: #198754; }
.note-editor { border-radius: var(--bs-border-radius); overflow: hidden; }
.note-editor.note-frame { border-color: var(--bs-border-color); }
.note-editor.note-frame .note-editing-area { min-height: 300px; }
.note-editor .note-toolbar { background: #f8f9fa; padding: 0.35rem 0.5rem; }
#catalogPicker .cp-item { border: 0; background: transparent; width: 100%; text-align: left; padding: 0.3rem 0.5rem; font-size: 0.85rem; border-radius: 0.25rem; }
#catalogPicker .cp-item:hover { background: #f0f3ff; }
#catalogPicker .cp-item.cp-active { background: #d1e7dd; }
#catalogPicker .cp-item.cp-disabled { opacity: 0.45; cursor: not-allowed; }
</style>

<script>
// ============ Slug / 基础 ============
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
    Zap.saveNodeForm({isNew: <?php echo $nodeId ? 'false' : 'true'; ?>});
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

<script>
// ============ 内容模型 + 分类联动 ============
var cpMetaUrl = '<?php echo url_action('Node@metaFields'); ?>';
var cpData = <?php echo json_encode($allCatalogs, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP); ?>;
var cpTypeTitles = <?php echo json_encode($typeTitleMap, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP); ?>;
var cpSelectedIds = <?php echo json_encode(array_map('intval', array_keys($node_relations)), JSON_HEX_TAG); ?>;
var cpType = '<?php echo e($formType); ?>';
var cpSelected = {};

function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}
function cpById(id) {
    for (var i = 0; i < cpData.length; i++) {
        if (cpData[i].id === id) return cpData[i];
    }
    return null;
}
function cpLockedType() {
    for (var k in cpSelected) { return cpSelected[k].type; }
    return '';
}

// 初始化已选分类（cid 预选 / 编辑回显）
cpSelectedIds.forEach(function (id) {
    var c = cpById(id);
    if (c) { cpSelected[id] = c; }
});

function cpToggle(id) {
    var c = cpById(id);
    if (!c) return;
    var locked = cpLockedType();
    if (!cpSelected[id] && locked !== '' && c.type !== locked) {
        ZapToast.error('只能选择「' + (cpTypeTitles[locked] || locked) + '」模型的分类，请先切换内容模型');
        return;
    }
    if (cpSelected[id]) {
        delete cpSelected[id];
    } else {
        cpSelected[id] = c;
        // 分类决定发布类型：首次选择其它模型的分类时自动切换模型并重载自定义字段
        if (cpType !== c.type) {
            cpType = c.type;
            $('#node_type_select').val(c.type);
            quickCatalogNodeType = c.type;
            reloadMetaFields(c.type);
        }
    }
    cpRenderChips(); cpRenderList(); cpSyncHidden();
}

function cpRenderChips() {
    var html = '';
    Object.keys(cpSelected).forEach(function (id) {
        html += '<span class="badge text-bg-primary d-inline-flex align-items-center gap-1">'
              + escapeHtml(cpSelected[id].title)
              + '<a href="javascript:void(0)" onclick="cpToggle(' + id + ')" class="text-white" style="text-decoration:none" title="移除">×</a>'
              + '</span>';
    });
    $('#cpChips').html(html);
}

function cpRenderList() {
    var q = $('#cpSearch').val().trim().toLowerCase();
    var locked = cpLockedType();
    var hasAny = false;
    var html = '';
    cpData.forEach(function (c) {
        if (q && c.title.toLowerCase().indexOf(q) === -1) { return; }
        hasAny = true;
        var active = !!cpSelected[c.id];
        var disabled = locked !== '' && c.type !== locked;
        var pad = (Math.max(1, c.level) - 1) * 1.2 + 0.5;
        html += '<button type="button" class="cp-item' + (active ? ' cp-active' : '') + (disabled ? ' cp-disabled' : '') + '"'
              + ' style="padding-left:' + pad + 'em;" onclick="cpToggle(' + c.id + ')"'
              + (disabled ? ' title="属于「' + escapeHtml(cpTypeTitles[c.type] || c.type) + '」模型，不可混选"' : '') + '>'
              + (active ? '<i class="fa fa-check-square text-success me-1"></i>' : '<i class="fa fa-square-o text-muted me-1"></i>')
              + escapeHtml(c.title)
              + (disabled
                    ? ' <i class="fa fa-lock text-muted small float-end"></i>'
                    : (locked === '' ? ' <span class="badge text-bg-light float-end">' + escapeHtml(cpTypeTitles[c.type] || c.type) + '</span>' : ''))
              + '</button>';
    });
    $('#cpList').html(html);
    $('#cpEmpty').toggle(!hasAny);
}

function cpSyncHidden() {
    var html = '';
    Object.keys(cpSelected).forEach(function (id) {
        html += '<input type="hidden" name="catalog[' + id + ']" value="' + cpSelected[id].level + '">';
    });
    $('#cpHidden').html(html);
}

// 下拉开合
function cpOpen() { $('#cpDropdown').show(); }
function cpClose() { $('#cpDropdown').hide(); }
$(function () {
    $('#cpBox').on('click', function () { $('#cpSearch').trigger('focus'); });
    $('#cpSearch').on('focus', cpOpen).on('input', cpRenderList);
    $(document).on('click', function (e) {
        if (!$(e.target).closest('#catalogPicker').length) { cpClose(); }
    });
});

// 手动切换内容模型：清除其它模型的已选分类并重载自定义字段
$('#node_type_select').on('change', function () {
    var t = this.value;
    if (t === cpType) return;
    cpType = t;
    quickCatalogNodeType = t;
    Object.keys(cpSelected).forEach(function (id) {
        if (cpSelected[id].type !== t) { delete cpSelected[id]; }
    });
    cpRenderChips(); cpRenderList(); cpSyncHidden();
    reloadMetaFields(t);
});

// AJAX 重载某内容模型的自定义字段
function reloadMetaFields(type) {
    $.get(cpMetaUrl, { type: type }, function (res) {
        if (!res || res.code !== 0) {
            $('#metaFieldsPane').empty();
        } else {
            $('#metaFieldsPane').html(res.html);
            $('#metaFieldsPane .datetimepicker').each(function () {
                $(this).datetimepicker({ format: $(this).data('format') || 'Y-m-d H:i:s' });
            });
        }
        var empty = $.trim($('#metaFieldsPane').html()) === '';
        $('#metaTabBtn').toggleClass('d-none', empty);
        if (empty && $('#tab-meta').hasClass('active')) {
            var btn = document.querySelector('[data-bs-target="#tab1"]');
            if (btn) { btn.click(); }
        }
    }, 'json');
}

// 初始渲染
$(function () {
    cpRenderChips(); cpRenderList(); cpSyncHidden();
});
</script>

<script>
// ============ 快速新建分类 ============
var quickCatalogNodeType = '<?php echo e($formType); ?>';

function openQuickCreateCatalog() {
    var opts = '<option value="0">- 无（顶级分区） -</option>';
    cpData.forEach(function (c) {
        if (c.type !== quickCatalogNodeType) { return; }
        opts += '<option value="' + c.id + '">'
              + '— '.repeat(Math.max(0, c.level - 1))
              + escapeHtml(c.title) + '</option>';
    });
    var modal = ZapModal.create({
        id: 'quickCatalogModal',
        title: '快速新建分类',
        dialog_class: 'modal-dialog-centered modal-sm',
        body_class: 'p-3',
        content:
            '<div class="mb-2">' +
            '  <label class="form-label small mb-1">名称 <span class="text-danger">*</span></label>' +
            '  <input type="text" id="quickCatalogTitle" class="form-control form-control-sm" placeholder="输入分类名称">' +
            '</div>' +
            '<div class="mb-0">' +
            '  <label class="form-label small mb-1">上级分类</label>' +
            '  <select id="quickCatalogPid" class="form-select form-select-sm">' + opts + '</select>' +
            '</div>',
        buttons: [
            {title: '取消', close: true, class: 'btn-outline-secondary'},
            {title: '创建', class: 'btn-success', close: false}
        ],
        btn2: function () { submitQuickCreateCatalog(modal); }
    }, true);
    modal.show();
    setTimeout(function () { $('#quickCatalogTitle').trigger('focus'); }, 300);
}

function submitQuickCreateCatalog(modal) {
    var title = $('#quickCatalogTitle').val().trim();
    if (!title) { ZapToast.error('请填写分类名称'); return; }
    var pid = parseInt($('#quickCatalogPid').val(), 10) || 0;
    var l = Zap.loading('正在创建...');
    $.ajax({
        url: '<?php echo url_action('Catalog@quickCreate'); ?>',
        method: 'post',
        data: { title: title, pid: pid, node_type: quickCatalogNodeType },
        dataType: 'json',
        success: function (res) {
            if (res && res.code === 0) {
                ZapToast.success(res.msg || '创建成功');
                modal.hide();
                // 新分类加入可选列表并自动选中
                var nc = res.catalog || {};
                if (nc.id) {
                    cpData.push({
                        id: nc.id,
                        title: nc.title || title,
                        type: quickCatalogNodeType,
                        level: nc.level || 1
                    });
                    var c = cpData[cpData.length - 1];
                    cpSelected[c.id] = c;
                    cpRenderChips(); cpRenderList(); cpSyncHidden();
                }
            } else {
                ZapToast.error((res && res.msg) || '创建失败');
            }
        },
        error: function (xhr) { ZapToast.error('创建失败：' + xhr.status); }
    }).always(function () { l.dispose(); });
}
</script>
<?php
\zapcms\services\Editor::instance()->create('.summernote', [
    'image_upload' => 'zapSendFile',
    'upload_url' => url_action('Upload@image')
]);
