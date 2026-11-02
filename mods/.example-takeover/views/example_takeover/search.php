<?php
defined('IN_ZAPCMS') or die('No permission to access');
/*
 * 示例：搜索结果接管模板（由 search_render 钩子渲染）。
 * 同样继承站点 layout，复用面包屑/侧栏，仅替换 content 区块内容。
 * $keyword 为搜索关键词，$mime 即搜索类型（可能为空）。
 */
$this->extend('layout/default');
$this->beginBlock('content');
$keyword = $keyword ?? '';
$mime = $mime ?? '';
?>
    <?php echo $this->partial('partials/_breadcrumb'); ?>
    <div class="container">
        <div class="row">
            <?php echo $this->partial('partials/_sidebar'); ?>
            <div class="col-sm-9">
                <div class="content-wrap">
                    <div class="alert alert-info">
                        此搜索结果页已由 <strong>.example-takeover</strong> mod 通过 <code>search_render</code> 钩子接管，并复用站点 layout 渲染。
                    </div>
                    <h1>搜索：<?php echo htmlspecialchars($keyword); ?></h1>
                    <p class="text-muted">搜索类型：<?php echo htmlspecialchars($mime); ?></p>
                </div>
            </div>
        </div>
    </div>
<?php $this->endBlock(); ?>
