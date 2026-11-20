<?php

namespace app\controllers\api;

use zap\http\Controller;

/**
 * API 控制器基类。
 *
 * 约定：
 *  - 成功：ok($data)        → {"code":0,"data":...}
 *  - 失败：error($msg)      → {"code":1,"msg":...}
 *  - 401：unauthorized()    → {"code":401,"msg":"未认证"}
 *  - 403：forbidden()       → {"code":403,"msg":"无权限"}
 *
 * 鉴权/授权：
 *  - requireAuth()  要求已认证（会话或 Bearer Token），并把身份同步到 RBAC；
 *  - requirePerm()  要求拥有指定权限（复用现有 admin_menu_{id} 权限键）。
 */
abstract class ApiController extends Controller
{
    /** 统一成功响应 */
    protected function ok($data = null, int $code = 0): void
    {
        $this->json(['code' => $code, 'data' => $data], 200);
    }

    /** 统一错误响应（HTTP 状态默认 200，业务码在 code 字段） */
    protected function error(string $msg, int $code = 1, int $http = 200): void
    {
        $this->json(['code' => $code, 'msg' => $msg], $http);
    }

    /** 401 未认证 */
    protected function unauthorized(string $msg = '未认证'): void
    {
        $this->json(['code' => 401, 'msg' => $msg], 401);
    }

    /** 403 无权限 */
    protected function forbidden(string $msg = '无权限'): void
    {
        $this->json(['code' => 403, 'msg' => $msg], 403);
    }

    /**
     * 要求已认证（会话或 Token），并把身份同步到 RBAC。
     * 未认证时直接输出 401 并 return null，调用方应据此 return。
     */
    protected function requireAuth(): ?int
    {
        $adminId = \zapcms\services\ApiAuth::resolve();
        if ($adminId === null) {
            $this->unauthorized();
            return null;
        }
        // 确保 RBAC 实例存在（前端/API 请求不会走后台 AdminController 初始化）
        $this->ensureRbac()->setUser($adminId);
        return $adminId;
    }

    /**
     * 要求拥有指定权限（admin_menu_{id}），无权限时输出 403 并 return false。
     */
    protected function requirePerm(string $permKey): bool
    {
        if (!$this->ensureRbac()->check($permKey)) {
            $this->forbidden();
            return false;
        }
        return true;
    }

    /** 取请求参数（POST 含 JSON body，GET 取 query string） */
    protected function input(string $key = null, $default = null)
    {
        $data = $this->request()->isPost()
            ? $this->request()->all()
            : $this->request()->get();

        if ($key === null) {
            return $data;
        }
        return $data[$key] ?? $default;
    }

    /** 确保 app()->rbac 实例存在（前端/API 请求不一定初始化过） */
    protected function ensureRbac(): \zapcms\auth\RBAC
    {
        $rbac = app()->rbac ?? null;
        if ($rbac === null) {
            $rbac = app()->make(\zapcms\auth\RBAC::class, [], 'rbac');
        }
        return $rbac;
    }
}
