<?php
defined('IN_ZAPCMS') or die('No permission to access');
/*
 * 示例：商品详情接管模板（由 node_render_product 钩子渲染）。
 *
 * 如何套用站点 layout（关键）：
 *   mod 返回的 View 与主题模板是同一个 zap\view\View 对象，因此写法完全一致 ——
 *   1) $this->extend('layout/default')  声明继承站点 layout（头部/底部/导航/CSS/JS 都在里面）
 *   2) beginBlock('content') / endBlock() 把本页内容填进 layout 的 content 区块
 *   3) $this->partial('partials/...') 复用主题已有的面包屑、侧栏等片段
 *   layout 名称按当前激活主题自动解析，所以 mod 是主题无关的：换主题即套新主题的 layout。
 *
 * $node 来自 NodeController::product() 已准备好的数据（含 loadMeta 后的 $node['meta']），
 * $mime 即内容模型类型（product）。pageState() 中的面包屑/侧栏数据也已在接管前由控制器设置好。
 */
$this->extend('layout/default');
$this->beginBlock('content');
$node = $node ?? [];
$mime = $mime ?? '';
?>
    <?php echo $this->partial('partials/_breadcrumb'); ?>
    <div class="container">
        <div class="row">
            <?php echo $this->partial('partials/_sidebar'); ?>
            <div class="col-sm-9">
                <div class="content-wrap">
                    <div class="alert alert-info">
                        此页面已由 <strong>example-takeover</strong> mod 通过 <code>node_render_product</code> 钩子接管，并复用站点 layout 渲染。
                    </div>
                    <h1><?php echo htmlspecialchars($node['title'] ?? ''); ?></h1>
                    <p class="text-muted">内容模型类型：<?php echo htmlspecialchars($mime); ?></p>

                    <?php if (!empty($node['meta'])): ?>
                        <ul class="list-inline text-muted small">
                            <?php foreach ($node['meta'] as $k => $v): ?>
                                <li class="list-inline-item"><strong><?php echo htmlspecialchars($k); ?>:</strong> <?php echo htmlspecialchars((string) $v); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <?php if (!empty($node['content'])): ?>
                        <div class="post-content mt-3"><?php echo $node['content']; ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<?php $this->endBlock(); ?>
