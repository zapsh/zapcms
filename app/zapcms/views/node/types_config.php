<?php use zap\facades\Url; ?>
<?php $this->layout('layouts/common'); ?>
<?php
/**
 * @var array $types   模型列表
 * @var array $configs 模型配置
 * @var array $fields  各模型自定义字段（用于列表列勾选）
 */
$configs  = $configs ?? [];
$fields   = $fields ?? [];
$defaults = \zapcms\services\NodeType::CONFIG_DEFAULTS;
$inputTypes = [
    'list_per_page'      => ['label' => '列表分页显示数量', 'hint' => '该模型列表页每页显示的内容条数'],
    'list_image_width'   => ['label' => '列表图片宽度',     'hint' => '列表页缩略图宽度（px）'],
    'list_image_height'  => ['label' => '列表图片高度',     'hint' => '列表页缩略图高度（px）'],
    'detail_image_width' => ['label' => '详情页图片宽度',   'hint' => '文章/产品详情页内图片宽度（px）'],
    'detail_image_height'=> ['label' => '详情页图片高度',   'hint' => '文章/产品详情页内图片高度（px）'],
];
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-1"><i class="fa fa-sliders-h"></i> 内容模型显示配置</h4>
        <div class="text-muted">按内容模型配置列表分页数量、图片尺寸与列表默认展示字段，保存后前台与后台列表页生效。</div>
    </div>
    <a href="<?= Url::action('Node/types')?>" class="btn btn-outline-secondary btn-sm">
        <i class="fa fa-arrow-left"></i> 返回内容模型
    </a>
</div>

<div class="row g-3">
    <!-- 左侧导航 -->
    <div class="col-md-3">
        <div class="list-group list-group-flush nav nav-pills flex-column" id="types-nav" role="tablist">
            <?php $navIdx = 0; foreach ($types as $i => $type): 
                if(\zapcms\services\NodeType::isVirtual((string)$type['type_name'])) continue; // 虚拟模型不需要配置
                $navIdx++;
                ?>
            <?php $typeName = (string)$type['type_name']; ?>
            <a href="#type-<?= (int)$type['type_id'] ?>" class="list-group-item list-group-item-action <?= $navIdx === 1 ? 'active' : '' ?>"
               data-bs-toggle="pill" role="tab" aria-selected="<?= $navIdx === 1 ? 'true' : 'false' ?>">
                <div class="d-flex align-items-center justify-content-between">
                    <span>
                        <i class="fa fa-cube me-2"></i><?= e($type['title']) ?>
                    </span>
                    <code class="small"><?= e($typeName) ?></code>
                </div>
                <?php if ((int)$type['status'] === 0): ?>
                <small class="text-muted"><span class="badge bg-secondary mt-1">已禁用</span></small>
                <?php endif; ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- 右侧配置内容 -->
    <div class="col-md-9">
        <form id="configForm" method="post" action="<?= Url::action('Node/typesConfigSave')?>">
            <div class="tab-content">
                <?php $tabIdx = 0; foreach ($types as $i => $type): 
                    if(\zapcms\services\NodeType::isVirtual((string)$type['type_name'])) continue; // 虚拟模型不需要配置
                    $tabIdx++;
                    ?>
                <?php
                    $typeName = (string)$type['type_name'];
                    $cfg = $configs[$typeName] ?? $defaults;
                    $typeFields = $fields[$typeName] ?? [];
                    $listColumns = (array)($cfg['list_columns'] ?? []);
                ?>
                <div class="tab-pane fade <?= $tabIdx === 1 ? 'show active' : '' ?>" id="type-<?= (int)$type['type_id'] ?>" role="tabpanel">
                    <div class="card">
                        <div class="card-header bg-white">
                            <h3 class="card-title"><i class="fa fa-cube"></i> <?= e($type['title']) ?>
                                <code class="ms-2 small"><?= e($typeName) ?></code>
                            </h3>
                            <div class="text-muted small"><?= e($type['description'] ?? '') ?></div>
                        </div>
                        <div class="card-body">
                            <h6 class="text-muted fw-semibold mb-3">基本显示设置</h6>
                            <div class="row g-3">
                                <?php foreach ($inputTypes as $key => $meta): ?>
                                <?php $value = $cfg[$key] ?? $defaults[$key]; ?>
                                <div class="col-md-6 col-lg-4">
                                    <label class="form-label d-block">
                                        <?= $meta['label']?>
                                        <span class="text-muted small">(默认 <?= (int)$defaults[$key]?>)</span>
                                    </label>
                                    <input type="number" min="1" step="1"
                                           name="config[<?= e($typeName)?>][<?= $key?>]"
                                           value="<?= (int)$value?>" class="form-control">
                                    <div class="form-text"><?= $meta['hint']?></div>
                                </div>
                                <?php endforeach; ?>
                            </div>

                            <hr>

                            <h6 class="text-muted fw-semibold mb-2">列表默认展示字段</h6>
                            <p class="text-secondary small mb-3">
                                勾选后，后台内容列表中该模型将默认加载并展示这些自定义字段列（数据保存于 meta，未勾选的字段不会在列表中展示）。
                            </p>
                            <?php if (empty($typeFields)): ?>
                            <div class="alert alert-light border small mb-0">
                                <i class="fa fa-info-circle me-1"></i>该模型暂无自定义字段，请在「字段管理」中添加字段后再配置列表展示。
                            </div>
                            <?php else: ?>
                            <div class="row g-2">
                                <?php foreach ($typeFields as $f): ?>
                                <?php
                                    $fName = (string)$f['field_name'];
                                    $checked = in_array($fName, $listColumns, true) ? 'checked' : '';
                                ?>
                                <div class="col-md-6 col-lg-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox"
                                               name="config[<?= e($typeName)?>][list_columns][]"
                                               value="<?= e($fName)?>" id="lc-<?= e($typeName)?>-<?= e($fName)?>" <?= $checked ?>>
                                        <label class="form-check-label" for="lc-<?= e($typeName)?>-<?= e($fName)?>">
                                            <?= e($f['field_label'] ?? $fName)?>
                                            <code class="small text-muted ms-1"><?= e($fName)?></code>
                                        </label>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="d-flex gap-2 mt-3">
                <button type="submit" class="btn btn-primary">
                    <i class="fa fa-save"></i> 保存全部配置
                </button>
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="history.back()"><i class="fa fa-cancel" ></i>  取消</button>
            </div>
        </form>
    </div>
</div>

<script>
(function(){
    $('#configForm').on('submit', function(e){
        e.preventDefault();
        var $form = $(this);
        $.post($form.attr('action'), $form.serialize(), function(res){
            if(res && res.code === 0){
                Swal.fire({
                    title: "保存成功",
                    icon: "success",
                    draggable: true
                    });
            }else{
                Swal.fire({
                    title: "保存失败",
                    icon: "error",
                    draggable: true
                });
            }
        }, 'json').fail(function(){
            Swal.fire({
                title: "网络错误",
                icon: "error",
                draggable: true
            });
        });
    });
})();
</script>
