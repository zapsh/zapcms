<?php
defined('IN_ZAPCMS') or die('No permission to access');
/*
 * 示例：商品列表接管模板（由 catalog_render_product 钩子渲染）。
 * 同样继承站点 layout，复用面包屑/侧栏，仅替换 content 区块内容。
 * $catalog 为当前栏目节点，$mime 即栏目内容模型类型（product）。
 */
$this->extend('layout/default');
$this->beginBlock('content');
$catalog = $catalog ?? [];
$mime = $mime ?? '';
?>
    <?php echo $this->partial('partials/_breadcrumb'); ?>
    <div class="container">
        <div class="row">
            <?php echo $this->partial('partials/_sidebar'); ?>
            <div class="col-sm-9">
                <div class="content-wrap">
                    <div class="alert alert-info">
                        此栏目列表已由 <strong>.example-takeover</strong> mod 通过 <code>catalog_render_product</code> 钩子接管，并复用站点 layout 渲染。
                    </div>
                    <h1><?php echo htmlspecialchars($catalog['title'] ?? ''); ?></h1>
                    <p class="text-muted">内容模型类型：<?php echo htmlspecialchars($mime); ?></p>
                </div>
            </div>
        </div>
    </div>
<?php $this->endBlock(); ?>
