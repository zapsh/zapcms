<?php
defined('IN_ZAPCMS') or die('No permission to access');
/*
 * 示例：404 接管模板（由 not_found_render 钩子渲染）。
 * 同样继承站点 layout，仅替换 content 区块内容；$uri 为触发 404 的请求路径。
 */
$this->extend('layout/default');
$this->beginBlock('content');
$uri = $uri ?? '/';
?>
    <div class="container">
        <div class="content-wrap">
            <div class="alert alert-info">
                此 404 页面已由 <strong>.example-takeover</strong> mod 通过 <code>not_found_render</code> 钩子接管，并复用站点 layout 渲染。
            </div>
            <h1>404 — 页面不存在</h1>
            <p class="text-muted">请求路径：<?php echo htmlspecialchars($uri); ?></p>
            <a href="<?php echo base_url('/'); ?>" class="btn btn-success">返回首页</a>
        </div>
    </div>
<?php $this->endBlock(); ?>
