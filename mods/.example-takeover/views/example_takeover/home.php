<?php
defined('IN_ZAPCMS') or die('No permission to access');
/*
 * 示例：首页接管模板（由 home_render 钩子渲染）。
 * 同样继承站点 layout，仅替换 content 区块内容；此页不依赖侧栏数据，故未引入 _sidebar。
 */
$this->extend('layout/default');
$this->beginBlock('content');
?>
    <div class="container">
        <div class="content-wrap">
            <div class="alert alert-info">
                此首页已由 <strong>.example-takeover</strong> mod 通过 <code>home_render</code> 钩子接管，并复用站点 layout 渲染。
            </div>
            <h1>欢迎（自定义首页）</h1>
            <p class="text-muted">mod 可在 boot_frontend.php 中注册 home_render，完全自定义首屏内容。</p>
        </div>
    </div>
<?php $this->endBlock(); ?>
