<?php defined('IN_ZAPCMS') or die('No permission to access'); ?>
<?php
// 分页区：左侧「共 N 条」统计，右侧页码
$pagerTotal = (!empty($page) && is_object($page) && method_exists($page, 'total')) ? (int) $page->total() : 0;
$pagerPer   = (!empty($page) && is_object($page) && method_exists($page, 'perPage')) ? (int) $page->perPage() : 10;
?>
<?php if ($pagerTotal > 0): ?>
<div class="pagination-wrapper">
    <span class="pagination-total">共 <b><?php echo number_format($pagerTotal); ?></b> 条</span>
    <?php if ($pagerTotal > $pagerPer): ?>
    <div class="pagination-nav">
        <?php echo $page->render(); ?>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>
