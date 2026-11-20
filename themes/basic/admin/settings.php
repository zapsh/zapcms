<?php
defined('IN_ZAPCMS_ADMIN') or die('No permission');

/**
 * 
 *
 * 布局：左侧侧边栏导航 + 右侧内容区。
 *
 * 约定：
 *   1. 侧边栏导航来自 themes/{主题名}/admin/_sidebar_settings.php
 *   2. 内容页存放在 themes/{主题名}/admin/ 下，以 ?page=xxx 参数切换
 *   3. 每个内容页自己处理表单提交与保存逻辑
 */

$page = req()->get('page', '_settings');

// 白名单校验，防止任意文件包含
$allowedPages = ['_settings', 'article', 'image'];
if (!in_array($page, $allowedPages)) {
    $page = '_settings';
}
?>
<div class="row">
    <!-- ========== 左侧侧边栏 ========== -->
    <div class="col-md-3">
        <?php include __DIR__ . '/_sidebar_settings.php'; ?>
    </div>

    <!-- ========== 右侧内容区 ========== -->
    <div class="col-md-9">
        <?php include __DIR__ . '/' . $page . '.php'; ?>
    </div>
</div>
