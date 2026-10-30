<?php
use \zap\facades\Url;
?>
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $this->e($title ?? 'ZAP CMS');?></title>
    <script>
        /* 主题引导：必须在样式表之前执行，避免暗色主题闪白 */
        (function () {
            var mode = 'light';
            try { mode = localStorage.getItem('zap-theme') || 'light'; } catch (e) {}
            if (mode !== 'light' && mode !== 'dark' && mode !== 'auto') { mode = 'light'; }
            var theme = mode === 'auto'
                ? (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')
                : mode;
            document.documentElement.setAttribute('data-bs-theme', theme);
            document.documentElement.setAttribute('data-zap-theme', mode);
        })();
    </script>
    <link href="<?php echo base_url();?>/assets/admin/css/zap-admin.css" rel="stylesheet">
    
    <script src="<?php echo base_url();?>/assets/sweetalert2.all.min.js"></script>
    <script src="<?php echo base_url();?>/assets/jquery/jquery-3.6.4.min.js"></script>
    <link href="<?php echo base_url();?>/assets/fontawesome/6.4.2/css/all.css" rel="stylesheet">
    <script src="<?php echo base_url();?>/assets/admin/js/admin.js"></script>
    <script>
        window.ZAP_BASE_URL = '<?php echo base_url(Z_ADMIN_PREFIX);?>';
        window.ZAP_ASSETS_URL = '<?php echo base_url();?>/assets/admin';
    </script>
    <?php print_styles(); ?>
    <?php print_scripts(ASSETS_HEAD); ?>
    <?php print_scripts(ASSETS_HEAD_TEXT); ?>
    <?php \zapcms\AdminHook::echo('admin_head'); ?>
</head>

<body class="<?php echo $body_class ?? 'layout-fixed sidebar-expand-lg bg-body-tertiary'; ?>">
    <div class="app-wrapper">
        <!-- 顶部导航栏 -->
        <nav class="app-header navbar navbar-expand bg-body shadow-sm">
            <div class="container-fluid">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" data-zui-toggle="sidebar" href="#" role="button">
                            <i class="fa fa-bars"></i>
                        </a>
                    </li>
                    <li class="nav-item d-none d-md-block">
                        <a href="<?php echo base_url('/');?>" class="nav-link" target="_blank">
                            <i class="fa fa-external-link-alt me-1"></i> 访问网站
                        </a>
                    </li>
                </ul>
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" data-zui-toggle="fullscreen" href="#" role="button" title="全屏">
                            <i class="fa fa-expand"></i>
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link" data-bs-toggle="dropdown" href="#" role="button" title="主题外观" aria-label="主题外观">
                            <i class="fa fa-sun zap-theme-icon" data-zap-theme-icon></i>
                        </a>
                        <div class="dropdown-menu dropdown-menu-end shadow" style="min-width: 170px;">
                            <button type="button" class="dropdown-item d-flex align-items-center zap-theme-option" data-theme="light" role="menuitemradio" aria-checked="false">
                                <i class="fa fa-sun fa-fw me-2"></i><span>Light</span>
                                <i class="fa fa-check ms-auto zap-theme-check invisible"></i>
                            </button>
                            <button type="button" class="dropdown-item d-flex align-items-center zap-theme-option" data-theme="dark" role="menuitemradio" aria-checked="false">
                                <i class="fa fa-moon fa-fw me-2"></i><span>Dark</span>
                                <i class="fa fa-check ms-auto zap-theme-check invisible"></i>
                            </button>
                            <button type="button" class="dropdown-item d-flex align-items-center zap-theme-option" data-theme="auto" role="menuitemradio" aria-checked="false">
                                <i class="fa fa-circle-half-stroke fa-fw me-2"></i><span>Auto</span>
                                <i class="fa fa-check ms-auto zap-theme-check invisible"></i>
                            </button>
                        </div>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link" data-bs-toggle="dropdown" href="#" role="button">
                            <img src="<?php echo !empty(\zapcms\services\Auth::user()['avatar_url']) ? htmlspecialchars(\zapcms\services\Auth::user()['avatar_url']) : base_url('/assets/admin/images/default-avatar.svg'); ?>"
                                 alt="" class="rounded-circle me-1" width="26" height="26" style="object-fit:cover;">
                            <span><?php echo htmlspecialchars(\zapcms\services\Auth::user()['nickname'] ?? \zapcms\services\Auth::user()['username'] ?? '管理员'); ?></span>
                        </a>
                        <div class="dropdown-menu dropdown-menu-end shadow" style="min-width: 240px;">
                            <div class="px-3 py-2 border-bottom">
                                <div class="fw-semibold text-dark text-truncate"><?php echo htmlspecialchars(\zapcms\services\Auth::user()['full_name'] ?? \zapcms\services\Auth::user()['username'] ?? ''); ?></div>
                                <small class="text-muted text-nowrap"><?php echo htmlspecialchars(\zapcms\services\Auth::user()['email'] ?? ''); ?></small>
                            </div>
                            <a class="dropdown-item" href="<?php echo \zap\facades\Url::action('User@profile'); ?>">
                                <i class="fa fa-user-cog me-2"></i>账户设置
                            </a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item" href="<?php echo \zap\facades\Url::action('System@settings'); ?>">
                                <i class="fa fa-cog me-2"></i>系统设置
                            </a>
                            <?php \zapcms\AdminHook::echo('admin_user_dropdown'); ?>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item text-danger" href="<?php echo \zap\facades\Url::action('Auth@signOut'); ?>">
                                <i class="fa fa-sign-out-alt me-2"></i>退出登录
                            </a>
                        </div>
                    </li>
                </ul>
            </div>
        </nav>

        <!-- 左侧边栏 -->
        <?php $this->include('layouts/top_navs','top_navs');?>
        <?php echo $this->block('top_navs');?>

        <!-- 主内容区 -->
        <main class="app-main">
            <!--begin::App Content Header-->
            <?php if (!empty($page_title)): ?>
            <div class="app-content-header">
                <div class="container-fluid">
                    <div class="row">
                        <div class="col-sm-6">
                            <h1 class="mb-0 fs-5"><?php echo htmlspecialchars($page_title); ?></h1>
                            <?php if (!empty($page_subtitle)): ?>
                            <small class="text-muted"><?php echo htmlspecialchars($page_subtitle); ?></small>
                            <?php endif; ?>
                        </div>
                        <div class="col-sm-6">
                            <nav aria-label="breadcrumb">
                                <ol class="breadcrumb float-sm-end">
                                    <?php if (!empty($breadcrumbs)): ?>
                                        <?php $last = count($breadcrumbs) - 1; ?>
                                        <?php foreach ($breadcrumbs as $i => $crumb): ?>
                                            <?php if ($i === $last): ?>
                                            <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($crumb['title']); ?></li>
                                            <?php else: ?>
                                            <li class="breadcrumb-item"><a href="<?php echo htmlspecialchars($crumb['url'] ?? '#'); ?>"><?php echo htmlspecialchars($crumb['title']); ?></a></li>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <li class="breadcrumb-item"><a href="<?php echo url_action('Dashboard'); ?>">首页</a></li>
                                        <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($page_title); ?></li>
                                    <?php endif; ?>
                                </ol>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            <!--end::App Content Header-->

            <div class="app-content">
                <div class="container-fluid px-4 py-3">
                    <?php \zapcms\AdminHook::echo('admin_content_before'); ?>
                    <?php echo $this->block('content');?>
                    <?php \zapcms\AdminHook::echo('admin_content_after'); ?>
                </div>
            </div>
        </main>

        <!-- 页脚 -->
        <footer class="app-footer">
            <div class="float-end d-none d-sm-block">
                Current Version <a href="<?php echo url_action('update'); ?>" ><b>v<?php echo ZAP_CMS_VERSION; ?></b></a>
            </div>
            <strong>Copyright &copy; <a href="https://zap.cn/product/zapcms" class="text-decoration-none" target="_blank">ZapCMS</a> 2014 ~ <?php echo date('Y');?></strong>. All rights reserved.  
        </footer>
    </div>

    <!-- Toast 消息容器 -->
    <div class="toast-container p-3 top-0 start-50 translate-middle-x" id="topCenterToast" data-original-class="toast-container p-3"></div>
    <div class="toast-container p-3 top-0 end-0" id="topRightToast" data-original-class="toast-container p-3"></div>
    <div class="toast-container p-3 top-50 start-50 translate-middle" id="centerToast" data-original-class="toast-container p-3"></div>
    <div class="toast-container p-3 bottom-0 end-0" id="bottomRightToast" data-original-class="toast-container p-3"></div>
 
    
    <script src="<?php echo base_url();?>/assets/admin/js/bootstrap.bundle.js"></script>
    <script src="<?php echo base_url();?>/assets/admin/js/zap-admin.js"></script>
    

    <script>
        <?php
        \zapcms\services\AdminPage::instance()->showFlashMessages();
        ?>
    </script>
    <?php echo $this->block('page_scripts'); ?>
    <?php print_scripts(ASSETS_BODY); ?>
    <?php print_scripts(ASSETS_BODY_TEXT); ?>
    <?php \zapcms\AdminHook::echo('admin_foot'); ?>
</body>
</html>
