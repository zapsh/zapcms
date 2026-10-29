<?php defined('IN_ZAPCMS') or die('No permission to access');

/**
 * 页脚 partial
 * 使用时需在外层传入 $footerCatalogMenu 变量
 *
 * @var array $footerCatalogMenu 页脚栏目数据
 */
?>
<footer class="site-footer">
    <div class="container">
        <div class="row">
            <?php foreach ($footerCatalogMenu as $menu):
                if (!in_array(4, explode(',', $menu['show_position'] ?? ''))) {
                    continue;
                }
            ?>
            <div class="col-md-3 col-sm-6 col-12 fbox">
                <h4><?php echo htmlspecialchars($menu['title']); ?></h4>
                <ul class="big">
                    <?php foreach ($menu['children'] as $child):
                        if (!in_array(4, explode(',', $child['show_position'] ?? ''))) {
                            continue;
                        }
                        $childUrl = smart_node_url($child);
                    ?>
                    <li><a href="<?php echo $childUrl; ?>" title="<?php echo htmlspecialchars($child['title']); ?>"><?php echo htmlspecialchars($child['title']); ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endforeach; ?>

            <div class="col-md-3 col-sm-6 col-12 fbox">
                <h4><?php echo htmlspecialchars(option('website.title')); ?></h4>
                <p class="text"></p>
                <p><a href="tel:<?php echo htmlspecialchars(option('website.tel')); ?>"><i class="fa fa-phone" aria-hidden="true"></i> <?php echo htmlspecialchars(option('website.tel')); ?></a></p>
                <p><a href="mailto:<?php echo htmlspecialchars(option('website.email')); ?>"><i class="fa fa-envelope-o" aria-hidden="true"></i> <?php echo htmlspecialchars(option('website.email')); ?></a></p>
                <p><i class="fa fa-map-marker" aria-hidden="true"></i> <?php echo htmlspecialchars(option('website.address')); ?></p>
            </div>
        </div>
    </div>
    <div id="copyright">
        <div class="container">
            <div class="row">
                <div class="col-md-12 text-center">
                   Copyright &copy; <?php echo date('Y') . ' ' . option('website.copyright'); ?> <a href="https://www.zap.cn/zapcms">Powered by ZapCMS</a>
                </div>
            </div>
        </div>
    </div>
</footer>
