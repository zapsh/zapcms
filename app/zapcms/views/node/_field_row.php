<?php
/**
 * 字段行模板（用于 PHP 渲染现有字段）
 *
 * 依赖变量：
 *   - $i : 行索引
 *   - $f : 字段数组
 *   - $fieldTypes : 类型定义（label/icon）
 *   - $optionsTypes : 使用选项列表的类型
 */
$fieldTypes   = $fieldTypes ?? ['text' => ['label' => '单行文本', 'icon' => 'fa fa-font'], 'file' => ['label' => '文件', 'icon' => 'fa fa-file']];
$optionsTypes = $optionsTypes ?? ['select', 'radio', 'checkbox'];
$fieldGroups  = $fieldGroups ?? [];
$type = $f['type'] ?? 'text';
$typeInfo = $fieldTypes[$type] ?? ['label' => $type, 'icon' => 'fa fa-square'];
$isOptions = in_array($type, $optionsTypes, true);
$required = (int)($f['required'] ?? 0);
?>
<div class="field-row border rounded mb-2 bg-white shadow-sm">
    <div class="field-row-head d-flex align-items-center px-3 py-2 bg-light-subtle border-bottom">
        <span class="fw-semibold me-2 field-row-title"><?=e($f['field_name'] ?? '新字段')?></span>
        <span class="badge bg-secondary-subtle text-secondary field-row-type"><?=e($typeInfo['label'])?></span>
        <?php if ($required): ?>
            <span class="badge bg-warning-subtle text-warning ms-2 field-row-required">必填</span>
        <?php else: ?>
            <span class="badge bg-warning-subtle text-warning ms-2 d-none field-row-required">必填</span>
        <?php endif; ?>
        <div class="ms-auto d-flex gap-1">
            <button type="button" class="btn btn-sm btn-outline-secondary field-toggle" title="展开">
                <i class="fa fa-chevron-right"></i>
            </button>
            <button type="button" class="btn btn-sm btn-outline-danger field-remove" title="删除">
                <i class="fa fa-trash"></i>
            </button>
        </div>
    </div>
    <div class="field-row-body p-3" style="display:none;">
        <div class="row g-2 align-items-end mb-2">
            <div class="col-md-3">
                <label class="form-label small mb-1">字段标识 <span class="text-danger">*</span></label>
                <input type="text" class="form-control form-control-sm field-name"
                       name="field[<?=$i?>][field_name]" data-name="field_name" value="<?=e($f['field_name'] ?? '')?>"
                       placeholder="如 price" pattern="[a-z][a-z0-9_]*" required>
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1">显示名称</label>
                <input type="text" class="form-control form-control-sm"
                       name="field[<?=$i?>][field_label]" data-name="field_label" value="<?=e($f['field_label'] ?? '')?>"
                       placeholder="如 产品价格">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">控件类型</label>
                <select class="form-select form-select-sm field-type" name="field[<?=$i?>][type]" data-name="type">
                    <?php foreach ($fieldTypes as $fk => $fv): ?>
                        <option value="<?=$fk?>" <?=$type === $fk ? 'selected' : ''?>><?=$fv['label']?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-1">
                <label class="form-label small mb-1">排序</label>
                <input type="number" class="form-control form-control-sm"
                       name="field[<?=$i?>][sort_order]" data-name="sort_order" value="<?=(int)($f['sort_order'] ?? 0)?>" min="0">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">是否必填</label>
                <select class="form-select form-select-sm" name="field[<?=$i?>][required]" data-name="required">
                    <option value="0" <?=$required === 0 ? 'selected' : ''?>>否</option>
                    <option value="1" <?=$required === 1 ? 'selected' : ''?>>是</option>
                </select>
            </div>
        </div>
        <div class="row g-2 mb-2">
            <div class="col-md-6">
                <label class="form-label small mb-1">占位提示</label>
                <input type="text" class="form-control form-control-sm"
                       name="field[<?=$i?>][placeholder]" data-name="placeholder" value="<?=e($f['placeholder'] ?? '')?>"
                       placeholder="如 199.00 / 请输入...">
            </div>
            <div class="col-md-6">
                <label class="form-label small mb-1">所属分组</label>
                <select class="form-select form-select-sm" name="field[<?=$i?>][group_name]" data-name="group_name">
                    <option value="">（无分组，显示在默认 Tab）</option>
                    <?php foreach ($fieldGroups as $g): ?>
                        <option value="<?=e($g)?>" <?=($f['group_name'] ?? '') === $g ? 'selected' : ''?>><?=e($g)?></option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">在节点编辑页，同一分组的字段将放到一个 Tab 中。</div>
            </div>
        </div>
        <div class="row g-2 mb-2 field-value-row">
            <div class="col-md-12">
                <label class="form-label small mb-1 field-value-label"><?=$isOptions ? '选项列表' : '默认值'?></label>
                <textarea rows="3" class="form-control form-control-sm"
                          name="field[<?=$i?>][field_value]" data-name="field_value"
                          placeholder="<?=$isOptions ? '每行一个，格式 值:标签' : '保存后作为该字段在节点表单中的默认值'?>"><?=e($f['field_value'] ?? '')?></textarea>
                <div class="form-text field-value-help">
                    <?=$isOptions
                        ? '每行一个，格式 "值:标签"（如 1:有货）。'
                        : '保存后将作为该字段在节点表单中的默认值。'?>
                </div>
            </div>
        </div>
        <div class="row g-2">
            <div class="col-md-12">
                <label class="form-label small mb-1">帮助文字</label>
                <input type="text" class="form-control form-control-sm"
                       name="field[<?=$i?>][help]" data-name="help" value="<?=e($f['help'] ?? '')?>"
                       placeholder="选填，节点编辑页字段下方显示的提示">
            </div>
        </div>
    </div>
</div>
