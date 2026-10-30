<?php

namespace zapcms\controllers;


use zapcms\services\Auth;
use zap\DB;
use zap\facades\Url;
use zap\http\Controller;
use zap\http\Request;
use zap\http\Response;
use zap\util\Password;
use zap\view\View;

class AuthController extends Controller
{
    function index()
    {
        Response::redirect(Url::action('Auth@signIn'))
            ->with('warning', '请先登录');
    }

    function signIn()
    {
        if (Request::isPost()) {
            $username = Request::post('user_login');
            $password = Request::post('user_pass');

            $admin = DB::table('admin')->where('username', $username)->fetch(FETCH_OBJ);
            // 短路求值：empty($admin) 为 true 时不会执行 Password::verify
            if (empty($admin) || !Password::verify($password, $admin->password)) {
                if (Request::isAjax()) {
                    Response::json(['code'=>1,'msg'=>'登录失败，用户名或密码错误']);
                    return;
                }
                Response::redirect(Url::action('Auth@signIn'))->with('error', '登录失败，用户名或密码错误');
                return;
            }

            //登录成功
            // 必须先重建会话 ID：登录前的会话 ID 可能已被攻击者预置（会话固定）
            session()->regenerate(true);
            // 同时更换 CSRF Token，避免登录前下发的 Token 被复用
            session()->regenerateToken(true);

            DB::table('admin')->set('last_ip', Request::ip())
                ->set('last_access_time', time())
                ->where('username',$username)
                ->update();
            session()->set('zapAdmin',[
                'id'              => $admin->id,
                'username'        => $admin->username,
                'full_name'       => $admin->full_name ?: $admin->username,
                'email'           => $admin->email ?? '',
                'phone_number'    => $admin->phone_number ?? '',
                'avatar_url'      => $admin->avatar_url ?? '',
                'last_ip'         => $admin->last_ip,
                'last_access_time'=> $admin->last_access_time,
            ]);

            if (Request::isAjax()) {
                Response::json(['code'=>0,'msg'=>'登录成功','redirect_to'=>Url::action('Index')]);
                return;
            }
            \zapcms\models\AdminLog::log('管理员登录', "用户 {$admin->username} 登录成功", $admin->id, $admin->username);
            Response::redirect(Url::action('Index'))->with('success', '登录成功');
            return;
        }
        View::render("auth.login");
    }

    function signOut()
    {
        \zapcms\models\AdminLog::log('管理员退出', '安全退出系统');
        Auth::signOut();
        Response::redirect(Url::action('Auth@signIn'))->with('success', '您已安全退出');
    }


}