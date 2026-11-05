<?php
defined('IN_ZAPCMS_ADMIN') or die('No permission');

/**
 * 主题设置 - 侧边栏导航模块 
 * 
 * 其他主题可直接复制此文件并根据需要修改 $menuItems 数组。
 * 使用方式：在主题的 zap/settings.php 中 include 此文件。
 */

$currentPage = req()->get('page', '_settings');

// ============================================================
// 定义侧边栏菜单项（其他主题修改此数组即可）
// ============================================================
$menuItems = [
    [
        'page'    => '_settings',
        'icon'    => 'fa-solid fa-home',
        'label'   => '首页设置',
    ],
    // [
    //     'page'    => 'article',
    //     'icon'    => 'fa-solid fa-newspaper',
    //     'label'   => '文章设置',
    // ],
    // [
    //     'page'    => 'image',
    //     'icon'    => 'fa-solid fa-image',
    //     'label'   => '图片设置',
    // ],
];
?>

<div class="card card-outline card-success mb-0">
    <div class="card-header">
        <h5 class="card-title mb-0">
            <i class="fa-solid fa-sliders me-2"></i>设置
        </h5>
    </div>
    <div class="card-body p-0">
        <div class="list-group list-group-flush">
            <?php foreach ($menuItems as $item): ?>
            <a href="<?php echo url_action('theme@settings', ['page' => $item['page']]); ?>"
               class="list-group-item list-group-item-action d-flex align-items-center<?php echo $currentPage === $item['page'] ? ' active' : ''; ?>">
                <i class="<?php echo $item['icon']; ?> nav-icon me-3" style="width: 20px; text-align: center;"></i>
                <?php echo $item['label']; ?>
                <?php if ($currentPage === $item['page']): ?>
                <i class="fa-solid fa-chevron-right ms-auto"></i>
                <?php endif; ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>
