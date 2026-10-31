<?php
/**
 * 自定义字段渲染（共享 partial）
 * 依赖变量：
 *   - $node_fields : 字段配置数组（NodeType::getFields 返回）
 *   - $node        : \zapcms\models\Node 实例
 *   - $meta_section_title : 区块标题（默认「自定义字段」）
 *   - $meta_show_header   : 是否输出标题分隔（默认 true；分组 Tab 内传 false）
 *
 * 支持类型：text / textarea / number / date / datetime / select / radio / checkbox / switch / image
 */
if (empty($node_fields)) {
    return;
}
$meta_section_title = $meta_section_title ?? '自定义字段';
$meta_show_header   = $meta_show_header ?? true;
?>
<?php if ($meta_show_header): ?>
<hr>
<h6 class="text-muted small mb-2"><i class="fa fa-list-alt me-1"></i><?= e($meta_section_title) ?></h6>
<?php endif; ?>
<?php foreach ($node_fields as $nf): ?>
    <?php
    $nfType  = $nf['type'] ?? 'text';
    $nfName  = $nf['field_name'];
    $nfLabel = $nf['field_label'] ?: $nfName;
    $nfValue = $node->get_node_meta($nfName);
    $nfPh    = e($nf['placeholder'] ?? '');
    $nfHelp  = (string)($nf['help'] ?? '');
    $nfReq   = !empty($nf['required']);
    $reqAttr = $nfReq ? ' required' : '';
    $star    = $nfReq ? ' <span class="text-danger">*</span>' : '';
    $metaName = 'meta[' . $nfName . ']';
    $metaId   = 'meta_' . $nfName;

    // 选项列表：每行一个 「值:标签」
    $options = [];
    foreach (preg_split('/\r\n|\r|\n/', (string)($nf['field_value'] ?? '')) as $opt) {
        $opt = trim($opt);
        if ($opt === '') {
            continue;
        }
        $parts = explode(':', $opt, 2);
        $options[] = [
            'value' => trim($parts[0]),
            'label' => isset($parts[1]) ? trim($parts[1]) : trim($parts[0]),
        ];
    }

    // checkbox 多选回显（兼容 JSON 与逗号分隔旧数据）
    $checkedVals = [];
    if ($nfType === 'checkbox' && $nfValue !== '' && $nfValue !== null) {
        $decoded = json_decode((string)$nfValue, true);
        $checkedVals = is_array($decoded)
            ? array_map('strval', $decoded)
            : preg_split('/\s*,\s*/', (string)$nfValue, -1, PREG_SPLIT_NO_EMPTY);
    }
    ?>
    <div class="mb-2">
        <label class="form-label"<?php echo in_array($nfType, ['radio', 'checkbox', 'switch'], true) ? '' : ' for="' . $metaId . '"'; ?>>
            <?php echo e($nfLabel); ?><?php echo e($star); ?>
        </label>

        <?php if ($nfType === 'textarea'): ?>
            <textarea class="form-control form-control-sm" name="<?php echo e($metaName); ?>" id="<?php echo e($metaId); ?>"
                      rows="3" placeholder="<?php echo e($nfPh); ?>"<?php echo e($reqAttr); ?>><?php echo e($nfValue); ?></textarea>

        <?php elseif ($nfType === 'select'): ?>
            <select class="form-select form-select-sm" name="<?php echo e($metaName); ?>" id="<?php echo e($metaId); ?>"<?php echo e($reqAttr); ?>>
                <option value="">请选择</option>
                <?php foreach ($options as $opt): ?>
                    <option value="<?php echo e($opt['value']); ?>" <?php echo (string)$nfValue === $opt['value'] ? 'selected' : ''; ?>><?php echo e($opt['label']); ?></option>
                <?php endforeach; ?>
            </select>

        <?php elseif ($nfType === 'number'): ?>
            <input type="number" step="0.01" min="0" class="form-control form-control-sm" name="<?php echo e($metaName); ?>"
                   id="<?php echo e($metaId); ?>" placeholder="<?php echo e($nfPh); ?>" value="<?php echo e($nfValue); ?>"<?php echo e($reqAttr); ?>>

        <?php elseif ($nfType === 'date'): ?>
            <input type="text" class="form-control form-control-sm datetimepicker" data-format="Y-m-d" name="<?php echo e($metaName); ?>"
                   id="<?php echo e($metaId); ?>" placeholder="<?php echo $nfPh ?: 'Y-m-d'; ?>" value="<?php echo e($nfValue); ?>"<?php echo e($reqAttr); ?>>

        <?php elseif ($nfType === 'datetime'): ?>
            <input type="text" class="form-control form-control-sm datetimepicker" data-format="Y-m-d H:i:s" name="<?php echo e($metaName); ?>"
                   id="<?php echo e($metaId); ?>" placeholder="<?php echo $nfPh ?: 'Y-m-d H:i:s'; ?>" value="<?php echo e($nfValue); ?>"<?php echo e($reqAttr); ?>>

        <?php elseif ($nfType === 'radio'): ?>
            <div class="d-flex flex-wrap gap-3 pt-1">
                <?php foreach ($options as $opt): ?>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="<?php echo e($metaName); ?>" id="<?php echo e($metaId); ?>_<?php echo e($opt['value']); ?>"
                               value="<?php echo e($opt['value']); ?>" <?php echo (string)$nfValue === $opt['value'] ? 'checked' : ''; ?><?php echo e($reqAttr); ?>>
                        <label class="form-check-label small" for="<?php echo e($metaId); ?>_<?php echo e($opt['value']); ?>"><?php echo e($opt['label']); ?></label>
                    </div>
                <?php endforeach; ?>
            </div>

        <?php elseif ($nfType === 'checkbox'): ?>
            <div class="d-flex flex-wrap gap-3 pt-1">
                <?php foreach ($options as $opt): ?>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="<?php echo e($metaName); ?>[]" id="<?php echo e($metaId); ?>_<?php echo e($opt['value']); ?>"
                               value="<?php echo e($opt['value']); ?>" <?php echo in_array($opt['value'], $checkedVals, true) ? 'checked' : ''; ?>>
                        <label class="form-check-label small" for="<?php echo e($metaId); ?>_<?php echo e($opt['value']); ?>"><?php echo e($opt['label']); ?></label>
                    </div>
                <?php endforeach; ?>
            </div>

        <?php elseif ($nfType === 'switch'): ?>
            <div class="form-check form-switch pt-1">
                <input class="form-check-input" type="checkbox" role="switch" name="<?php echo e($metaName); ?>" id="<?php echo e($metaId); ?>"
                       value="1" <?php echo (string)$nfValue === '1' ? 'checked' : ''; ?><?php echo e($reqAttr); ?>>
                <label class="form-check-label small" for="<?php echo e($metaId); ?>"><?php echo $nfHelp ?: '开 / 关'; ?></label>
            </div>

        <?php elseif ($nfType === 'image'): ?>
            <div class="d-flex align-items-center gap-2">
                <img src="<?php echo \zapcms\helpers\ThumbHelper::thumb($nfValue, 80, 80); ?>"
                     class="img-thumbnail rounded" id="<?php echo e($metaId); ?>_thumb" style="width:80px;height:80px;object-fit:cover;" alt="">
                <input type="hidden" name="<?php echo e($metaName); ?>" id="<?php echo e($metaId); ?>" value="<?php echo e($nfValue); ?>">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-zap-toggle="image"
                        data-zap-target="#<?php echo e($metaId); ?>|#<?php echo e($metaId); ?>_thumb">
                    <i class="fa fa-upload me-1"></i>选择图片
                </button>
                <button type="button" class="btn btn-outline-danger btn-sm"
                        onclick="$('#<?php echo e($metaId); ?>').val('');$('#<?php echo e($metaId); ?>_thumb').attr('src','').hide();">
                    <i class="fa fa-trash"></i>
                </button>
            </div>

        <?php else: ?>
            <input type="text" class="form-control form-control-sm" name="<?php echo e($metaName); ?>"
                   id="<?php echo e($metaId); ?>" placeholder="<?php echo e($nfPh); ?>" value="<?php echo e($nfValue); ?>"<?php echo e($reqAttr); ?>>
        <?php endif; ?>

        <?php if ($nfHelp !== '' && $nfType !== 'switch'): ?>
            <div class="form-text"><?php echo e($nfHelp); ?></div>
        <?php endif; ?>
    </div>
<?php endforeach; ?>
