<?php
/*
 * 插件引导脚本
 *
 * 由 plugin.json 的 "boot" 字段指向本文件。系统初始化阶段，
 * PluginLoader::bootAll() 会在「前台与后台」各自动 include 本脚本一次。
 *
 * 在这里通过统一 Hook 总线 zapcms\Hook 注册「事件 / 过滤器」，
 * 即可在系统初始化时介入 CMS 行为——不再依赖仅后台视图层的 AdminHook 写法。
 */

use zapcms\Hook;
use zapcms\AdminHook;

// 1) 系统级初始化钩子：所有插件引导完成后触发一次，适合做插件级全局初始化
Hook::on('boot', function () {
    // 例如：注册自定义路由、绑定服务容器、挂载领域事件监听器等
});

// 2) 领域事件：内容保存时触发（业务点会调用 Hook::fire('content.save', $node)）
Hook::on('content.save', function (array $node) {
    // 示例：对保存的内容做处理（加前缀、记录日志、触发同步等）
    // error_log('content saved: ' . ($node['title'] ?? ''));
});

// 3) 过滤器：修改某个值后再返回（业务点调用 Hook::applyFilters('menu.build', $items)）
Hook::filter('menu.build', function (array $items) {
    $items[] = [
        'title' => '示例插件',
        'url'   => 'mod/example-plugin/admin/settings',
        'icon'  => 'fa fa-puzzle-piece',
    ];
    return $items;
});

// 4) 后台视图层注入（兼容原 AdminHook 写法，现已桥接同一总线）
//    后台布局通过 AdminHook::echo('admin_head') 触发，返回字符串会被输出
AdminHook::on('admin_head', function () {
    return '<meta name="generator" content="example-plugin">';
});

// 5) 静态资源引用示例（演示模块自带图片 / JS / CSS 如何被引用与输出）
//    约定：资源放在 mods/<name>/assets/ 下，用 mod_url('<name>', 'assets/...') 生成 URL。
//    两种部署模式都通：根目录模式直接访问 /mods/...；public 模式 MakePublic 软链 mods → public/mods。
//    仅当访问首页并附加 ?pt_demo=1 时才接管渲染，正常访问首页不受影响。
Hook::filter('home_render', function ($_, $ctrl) {
    if (empty($_GET['pt_demo'])) {
        return $_; // 非示例模式：保持原首页
    }
    // 把本 mod 的视图目录加入模板搜索路径
    View::paths(APP_ROOT . '/mods/example-plugin/views');
    // 返回 View（自动套用主题 layout，register_styles/scripts 会在其中输出）
    return view('plugin_template.asset_demo', []);
});
