<?php
use \zap\facades\Url;
?>
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $this->e($title ?? 'ZAP CMS');?></title>
    <?php echo \zapcms\support\Csrf::meta(); ?>
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
    <link href="<?php echo \zapcms\support\Vite::css('zap-admin'); ?>" rel="stylesheet">
    <style>
        /* 编辑页卡片头右上角「取消/关闭」按钮：避免紧贴右边角，并增加 hover 反馈 */
        .card-header .card-tools .btn-tool {
            margin-right: .25rem;
            border-radius: .375rem;
        }
        .card-header .card-tools .btn-tool:hover {
            background-color: rgba(0, 0, 0, .06);
        }

        /* 顶部右侧图标小按钮：收紧左右内边距，避免图标之间间距过宽 */
        .app-header .nav-icon-btn {
            padding-left: .4rem !important;
            padding-right: .4rem !important;
        }

        /* 用户/角色/权限 区导航 pills：激活态淡化（浅底+主色字，而非实色填充） */
        .zap-section-pills .nav-link {
            color: #6c757d;
            background: transparent;
            border: 1px solid transparent;
        }
        .zap-section-pills .nav-link:hover {
            background: rgba(0, 0, 0, .04);
        }
        .zap-section-pills .nav-link.active {
            background: rgba(13, 110, 253, .10);
            color: #0d6efd;
            font-weight: 600;
            border-color: rgba(13, 110, 253, .20);
        }
    </style>
    
    <script src="<?php echo base_url();?>/assets/sweetalert2.all.min.js"></script>
    <script src="<?php echo base_url();?>/assets/jquery/jquery-3.6.4.min.js"></script>
    <link href="<?php echo base_url();?>/assets/fontawesome/6.4.2/css/all.css" rel="stylesheet">
    <script src="<?php echo base_url();?>/assets/admin/js/admin.js?v=<?php echo (int) @filemtime(APP_ROOT . '/assets/admin/js/admin.js'); ?>"></script>
    <script>
        window.ZAP_BASE_URL = '<?php echo base_url(Z_ADMIN_PREFIX);?>';
        window.ZAP_ASSETS_URL = '<?php echo base_url();?>/assets/admin';
        window.ZAP_CSRF_TOKEN = '<?php echo \zapcms\support\Csrf::token(); ?>';

        /**
         * CSRF 统一注入：
         *  1. jQuery AJAX、原生 fetch/XHR 在同域请求上自动附带 X-CSRF-Token；
         *  2. 跨域请求一律不加，以免触发无谓的预检或被第三方站点记录凭证。
         */
        (function (token) {
            function sameOrigin(url) {
                try {
                    return new URL(url, window.location.href).origin === window.location.origin;
                } catch (e) {
                    return false;
                }
            }

            if (window.jQuery) {
                jQuery.ajaxSetup({
                    beforeSend: function (xhr, settings) {
                        if (!settings.crossDomain && sameOrigin(settings.url || '')) {
                            xhr.setRequestHeader('X-CSRF-Token', token);
                        }
                    }
                });
            }

            var rawXhrOpen = window.XMLHttpRequest ? window.XMLHttpRequest.prototype.open : null;
            if (rawXhrOpen) {
                window.XMLHttpRequest.prototype.open = function (method, url) {
                    var res = rawXhrOpen.apply(this, arguments);
                    try {
                        if (sameOrigin(String(url))) {
                            this.setRequestHeader('X-CSRF-Token', token);
                        }
                    } catch (e) {}
                    return res;
                };
            }

            if (window.fetch) {
                var rawFetch = window.fetch;
                window.fetch = function (input, init) {
                    init = init || {};
                    var target = (typeof input === 'string') ? input : (input && input.url);
                    if (sameOrigin(String(target || ''))) {
                        var headers = new Headers(init.headers || (input && input.headers) || {});
                        if (!headers.has('X-CSRF-Token')) {
                            headers.set('X-CSRF-Token', token);
                        }
                        init.headers = headers;
                    }
                    return rawFetch(input, init);
                };
            }
        })(window.ZAP_CSRF_TOKEN);
    </script>
    <?php print_styles(); ?>
    <?php print_scripts(ASSETS_HEAD); ?>
    <?php print_scripts(ASSETS_HEAD_TEXT); ?>
    <?php echo \zapcms\Hook::fire('admin_head'); ?>
</head>

<body class="<?php echo e($body_class ?? 'layout-fixed sidebar-expand-lg bg-body-tertiary'); ?>">
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
                            <i class="fa fa-external-link-alt me-1"></i> <?php echo __('admin.visit_site'); ?>
                        </a>
                    </li>
                </ul>
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link nav-icon-btn" href="#" role="button" title="<?php echo __('admin.clear_cache'); ?>" onclick="zapClearCacheQuick(event)">
                            <i class="fa fa-eraser"></i>
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link nav-icon-btn" data-bs-toggle="dropdown" href="#" role="button" title="<?php echo __('admin.switch_language'); ?>" aria-label="<?php echo __('admin.switch_language'); ?>">
                            <i class="fa fa-language"></i>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow">
                            <?php
                            // 勾选/加粗标记「当前语言」而非默认语言，切换后勾选要跟着走
                            $currentLocale = \zapcms\services\Locale::current();
                            foreach (\zapcms\services\Locale::all() as $lang):
                                $isCurrent = $lang['code'] === $currentLocale;
                                ?>
                            <li>
                                <form method="post" action="<?php echo \zap\facades\Url::action('Index@switchLocale'); ?>">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="locale" value="<?php echo htmlspecialchars($lang['code']); ?>">
                                    <button type="submit" class="dropdown-item d-flex align-items-center w-100<?php echo $isCurrent ? ' fw-semibold' : ''; ?>">
                                        <span><?php echo htmlspecialchars($lang['native']); ?></span>
                                        <?php if ($isCurrent): ?><i class="fa fa-check ms-auto"></i><?php endif; ?>
                                    </button>
                                </form>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link nav-icon-btn" data-bs-toggle="dropdown" href="#" role="button" title="<?php echo __('admin.appearance'); ?>" aria-label="<?php echo __('admin.appearance'); ?>">
                            <i class="fa fa-sun zap-theme-icon" data-zap-theme-icon></i>
                        </a>
                        <div class="dropdown-menu dropdown-menu-end shadow" style="min-width: 170px;">
                            <button type="button" class="dropdown-item d-flex align-items-center zap-theme-option" data-theme="light" role="menuitemradio" aria-checked="false">
                                <i class="fa fa-sun fa-fw me-2"></i><span><?php echo __('admin.theme_light'); ?></span>
                                <i class="fa fa-check ms-auto zap-theme-check invisible"></i>
                            </button>
                            <button type="button" class="dropdown-item d-flex align-items-center zap-theme-option" data-theme="dark" role="menuitemradio" aria-checked="false">
                                <i class="fa fa-moon fa-fw me-2"></i><span><?php echo __('admin.theme_dark'); ?></span>
                                <i class="fa fa-check ms-auto zap-theme-check invisible"></i>
                            </button>
                            <button type="button" class="dropdown-item d-flex align-items-center zap-theme-option" data-theme="auto" role="menuitemradio" aria-checked="false">
                                <i class="fa fa-circle-half-stroke fa-fw me-2"></i><span><?php echo __('admin.theme_auto'); ?></span>
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
                                <i class="fa fa-user-cog me-2"></i><?php echo __('admin.account_settings'); ?>
                            </a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item" href="<?php echo \zap\facades\Url::action('System@settings'); ?>">
                                <i class="fa fa-cog me-2"></i><?php echo __('admin.system_settings'); ?>
                            </a>
                            <?php echo \zapcms\Hook::fire('admin_user_dropdown'); ?>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item text-danger" href="<?php echo \zap\facades\Url::action('Auth@signOut'); ?>">
                                <i class="fa fa-sign-out-alt me-2"></i><?php echo __('admin.logout'); ?>
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
                            <h1 class="mb-0 fs-5" id="zapPageTitle"><?php echo htmlspecialchars($page_title); ?></h1>
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
                <div class="container-fluid py-3" id="zContent">
                    <?php echo \zapcms\Hook::fire('admin_content_before'); ?>
                    <?php echo $this->block('content');?>
                    <?php echo \zapcms\Hook::fire('admin_content_after'); ?>
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

    <!-- Toast 消息容器
         Bootstrap 的 .toast-container 默认是 position:absolute，长页面滚动后会定位到文档顶部
         而看不见提示，这里统一用 position-fixed 固定在视口 -->
    <div class="toast-container position-fixed p-3 top-0 start-50 translate-middle-x" id="topCenterToast" data-original-class="toast-container position-fixed p-3"></div>
    <div class="toast-container position-fixed p-3 top-0 end-0" id="topRightToast" data-original-class="toast-container position-fixed p-3"></div>
    <div class="toast-container position-fixed p-3 top-50 start-50 translate-middle" id="centerToast" data-original-class="toast-container position-fixed p-3"></div>
    <div class="toast-container position-fixed p-3 bottom-0 end-0" id="bottomRightToast" data-original-class="toast-container position-fixed p-3"></div>
 
    
    <script src="<?php echo \zapcms\support\Vite::js('bootstrap.bundle'); ?>"></script>
    


    <script>
        <?php
        \zapcms\services\AdminPage::instance()->showFlashMessages();
        ?>
    </script>

    <script>
        // 顶栏「清理缓存」快捷入口：只清理数据缓存与模板缓存
        function zapClearCacheQuick(event) {
            event.preventDefault();

            Swal.fire({
                title: '确认清理缓存？',
                text: '将清空数据缓存与模板缓存，页面会在下次访问时自动重建。',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: '立即清理',
                cancelButtonText: '取消',
                confirmButtonColor: '#dc3545'
            }).then(function (result) {
                if (!result.isConfirmed) {
                    return;
                }

                const load = Zap.loading('正在清理缓存，请稍后');

                $.ajax({
                    url: '<?php echo \zap\facades\Url::action('System@cacheClear'); ?>',
                    method: 'post',
                    data: {type: 'cache'},
                    dataType: 'json',
                    success: function (data) {
                        if (data.code === 0) {
                            ZapToast.alert(data.msg, {bgColor: bgSuccess, position: Toast_Pos_Center});
                        } else {
                            ZapToast.alert(data.msg, {bgColor: bgDanger, position: Toast_Pos_Center});
                        }
                    },
                    error: function (xhr) {
                        ZapToast.alert('请求失败：' + xhr.status, {bgColor: bgDanger, position: Toast_Pos_Center});
                    }
                }).always(function () {
                    load.dispose();
                });
            });
        }
    </script>

    <script>
        /**
         * 自动为所有 POST 表单注入 CSRF 隐藏字段。
         * 后台视图为原生 PHP 输出、表单数量多，统一在此注入可避免逐个遗漏。
         */
        (function (token) {
            var FIELD = '_token';

            function fill(form) {
                if (!form || form.tagName !== 'FORM') {
                    return;
                }
                if (form.method && form.method.toUpperCase() !== 'POST') {
                    return;
                }
                if (form.querySelector('input[name="' + FIELD + '"]')) {
                    return;
                }
                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = FIELD;
                input.value = token;
                form.appendChild(input);
            }

            function fillAll() {
                Array.prototype.forEach.call(document.querySelectorAll('form'), fill);
            }

            document.addEventListener('DOMContentLoaded', fillAll);
            // 动态创建的表单在提交瞬间兜底注入
            document.addEventListener('submit', function (e) {
                fill(e.target);
            }, true);
        })(window.ZAP_CSRF_TOKEN);
    </script>
    <?php echo $this->block('page_scripts'); ?>
    <?php print_scripts(ASSETS_BODY); ?>
    <?php print_scripts(ASSETS_BODY_TEXT); ?>
    <?php echo \zapcms\Hook::fire('admin_foot'); ?>
</body>
</html>
