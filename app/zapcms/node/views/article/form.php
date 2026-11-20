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
 * @var string $_action
 */

$nodeTagTitles = \zapcms\services\Tag::getNodeTagTitles((int) $node->id);

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

// ===== 自定义字段：按文章动态选字段（字段库 = 模型字段；节点显示字段存 node_meta._meta_fields）=====
$allMetaFields = \zapcms\services\NodeType::getFields('article');
$fieldMap = [];
foreach ($allMetaFields as $mf) {
    $fieldMap[$mf['field_name']] = $mf;
}
$savedSelectedRaw = $node->get_node_meta('_meta_fields');
$savedSelected = ($savedSelectedRaw !== '' && $savedSelectedRaw !== null) ? json_decode((string)$savedSelectedRaw, true) : null;
if ($savedSelected === null) {
    // 从未保存过组合：初始不显示任何自定义字段，由「添加属性」按需添加
    $selectedNames = [];
} else {
    // 已保存过：以保存的为准（兼容旧 source 重命名为 article_copyright）
    $selectedNames = array_values($savedSelected);
    $selectedNames = array_map(fn($n) => $n === 'source' ? 'article_copyright' : $n, $selectedNames);
}
foreach ($allMetaFields as $mf) {
    if (!empty($mf['required']) && !in_array($mf['field_name'], $selectedNames, true)) {
        $selectedNames[] = $mf['field_name'];
    }
}
$selectedNames = array_values(array_filter($selectedNames, fn($n) => isset($fieldMap[$n])));

// 单个字段的渲染外壳（含移除按钮；控件复用 _meta_fields.php）
$renderMetaFieldItem = function (array $mf) use ($node) {
    ?>
    <div class="meta-field-item position-relative border rounded p-2 pt-3 mb-2" data-field="<?php echo e($mf['field_name']); ?>">
        <?php if (empty($mf['required'])): ?>
        <button type="button" class="btn-close btn-sm position-absolute top-0 end-0 m-1 meta-field-remove" title="移除该属性" aria-label="移除"></button>
        <?php endif; ?>
        <?php
        $node_fields = [$mf];
        $meta_show_header = false;
        include __DIR__ . '/../_meta_fields.php';
        unset($node_fields, $meta_show_header);
        ?>
    </div>
    <?php
};
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
                    <?php
                    $nodeTitle = trim((string) $node->title) !== '' ? (string) $node->title : '新建文章';
                    $previewUrl = trim((string) $node->slug) !== ''
                        ? site_url('/') . ltrim((string) $node->slug, '/') . (string) config('config.suffix', '')
                        : '';
                    $listUrl = url_action("Node@{$_controller}", $_GET);
                    ?>
                    <div class="zap-editor-topbar d-flex align-items-center gap-2 px-3 py-2 border-bottom">
                        <div class="zap-editor-doc-title text-truncate flex-grow-1" title="<?php echo e($nodeTitle); ?>">
                            <i class="fa fa-file-alt me-2 text-muted"></i><?php echo e($nodeTitle); ?>
                        </div>
                        <div class="card-tools d-flex align-items-center gap-1">
                            <button type="button" class="btn btn-tool btn-sm" id="zapFocusBtn" title="专注模式" onclick="toggleEditorFocus()">
                                <i class="fa fa-expand"></i>
                            </button>
                            <?php if ($previewUrl !== ''): ?>
                            <a class="btn btn-tool btn-sm" href="<?php echo e($previewUrl); ?>" target="_blank" rel="noopener" title="预览文章">
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
                </div>
                <div class="card-body">
                    <div class="tab-content">
                        <div class="tab-pane active" id="tab1">
                            <?php echo $this->partial('_translations', ['translations' => $translations ?? [], 'node' => $node ?? null, 'mode' => 'main_tabs']); ?>
                            <div class="tab-content" id="zapMainLangContent">
                            <div class="tab-pane fade show active" id="zap-lang-default" role="tabpanel">
                            <div class="mb-2">
                                <label for="node_title" class="form-label">标题 <span class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-sm" name="node[title]" id="node_title"
                                       placeholder="请输入文章标题" required value="<?php echo e($node->title); ?>">
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
                                    <div id="slug_preview" class="form-text small text-muted" style="display:none;">
                                        <i class="fa fa-link me-1"></i><span></span>
                                    </div>
                                </div>
                            </div>
                            <div class="mb-2">
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
                            <?php echo $this->partial('_translations', ['translations' => $translations ?? [], 'node' => $node ?? null, 'mode' => 'main_panes']); ?>
                            </div>
                        </div>
                        <div class="tab-pane" id="tab-image">
                            <div class="mb-3">
                                <label class="form-label">文章主图</label>
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
                                        <div class="form-text">文章主图将显示在列表与详情页封面。</div>
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
                                    <span class="gallery-handle text-muted" title="拖拽排序" draggable="true"><i class="fa fa-grip-vertical"></i></span>
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
                        <div class="tab-pane" id="tab-attr">
                            <div class="mb-2">
                                <label class="form-label">分类</label>
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
                            <div class="mb-2">
                                <label for="node_tags" class="form-label">标签</label>
                                <input type="text" class="form-control form-control-sm" name="tags" id="node_tags"
                                       placeholder="多个标签用逗号分隔，如：PHP,CMS,教程"
                                       value="<?php echo e(implode(',', $nodeTagTitles)); ?>"
                                       list="tag-suggest-list" autocomplete="off"/>
                                <datalist id="tag-suggest-list"></datalist>
                            </div>
                            <div class="zap-attr-grid">
                                <div>
                                    <label for="node_status_select" class="form-label">状态</label>
                                    <select class="form-select form-select-sm" name="node[status]" id="node_status_select">
                                        <?php foreach($node->getStatus() as $id => $title){
                                            if($id == \zapcms\models\Node::STATUS_SOFT_DELETE or $id == \zapcms\models\Node::STATUS_TRASH){ continue; } ?>
                                            <option value="<?php echo e($id); ?>" <?php echo $node->status==$id?'selected':null ;?> ><?php echo e($title); ?></option>
                                        <?php } ?>
                                    </select>
                                </div>
                                <div>
                                    <label class="form-label">发布人</label>
                                    <div class="zap-author-box d-flex align-items-center gap-2">
                                        <?php
                                        $authorName = (string)(($node->author_id ? \zap\DB::table('admin')->select('full_name')->where('id',(int)$node->author_id)->fetchColumn() : '') ?: \zapcms\services\Auth::user('full_name'));
                                        if ($authorName === '') { $authorName = (string)\zapcms\services\Auth::user('username'); }
                                        $authorInitial = function_exists('mb_substr') ? mb_substr($authorName, 0, 1, 'UTF-8') : substr($authorName, 0, 1);
                                        ?>
                                        <span class="zap-author-avatar"><?php echo e($authorInitial); ?></span>
                                        <span class="small text-body-secondary text-truncate"><?php echo e($authorName); ?></span>
                                    </div>
                                </div>
                                <div class="zap-attr-full">
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
                                <div>
                                    <label for="node_hits" class="form-label">点击量</label>
                                    <input type="number" class="form-control form-control-sm" name="node[hits]" id="node_hits"
                                           value="<?php echo e($node->hits ?? 0); ?>">
                                </div>
                                <div>
                                    <label for="node_sort_order" class="form-label">排序</label>
                                    <input type="number" class="form-control form-control-sm" name="node[sort_order]" id="node_sort_order"
                                           value="<?php echo e($node->sort_order ?? 0); ?>">
                                </div>
                                <div class="zap-attr-full">
                                    <input type="hidden" name="node[is_top]" value="0">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <label class="form-label mb-0" for="node_is_top">列表置顶</label>
                                        <div class="form-check form-switch mb-0">
                                            <input class="form-check-input" type="checkbox" role="switch" id="node_is_top" name="node[is_top]" value="1"<?php echo !empty($node->is_top) ? ' checked' : ''; ?>>
                                            <label class="form-check-label small" for="node_is_top">置顶显示</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-3" id="meta-fields-section">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <h6 class="text-muted small mb-0"><i class="fa fa-list-alt me-1"></i>自定义字段</h6>
                                    <div class="position-relative">
                                        <button type="button" class="btn btn-outline-primary btn-sm" id="meta-field-add-btn">
                                            <i class="fa fa-plus me-1"></i>添加属性
                                        </button>
                                        <div class="card shadow p-2 d-none zap-meta-panel" id="meta-field-panel"
                                             style="width:360px;max-height:400px;overflow-y:auto;z-index:1050;">
                                            <input type="text" class="form-control form-control-sm mb-1" id="meta-field-search"
                                                   placeholder="搜索属性名称 / 标识…" autocomplete="off">
                                            <div id="meta-field-panel-list">
                                                <?php
                                                $currentGroup = null;
                                                foreach ($allMetaFields as $mf):
                                                    $mfg = (string)($mf['group_name'] ?? '');
                                                    if ($mfg !== $currentGroup):
                                                        $currentGroup = $mfg; ?>
                                                        <div class="d-flex align-items-center justify-content-between meta-field-group mt-2 mb-1" data-group="<?php echo e($mfg); ?>">
                                                            <span class="text-muted small fw-bold"><?php echo $mfg !== '' ? e($mfg) : '未分组'; ?></span>
                                                            <button type="button" class="btn btn-outline-secondary btn-sm py-0 meta-field-add-group" data-group="<?php echo e($mfg); ?>">
                                                                <i class="fa fa-plus me-1"></i>整组添加
                                                            </button>
                                                        </div>
                                                    <?php endif; ?>
                                                        <div class="d-flex align-items-center justify-content-between py-1 meta-field-option"
                                                             data-field="<?php echo e($mf['field_name']); ?>" data-group="<?php echo e($mfg); ?>"
                                                             data-search="<?php echo e(($mf['field_label'] ?: $mf['field_name']) . ' ' . $mf['field_name'] . ' ' . $mfg); ?>">
                                                            <div class="small text-truncate me-2">
                                                                <?php echo e($mf['field_label'] ?: $mf['field_name']); ?>
                                                                <code class="text-muted small ms-1"><?php echo e($mf['field_name']); ?></code>
                                                                <span class="badge text-bg-light ms-1"><?php echo e($mf['type'] ?? 'text'); ?></span>
                                                            </div>
                                                            <button type="button" class="btn btn-outline-primary btn-sm py-0 meta-field-add"
                                                                    data-field="<?php echo e($mf['field_name']); ?>">添加</button>
                                                        </div>
                                                <?php endforeach; ?>
                                            </div>
                                            <div class="text-muted small text-center py-2 d-none" id="meta-field-empty">未找到匹配的属性</div>
                                        </div>
                                    </div>
                                </div>
                                <div id="meta-field-list">
                                    <?php
                                    $lastGroup = null;
                                    foreach ($selectedNames as $sname):
                                        $smf = $fieldMap[$sname];
                                        if (($smf['group_name'] ?? '') !== $lastGroup) {
                                            $lastGroup = (string)($smf['group_name'] ?? '');
                                            if ($lastGroup !== '') {
                                                echo '<h6 class="text-muted small mb-2 mt-2"><i class="fa fa-tags me-1"></i>' . e($lastGroup) . '</h6>';
                                            }
                                        }
                                        $renderMetaFieldItem($smf);
                                    endforeach;
                                    ?>
                                    <div class="text-muted small py-3 text-center d-none" id="meta-field-none">暂无自定义属性</div>
                                </div>
                                <input type="hidden" name="meta[_meta_fields]" id="meta_selected_input"
                                       value="<?php echo e(json_encode($selectedNames, JSON_UNESCAPED_UNICODE)); ?>">
                            </div>
                            <div class="d-none" id="meta-field-templates">
                                <?php foreach ($allMetaFields as $mf): ?>
                                    <template data-field="<?php echo e($mf['field_name']); ?>"><?php $renderMetaFieldItem($mf); ?></template>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="tab-pane" id="tab2">
                            <!-- 搜索结果实时预览 -->
                            <div class="zap-serp-preview mb-3">
                                <div class="zap-serp-label"><i class="fa fa-search me-1"></i>搜索结果预览</div>
                                <div class="zap-serp-title" id="serpTitle">页面标题</div>
                                <div class="zap-serp-url" id="serpUrl"><?php echo e(site_url('/')); ?></div>
                                <div class="zap-serp-desc" id="serpDesc">页面描述，建议控制在 150 字以内。</div>
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
                                        <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none"
                                                onclick="fillDescFromContent()" title="提取正文前 150 字作为描述">
                                            <i class="fa fa-magic me-1"></i>从正文提取
                                        </button>
                                    </div>
                                </div>
                                <textarea class="form-control form-control-sm" name="node[description]"
                                          id="node_description" rows="3"
                                          placeholder="建议控制在150字以内，用于搜索引擎摘要"><?php echo e($node->description); ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <span class="text-muted small" id="zapSavedAt"></span>
                    <span class="text-muted small">
                        <i class="fa fa-clock me-1"></i>创建于 <span id="node_pub_time_label"><?php echo date('Y-m-d H:i', $node->pub_time ?: time()); ?></span>
                        &nbsp;|&nbsp;
                        <i class="fa fa-edit me-1"></i>最后更新于 <span id="node_update_time_label"><?php echo date('Y-m-d H:i', $node->update_time ?: time()); ?></span>
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
.catalog-list { max-height: 180px; overflow-y: auto; border: 1px solid var(--bs-border-color); border-radius: var(--bs-border-radius); padding: 0.5rem; }
.note-editor { border-radius: var(--bs-border-radius); overflow: hidden; }
.note-editor.note-frame { border-color: var(--bs-border-color); }
.note-editor.note-frame .note-editing-area { min-height: 300px; }
.note-editor .note-toolbar { background: #f8f9fa; padding: 0.2rem 0.4rem; }
.note-editor .note-toolbar .note-btn-group { margin-right: .35rem; }
.note-editor .note-toolbar .note-btn {
    padding: .25rem .55rem; font-size: .8125rem; line-height: 1.3;
}
/* 大纲/字数注入到 Summernote 工具栏右侧 */
.zap-editor-meta {
    float: right; display: flex; align-items: center; gap: .6rem;
    position: relative; z-index: 5; min-height: 24px;
}
.zap-editor-meta .dropdown-menu { z-index: 1080; }
/* 正文区不限制宽度，让内容铺满编辑区 */
.note-editor .note-editable {
    padding-left: 1.5rem; padding-right: 1.5rem;
    overflow-wrap: break-word; word-break: break-word;
}
.note-editor .note-editable pre { white-space: pre-wrap; word-break: break-word; }
/* 与列表页一致：隐藏重复的页面标题/面包屑，保留顶部栏目导航 */
.app-main > .app-content-header > .container-fluid > .row { display: none !important; }
.app-main > .app-content-header { padding: 0 !important; border-bottom: 0 !important; }
.app-main > .app-content-header .zap-top-catalog-nav {
    padding: .35rem 0; margin-top: 0; border-top: 0;
}
/* 主内容区语言标签 */
.zap-main-lang-tabs { border-bottom: 1px solid var(--bs-border-color); }
.zap-main-lang-tabs .nav-link { font-size: .8125rem; padding: .4rem .8rem; }
.zap-main-lang-tabs .nav-link.active { color: #0d6efd; border-color: var(--bs-border-color) var(--bs-border-color) #fff; }
#zapMainLangContent > .tab-pane { padding-top: 0; }
/* ── 顶部工具栏（WordPress 式） ── */
.zap-editor-topbar { min-height: 44px; }
.zap-editor-doc-title { min-width: 0; font-size: .875rem; font-weight: 600; color: var(--bs-body-color); }
.zap-editor-topbar .btn-tool {
    width: 30px; height: 30px; padding: 0;
    display: inline-flex; align-items: center; justify-content: center;
}
.zap-editor-topbar .btn-tool:hover,
.zap-editor-topbar .btn-tool.active { color: #0d6efd; background: #e7f1ff; }
/* 专注模式：隐藏侧栏，编辑区占满 */
.zap-editor-card.zap-editor-focus > .card-body > .tab-content { grid-template-columns: 1fr; }
.zap-editor-card.zap-editor-focus .zap-editor-side { display: none; }
/* Summernote 全屏：隐藏后台外壳，让编辑器真正铺满视口 */
body.note-fullscreen-body .app-header,
body.note-fullscreen-body .app-sidebar,
body.note-fullscreen-body .app-content-header,
body.note-fullscreen-body .app-footer,
body.note-fullscreen-body .zap-editor-card > .card-header,
body.note-fullscreen-body .zap-editor-card > .card-footer,
body.note-fullscreen-body .zap-editor-side { display: none !important; }
body.note-fullscreen-body .note-editor.note-frame.fullscreen {
    z-index: 3000 !important; height: 100vh !important; background: #fff;
}
body.note-fullscreen-body .note-editor.fullscreen .note-editable {
    max-width: none; margin: 0; padding: 1rem 2rem;
}
/* ── 两栏布局：高度自适应内容 ── */
.zap-editor-card > .card-body { padding: 0; }
.zap-editor-card > .card-body > .tab-content {
    display: grid; grid-template-columns: minmax(0, 1fr) 320px; gap: 0;
    align-items: stretch;
}
/* 仅作用于顶层主面板，避免影响翻译面板内部嵌套的 tab-content */
.zap-editor-card > .card-body > .tab-content > #tab1 {
    display: block !important; opacity: 1 !important;
    grid-column: 1; grid-row: 1; min-width: 0;
    padding: 1rem;
}
/* 侧栏字段连续排列（无卡片分区），保留 WordPress 式可折叠轻标题，属性在最前 */
.zap-editor-side {
    grid-column: 2; grid-row: 1; min-width: 0;
    display: flex; flex-direction: column; gap: 1rem;
    padding: 1rem;
    border-left: 1px solid var(--bs-border-color);
}
/* 双栏滚动条：默认隐藏，悬停显示 4px 细条 */
.zap-editor-card > .card-body > .tab-content > #tab1,
.zap-editor-side {
    scrollbar-width: thin;
    scrollbar-color: transparent transparent;
    box-shadow: inset 0 -10px 12px -12px rgba(0,0,0,.16);
}
.zap-editor-card > .card-body > .tab-content > #tab1:hover,
.zap-editor-side:hover { scrollbar-color: rgba(0,0,0,.28) transparent; }
.zap-editor-card > .card-body > .tab-content > #tab1::-webkit-scrollbar,
.zap-editor-side::-webkit-scrollbar { width: 4px; height: 4px; }
.zap-editor-card > .card-body > .tab-content > #tab1::-webkit-scrollbar-track,
.zap-editor-side::-webkit-scrollbar-track { background: transparent; }
.zap-editor-card > .card-body > .tab-content > #tab1::-webkit-scrollbar-thumb,
.zap-editor-side::-webkit-scrollbar-thumb { background: transparent; border-radius: 4px; }
.zap-editor-card > .card-body > .tab-content > #tab1:hover::-webkit-scrollbar-thumb,
.zap-editor-side:hover::-webkit-scrollbar-thumb { background: rgba(0,0,0,.28); }
[data-bs-theme="dark"] .zap-editor-card > .card-body > .tab-content > #tab1,
[data-bs-theme="dark"] .zap-editor-side { box-shadow: inset 0 -10px 12px -12px rgba(255,255,255,.12); }
[data-bs-theme="dark"] .zap-editor-card > .card-body > .tab-content > #tab1:hover,
[data-bs-theme="dark"] .zap-editor-side:hover { scrollbar-color: rgba(255,255,255,.28) transparent; }
[data-bs-theme="dark"] .zap-editor-card > .card-body > .tab-content > #tab1:hover::-webkit-scrollbar-thumb,
[data-bs-theme="dark"] .zap-editor-side:hover::-webkit-scrollbar-thumb { background: rgba(255,255,255,.28); }
.zap-editor-side > .tab-pane { display: block !important; opacity: 1 !important; }
.zap-editor-side > .tab-pane > .zap-side-title {
    display: flex; align-items: center; gap: .35rem;
    width: 100%; padding: .25rem .4rem; margin-left: -.4rem;
    border: 0; border-radius: var(--bs-border-radius-sm); background: transparent;
    font-weight: 600; font-size: .8125rem; color: var(--bs-secondary-color, #6c757d);
    text-align: left; cursor: pointer;
    transition: background-color .15s ease, color .15s ease;
}
.zap-editor-side > .tab-pane > .zap-side-title:hover,
.zap-editor-side > .tab-pane > .zap-side-title:focus-visible {
    background: var(--bs-tertiary-bg, #f1f3f5);
    color: var(--bs-body-color);
}
.zap-editor-side > .tab-pane > .zap-side-title:focus-visible {
    outline: 2px solid var(--bs-primary, #0d6efd); outline-offset: 1px;
}
.zap-side-title .fa-chevron-down {
    font-size: .7rem; transition: transform .15s ease, color .15s ease;
}
.zap-side-title:hover .fa-chevron-down { color: var(--bs-primary, #0d6efd); }
.zap-side-title.collapsed .fa-chevron-down { transform: rotate(-90deg); }
/* 间距放在折叠体内部，随 height 动画一起收起，避免 margin-top 突变抖动 */
.zap-side-body-inner { padding-top: .35rem; }
/* 自定义字段面板挂到 body，固定定位避免被侧栏 overflow 裁切 */
.zap-meta-panel { position: fixed !important; max-width: calc(100vw - 16px); }
@media (max-width: 991.98px) {
    .zap-editor-card > .card-body > .tab-content {
        grid-template-columns: 1fr; height: auto; min-height: 0;
    }
    .zap-editor-card > .card-body > .tab-content > #tab1 {
        grid-column: 1; grid-row: auto; overflow: visible;
    }
    .zap-editor-side {
        grid-column: 1; grid-row: auto; overflow: visible; border-left: 0;
    }
}
/* 搜索结果预览卡片 */
.zap-serp-preview { padding: .75rem; background: #fff; border: 1px solid #e3e6e9; border-radius: var(--bs-border-radius); }
.zap-serp-label { color: #98a1aa; font-size: .72rem; margin-bottom: .4rem; }
.zap-serp-title { color: #1a0dab; font-size: 1.05rem; line-height: 1.3; margin-bottom: .1rem; }
.zap-serp-url { color: #006621; font-size: .78rem; margin-bottom: .2rem; word-break: break-all; }
.zap-serp-desc { color: #545454; font-size: .82rem; line-height: 1.5; }
/* 附加图片拖拽 */
.gallery-handle { cursor: grab; padding: 0 .15rem; user-select: none; }
.gallery-handle:active { cursor: grabbing; }
.gallery-item.gallery-dragging { opacity: .4; }
.gallery-item.drag-over { outline: 2px dashed #0d6efd; outline-offset: 2px; }
/* 脏字段轻提示：改动过的字段左侧细线，保存后清除 */
.zap-field-dirty { border-left: 2px solid #0d6efd; padding-left: .6rem; margin-left: -.6rem; }
.zap-author-avatar {
    display: inline-flex; align-items: center; justify-content: center;
    width: 22px; height: 22px; border-radius: 50%; flex: 0 0 auto;
    background: #0d6efd; color: #fff; font-size: .72rem; font-weight: 600;
}
/* 属性面板：适配 320px 侧栏的紧凑两列布局 */
.zap-editor-side #tab-attr .zap-attr-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .75rem; }
.zap-editor-side #tab-attr .zap-attr-full { grid-column: 1 / -1; }
.zap-editor-side #tab-attr .zap-author-box {
    height: 31px; padding: .25rem .5rem; border: 1px solid var(--bs-border-color);
    border-radius: var(--bs-border-radius-sm); background: var(--bs-tertiary-bg, #f8f9fa);
}
.zap-editor-side #tab-attr .zap-author-box .text-truncate { min-width: 0; }
.zap-editor-side #tab-attr .form-check.form-switch { padding-left: 2.25rem; }
/* 正文大纲 */
.zap-outline-menu { max-height: 320px; overflow-y: auto; min-width: 220px; font-size: .85rem; }
.zap-outline-menu .dropdown-item { white-space: normal; }
.note-editable h2, .note-editable h3 { transition: background .2s ease; }
.note-editable .zap-outline-hit { background: #fff3cd; }
[data-bs-theme="dark"] .zap-serp-preview { background: #1c2126; border-color: #2d343b; }
[data-bs-theme="dark"] .zap-serp-title { color: #8ab4f8; }
[data-bs-theme="dark"] .zap-serp-url { color: #81c995; }
[data-bs-theme="dark"] .zap-serp-desc { color: #c9d1d9; }
</style>

<script>
var slugConfig = {
    separator: '<?php echo option('slug.separator', '-'); ?>',
    style: '<?php echo option('slug.style', 'default'); ?>',
    ajaxUrl: '<?php echo url_action('System@ajaxSlug'); ?>'
};

$(function(){
    // 两栏布局：侧栏字段连续排列（无卡片分区、可折叠），属性放第一个
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
        var panels = [
            {sel:'#tab-attr',  title:'属性',  open:true},
            {sel:'#tab-image', title:'图片',  open:true},
            {sel:'#tab2',      title:'SEO',   open:true}
        ];
        panels.forEach(function(p){
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

    // 把大纲/字数注入 Summernote 工具栏右侧，编辑区不再单独占一行
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

    // 让正文区补满右栏撑出的高度，避免编辑器下方留白
    (function fitEditorOnReady(){
        var timer = setInterval(function(){
            if (document.querySelector('.note-editable')) {
                clearInterval(timer);
                fitEditorFill();
            }
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
    $('#zapForm').validate({ignore:'', messages:{"node[title]":"文章标题必须填写"}});

    updateContentCount();
    updateDescCount();
    $('#node_description').on('input', updateDescCount);
    $('#node_keywords').on('input', updateSerpPreview);
    $('#node_description').on('input', updateSerpPreview);
    $('#node_title').on('input', updateSerpPreview);
    updateSerpPreview();

    // 草稿恢复：若本地有比服务器更新的暂存，询问是否恢复
    var draft = zapDraftRead();
    if (draft && draft.saved_at) {
        Swal.fire({
            title: '发现未保存的内容',
            html: '检测到本地暂存于 <strong>' + new Date(draft.saved_at).toLocaleString('zh-CN') + '</strong> 的编辑内容，是否恢复？',
            icon: 'question', showCancelButton: true,
            confirmButtonText: '恢复内容', cancelButtonText: '放弃',
            reverseButtons: true
        }).then(function (r) {
            if (r.isConfirmed) {
                if (draft.title != null) $('#node_title').val(draft.title);
                if (draft.slug != null) $('#node_slug').val(draft.slug);
                if (draft.description != null) $('#node_description').val(draft.description);
                if (draft.keywords != null) $('#node_keywords').val(draft.keywords);
                if (draft.tags != null) $('#node_tags').val(draft.tags);
                if (draft.content != null) {
                    $('#node_content').val(draft.content);
                    if ($('#node_content').data('summernote')) $('#node_content').summernote('code', draft.content);
                }
                updateContentCount(); updateDescCount(); updateSerpPreview(); updateSlugPreview();
                zapDirty = true;
            } else {
                zapDraftClear();
            }
        });
    }

    // 输入即标记脏字段 + 自动暂存
    $('#zapForm').on('input change', 'input, textarea, select', markDirty);

    // 离开页面提醒（排除保存后的正常跳转）
    window.addEventListener('beforeunload', function (e) {
        if (!zapDirty) return;
        e.preventDefault();
        e.returnValue = '';
    });

    // Ctrl / Cmd + S 或 Ctrl / Cmd + Enter 保存
    $(document).on('keydown.saveShortcut', function (e) {
        if ((e.ctrlKey || e.metaKey) && ['s', 'enter'].indexOf(e.key.toLowerCase()) !== -1) {
            e.preventDefault();
            if (typeof save === 'function') { save(); }
        }
    });
});
// ── 自动暂存与离开提醒 ──
var ZAP_DRAFT_KEY = 'zap_draft_article_<?php echo (int)$node->id; ?>';
var zapDirty = false;
var zapDraftTimer = null;
function zapDraftRead() {
    try { return JSON.parse(localStorage.getItem(ZAP_DRAFT_KEY) || 'null'); } catch (e) { return null; }
}
function zapDraftClear() {
    try { localStorage.removeItem(ZAP_DRAFT_KEY); } catch (e) {}
}
function zapDraftSave() {
    var $ed = $('#node_content');
    var content = ($ed.data('summernote') && $ed.summernote('code')) ? $ed.summernote('code') : $ed.val();
    var data = {
        title: $('#node_title').val(), slug: $('#node_slug').val(),
        content: content, description: $('#node_description').val(),
        keywords: $('#node_keywords').val(), tags: $('#node_tags').val(),
        saved_at: Date.now()
    };
    try { localStorage.setItem(ZAP_DRAFT_KEY, JSON.stringify(data)); } catch (e) {}
    var t = $('#zapSavedAt');
    if (t.length) { t.text('已暂存至本地 ' + new Date().toTimeString().slice(0, 5)); }
}
function markDirty() {
    zapDirty = true;
    // 脏字段轻提示：给改动的字段加左侧细线
    var f = this;
    if (f && f.closest) {
        var wrap = f.closest('.mb-2');
        if (wrap) wrap.classList.add('zap-field-dirty');
    }
    clearTimeout(zapDraftTimer);
    zapDraftTimer = setTimeout(zapDraftSave, 2000);
}
// 正文区专用：只标记正文容器为脏
function zapMarkContentDirty() {
    zapDirty = true;
    var wrap = document.getElementById('node_content');
    if (wrap) {
        var box = wrap.closest('.mb-2');
        if (box) box.classList.add('zap-field-dirty');
    }
    clearTimeout(zapDraftTimer);
    zapDraftTimer = setTimeout(zapDraftSave, 2000);
}
function zapDirtyReset() {
    zapDirty = false;
    document.querySelectorAll('.zap-field-dirty').forEach(function (el) { el.classList.remove('zap-field-dirty'); });
}
// 发布时间快捷设置（datetimepicker 格式 Y-m-d H:i:s）
function zapFormatDateTime(d) {
    var p = function (n) { return String(n).padStart(2, '0'); };
    return d.getFullYear() + '-' + p(d.getMonth() + 1) + '-' + p(d.getDate()) + ' '
        + p(d.getHours()) + ':' + p(d.getMinutes()) + ':' + p(d.getSeconds());
}
function setPubTimeNow() {
    $('#node_pub_time').val(zapFormatDateTime(new Date())).trigger('change');
}
function setPubTimeLater() {
    var d = new Date(); d.setDate(d.getDate() + 1);
    $('#node_pub_time').val(zapFormatDateTime(d)).trigger('change');
}
// 正文/SEO 字数统计（纯文本字数，忽略标签）
function zapCountText(html) {
    var text = $('<div>').html(html || '').text().replace(/\s+/g, '');
    return text.length;
}
function updateContentCount() {
    var $ed = $('#node_content');
    var html = ($ed.data('summernote') && $ed.summernote('code')) ? $ed.summernote('code') : $ed.val();
    var el = $('#zapContentCount');
    if (el.length) { el.text(zapCountText(html) + ' 字'); }
    updateOutline(html);
}
// 正文大纲：提取 H2/H3，点击滚动定位到编辑器内的标题
function updateOutline(html) {
    var menu = document.getElementById('zapOutlineMenu');
    if (!menu) return;
    var $doc = $('<div>').html(html || '');
    var heads = $doc.find('h2, h3').toArray();
    if (!heads.length) {
        menu.innerHTML = '<li><span class="dropdown-item-text text-muted small">暂无标题（用 H2/H3 生成）</span></li>';
        return;
    }
    var items = heads.map(function (h, i) {
        var t = ($(h).text() || '').replace(/\s+/g, ' ').trim() || ('标题 ' + (i + 1));
        var cls = h.tagName.toLowerCase() === 'h3' ? ' ps-3' : '';
        return '<li><button type="button" class="dropdown-item' + cls + '" onclick="jumpToHeading(' + i + ')">' + $('<div>').text(t).html() + '</button></li>';
    });
    menu.innerHTML = items.join('');
}
function jumpToHeading(index) {
    var heads = document.querySelectorAll('.note-editable h2, .note-editable h3');
    var target = heads[index];
    if (!target) return;
    target.scrollIntoView({behavior: 'smooth', block: 'center'});
    // 闪烁高亮提示定位
    target.classList.add('zap-outline-hit');
    setTimeout(function () { target.classList.remove('zap-outline-hit'); }, 1200);
}
function updateDescCount() {
    var el = $('#zapDescCount');
    if (!el.length) { return; }
    var n = zapCountText($('#node_description').val());
    el.text(n + ' / 150');
    el.toggleClass('text-danger', n > 150);
}
// 搜索结果预览：标题 / URL / 描述，超长标红
function updateSerpPreview() {
    var title = $('#node_title').val().trim() || '页面标题';
    var slug  = $('#node_slug').val().trim();
    var url   = '<?php echo site_url('/'); ?>' + slug + '<?php echo htmlspecialchars((string) config('config.suffix', '')); ?>';
    var desc  = $('#node_description').val().trim() || '页面描述，建议控制在 150 字以内。';
    $('#serpTitle').text(title.length > 60 ? title.slice(0, 60) + '…' : title);
    $('#serpUrl').text(url);
    $('#serpDesc').text(desc.length > 150 ? desc.slice(0, 150) + '…' : desc);
    $('#serpDesc').toggleClass('text-danger', zapCountText($('#node_description').val()) > 150);
}
// 提取正文纯文本前 150 字作为描述
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
    if (window.innerWidth <= 991) { return; }
    if (document.querySelector('.note-editor.fullscreen')) { return; }
    var pane = document.getElementById('tab1');
    var editable = document.querySelector('.note-editable');
    var content = document.getElementById('zapMainLangContent');
    if (!pane || !editable || !content || !pane.clientHeight) { return; }
    // 让编辑器占满 #tab1 被右栏撑出的剩余高度，但不低于 Summernote 默认高度
    var cs = window.getComputedStyle(pane);
    var padBottom = parseFloat(cs.paddingBottom) || 0;
    var paneTop = pane.getBoundingClientRect().top;
    var contentBottom = content.getBoundingClientRect().bottom - paneTop;
    var targetBottom = pane.clientHeight - padBottom;
    var h = editable.offsetHeight + (targetBottom - contentBottom);
    if (h < 400) { h = 400; }
    editable.style.height = h + 'px';
    var codable = document.querySelector('.note-codable');
    if (codable) { codable.style.height = h + 'px'; }
}
function save() {
    // 保存成功后不刷新页面：新建会把地址 replaceState 到 edit/{id}，修改则就地更新时间
    Zap.saveNodeForm({
        isNew: <?php echo $node->id ? 'false' : 'true'; ?>,
        beforeSerialize: function () { cleanGallery(); },
        onSaved: function () {
            var t = $('#zapSavedAt');
            if (t.length) { t.text('已保存 ' + new Date().toTimeString().slice(0, 5)); }
            zapDraftClear();
            zapDirtyReset();
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
    setTimeout(fitEditorFill, 60);
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
        '<span class="gallery-handle text-muted" title="拖拽排序" draggable="true"><i class="fa fa-grip-vertical"></i></span>' +
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
// 附加图片拖拽排序（仅手柄可发起，拖动预览为整行）
(function () {
    var dragItem = null;
    document.addEventListener('dragstart', function (e) {
        var handle = e.target.closest && e.target.closest('#gallery-list .gallery-handle');
        if (!handle) return;
        var item = handle.closest('.gallery-item');
        if (!item) return;
        dragItem = item;
        item.classList.add('gallery-dragging');
        e.dataTransfer.effectAllowed = 'move';
        try {
            e.dataTransfer.setData('text/plain', '');
            e.dataTransfer.setDragImage(item, 12, 12);
        } catch (err) {}
    });
    document.addEventListener('dragover', function (e) {
        if (!dragItem) return;
        var item = e.target.closest && e.target.closest('#gallery-list .gallery-item');
        if (!item || item === dragItem) return;
        e.preventDefault();
        var box = item.getBoundingClientRect();
        var before = (e.clientY - box.top) < box.height / 2;
        document.getElementById('gallery-list').insertBefore(dragItem, before ? item : item.nextSibling);
    });
    document.addEventListener('dragend', function () {
        if (!dragItem) return;
        dragItem.classList.remove('gallery-dragging');
        dragItem = null;
    });
})();
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
    // 标签联想（输入时补全已有标签）
    $('#node_tags').on('input', function () {
        var kw = $(this).val().split(/[,，、\s]+/).pop().trim();
        if (kw.length < 1) { $('#tag-suggest-list').empty(); return; }
        $.getJSON('<?php echo url_action('Tag@suggest'); ?>', {keyword: kw}, function (res) {
            if (res.code !== 0 || !res.list) { return; }
            var $list = $('#tag-suggest-list').empty();
            $.each(res.list, function (i, t) {
                $('<option>').val(t.title).text(t.title).appendTo($list);
            });
        });
    });
});

// ---- 自定义字段：按文章组合（搜索添加 / 分组批量添加）----
var metaSelected = [];
function metaSync() {
    $('#meta_selected_input').val(JSON.stringify(metaSelected));
    $('#meta-field-panel .meta-field-option').each(function () {
        var added = metaSelected.indexOf(String($(this).data('field'))) !== -1;
        $(this).find('.meta-field-add').prop('disabled', added).text(added ? '已添加' : '添加');
    });
    $('#meta-field-none').toggleClass('d-none', metaSelected.length > 0);
}
function metaBindPickers($scope) {
    if (!$scope) { return; }
    $scope.find('.datetimepicker').each(function () {
        $(this).datetimepicker({ format: $(this).data('format') || 'Y-m-d H:i:s' });
    });
}
function metaAdd(name) {
    name = String(name);
    if (metaSelected.indexOf(name) !== -1) return;
    var tpl = document.querySelector('#meta-field-templates template[data-field="' + name + '"]');
    if (!tpl) return;
    metaSelected.push(name);
    var $item = $(tpl.content.cloneNode(true)).appendTo('#meta-field-list');
    metaBindPickers($item);
    metaSync();
}
function metaAddGroup(group) {
    document.querySelectorAll('#meta-field-panel .meta-field-option[data-group="' + group + '"]').forEach(function (el) {
        metaAdd(el.getAttribute('data-field'));
    });
}
$(function () {
    try { metaSelected = JSON.parse($('#meta_selected_input').val() || '[]'); } catch (e) { metaSelected = []; }
    metaSync();
    var $metaPanel = $('#meta-field-panel').appendTo(document.body);
    function positionMetaPanel($btn) {
        $metaPanel.removeClass('d-none');
        var r = $btn[0].getBoundingClientRect();
        var w = $metaPanel.outerWidth();
        var h = $metaPanel.outerHeight();
        var left = Math.min(Math.max(8, r.right - w), Math.max(8, window.innerWidth - w - 8));
        var top = r.bottom + 4;
        if (top + h > window.innerHeight - 8) {
            top = Math.max(8, r.top - h - 4);
        }
        $metaPanel.css({top: top + 'px', left: left + 'px'});
    }
    $('#meta-field-add-btn').on('click', function (e) {
        e.stopPropagation();
        if ($metaPanel.hasClass('d-none')) {
            positionMetaPanel($(this));
        } else {
            $metaPanel.addClass('d-none');
        }
        $('#meta-field-search').trigger('input');
    });
    $(window).on('resize', function () { $metaPanel.addClass('d-none'); });
    document.addEventListener('scroll', function (e) {
        if ($metaPanel[0].contains(e.target)) { return; }
        $metaPanel.addClass('d-none');
    }, true);
    $(document).on('click', function (e) {
        if (!$(e.target).closest('#meta-field-panel, #meta-field-add-btn').length) {
            $metaPanel.addClass('d-none');
        }
    });
    $('#meta-field-search').on('input', function () {
        var kw = $(this).val().trim().toLowerCase(), any = false;
        $('#meta-field-panel .meta-field-option').each(function () {
            var hit = !kw || String($(this).data('search')).toLowerCase().indexOf(kw) !== -1;
            $(this).toggleClass('d-none', !hit);
            if (hit) any = true;
        });
        $('#meta-field-panel .meta-field-group').each(function () {
            var $items = $('#meta-field-panel .meta-field-option[data-group="' + $(this).data('group') + '"]:not(.d-none)');
            $(this).toggleClass('d-none', kw !== '' && $items.length === 0);
        });
        $('#meta-field-empty').toggleClass('d-none', any || kw === '');
    });
    $(document).on('click', '.meta-field-add', function () { metaAdd($(this).data('field')); });
    $(document).on('click', '.meta-field-add-group', function () { metaAddGroup(String($(this).data('group'))); });
    $(document).on('click', '.meta-field-remove', function () {
        var $item = $(this).closest('.meta-field-item'), name = String($item.data('field'));
        $item.remove();
        metaSelected = metaSelected.filter(function (n) { return n !== name; });
        metaSync();
    });
});
</script>
<?php
\zapcms\services\Editor::instance()->create('.summernote', [
    'image_upload' => 'zapSendFile',
    'upload_url' => url_action('Upload@image'),
    'on_change' => 'function(){ if(typeof updateContentCount === "function"){ updateContentCount(); } if(typeof zapMarkContentDirty === "function"){ zapMarkContentDirty(); } }'
]);
