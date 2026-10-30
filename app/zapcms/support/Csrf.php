<?php

namespace zapcms\support;

use zap\http\Request;
use zap\http\Response;

/**
 * 后台 CSRF 防护
 *
 * 设计要点：
 *  - Token 生成与校验复用框架的 Session（random_bytes + hash_equals）
 *  - 只拦截"不安全"方法（POST/PUT/PATCH/DELETE），GET/HEAD/OPTIONS 放行
 *  - Token 可从请求头（X-CSRF-Token）或表单字段（_token / 兼容 token）读取，
 *    因此 jQuery/Fetch 与普通表单提交都能覆盖
 *  - 豁免名单来自静态配置 config('security.csrf_exempt')，**不读取数据库选项**，
 *    避免攻击者通过后台配置把自己关心的接口加入豁免
 */
class Csrf
{
    /** 表单字段名 */
    public const FIELD = '_token';

    /** 可读取 Token 的请求头（小写形式也会做兼容匹配） */
    protected const HEADERS = ['X-CSRF-Token', 'X-CSRF-TOKEN', 'X-Zap-Token'];

    /** 历史遗留字段名（如 auth/login.php 使用 name="token"） */
    protected const LEGACY_FIELDS = ['token'];

    /** 需要校验的 HTTP 方法 */
    protected const UNSAFE_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    /**
     * 当前 Token
     */
    public static function token(): string
    {
        return session()->token();
    }

    /**
     * 输出隐藏表单字段
     */
    public static function field(): string
    {
        return '<input type="hidden" name="' . self::FIELD . '" value="' . esc(self::token()) . '" />';
    }

    /**
     * 输出 <meta>，便于前端脚本读取 Token
     */
    public static function meta(): string
    {
        return '<meta name="csrf-token" content="' . esc(self::token()) . '" />';
    }

    /**
     * 从当前请求中提取 Token
     */
    public static function requestToken(): string
    {
        foreach (self::HEADERS as $name) {
            $value = Request::headers($name, '');
            if ($value === '') {
                $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
                $value = $_SERVER[$key] ?? '';
            }
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        $fields = array_merge([self::FIELD], self::LEGACY_FIELDS);
        foreach ($fields as $field) {
            $value = Request::post($field, '');
            if ($value === '') {
                $value = Request::get($field, '');
            }
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return '';
    }

    /**
     * 校验当前请求的 Token
     */
    public static function verify(): bool
    {
        $token = self::requestToken();
        if ($token === '') {
            return false;
        }

        return session()->validateToken($token);
    }

    /**
     * 是否需要对当前请求做校验
     *
     * @param string $route 形如 "node@edit"，controller 与 method 均为 URL 中的原始写法
     */
    public static function shouldVerify(string $route = ''): bool
    {
        if (!in_array(strtoupper(Request::method()), self::UNSAFE_METHODS, true)) {
            return false;
        }

        return !self::isExempt($route);
    }

    /**
     * CSRF 守卫：校验失败时直接终止请求
     *
     * @param string $route 形如 "node@edit"
     */
    public static function guard(string $route = ''): void
    {
        if (!self::shouldVerify($route)) {
            return;
        }

        if (self::verify()) {
            return;
        }

        // 灰度开关：config('security.csrf_mode') = 'log' 时只记录不拦截，
        // 便于在正式开启前发现尚未注入 Token 的表单/接口；观察稳定后改回 enforce
        if (strtolower((string)config('security.csrf_mode', 'enforce')) === 'log') {
            error_log(sprintf(
                '[CSRF] 校验未通过（观察模式） route=%s ip=%s ua=%s',
                $route,
                (string)Request::ip(),
                substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 120)
            ));
            return;
        }

        self::abort();
    }

    /**
     * 豁免名单（静态配置优先，其次类内默认）
     */
    protected static function isExempt(string $route): bool
    {
        if ($route === '') {
            return false;
        }

        $exempt = config('security.csrf_exempt', []);
        if (!is_array($exempt)) {
            $exempt = [];
        }

        $list = array_map('strtolower', array_map('strval', $exempt));
        $route = strtolower($route);

        return in_array($route, $list, true) || in_array('*', $list, true);
    }

    /**
     * 校验失败响应
     */
    protected static function abort(): void
    {
        http_response_code(403);

        if (Request::isAjax()) {
            Response::json([
                'code' => 1,
                'msg'  => '表单已过期或请求来源不可信（CSRF 校验失败），请刷新页面后重试',
            ], 403);
            return;
        }

        echo '<!DOCTYPE html><html lang="zh"><head><meta charset="utf-8">'
            . '<title>403 请求被拒绝</title></head><body style="font-family:sans-serif;padding:40px">'
            . '<h1 style="font-size:20px">403 请求被拒绝</h1>'
            . '<p>表单已过期或请求来源不可信（CSRF 校验失败）。请返回上一页刷新后重试。</p>'
            . '<p><a href="javascript:history.back()">返回上一页</a></p>'
            . '</body></html>';
        exit;
    }
}
