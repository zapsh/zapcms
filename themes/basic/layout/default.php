<?php
defined('IN_ZAPCMS') or die('No permission to access');

$state = pageState();
$catalogMenu = $state->getCatalogList();
$footerCatalogMenu = $catalogMenu;
?>
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php echo $state->printMeta(); ?>
    <link href="<?php echo theme_url('css/bootstrap.min.css'); ?>" rel="stylesheet">
    <link href="<?php echo theme_url('css/font-awesome.min.css'); ?>" rel="stylesheet">
    <link href="<?php echo theme_url('css/style.css'); ?>" rel="stylesheet">
    <?php if ($state->isHome): ?>
    <link href="<?php echo theme_url('css/swiper-bundle.min.css'); ?>" rel="stylesheet">
    <script src="<?php echo theme_url('js/swiper-bundle.min.js'); ?>"></script>
    <?php endif; ?>
    <link href="<?php echo theme_url('css/page-styles.css'); ?>" rel="stylesheet">

    <?php echo option('website.head_script'); ?>
</head>
<body>

<header class="site-header">
    <form action="<?php echo append_url_suffix(site_url('/search')); ?>">
    <div class="top d-none d-md-block">
        <div class="container">
            <div class="row">
                <div class="col-sm-6">
                </div>
                <div class="col-sm-6">
                    <ul class="list-inline float-end">
                        <li class="list-inline-item">
                            <input autocomplete="off" type="text" name="q" value="" placeholder="站内搜索" />
                            <input type="submit" value="搜索" />
                        </li>
                        <li class="list-inline-item">
                            <a href="tel:<?php echo htmlspecialchars(option('website.tel')); ?>">
                                <i class="fa fa-phone"></i> <?php echo htmlspecialchars(option('website.tel')); ?>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    </form>

    <?php echo $this->partial('partials/_navbar', ['catalogMenu' => $catalogMenu]); ?>
</header>

<?php echo $this->block('content'); ?>

<?php echo $this->partial('partials/_footer', ['footerCatalogMenu' => $footerCatalogMenu]); ?>


<script src="<?php echo theme_url('js/bootstrap.bundle.min.js'); ?>"></script>

<?php
print_scripts(ASSETS_BODY);
print_scripts(ASSETS_BODY_TEXT);
?>

<?php echo option('website.foot_script'); ?>
<script>
(function () {
    // 移动端多级菜单：点击展开 / 收起（桌面端由 CSS hover 负责）
    document.querySelectorAll('.main-navbar-nav .dropdown-toggle').forEach(function (toggler) {
        toggler.addEventListener('click', function (e) {
            if (window.innerWidth < 992) {
                e.preventDefault();
                e.stopPropagation();
                var item = toggler.closest('.dropdown');
                if (!item) return;
                var willShow = !item.classList.contains('show');
                // 展开当前项时收起同级其他子菜单，避免手机端菜单过长
                var siblings = item.parentNode ? item.parentNode.children : [];
                Array.prototype.forEach.call(siblings, function (sib) {
                    if (sib !== item) sib.classList.remove('show');
                });
                item.classList.toggle('show', willShow);
            }
        });
    });

    // 回到桌面端时清理遗留的展开态
    window.addEventListener('resize', function () {
        if (window.innerWidth >= 992) {
            document.querySelectorAll('.main-navbar-nav .dropdown.show').forEach(function (item) {
                item.classList.remove('show');
            });
        }
    });

    // 返回顶部
    var btn = document.querySelector('.back-to-top');
    if (btn) {
        window.addEventListener('scroll', function () {
            btn.classList.toggle('show', window.scrollY > 300);
        });
        btn.addEventListener('click', function () {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }
})();
</script>

<div class="back-to-top">
    <i class="fa fa-chevron-up"></i>
</div>

</body>
</html>
