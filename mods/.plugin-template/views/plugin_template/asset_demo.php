<?php
defined('IN_ZAPCMS') or die('No permission to access');
/*
 * 模块静态资源引用示例
 * ─────────────────────────────────────────────────────────────
 * 演示「模块自带的图片 / JS / CSS」如何被引用与输出。
 *
 * 关键规则（与 .example-takeover 一致）：
 *   1) 静态资源统一放在  mods/<模块目录名>/assets/  下
 *   2) 用 mod_url('<模块目录名>', 'assets/...') 生成可访问 URL
 *        => /mods/<模块目录名>/assets/img/logo.svg
 *   3) 两种部署模式都通：
 *        - 根目录模式（docroot = 项目根）：/mods/... 直接是静态文件
 *        - public 模式：运行 php console zapcms:MakePublic，mods 被软链进 public/mods
 *   4) CSS / JS 走资产队列 register_styles / register_scripts，
 *      由主题 layout 的 print_styles / print_scripts 统一输出；
 *      图片则直接在标签里用 mod_url() 引用（或内联 style 背景图）。
 *
 * 注意：把下面所有的 'example-plugin' 换成你自己的模块目录名。
 * 访问方式（在 boot.php 中已挂 ?pt_demo=1 守卫钩子）：首页附加 ?pt_demo=1
 */
// 注册本模块自带的 CSS / JS（资源队列，layout 的 print_styles/print_scripts 会输出）
register_styles(mod_url('example-plugin', 'assets/css/style.css'), ASSETS_HEAD);
register_scripts(mod_url('example-plugin', 'assets/js/main.js'), ASSETS_BODY);

$this->extend('layout/default');
$this->beginBlock('content');
?>
<div class="container py-4">
    <h3>模块静态资源引用示例</h3>
    <p class="text-muted">以下图片、背景图、CSS、JS 均来自本模块 <code>assets/</code> 目录，通过 <code>mod_url()</code> 引用。</p>

    <h6 class="mt-3">1) 直接用 mod_url() 引用图片（&lt;img&gt;）</h6>
    <p>
        <img src="<?php echo mod_url('example-plugin', 'assets/img/logo.svg'); ?>" alt="logo" height="48">
    </p>

    <h6 class="mt-3">2) 背景图（内联 style，同样用 mod_url()）</h6>
    <div style="width:120px;height:120px;border:1px solid #ddd;border-radius:8px;
         background:url('<?php echo mod_url('example-plugin', 'assets/img/logo.svg'); ?>') center/contain no-repeat;"></div>

    <h6 class="mt-3">3) 由 assets/css/style.css 提供的样式（相对路径引用图片）</h6>
    <div class="pt-demo-box"></div>
    <div class="pt-demo-tip">这个方块用的是 CSS 里的 <code>background:url('../img/logo.svg')</code></div>

    <h6 class="mt-3">4) 由 assets/js/main.js 提供的交互</h6>
    <button id="pt-demo-btn" class="btn btn-primary">点我（JS 已加载）</button>
</div>
<?php $this->endBlock(); ?>
