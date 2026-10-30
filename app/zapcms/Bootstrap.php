<?php
/*
 * Copyright (c) 2023.  ZAP.CN  - ZAP CMS - All Rights Reserved
 * @author Allen
 * @email zapcms@zap.cn
 * @date 2023/12/27 上午11:38
 * @lastModified 2024/08/04
 *
 */

namespace zapcms;

use zap\http\Router;
use zap\http\Request;
use zap\view\View;
use zapcms\services\Option;
use zapcms\support\Csrf;

class Bootstrap
{
    protected Router $router;

    public function __construct()
    {

    }

    /**
     * 处理后台路由注册
     *
     * @param Router $router 路由器实例
     */
    public function handle(?Router $router = null)
    {
        if ($router) {
            $this->router = $router;
        }
        // ──── 注册后台自动路由 ────
        $prefix = trim(Z_ADMIN_PREFIX, '/');
        // 将后台前缀写入框架配置：UrlHelper 生成后台 URL 时以 config('admin.prefix') 为唯一依赖
        config_set('admin.prefix', $prefix);
        // 根路径: /z-admin 或 /z-admin/
        $this->router->any("/{$prefix}", function () {
            $this->dispatchAdmin('');
        });
        // 子路径: /z-admin/xxx/yyy
        $this->router->any("/{$prefix}/{any:.*}", function ($any = '') {
            $this->dispatchAdmin($any);
        });
    }

    /**
     * 初始化后台环境（在路由匹配后由中间件回调调用）
     */
    protected function initAdminEnv(): void
    {
        define('IN_ZAPCMS_ADMIN', true);
        config_set('config.theme', false);
        define('IS_AJAX', Request::isAjax());
        View::paths(realpath(__DIR__ . '/views'));

        $theme = option('website.theme', 'basic');
        // 后端只加载 admin/functions.php，不加载前台的 functions.php
        if (is_file(themes_path("{$theme}/admin/functions.php"))) {
            include themes_path("{$theme}/admin/functions.php");
        }

        // 加载已安装模块的 autoload 脚本（options 表登记，status=1 且配置了 autoload）
        $this->loadModAutoloads();
    }

    /**
     * 后台启动时加载已安装模块的 autoload 脚本
     *
     * 从 options 表读取 mod.installed.* 记录，对启用的模块加载其 autoload 脚本，
     * 脚本中可注册 hooks、菜单等。
     */
    protected function loadModAutoloads(): void
    {
        try {
            $rows = Option::getArray('mod.installed.', 'REGEXP');
        } catch (\Throwable $e) {
            return; // options 表不可用时静默跳过
        }
        foreach ($rows as $key => $json) {
            $name = substr($key, strlen('mod.installed.'));
            $info = is_string($json) ? (json_decode($json, true) ?: []) : [];
            if (empty($info['status'])) {
                continue;
            }

            // 同步模块后台菜单：mod.json 的 admin_menu 有变化时才写库
            try {
                \zapcms\support\ModMenu::sync($name, $info);
            } catch (\Throwable $e) {
                // 菜单同步失败不影响后台启动
            }

            if (empty($info['autoload'])) {
                continue;
            }
            $script = APP_ROOT . '/mods/' . $name . '/' . $info['autoload'];
            if (is_file($script)) {
                include_once $script;
            }
        }
    }

    /**
     * 将后台 URL 分发到对应的控制器和方法
     *
     * URL 规则: /{prefix}/{controller}/{method}
     * 例如:
     *   /z-admin                    → IndexController@index
     *   /z-admin/auth/sign-in       → AuthController@signIn
     *   /z-admin/node/edit/123      → NodeController@edit  (params: [123])
     */
    protected function dispatchAdmin(string $path): void
    {
        $this->initAdminEnv();

        $segments = $path === '' ? [] : array_values(array_filter(explode('/', $path)));

        $controllerName = $segments[0] ?? 'index';
        $methodName     = $segments[1] ?? 'index';

        // 额外的 URL 参数（从第2个之后）
        $extraParams = array_slice($segments, 2);

        $className = '\zapcms\controllers\\' . Router::convertToName($controllerName) . 'Controller';

        // 将 kebab-case 方法名转换为 camelCase（如 save-admin-menu → saveAdminMenu）
        $actionMethod = lcfirst(Router::convertToName($methodName));

        if (!class_exists($className)) {
            // 尝试将第一个段作为方法名
            $className = '\zapcms\controllers\IndexController';
            $methodName = $controllerName;
            $actionMethod = lcfirst(Router::convertToName($methodName));
            $extraParams = array_slice($segments, 1);

            if (!method_exists($className, $actionMethod)) {
                http_response_code(404);
                echo '<h1>404 Not Found</h1>';
                exit;
            }
        }

        $instance = new $className();

        if (!method_exists($className, $actionMethod) && !method_exists($className, '__call')) {
            // 支持 _invoke 动态路由（如 NodeController）
            if (method_exists($instance, '_invoke')) {
                // 保存 controller/method（kebab-case）到 Router 供全局访问
                app()->router->controller = $controllerName;
                app()->router->method = strtolower($methodName);
                // CSRF：控制器构造完成后、执行动作前统一校验
                Csrf::guard($controllerName . '@' . $methodName);
                $instance->_invoke($methodName, $extraParams);
                return;
            }
            // 尝试 index 方法 + 第一个段作为参数
            if (method_exists($instance, 'index')) {
                $actionMethod = 'index';
                $extraParams = $segments;
            } else {
                http_response_code(404);
                echo '<h1>404 Not Found</h1>';
                exit;
            }
        }

        // 保存已解析的 controller/method（kebab-case）到 Router 供全局访问
        app()->router->controller = $controllerName;
        app()->router->method = strtolower($methodName);

        // CSRF：控制器构造完成后、执行动作前统一校验（GET/HEAD/OPTIONS 自动放行）
        Csrf::guard($controllerName . '@' . $methodName);

        // 调用控制器方法（camelCase），传入额外参数
        if (!empty($extraParams)) {
            call_user_func_array([$instance, $actionMethod], $extraParams);
        } else {
            $instance->$actionMethod();
        }
    }
}
