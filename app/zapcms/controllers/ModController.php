<?php

namespace zapcms\controllers;

use zap\view\View;

class ModController extends AdminController
{
    /**
     * 后台模块路由入口
     *
     * URL: /{后台前缀}/mod/{module}/{controller}/{action}/{params...}
     */
    public function _invoke($module, $params)
    {
        if ($module === 'index' || $module === '') {
            $this->index();
            return;
        }

        // 模块目录不存在时给出明确提示，避免落到控制器 404 后难排查
        if (!is_dir(base_path("mods/{$module}"))) {
            http_response_code(404);
            echo 'Module [' . htmlspecialchars($module) . '] is not installed.';
            return;
        }

        View::paths(base_path("mods/{$module}/views"));

        $controller = array_shift($params) ?? 'Index';
        $action     = array_shift($params) ?? 'index';
        $controller = str_replace('-', '', ucwords($controller, '-'));
        $action     = str_replace('-', '', ucwords($action, '-'));

        // 优先走模块入口类 Mod::invoke()
        $mod = "\\mods\\{$module}\\Mod";
        if (class_exists($mod)) {
            call_user_func_array([$mod, 'invoke'], [$module, $controller, $action, $params]);
            return;
        }

        // 走模块控制器
        $class = "\\mods\\{$module}\\controllers\\{$controller}Controller";
        if (!class_exists($class)) {
            View::render('mod.notfound', [
                'module'     => $module,
                'controller' => $controller,
                'method'     => $action,
                'error'      => "{$class} - Class does not exist!!",
            ]);
            return;
        }
        if (!method_exists($class, $action)) {
            View::render('mod.notfound', [
                'module'     => $module,
                'controller' => $controller,
                'method'     => $action,
                'error'      => "{$class}::{$action} - Method does not exist!!",
            ]);
            return;
        }
        call_user_func_array([new $class(), $action], $params);
    }

    public function index()
    {
        View::render('mod.index', []);
    }
}
