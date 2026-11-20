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
 * @var string              $_controller
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

// ---- 全量分类项（扁平、带模型标识，前端按模型联动过滤） ----
// 分类已并入 node（node_type='category'）；树序（DFS）输出：子树紧跟父级，层级缩进显示才正确。
$allCatalogs = [];
$catalogRows = (array) DB::table('node')
    ->where('node_type', 'category')
    ->orderBy('sort_order', 'ASC')->orderBy('id', 'ASC')
    ->fetchAll();
$catalogChildren = [];
foreach ($catalogRows as $c) {
    $catalogChildren[(int) ($c['parent_id'] ?? 0)][] = $c;
}
$walkCatalogTree = function (int $pid) use (&$walkCatalogTree, $catalogChildren, &$allCatalogs) {
    foreach ($catalogChildren[$pid] ?? [] as $c) {
        $allCatalogs[] = [
            'id'    => (int) $c['id'],
            'title' => (string) $c['title'],
            'type'  => (string) ($c['mime_type'] ?? ''),
            'level' => (int) ($c['level'] ?? 1),
        ];
        $walkCatalogTree((int) $c['id']);
    }
};
$walkCatalogTree(0);
// 兜底：parent_id 指向已删除分类的孤儿节点按 path 追加，避免从选择器中消失
if (count($allCatalogs) < count($catalogRows)) {
    $seen = [];
    foreach ($allCatalogs as $c) { $seen[$c['id']] = true; }
    $orphans = array_values(array_filter($catalogRows, fn ($c) => !isset($seen[(int) $c['id']])));
    usort($orphans, fn ($a, $b) => strcmp((string) $a['path'], (string) $b['path']));
    foreach ($orphans as $c) {
        $allCatalogs[] = [
            'id'    => (int) $c['id'],
            'title' => (string) $c['title'],
            'type'  => (string) ($c['mime_type'] ?? ''),
            'level' => (int) ($c['level'] ?? 1),
        ];
    }
}
$catalogTypeMap = [];
foreach ($allCatalogs as $c) {
    $catalogTypeMap[$c['id']] = $c['type'];
}

// ---- 初始内容模型 ----
// 通用发布（node/default）允许按分类/表单切换；模型专用入口（node/download 等）和编辑页固定当前模型。
$currentType = strtolower((string) ($node_type ?? ''));
$lockType    = ($currentType !== '' && $currentType !== 'default') || $isEdit;
$formType    = '';
$cid = (int) ($_GET['cid'] ?? 0);
if ($lockType && $currentType !== '' && $currentType !== 'default') {
    $formType = $currentType;
} elseif ($isEdit) {
    $formType = (string) ($node->node_type ?? '');
} elseif ($cid > 0 && isset($catalogTypeMap[$cid])) {
    $formType = $catalogTypeMap[$cid];
}
if ($formType === '' && $node_relations !== []) {
    $formType = (string) ($catalogTypeMap[(int) array_key_first($node_relations)] ?? '');
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
$nodeTitle = trim((string)$node->title) !== '' ? (string)$node->title : '新建内容';
$previewUrl = trim((string)$node->slug) !== ''
    ? site_url('/') . ltrim((string)$node->slug, '/') . (string)config('config.suffix', '')
    : '';
$listUrl = url_action("Node@{$_controller}", $_GET);
$authorName = (string)(($node->author_id ? \zap\DB::table('admin')->select('full_name')->where('id', (int)$node->author_id)->fetchColumn() : '') ?: \zapcms\services\Auth::user('full_name'));
if ($authorName === '') { $authorName = (string)\zapcms\services\Auth::user('username'); }
$authorInitial = function_exists('mb_substr') ? mb_substr($authorName, 0, 1, 'UTF-8') : substr($authorName, 0, 1);
?>

<form id="zapForm" method="post">
    <input type="hidden" value="<?php echo e($node->id); ?>" name="node_id">
    <input type="hidden" name="node[pub_time]" value="<?php echo $node->getPubTimeToDate(); ?>" />
    <input name="node[status]" id="node_status" type="hidden" value="<?php echo $node->status ?: \zapcms\models\Node::STATUS_PUBLISH;?>" />
    <input type="hidden" id="node_author_id" name="node[author_id]" value="<?php echo ((int)($node->author_id ?? 0) ?: \zapcms\services\Auth::user('id')); ?>">

    <div class="row g-3">
        <div class="col-12">
            <div class="card zap-editor-card">
                <div class="card-header p-0">
                    <div class="zap-editor-topbar d-flex align-items-center gap-2 px-3 py-2 border-bottom">
                        <div class="zap-editor-doc-title text-truncate flex-grow-1" title="<?php echo e($nodeTitle); ?>">
                            <i class="fa fa-file-alt me-2 text-muted"></i><?php echo e($nodeTitle); ?>
                        </div>
                        <div class="card-tools d-flex align-items-center gap-1">
                            <button type="button" class="btn btn-tool btn-sm" id="zapFocusBtn" title="专注模式" onclick="toggleEditorFocus()">
                                <i class="fa fa-expand"></i>
                            </button>
                            <?php if ($previewUrl !== ''): ?>
                            <a class="btn btn-tool btn-sm" href="<?php echo e($previewUrl); ?>" target="_blank" rel="noopener" title="预览内容">
                                <i class="fa fa-eye"></i>
                            </a>
                            <?php endif; ?>
                            <div class="btn-group">
                                <button type="button" class="btn btn-primary btn-sm" onclick="save();">
                                    <i class="fa fa-save me-1"></i>保存
                                </button>
                                <button type="button" class="btn btn-primary btn-sm dropdown-toggle dropdown-toggle-split"
                                        data-bs-toggle="dropdown" aria-expanded="false" title="更多保存选项"></button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><button type="button" class="dropdown-item" onclick="saveAsStatus('<?php echo e(\zapcms\models\Node::STATUS_DRAFT); ?>')">
                                        <i class="fa fa-save me-2"></i>保存草稿</button></li>
                                    <li><button type="button" class="dropdown-item" onclick="saveAsStatus('<?php echo e(\zapcms\models\Node::STATUS_PUBLISH); ?>')">
                                        <i class="fa fa-paper-plane me-2"></i>发布</button></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="<?php echo e($listUrl); ?>">
                                        <i class="fa fa-arrow-left me-2"></i>返回列表</a></li>
                                </ul>
                            </div>
                            <div class="dropdown">
                                <button type="button" class="btn btn-tool btn-sm" data-bs-toggle="dropdown" title="更多" aria-expanded="false">
                                    <i class="fa fa-ellipsis-v"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><button type="button" class="dropdown-item" onclick="showEditorShortcuts()">
                                        <i class="fa fa-keyboard me-2"></i>快捷键说明</button></li>
                                    <li><button type="button" class="dropdown-item" onclick="toggleEditorFocus()">
                                        <i class="fa fa-expand me-2"></i>切换专注模式</button></li>
                                </ul>
                            </div>
                            <a class="btn btn-tool btn-sm" href="<?php echo e($listUrl); ?>" data-zap-back="<?php echo e($listUrl); ?>" title="关闭">
                                <i class="fa fa-times"></i>
                            </a>
                        </div>
                    </div>
                    <ul class="nav nav-underline px-3" role="tablist">
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
                        <?php echo $this->partial('_translations_tab'); ?>
                    </ul>
                </div>
                <div class="card-body">
                    <div class="tab-content">
                        <div class="tab-pane active" id="tab1">
                        <div id="zapMainLangContent">
                            <!-- 发布设置：内容模型 + 可搜索多选分类 -->
                            <div class="row g-2 mb-3">
                                <div class="col-md-4">
                                    <label for="node_type_select" class="form-label">内容模型 <span class="text-danger">*</span></label>
                                    <?php if ($lockType): ?>
                                        <input type="hidden" id="node_type_select" name="node_type" value="<?php echo e($formType); ?>">
                                        <input type="text" class="form-control form-control-sm"
                                               value="<?php echo e($typeTitleMap[$formType] ?? $formType); ?>" disabled>
                                        <div class="form-text small">当前模型已固定，发布后进入对应模型管理</div>
                                    <?php else: ?>
                                        <select class="form-select form-select-sm" id="node_type_select" name="node_type">
                                            <?php foreach ($types as $t): ?>
                                                <option value="<?php echo e($t['name']); ?>"
                                                    <?php echo $t['name'] === $formType ? 'selected' : ''; ?>>
                                                    <?php echo e($t['title']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="form-text small">选择分类后自动匹配，发布后进入对应模型管理</div>
                                    <?php endif; ?>
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
                            <div id="zapEditorMeta" class="d-none">
                                <div class="dropdown">
                                    <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none"
                                            id="zapOutlineBtn" data-bs-toggle="dropdown" aria-expanded="false"
                                            title="正文大纲"><i class="fa fa-list me-1"></i>大纲</button>
                                    <ul class="dropdown-menu dropdown-menu-end zap-outline-menu" id="zapOutlineMenu">
                                        <li><span class="dropdown-item-text text-muted small">暂无标题（用 H2/H3 生成）</span></li>
                                    </ul>
                                </div>
                                <span class="text-muted small" id="zapContentCount">0 字</span>
                            </div>
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
                                        <div class="d-flex align-items-center justify-content-between gap-2 mb-1">
                                            <label for="node_pub_time" class="form-label mb-0">
                                                发布时间
                                                <i class="fa fa-question-circle text-muted" aria-hidden="true"
                                                   title="未来时间仅记录发布时间；到点自动发布需配置队列 Worker。"></i>
                                            </label>
                                            <div class="btn-group btn-group-sm flex-nowrap">
                                                <button type="button" class="btn btn-outline-secondary" onclick="setPubTimeNow()" title="设为当前时间">立即</button>
                                                <button type="button" class="btn btn-outline-secondary" onclick="setPubTimeLater()" title="设为明天此时">明天此时</button>
                                            </div>
                                        </div>
                                        <input type="text" class="form-control form-control-sm datetimepicker" name="node[pub_time]"
                                               id="node_pub_time" value="<?php echo $node->getPubTimeToDate(); ?>" required/>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-2">
                                        <label class="form-label">发布人</label>
                                        <div class="zap-author-box d-flex align-items-center gap-2">
                                            <span class="zap-author-avatar"><?php echo e($authorInitial); ?></span>
                                            <span class="small text-body-secondary text-truncate"><?php echo e($authorName); ?></span>
                                        </div>
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
                            <div class="zap-serp-preview mb-3">
                                <div class="zap-serp-label"><i class="fa fa-search me-1"></i>搜索结果预览</div>
                                <div class="zap-serp-title" id="serpTitle">页面标题</div>
                                <div class="zap-serp-url" id="serpUrl">https://...</div>
                                <div class="zap-serp-desc" id="serpDesc">页面描述会显示在这里，建议控制在 150 字以内。</div>
                            </div>
                            <div class="mb-2">
                                <label for="node_keywords" class="form-label">关键词</label>
                                <input type="text" class="form-control form-control-sm" name="node[keywords]" id="node_keywords"
                                       placeholder="多个用逗号分隔（用于 SEO）" value="<?php echo e($node->keywords); ?>"/>
                            </div>
                            <div class="mb-2">
                                <div class="d-flex align-items-center justify-content-between">
                                    <label for="node_description" class="form-label mb-0">页面描述</label>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="text-muted small" id="zapDescCount">0 / 150</span>
                                        <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none" onclick="fillDescFromContent()" title="提取正文前 150 字作为描述">
                                            <i class="fa fa-magic me-1"></i>从正文提取
                                        </button>
                                    </div>
                                </div>
                                <textarea class="form-control form-control-sm mt-1" name="node[description]"
                                          id="node_description" rows="3"
                                          placeholder="建议控制在150字以内，用于搜索引擎摘要"><?php echo e($node->description); ?></textarea>
                            </div>
                        </div>

                        <div class="tab-pane" id="tab-meta">
                            <div id="metaFieldsPane"><?php echo $metaFieldsHtml; ?></div>
                        </div>
                        <?php echo $this->partial('_translations', ['translations' => $translations ?? [], 'node' => $node ?? null]); ?>
                    </div>
                </div>
                <div class="card-footer d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <span class="text-muted small" id="zapSavedAt"></span>
                    <span class="text-muted small pt-1">
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
.nav-underline .nav-link.active { color: #0d6efd; border-bottom-color: #0d6efd; }
.nav-underline .nav-link { color: #495057; }
.note-editor { border-radius: var(--bs-border-radius); overflow: hidden; }
.note-editor.note-frame { border-color: var(--bs-border-color); }
.note-editor.note-frame .note-editing-area { min-height: 300px; }
.note-editor .note-toolbar { background: #f8f9fa; padding: 0.35rem 0.5rem; }
#catalogPicker .cp-item { border: 0; background: transparent; width: 100%; text-align: left; padding: 0.3rem 0.5rem; font-size: 0.85rem; border-radius: 0.25rem; }
#catalogPicker .cp-item:hover { background: #f0f3ff; }
#catalogPicker .cp-item.cp-active { background: #d1e7dd; }
#catalogPicker .cp-item.cp-disabled { opacity: 0.45; cursor: not-allowed; }
.zap-editor-topbar { min-height: 44px; }
.zap-editor-doc-title { min-width: 0; font-size: .875rem; font-weight: 600; color: var(--bs-body-color); }
.zap-editor-topbar .btn-tool {
    width: 30px; height: 30px; padding: 0;
    display: inline-flex; align-items: center; justify-content: center;
}
.zap-editor-topbar .btn-tool:hover, .zap-editor-topbar .btn-tool.active { color: #0d6efd; background: #e7f1ff; }
.zap-author-box {
    height: 31px; padding: .25rem .5rem; border: 1px solid var(--bs-border-color);
    border-radius: var(--bs-border-radius-sm); background: var(--bs-tertiary-bg, #f8f9fa);
}
.zap-author-box .text-truncate { min-width: 0; }
.zap-author-avatar {
    display: inline-flex; align-items: center; justify-content: center;
    width: 22px; height: 22px; border-radius: 50%; flex: 0 0 auto;
    background: #0d6efd; color: #fff; font-size: .72rem; font-weight: 600;
}
/* 两栏发布布局：正文 + 可折叠侧栏 */
/* 隐藏重复的页面标题/面包屑，保留顶部栏目导航 */
.app-main > .app-content-header > .container-fluid > .row { display: none !important; }
.app-main > .app-content-header { padding: 0 !important; border-bottom: 0 !important; }
.app-main > .app-content-header .zap-top-catalog-nav { padding: .35rem 0; margin-top: 0; border-top: 0; }
.zap-editor-card > .card-header > .nav { display: none !important; }
.zap-editor-card > .card-body { padding: 0; }
.zap-editor-card > .card-body > .tab-content {
    display: grid; grid-template-columns: minmax(0, 1fr) 320px; gap: 0; align-items: stretch;
}
.zap-editor-card > .card-body > .tab-content > #tab1 {
    display: block !important; opacity: 1 !important; grid-column: 1; grid-row: 1;
    min-width: 0; padding: 1rem;
}
.zap-editor-side {
    grid-column: 2; grid-row: 1; min-width: 0;
    display: flex; flex-direction: column; gap: 1rem; padding: 1rem;
    border-left: 1px solid var(--bs-border-color);
}
.zap-editor-side > .tab-pane { display: block !important; opacity: 1 !important; }
.zap-editor-side > .tab-pane > .zap-side-title {
    display: flex; align-items: center; gap: .35rem; width: 100%;
    padding: .25rem .4rem; margin-left: -.4rem; border: 0; background: transparent;
    font-weight: 600; font-size: .8125rem; color: var(--bs-secondary-color, #6c757d);
    text-align: left; cursor: pointer; transition: background-color .15s ease, color .15s ease;
}
.zap-editor-side > .tab-pane > .zap-side-title:hover { color: var(--bs-body-color); background: var(--bs-tertiary-bg, #f1f3f5); }
.zap-side-title .fa-chevron-down { font-size: .7rem; transition: transform .15s ease; }
.zap-side-title.collapsed .fa-chevron-down { transform: rotate(-90deg); }
.zap-side-body-inner { padding-top: .35rem; }
.zap-editor-side .row.g-2 { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .75rem; }
.zap-editor-side .row.g-2 > .col-md-4 { width: auto; }
.zap-editor-side .row.g-2 > .col-md-4:nth-child(1) { grid-column: 1; grid-row: 1; }
.zap-editor-side .row.g-2 > .col-md-4:nth-child(3) { grid-column: 2; grid-row: 1; }
.zap-editor-side .row.g-2 > .col-md-4:nth-child(2) { grid-column: 1 / -1; grid-row: 2; }
.zap-editor-side .row.g-2 > .col-md-4:nth-child(4) { grid-column: 1; grid-row: 3; }
.zap-editor-side .row.g-2 > .col-md-4:nth-child(5) { grid-column: 2; grid-row: 3; }
.zap-serp-preview { padding: .75rem; background: #fff; border: 1px solid #e3e6e9; border-radius: var(--bs-border-radius); }
.zap-serp-label { color: #98a1aa; font-size: .72rem; margin-bottom: .4rem; }
.zap-serp-title { color: #1a0dab; font-size: 1.05rem; line-height: 1.3; }
.zap-serp-url { color: #006621; font-size: .78rem; word-break: break-all; }
.zap-serp-desc { color: #545454; font-size: .82rem; line-height: 1.5; }
.zap-editor-meta { float: right; display: flex; align-items: center; gap: .6rem; position: relative; z-index: 5; }
.zap-editor-meta .dropdown-menu { z-index: 1080; }
.zap-outline-menu { max-height: 320px; overflow-y: auto; min-width: 220px; font-size: .85rem; }
@media (max-width: 991.98px) {
    .zap-editor-card > .card-body > .tab-content { grid-template-columns: 1fr; }
    .zap-editor-card > .card-body > .tab-content > #tab1 { grid-column: 1; grid-row: auto; }
    .zap-editor-side { grid-column: 1; grid-row: auto; border-left: 0; }
}
</style>

<script>
// ============ Slug / 基础 ============
var slugConfig = {
    separator: '<?php echo option('slug.separator', '-'); ?>',
    style: '<?php echo option('slug.style', 'default'); ?>',
    ajaxUrl: '<?php echo url_action('System@ajaxSlug'); ?>'
};

$(function(){
    (function initSidePanels(){
        var card = document.querySelector('.zap-editor-card');
        if (!card) return;
        var content = card.querySelector('.card-body > .tab-content');
        if (!content) return;
        var side = content.querySelector('.zap-editor-side');
        if (!side) {
            side = document.createElement('div');
            side.className = 'zap-editor-side';
        }
        [
            {sel:'#tab-attr',  title:'属性',  open:true},
            {sel:'#tab-image', title:'图片',  open:true},
            {sel:'#tab2',      title:'SEO',   open:true},
            {sel:'#tab-meta',  title:'自定义字段', open:false},
            {sel:'#tab-lang',  title:'多语言', open:false}
        ].forEach(function(p){
            var pane = card.querySelector(p.sel);
            if (!pane || pane.dataset.sideReady) return;
            pane.dataset.sideReady = '1';
            var inner = document.createElement('div');
            while (pane.firstChild) inner.appendChild(pane.firstChild);
            var bodyId = 'zap-side-' + p.sel.replace(/[^a-z0-9]/gi, '');
            var head = document.createElement('button');
            head.type = 'button';
            head.className = 'zap-side-title' + (p.open ? '' : ' collapsed');
            head.setAttribute('data-bs-toggle', 'collapse');
            head.setAttribute('data-bs-target', '#' + bodyId);
            head.setAttribute('aria-expanded', p.open ? 'true' : 'false');
            head.innerHTML = '<i class="fa fa-chevron-down me-1"></i><span>' + p.title + '</span>';
            var body = document.createElement('div');
            body.className = 'collapse' + (p.open ? ' show' : '');
            body.id = bodyId;
            var bodyInner = document.createElement('div');
            bodyInner.className = 'zap-side-body-inner';
            while (inner.firstChild) bodyInner.appendChild(inner.firstChild);
            body.appendChild(bodyInner);
            pane.appendChild(head);
            pane.appendChild(body);
            side.appendChild(pane);
        });
        if (side.children.length && !side.parentElement) content.appendChild(side);
    })();
    (function placeEditorMeta(){
        var meta = document.getElementById('zapEditorMeta');
        if (!meta) return;
        var tryPlace = function(){
            var toolbar = document.querySelector('.note-editor .note-toolbar');
            if (!toolbar) { setTimeout(tryPlace, 50); return; }
            meta.classList.remove('d-none');
            meta.classList.add('zap-editor-meta');
            toolbar.appendChild(meta);
        };
        tryPlace();
    })();
    updateContentCount();
    updateDescCount();
    updateSerpPreview();
    $('#node_description').on('input', function(){ updateDescCount(); updateSerpPreview(); });
    $('#node_keywords').on('input', updateSerpPreview);
    $('#node_title').on('input', updateSerpPreview);
    (function fitEditorOnReady(){
        var timer = setInterval(function(){
            if (document.querySelector('.note-editable')) { clearInterval(timer); fitEditorFill(); }
        }, 50);
    })();
    $(window).on('resize', fitEditorFill);
    $(document).on('shown.bs.collapse hidden.bs.collapse', '.zap-editor-side .collapse', function () {
        setTimeout(fitEditorFill, 60);
    });
    $.datetimepicker.setLocale('zh');
    $('.datetimepicker').each(function(){
        $(this).datetimepicker({ format: $(this).data('format') || 'Y-m-d H:i:s' });
    });
    $('#zapForm').validate({ignore:'', messages:{"node[title]":"标题必须填写"}});
    $(document).on('keydown.saveShortcut', function (e) {
        if ((e.ctrlKey || e.metaKey) && ['s', 'enter'].indexOf(e.key.toLowerCase()) !== -1) {
            e.preventDefault();
            if (typeof save === 'function') { save(); }
        }
    });
});
function zapCountText(html) {
    return $('<div>').html(html || '').text().replace(/\s+/g, '').length;
}
function updateContentCount() {
    var $ed = $('#node_content');
    var html = ($ed.data('summernote') && $ed.summernote('code')) ? $ed.summernote('code') : $ed.val();
    $('#zapContentCount').text(zapCountText(html) + ' 字');
    var menu = document.getElementById('zapOutlineMenu');
    if (!menu) return;
    var heads = $('<div>').html(html || '').find('h2, h3').toArray();
    if (!heads.length) {
        menu.innerHTML = '<li><span class="dropdown-item-text text-muted small">暂无标题（用 H2/H3 生成）</span></li>';
        return;
    }
    menu.innerHTML = heads.map(function (h, i) {
        var t = ($(h).text() || '').replace(/\s+/g, ' ').trim() || ('标题 ' + (i + 1));
        var cls = h.tagName.toLowerCase() === 'h3' ? ' ps-3' : '';
        return '<li><button type="button" class="dropdown-item' + cls + '" onclick="jumpToHeading(' + i + ')">' + $('<div>').text(t).html() + '</button></li>';
    }).join('');
}
function jumpToHeading(index) {
    var target = document.querySelectorAll('.note-editable h2, .note-editable h3')[index];
    if (!target) return;
    target.scrollIntoView({behavior: 'smooth', block: 'center'});
}
function updateDescCount() {
    var el = $('#zapDescCount');
    if (!el.length) return;
    var n = zapCountText($('#node_description').val());
    el.text(n + ' / 150').toggleClass('text-danger', n > 150);
}
function updateSerpPreview() {
    var title = $('#node_title').val().trim() || '页面标题';
    var slug = $('#node_slug').val().trim();
    var url = '<?php echo site_url('/'); ?>' + slug + '<?php echo htmlspecialchars((string) config('config.suffix', '')); ?>';
    var desc = $('#node_description').val().trim() || '页面描述会显示在这里，建议控制在 150 字以内。';
    $('#serpTitle').text(title.length > 60 ? title.slice(0, 60) + '…' : title);
    $('#serpUrl').text(url);
    $('#serpDesc').text(desc.length > 150 ? desc.slice(0, 150) + '…' : desc);
}
function fillDescFromContent() {
    var $ed = $('#node_content');
    var html = ($ed.data('summernote') && $ed.summernote('code')) ? $ed.summernote('code') : $ed.val();
    var text = $('<div>').html(html || '').text().replace(/\s+/g, ' ').trim();
    if (!text) { ZapToast.error('正文为空，无法提取'); return; }
    $('#node_description').val(text.slice(0, 150));
    updateDescCount();
    updateSerpPreview();
    ZapToast.success('已从正文提取描述');
}
function fitEditorFill() {
    if (window.innerWidth <= 991 || document.querySelector('.note-editor.fullscreen')) return;
    var pane = document.getElementById('tab1');
    var editable = document.querySelector('.note-editable');
    var content = document.getElementById('zapMainLangContent');
    if (!pane || !editable || !content || !pane.clientHeight) return;
    var padBottom = parseFloat(window.getComputedStyle(pane).paddingBottom) || 0;
    var paneTop = pane.getBoundingClientRect().top;
    var delta = (pane.clientHeight - padBottom) - (content.getBoundingClientRect().bottom - paneTop);
    var h = Math.max(400, editable.offsetHeight + delta);
    editable.style.height = h + 'px';
    var codable = document.querySelector('.note-codable');
    if (codable) { codable.style.height = h + 'px'; }
}
function save() {
    Zap.saveNodeForm({
        isNew: <?php echo $nodeId ? 'false' : 'true'; ?>,
        onSaved: function () {
            var t = $('#zapSavedAt');
            if (t.length) { t.text('已保存 ' + new Date().toTimeString().slice(0, 5)); }
        }
    });
}
function saveAsStatus(status) {
    var select = document.getElementById('node_status_select');
    if (select) { select.value = status; }
    var hidden = document.getElementById('node_status');
    if (hidden) { hidden.value = status; }
    save();
}
function toggleEditorFocus() {
    var card = document.querySelector('.zap-editor-card');
    if (!card) return;
    var on = card.classList.toggle('zap-editor-focus');
    var btn = document.getElementById('zapFocusBtn');
    if (btn) {
        btn.classList.toggle('active', on);
        btn.innerHTML = '<i class="fa ' + (on ? 'fa-compress' : 'fa-expand') + '"></i>';
        btn.title = on ? '退出专注模式' : '专注模式';
    }
}
function showEditorShortcuts() {
    Swal.fire({
        title: '快捷键',
        html: '<div class="text-start small">'
            + '<div><kbd>Ctrl / Cmd</kbd> + <kbd>S</kbd> 保存</div>'
            + '<div class="mt-1"><kbd>Ctrl / Cmd</kbd> + <kbd>Enter</kbd> 保存</div>'
            + '</div>',
        icon: 'info'
    });
}
function zapFormatDateTime(d) {
    var p = function (n) { return String(n).padStart(2, '0'); };
    return d.getFullYear() + '-' + p(d.getMonth() + 1) + '-' + p(d.getDate()) + ' '
        + p(d.getHours()) + ':' + p(d.getMinutes()) + ':' + p(d.getSeconds());
}
function setPubTimeNow() { $('#node_pub_time').val(zapFormatDateTime(new Date())).trigger('change'); }
function setPubTimeLater() {
    var d = new Date(); d.setDate(d.getDate() + 1);
    $('#node_pub_time').val(zapFormatDateTime(d)).trigger('change');
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
    'upload_url' => url_action('Upload@image'),
    'on_change' => 'function(){ updateContentCount(); updateSerpPreview(); }'
]);
