<?php
/*
 * Copyright (c) 2023.  ZAP.CN  - ZAP CMS
 */

namespace app\controllers;

use zap\http\Controller;
use zap\view\View;

/**
 * 前台模块控制器
 *
 * URL 分发规则：/mod/{模块名}/{控制器}/{方法}/{参数...}
 *   ─ /mod/shop/product/view/123 → \mods\shop\controllers\ProductController@view('123')
 *   ─ /mod/shop               → \mods\shop\controllers\IndexController@index()
 *
 * 别名支持：在 config/module.php 中配置 aliases 映射 URL 前缀到实际模块目录名
 */
class ModController extends Controller
{
    /**
     * 前端模块路由入口
     *
     * URL 段解析：/mod/{$module}/{$controller}/{$action}/{$params...}
     *
     * 注意：第一个参数名必须是 $method —— Startup::dispatchFrontend() 以
     * 命名参数 ['method' => ..., 'params' => ...] 调用本方法。
     *
     * @param string $method 模块名（URL 前缀对应的模块目录名）
     * @param array  $params 剩余 URL 段
     */
    public function _invoke($method, $params): void
    {
        $module = (string)$method;

        if (empty($module)) {
            http_response_code(404);
            echo "Module name is required.";
            return;
        }

        // ──── 应用别名 ────
        $module = $this->resolveAlias($module);

        if (!is_dir(base_path("mods/{$module}"))) {
            http_response_code(404);
            echo "Module [{$module}] is not installed.";
            return;
        }

        // ──── 加载模块视图目录 ────
        View::paths(base_path("mods/{$module}/views"));

        $controller = array_shift($params) ?? 'Index';
        $action     = array_shift($params) ?? 'index';
        $controller = str_replace('-', '', ucwords($controller, '-'));
        $action     = str_replace('-', '', ucwords($action, '-'));

        // ──── 优先走模块入口类 Mod::invoke() ────
        $modClass = "\\mods\\{$module}\\Mod";
        if (class_exists($modClass)) {
            call_user_func_array([$modClass, 'invoke'], [$module, $controller, $action, $params]);
            return;
        }

        // ──── 走模块控制器 ────
        $className = "\\mods\\{$module}\\controllers\\{$controller}Controller";

        if (!class_exists($className)) {
            http_response_code(404);
            echo "Module [{$module}]: controller [{$controller}] not found.";
            return;
        }

        if (!method_exists($className, $action)) {
            http_response_code(404);
            echo "Module [{$module}]: action [{$controller}::{$action}] not found.";
            return;
        }

        call_user_func_array([new $className(), $action], $params);
    }

    /**
     * 根据 config/module.php 中的别名解析真实模块目录名
     */
    private function resolveAlias(string $module): string
    {
        $prefixes = config('module.prefixes', []);
        return $prefixes[$module] ?? $module;
    }

    public function index(): void
    {
    }
}
