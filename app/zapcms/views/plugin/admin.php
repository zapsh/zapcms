<?php
/*
 * 插件后台宿主页（无左侧菜单的插件配置/管理入口）
 * 由 PluginController::settings() 渲染：左侧子导航（来自插件 admin.pages）+ 右侧内容区。
 */
!IS_AJAX && $this->layout('layouts/common');

use zap\facades\Url;

$plugin   = $plugin ?? ['name' => '', 'title' => '', 'type' => ''];
$pages    = $pages ?? [];
$activeKey = $activeKey ?? '';
$content  = $content ?? '';
$typeBadge = $plugin['type'] === 'plugin'
    ? '<span class="badge bg-info bg-opacity-10 text-info"><i class="fa fa-plug me-1"></i>插件</span>'
    : '<span class="badge bg-secondary bg-opacity-10 text-secondary"><i class="fa fa-cube me-1"></i>模块</span>';
?>

<div class="container-fluid p-0">
    <!-- 头部 -->
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h5 class="mb-1">
                <i class="fa fa-puzzle-piece me-2 text-primary"></i><?= htmlspecialchars($plugin['title'] ?: $plugin['name']) ?>
                <?= $typeBadge ?>
            </h5>
            <small class="text-muted"><?= htmlspecialchars($plugin['name']) ?></small>
        </div>
        <a href="<?= Url::action('Plugin@index') ?>" class="btn btn-outline-secondary btn-sm">
            <i class="fa fa-arrow-left me-1"></i>返回插件列表
        </a>
    </div>

    <div class="row g-3">
        <!-- 子导航 -->
        <?php if (count($pages) > 1): ?>
        <div class="col-lg-3 col-md-4">
            <div class="list-group shadow-sm">
                <?php foreach ($pages as $p): ?>
                    <?php
                    $isActive = $p['key'] === $activeKey;
                    if (!empty($p['url'])) {
                        $href   = htmlspecialchars($p['url']);
                        $target = ' target="_blank" rel="noopener"';
                    } else {
                        $href   = url_action('Plugin@settings', ['plugin' => $plugin['name'], 'page' => $p['key']]);
                        $target = '';
                    }
                    ?>
                    <a href="<?= $href ?>"<?= $target ?>
                       class="list-group-item list-group-item-action d-flex align-items-center gap-2 <?= $isActive ? 'active' : '' ?>">
                        <i class="<?= htmlspecialchars($p['icon']) ?>"></i>
                        <span><?= htmlspecialchars($p['title']) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="col-lg-9 col-md-8">
        <?php else: ?>
        <div class="col-12">
        <?php endif; ?>

            <!-- 内容区：插件页面片段注入此处 -->
            <div class="card shadow-sm">
                <div class="card-body">
                    <?= $content ?>
                </div>
            </div>
        </div>
    </div>
</div>
