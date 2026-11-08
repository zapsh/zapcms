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

        // 已登录：实时校验账号状态，账号被禁用/删除后立即踢下线
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
     */
    protected static function isAccountActive(int $userId): bool
    {
        $admin = DB::table('admin')->select('status')->where('id', $userId)->fetch(FETCH_OBJ);
        return !empty($admin) && strcasecmp((string) $admin->status, 'activated') === 0;
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