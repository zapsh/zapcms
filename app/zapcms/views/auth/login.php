<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ZAP CMS - 登录</title>

    <script>
        /* 主题引导：与后台保持一致，避免暗色主题闪白 */
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

    <link rel="stylesheet" href="<?php echo base_url();?>/assets/admin/css/zap-admin.css">
<script src="<?php echo base_url();?>/assets/jquery/jquery-3.6.4.min.js"></script>
    <link href="<?php echo base_url();?>/assets/fontawesome/6.4.2/css/all.css" rel="stylesheet">
    <!-- 动态样式 -->
    <style>
        .auth-login-box {
            width: 100%;
            max-width: 440px;
        }
        .auth-login-logo {
            font-size: 2.1rem;
            font-weight: 300;
            margin-bottom: 0.75rem;
        }
        .auth-login-logo img {
            height: 48px;
        }
    </style>


</head>
<body class="login-page bg-body-secondary">

    <!-- Toast 容器 -->
    <div class="toast-container top-50 start-50 translate-middle" id="centerToast"></div>
    <div id="topCenterToast" class="toast-container top-0 start-50 translate-middle-x"></div>
    <div id="topRightToast" class="toast-container top-0 end-0"></div>

    <div class="login-box auth-login-box">
        <!-- Logo & 标题 -->
        <div class="login-logo">
            <a href="<?php echo base_url();?>">
                <img src="<?php echo base_url();?>/assets/admin/img/zap_logo_green.svg"
                     alt="ZAP CMS"
                     class="auth-login-logo img-fluid mb-2"
                     style="height: 52px;">
            </a>
            <p class="text-secondary fs-6 mt-2">简单高效的建站系统</p>
        </div>

        <!-- 登录卡片 -->
        <div class="card">
            <div class="card-body login-card-body">
                <p class="login-box-msg">请输入账号和密码登录</p>

                <!-- Flash 消息区域 -->
                <script>
                <?php \zapcms\services\AdminPage::instance()->showFlashMessages();?>
                </script>
                <form id="loginForm" action="" method="post" autocomplete="off">
                    <input type="hidden" name="token" value="<?php echo csrf_token();?>">

                    <!-- 用户名 -->
                    <div class="input-group mb-3">
                        <input type="text"
                               name="user_login"
                               id="user_login"
                               class="form-control"
                               placeholder="用户名"
                               required
                               autofocus
                               autocomplete="username">
                        <div class="input-group-text">
                            <span class="fas fa-user"></span>
                        </div>
                    </div>

                    <!-- 密码 -->
                    <div class="input-group mb-3">
                        <input type="password"
                               name="user_pass"
                               id="user_pass"
                               class="form-control"
                               placeholder="密码"
                               required
                               autocomplete="current-password">
                        <div class="input-group-text">
                            <span class="fas fa-lock"></span>
                        </div>
                    </div>

                    <!-- 记住我 & 忘记密码 -->
                    <div class="row mb-3">
                        <div class="col-8">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="remember" id="remember" value="1">
                                <label class="form-check-label" for="remember">记住我</label>
                            </div>
                        </div>
                        <div class="col-4 text-end">
                            <a href="<?php echo url_action('Auth@forgot');?>" class="text-decoration-none">忘记密码</a>
                        </div>
                    </div>

                    <!-- 登录按钮 -->
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary" id="btn-login">
                            <i class="fas fa-sign-in-alt me-1"></i>登录
                        </button>
                    </div>
                </form>

            </div>
        </div>

        <!-- 页脚链接 -->
        <?php $indexPage = \zapcms\services\Option::get('home','home') ?: '1';?>
        <div class="text-center mt-3">
            <a href="<?php echo base_url('/');?>" class="text-decoration-none text-secondary">
                <i class="fas fa-home me-1"></i>返回网站首页
            </a>
        </div>
    </div>

    <!-- Bootstrap 5 Bundle JS（Vite 构建产物） -->
    <script src="<?php echo base_url();?>/assets/admin/js/bootstrap.bundle.js"></script>

    <!-- ZAP Admin JS -->
    <script src="<?php echo base_url();?>/assets/admin/js/admin.js"></script>
    <script src="<?php echo base_url();?>/assets/admin/js/zap-admin.js"></script>

    <script>
        var loginUrl  = "<?php echo url_action('Auth@signIn');?>";
        var indexUrl  = "<?php echo url_action('Index');?>";
        var baseUrl   = "<?php echo base_url();?>";

   
        $(function () {
            // 回车键提交
            $('#loginForm').on('keypress', function(e) {
                if (e.which === 13) {
                    $('#loginForm').submit();
                }
            });
        });

        $('#loginForm').submit(function() {
            var username  = $.trim($('#user_login').val());
            var password  = $.trim($('#user_pass').val());
            var token     = $('input[name="token"]').val();
            var remember  = $('#remember').is(':checked') ? 1 : 0;

            if (username == '') {
                ZapToast.alert({title: '请输入账号', bg: bgDanger});
                $('#user_login').focus();
                return false;
            }
            if (password == '') {
                ZapToast.alert({title: '请输入密码', bg: bgDanger});
                $('#user_pass').focus();
                return false;
            }

            loginSystem(username, password, token, remember);
            return false;
        });

        function loginSystem(username, password, token, remember)
        {
            var btn = $('#btn-login');
            btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>正在登录...');

            $.ajax({
                url: loginUrl,
                data: {
                    user_login: username,
                    user_pass: password,
                    token: token,
                    remember: remember
                },
                dataType: 'json',
                method: 'POST',
                timeout: 15000,
                success: function (ret) {
                    if (ret.code == 0) {
                        ZapToast.alert({title: ret.msg || '登录成功', bg: bgSuccess});
                        setTimeout(function () {
                            window.location.href = ret.data?.redirect || indexUrl;
                        }, 600);
                    } else {
                        ZapToast.alert({title: ret.msg || '登录失败', bg: bgDanger});
                        btn.prop('disabled', false).html('<i class="fas fa-sign-in-alt me-1"></i>登录');
                        // 刷新 CSRF Token
                        if (ret.token) {
                            $('input[name="token"]').val(ret.token);
                        }
                    }
                },
                error: function (xhr, status, error) {
                    ZapToast.alert({title: '请求失败，请稍后重试', bg: bgDanger});
                    btn.prop('disabled', false).html('<i class="bi bi-box-arrow-in-right me-1"></i>登录');
                }
            });
        }
    </script>

</body>
</html>
