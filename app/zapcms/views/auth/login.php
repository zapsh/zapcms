<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
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

    <link rel="stylesheet" href="<?php echo \zapcms\support\Vite::css('zap-admin'); ?>">
    <script src="<?php echo base_url();?>/assets/jquery/jquery-3.6.4.min.js"></script>
    <link href="<?php echo base_url();?>/assets/fontawesome/6.4.2/css/all.css" rel="stylesheet">
    <!-- 动态样式 -->
    <style>
        .auth-login-box {
            width: 100%;
            max-width: 420px;
        }
        .auth-login-logo {
            font-size: 2.1rem;
            font-weight: 300;
            margin-bottom: 0.75rem;
        }
        .auth-login-logo img {
            height: 48px;
        }
        .auth-login-box .card {
            border: 0;
            border-radius: 1rem;
            box-shadow: 0 10px 30px rgba(15, 23, 42, .08);
        }
        .auth-login-box .login-card-body {
            padding: 1.75rem;
        }
        .auth-login-box .login-box-msg {
            padding: 0 0 1.25rem;
            margin: 0;
            text-align: center;
            color: var(--bs-secondary-color);
        }
        .auth-login-box .form-control:focus {
            box-shadow: none;
        }
        .auth-login-box #togglePassword {
            cursor: pointer;
        }
        .auth-login-box .btn-login {
            padding: .6rem 1rem;
            font-weight: 600;
            letter-spacing: .02em;
        }
        .auth-login-footer {
            color: var(--bs-secondary-color);
            font-size: .8125rem;
        }
        [data-bs-theme="dark"] .auth-login-box .card {
            box-shadow: 0 10px 30px rgba(0, 0, 0, .45);
        }
    </style>


</head>
<body class="login-page bg-body-secondary">

    <!-- 顶部 Toast 容器：登录反馈统一在此弹出，浮于页面最上层且不遮挡表单 -->
    <div id="topCenterToast" class="toast-container position-fixed p-3 top-0 start-50 translate-middle-x" style="z-index: 1090;"></div>
    <div id="topRightToast" class="toast-container position-fixed p-3 top-0 end-0" style="z-index: 1090;"></div>

    <div class="login-box auth-login-box">
        <!-- Logo & 标题 -->
        <div class="login-logo">
            <a href="<?php echo base_url();?>">
                <img src="<?php echo base_url();?>/assets/admin/img/zap_logo_green.svg"
                     alt="ZAP CMS"
                     class="auth-login-logo img-fluid mb-2"
                     style="height: 52px;">
            </a>
            <p class="text-secondary fs-6 mt-2 mb-0">简单高效的建站系统</p>
        </div>

        <!-- 登录卡片 -->
        <div class="card">
            <div class="card-body login-card-body">
                <p class="login-box-msg">请输入账号和密码登录</p>

                <?php
                // Flash 消息（退出登录 / 未登录跳转 / 限流 / 非 AJAX 登录失败）
                // 统一改为页面顶部 Toast 弹出；此处只收集文本，由底部脚本负责渲染。
                $flashAll = session()->flash();
                $flashVariant = [
                    'error'   => 'danger',
                    'danger'  => 'danger',
                    'warning' => 'warning',
                    'success' => 'success',
                    'info'    => 'info',
                ];
                $loginFlashMessages = [];
                if (!empty($flashAll)) {
                    foreach ($flashAll as $flashKey => $flashMsg) {
                        if ($flashKey === '__old__') { continue; }
                        $variant = $flashKey === '_validation_errors'
                            ? 'danger'
                            : ($flashVariant[$flashKey] ?? 'info');
                        if (is_array($flashMsg)) {
                            $parts = [];
                            array_walk_recursive($flashMsg, function ($v) use (&$parts) {
                                $parts[] = (string) $v;
                            });
                            $text = implode('；', $parts);
                        } else {
                            $text = (string) $flashMsg;
                        }
                        if (trim($text) === '') { continue; }
                        $loginFlashMessages[] = ['variant' => $variant, 'text' => $text];
                    }
                }
                ?>

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
                               autocomplete="username"
                               aria-label="用户名">
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
                               autocomplete="current-password"
                               aria-label="密码">
                        <button class="input-group-text" type="button" id="togglePassword" title="显示密码" aria-label="显示密码">
                            <span class="fas fa-eye"></span>
                        </button>
                    </div>

                    <!-- 登录按钮 -->
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary btn-login" id="btn-login">
                            <i class="fas fa-sign-in-alt me-1"></i>登录
                        </button>
                    </div>
                </form>

            </div>
        </div>

        <!-- 页脚链接 -->
        <div class="text-center mt-3">
            <a href="<?php echo base_url('/');?>" class="text-decoration-none text-secondary">
                <i class="fas fa-home me-1"></i>返回网站首页
            </a>
        </div>
        <div class="text-center mt-2 auth-login-footer">
            &copy; <?php echo date('Y'); ?> ZapCMS
        </div>
    </div>

    <!-- Bootstrap 5 Bundle JS（Vite 构建产物） -->
    <script src="<?php echo \zapcms\support\Vite::js('bootstrap.bundle'); ?>"></script>

    <!-- ZAP Admin JS -->
    <script src="<?php echo base_url();?>/assets/admin/js/admin.js?v=<?php echo (int) @filemtime(APP_ROOT . '/assets/admin/js/admin.js'); ?>"></script>

    <script>
        var loginUrl  = "<?php echo url_action('Auth@signIn');?>";
        var indexUrl  = "<?php echo url_action('Index');?>";
        var baseUrl   = "<?php echo base_url();?>";

        var submitting = false;

        // 服务端 flash 消息（退出登录、未登录跳转、限流等）同样在顶部弹出
        <?php
        // HEX_* 防止消息里的 </script> 提前闭合脚本标签
        $loginFlashJson = json_encode($loginFlashMessages, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        echo 'var loginFlashMessages = ' . ($loginFlashJson !== false ? $loginFlashJson : '[]') . ';';
        ?>

        // ZapToast.notify 会把消息直接写入 innerHTML，必须先做 HTML 转义
        function escapeLoginMsg(value) {
            return $('<div>').text(value == null ? '' : String(value)).html();
        }

        function showLoginToast(msg, variant, delay) {
            var colors = {
                success: bgSuccess, danger: bgDanger,
                warning: bgWarning, info: bgInfo
            };
            var icons = {
                success: 'fa-solid fa-circle-check',
                danger: 'fa-solid fa-circle-exclamation',
                warning: 'fa-solid fa-triangle-exclamation',
                info: 'fa-solid fa-circle-info'
            };
            ZapToast.alert(escapeLoginMsg(msg), {
                bgColor: colors[variant] || bgInfo,
                icon: icons[variant] || icons.info,
                position: Toast_Pos_TopCenter,
                delay: delay || 2500
            });
        }

        $(function () {
            // 页面加载后把 flash 逐条弹到顶部
            (loginFlashMessages || []).forEach(function (item) {
                showLoginToast(item.text, item.variant, 3500);
            });

            // 密码显示 / 隐藏
            $('#togglePassword').on('click', function () {
                var $input = $('#user_pass');
                var isPassword = $input.attr('type') === 'password';
                $input.attr('type', isPassword ? 'text' : 'password');
                $(this).find('span')
                    .toggleClass('fa-eye', !isPassword)
                    .toggleClass('fa-eye-slash', isPassword);
                $(this).attr('title', isPassword ? '隐藏密码' : '显示密码')
                    .attr('aria-label', isPassword ? '隐藏密码' : '显示密码');
                $input.trigger('focus');
            });

            // 回车键提交（阻止默认提交，避免重复请求）
            $('#loginForm').on('keypress', function (e) {
                if (e.which === 13) {
                    e.preventDefault();
                    $('#loginForm').submit();
                }
            });
        });

        $('#loginForm').submit(function () {
            var username  = $.trim($('#user_login').val());
            var password  = $.trim($('#user_pass').val());
            var token     = $('input[name="token"]').val();

            if (username == '') {
                showLoginToast('请输入账号', 'danger');
                $('#user_login').focus();
                return false;
            }
            if (password == '') {
                showLoginToast('请输入密码', 'danger');
                $('#user_pass').focus();
                return false;
            }

            loginSystem(username, password, token);
            return false;
        });

        function loginSystem(username, password, token)
        {
            if (submitting) { return; }
            submitting = true;

            var btn = $('#btn-login');
            btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>正在登录...');

            $.ajax({
                url: loginUrl,
                data: {
                    user_login: username,
                    user_pass: password,
                    token: token
                },
                dataType: 'json',
                method: 'POST',
                timeout: 15000,
                success: function (ret) {
                    if (ret.code == 0) {
                        showLoginToast(ret.msg || '登录成功，正在跳转...', 'success', 2000);
                        setTimeout(function () {
                            window.location.href = ret.redirect_to || (ret.data && ret.data.redirect) || indexUrl;
                        }, 500);
                    } else {
                        showLoginToast(ret.msg || '登录失败', 'danger');
                        btn.prop('disabled', false).html('<i class="fas fa-sign-in-alt me-1"></i>登录');
                        submitting = false;
                        // 刷新 CSRF Token
                        if (ret.token) {
                            $('input[name="token"]').val(ret.token);
                        }
                    }
                },
                error: function (xhr, status, error) {
                    var msg = (xhr && xhr.responseJSON && xhr.responseJSON.msg) || '请求失败，请稍后重试';
                    showLoginToast(msg, 'danger');
                    btn.prop('disabled', false).html('<i class="fas fa-sign-in-alt me-1"></i>登录');
                    submitting = false;
                }
            });
        }
    </script>

</body>
</html>
