<?php
/*
 * example-takeover —— 演示「统一接管钩子」。
 *
 * 本文件在每次前台请求初始化阶段被 PluginLoader include（因 mod.json 声明了
 * boot_frontend），因此在这里注册全局过滤器即可对所有请求生效 —— 不必写在
 * Mod::invoke 里（那只在 /mod/... 路由时才跑）。
 *
 * 接管逻辑由 app/controllers/RenderTakeover trait 负责触发，约定式钩子：
 *   node_render           主钩子（全模型类型）
 *   node_render_{mime}   按模型类型接管详情页，如 node_render_product
 *   node_{mime}_render   旧式别名（向后兼容，如 node_product_render）
 *   catalog_render_{mime} 按模型类型接管栏目列表页
 *
 * 回调可返回：
 *   - \zap\view\View 实例（推荐，可复用主题模板/layout）
 *   - 非空字符串（直接输出，需自行拼整页）
 *   - callable（返回上述任意类型）
 */

use zap\view\View;
use zapcms\Hook;

// 把本 mod 的视图目录加入模板搜索路径，使 view('example_takeover.product') 可解析
View::paths(APP_ROOT . '/mods/example-takeover/views');

// ── 接管「商品详情页」：返回 View（演示最优雅的写法，自动套用主题模板约定）──
Hook::filter('node_render_product', function ($_, $node, $mime, $ctrl) {
    return view('example_takeover.product', [
        'node' => $node,
        'mime' => $mime,
    ]);
});

// ── 接管「商品列表页」（栏目 mimeType=product）──
Hook::filter('catalog_render_product', function ($_, $catalog, $mime, $ctrl) {
    return view('example_takeover.product_list', [
        'catalog' => $catalog,
        'mime'    => $mime,
    ]);
});

// ── 主钩子：接管「所有」模型类型的详情页（演示用，取消注释即全接管）──
// Hook::filter('node_render', function ($_, $node, $mime, $ctrl) {
//     return '接管全部内容模型：' . $mime;
// });
