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
     *
     * 双层策略：
     *  1. 请求携带有效 token 时直接放行；
     *  2. token 缺失或失效时退化为「同源 Origin/Referer 校验」。
     *     浏览器对同源 POST 会自动附带 Origin/Referer，而跨站伪造请求的
     *     Origin 必然是第三方站点 —— 因此该回退依然能阻断 CSRF。
     *     回退主要兜住两类误伤：动态表单/iframe 上传器漏发 token，
     *     以及 session 重建后（如登录）旧页面上残留的过期 token。
     */
    public static function verify(string $route = ''): bool
    {
        $token = self::requestToken();

        if ($token !== '' && session()->validateToken($token)) {
            return true;
        }

        $sameOrigin = self::isSameOriginRequest();
        if ($sameOrigin) {
            // 记录下来便于发现尚未注入 token 的页面/组件，或需要重新加载的旧标签页
            error_log(sprintf(
                '[CSRF] token %s，已按同源放行 route=%s ip=%s',
                $token === '' ? '未携带' : '不匹配',
                $route,
                (string)Request::ip()
            ));
        }

        return $sameOrigin;
    }

    /**
     * 判断当前请求是否来自同源页面（依据 Origin / Referer）
     */
    protected static function isSameOriginRequest(): bool
    {
        $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
        if ($host === '') {
            return false;
        }

        foreach (['HTTP_ORIGIN', 'HTTP_REFERER'] as $key) {
            $value = (string)($_SERVER[$key] ?? '');
            if ($value === '') {
                continue;
            }

            // sandboxed iframe 等场景 Origin 可能为字符串 "null"
            if (strtolower($value) === 'null') {
                return false;
            }

            $originHost = parse_url($value, PHP_URL_HOST);
            if ($originHost === null || $originHost === false || $originHost === '') {
                return false;
            }

            // HTTP_HOST 在非默认端口时包含端口，Origin 比较时也需要拼上
            $originPort = parse_url($value, PHP_URL_PORT);
            $fullHost = strtolower($originHost . ($originPort ? ':' . $originPort : ''));

            return $fullHost === $host;
        }

        return false;
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

        if (self::verify($route)) {
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
