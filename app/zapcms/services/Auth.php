<?php
/*
 * Copyright (c) 2023.  ZAP.CN  - ZAP CMS - All Rights Reserved
 * @author Allen
 * @email zapcms@zap.cn
 * @date 2023/12/27 上午11:29
 * @lastModified 2023/12/27 上午11:20
 *
 */

namespace zapcms\services;


use zap\DB;
use zap\facades\Url;
use zap\http\Request;
use zap\http\Response;

class Auth
{

    protected static $scope = 'zapAdmin';

    /** 同一请求内只做一次账号状态校验（Auth::check 会被多处调用） */
    protected static bool $statusChecked = false;

    /** 账号状态校验结果缓存时长（秒），避免每次请求都查库 */
    protected const STATUS_CACHE_TTL = 60;

    public static function isLogin(): bool
    {

        if(session()->has(static::$scope)){
            return true;
        }
        return false;
    }

    /**
     * @throws \Exception
     */
    public static function check($url = null, $message = null)
    {
        if(!session()->has(static::$scope)){
            $url  = $url ?? Url::action('Auth@signIn');
            $message = $message ?? '未登录或已超时，请重新登录';
            if(Request::isAjax()){
                Response::json(['code'=>-1,'msg'=>$message]);
            }
            Response::redirect($url)->with('info', $message);
        }

        // 已登录：校验账号状态（结果缓存 60 秒，账号被禁用/删除最多 60 秒后踢下线）
        if (!static::$statusChecked) {
            static::$statusChecked = true;
            $userId = (int) (static::user('id') ?? 0);
            if ($userId > 0 && !static::isAccountActive($userId)) {
                static::signOut();
                $message = '账号已被禁用或删除，请联系管理员';
                if (Request::isAjax()) {
                    Response::json(['code' => -1, 'msg' => $message]);
                }
                Response::redirect($url ?? Url::action('Auth@signIn'))->with('warning', $message);
            }
        }
    }

    /**
     * 账号是否处于激活状态（admin.status === 'activated'）
     * 校验结果按 user_id 缓存在 session，60 秒内不重复查库。
     */
    protected static function isAccountActive(int $userId): bool
    {
        $cacheKey = static::scope() . 'StatusCheck';
        $cached   = session()->get($cacheKey);
        $now      = time();
        if (is_array($cached)
            && (int)($cached['uid'] ?? 0) === $userId
            && $now - (int)($cached['time'] ?? 0) < static::STATUS_CACHE_TTL
        ) {
            return (bool) $cached['active'];
        }

        try {
            $admin = DB::table('admin')->select('status')->where('id', $userId)->fetch(FETCH_OBJ);
        } catch (\Throwable $e) {
            // 查库异常（如刚执行过数据更新、连接抖动）时放行，避免把合法用户误踢回登录页
            return true;
        }
        // 找不到账号行：登录态是在登录成功后写入的，账号若被删应由登录流程拦截；
        // 这里仅在明确读到非 activated 状态时才判定停用，避免误踢。
        $active = empty($admin) || strcasecmp((string) $admin->status, 'activated') === 0;
        session()->set($cacheKey, ['uid' => $userId, 'active' => $active, 'time' => $now]);
        return $active;
    }

    /** 登录成功后调用，清除可能残留的旧账号状态缓存 */
    public static function clearStatusCache(): void
    {
        session()->remove(static::scope() . 'StatusCheck');
    }

    public static function user($key = null,$default = null){
        app()->has(static::$scope) ?: app()->set(static::$scope,session()->get(static::$scope));
        if($key){
            return app()->get(static::$scope)[$key] ?? $default;
        }
        return app()->get(static::$scope) ?? $default;
    }

    public static function signOut($scope = null){
        $scope = $scope ?? static::scope();
        session()->remove($scope);
        session()->remove($scope . 'StatusCheck');
    }

    public static function scope($scope = null): string
    {
        if($scope){
            static::$scope = $scope;
        }
        return static::$scope;
    }

    public static function login($data){

    }
}