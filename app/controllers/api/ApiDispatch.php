<?php

namespace app\controllers\api;

use zap\http\Controller;
use zap\http\Router;

/**
 * /api/admin 与 /api/mod 的统一分发器（由 Startup 注册路由调用）。
 *
 *  /api/admin/{resource}/{action}/{params...}
 *      → app\controllers\api\{Resource}ApiController@{action}
 *
 *  /api/mod/{module}/{controller}/{action}/{params...}
 *      → 优先 mods\{module}\Mod::invoke($module,$controller,$action,$params,'api')
 *        否则 mods\{module}\api\{Controller}Controller@{action}
 *
 * 后台 API 强制要求认证（会话或 Bearer Token）；模块 API 是否鉴权由模块自身决定
 * （模块控制器同样继承 ApiController，可调用 requireAuth()/requirePerm()）。
 */
class ApiDispatch extends Controller
{
    public function admin(string $any = ''): void
    {
        // API 请求异常一律返回 JSON（debug 模式含 exception/file/line/trace），
        // 避免 500 时输出 HTML 调试页
        \zap\ErrorHandler::forceJson(true);

        $adminId = \zapcms\services\ApiAuth::resolve();
        if ($adminId === null) {
            $this->json(['code' => 401, 'msg' => '未认证'], 401);
            return;
        }
        $this->ensureRbac()->setUser($adminId);
        $this->routeToController('admin', $any);
    }

    public function module(string $any = ''): void
    {
        \zap\ErrorHandler::forceJson(true);
        $this->routeToController('module', $any);
    }

    protected function routeToController(string $area, string $any): void
    {
        $segments = array_values(array_filter(explode('/', $any), static fn ($s) => $s !== ''));

        if ($area === 'admin') {
            $resource = $segments[0] ?? 'index';
            $action   = $segments[1] ?? 'index';
            $params   = array_slice($segments, 2);

            $class = '\\app\\controllers\\api\\' . Router::convertToName($resource) . 'ApiController';
            if (!class_exists($class) || !method_exists($class, $action)) {
                $this->json(['code' => 404, 'msg' => "API 资源不存在: {$resource}/{$action}"], 404);
                return;
            }
            $this->invoke(new $class(), $action, $params);
            return;
        }

        // ──── 模块 API ────
        $module     = $segments[0] ?? '';
        $controller = $segments[1] ?? 'index';
        $action     = $segments[2] ?? 'index';
        $params     = array_slice($segments, 3);

        if ($module === '') {
            $this->json(['code' => 404, 'msg' => '缺少模块名'], 404);
            return;
        }

        $module = $this->resolveAlias($module);
        if (!is_dir(base_path("mods/{$module}"))) {
            $this->json(['code' => 404, 'msg' => "模块不存在: {$module}"], 404);
            return;
        }

        // 优先走模块入口类 Mod::invoke（第 5 个参数为区域标识 'api'）
        $modClass = "\\mods\\{$module}\\Mod";
        if (class_exists($modClass) && method_exists($modClass, 'invoke')) {
            call_user_func_array([$modClass, 'invoke'], [$module, $controller, $action, $params, 'api']);
            return;
        }

        // 否则走 mods/{module}/api/{Controller}Controller
        $class = "\\mods\\{$module}\\api\\" . Router::convertToName($controller) . 'Controller';
        if (!class_exists($class) || !method_exists($class, $action)) {
            $this->json(['code' => 404, 'msg' => "模块 API 不存在: {$module}/{$controller}/{$action}"], 404);
            return;
        }
        $this->invoke(new $class(), $action, $params);
    }

    protected function invoke(object $instance, string $action, array $params): void
    {
        call_user_func_array([$instance, $action], $params);
    }

    private function resolveAlias(string $module): string
    {
        $prefixes = config('module.prefixes', []);
        return $prefixes[$module] ?? $module;
    }

    protected function ensureRbac(): \zapcms\auth\RBAC
    {
        $rbac = app()->rbac ?? null;
        if ($rbac === null) {
            $rbac = app()->make(\zapcms\auth\RBAC::class, [], 'rbac');
        }
        return $rbac;
    }
}
