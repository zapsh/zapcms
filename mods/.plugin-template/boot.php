<?php
/*
 * 插件引导脚本
 *
 * 由 plugin.json 的 "boot" 字段指向本文件。
 * PluginLoader::bootAll() 会在「前台与后台」各自动 include 本脚本一次，
 * 此时通过统一 Hook 总线 zapcms\Hook 注册“事件 / 过滤器”，即可在系统初始化时介入 CMS。
 */

use zapcms\Hook;

// 1) 后台 <head> 注入：admin_head 由后台布局 Hook::fire('admin_head') 真实触发
Hook::on('admin_head', function () {
    return '<meta name="generator" content="plugin_template">';
});

// 2) 工作台挂件：dashboard.widgets 由 Dashboard 真实 fire/applyFilters，刷新后台首页即可看到
Hook::filter('dashboard.widgets', function (array $widgets, int $adminId) {
    $widgets['plugin_template_demo'] = [
        'title'      => '示例插件',
        'icon'       => 'fa fa-puzzle-piece',
        'col'        => 'col-md-6',
        'default_on' => true,
        'render'     => function () {
            return '<div class="small text-muted">这是示例插件通过 Hook 注册的工作台挂件——证明 hook 已生效。</div>';
        },
    ];
    return $widgets;
});

// 3) 系统级初始化事件：所有插件引导完成后触发一次（仅演示，可在此注册路由 / 绑定服务）
Hook::on('boot', function () {
    // 例如：Route::get('/demo', ...);
});

// 4) 前台首页接管：home_render 由 IndexController 真实触发，访问首页加 ?pt_demo=1 时生效
Hook::filter('home_render', function ($_, $ctrl) {
    if (empty($_GET['pt_demo'])) {
        return $_;
    }
    View::paths(APP_ROOT . '/mods/plugin_template/views');
    return view('plugin_template.asset_demo', []);
});

/*
 * 说明（避免踩坑）：
 *  - content.save / menu.build 在核心里【没有】对应的 fire 点（仅为约定示例），
 *    用它们注册的回调永远不会执行。
 *  - 左侧菜单不靠 hook，而是写在 plugin.json 的 admin_menu，由 PluginLoader::ModMenu::sync()
 *    写入 admin_menu 表（见本模块 plugin.json 的 admin_menu 字段）。
 */
