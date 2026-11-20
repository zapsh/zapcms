<?php
/**
 * 分类勾选列表（内容表单右侧「分类」卡片内容，默认表单与 Catalog@catalogOptions 共用）
 *
 * @var array $catalogList   栏目树（按需被 array_shift 消耗）
 * @var array $node_relations 已勾选栏目 => 任意 truthy 值
 */
?>
<?php while ($catalog = array_shift($catalogList)): ?>
    <?php $indent = $catalog['level'] * 1; ?>
    <div class="form-check mb-1" style="padding-left: <?php echo $indent + 1.5; ?>em;">
        <input class="form-check-input" type="checkbox" name="catalog[<?php echo e($catalog['id']); ?>]"
               <?php echo !empty($node_relations[$catalog['id']]) ? 'checked' : ''; ?>
               value="<?php echo e($catalog['level']); ?>"
               id="catalog-<?php echo e($catalog['id']); ?>">
        <label class="form-check-label small" for="catalog-<?php echo e($catalog['id']); ?>">
            <?php echo e($catalog['title']); ?>
        </label>
    </div>
    <?php
    if (isset($catalog['children'])) {
        while ($children = array_pop($catalog['children'])) {
            array_unshift($catalogList, $children);
        }
    }
    ?>
<?php endwhile; ?>
