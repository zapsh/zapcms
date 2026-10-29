<?php defined('IN_ZAPCMS') or die('No permission to access');

/**
 * 导航菜单 partial（Bootstrap 5）
 * 使用时需在外层传入 $catalogMenu 变量
 *
 * @var array $catalogMenu 栏目菜单数据
 */
$state = pageState();
$childLastId = [];
?>
<nav class="navbar navbar-expand-lg navbar-default">
    <div class="container">
        <a href="<?php echo home_url(); ?>" class="navbar-brand">
            <img src="<?php echo theme_url(); ?>/img/zap_logo_green.svg" width="120" alt="ZAP CMS Logo">
        </a>
        <button type="button" class="navbar-toggler navbar-toggle" data-bs-toggle="collapse" data-bs-target="#bs-navbar-collapse" aria-controls="bs-navbar-collapse" aria-expanded="false" aria-label="展开导航">
            <i class="fa fa-bars"></i>
        </button>
        <div class="collapse navbar-collapse" id="bs-navbar-collapse">
            <ul class="navbar-nav main-navbar-nav">
                <li class="nav-item<?php if ($state->isHome) echo ' active'; ?>">
                    <a class="nav-link" href="<?php echo base_url('/'); ?>">首页</a>
                </li>
                <?php while ($menu = array_shift($catalogMenu)):
                    $hasChildren = !empty($menu['children']);
                    $isChild     = count($childLastId) > 0;
                    $isActive    = ($menu['id'] === $state->nodeId || $state->nodeId === $menu['link_object']);
                    $linkClass   = $isChild ? 'dropdown-item' : 'nav-link';
                    if ($hasChildren) {
                        $linkClass .= ' dropdown-toggle';
                    }
                ?>
                <li class="nav-item<?php
                    echo $hasChildren ? ' dropdown' : '';
                    echo $isActive ? ' active' : '';
                ?>">
                    <a data-id="<?php echo $menu['id']; ?>"
                       href="<?php echo smart_node_url($menu); ?>"
                       title="<?php echo htmlspecialchars($menu['title']); ?>"
                       class="<?php echo $linkClass; ?>"
                       <?php if ($hasChildren): ?>role="button" aria-expanded="false"<?php endif; ?>>
                        <?php echo htmlspecialchars($menu['title']); ?>
                    </a>
                    <?php if ($hasChildren): ?>
                        <ul class="dropdown-menu">
                        <?php
                        $childLastId[] = end($menu['children'])['id'];
                        while ($children = array_pop($menu['children'])) {
                            array_unshift($catalogMenu, $children);
                        }
                        ?>
                    <?php endif; ?>

                    <?php if ($menu['id'] == end($childLastId)): ?>
                        </ul>
                        <?php array_pop($childLastId); ?>
                    <?php endif; ?>
                </li>
                <?php endwhile; ?>
            </ul>
        </div>
    </div>
</nav>
