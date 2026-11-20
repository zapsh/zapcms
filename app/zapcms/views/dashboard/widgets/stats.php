<?php
use zapcms\services\NodeType;

$box_colors = ['text-bg-primary', 'text-bg-success', 'text-bg-warning', 'text-bg-info', 'text-bg-danger', 'text-bg-secondary'];
$box_icons  = ['fa fa-file-alt', 'fa fa-th', 'fa fa-camera', 'fa fa-layer-group', 'fa fa-images', 'fa fa-folder'];
$color_idx  = 0;
$icon_idx   = 0;
?>
<div class="row">
    <?php foreach ($node_types_statistics as $type => $count):
        $color = $box_colors[$color_idx % count($box_colors)];
        $icon  = $box_icons[$icon_idx % count($box_icons)];
        $title = NodeType::getTitle($type);
        $color_idx++;
        $icon_idx++;
    ?>
    <div class="col-lg-3 col-6">
        <div class="small-box <?php echo e($color); ?>">
            <div class="inner">
                <h3><?php echo e($count); ?></h3>
                <p><?php echo e($title); ?></p>
            </div>
            <i class="<?php echo e($icon); ?> small-box-icon fs-1"></i>
            <a href="<?php echo url_action("Node@{$type}"); ?>"
               class="small-box-footer link-light link-underline-opacity-0 link-underline-opacity-50-hover">
                <?php echo e(__('admin.manage', ['title' => $title])); ?> <i class="fa fa-chevron-right"></i>
            </a>
        </div>
    </div>
    <?php endforeach; ?>
</div>
