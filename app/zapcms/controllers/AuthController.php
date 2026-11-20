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

            $ip  = Request::ip() ?? '0.0.0.0';
            $key = 'login:' . md5($ip . '|' . strtolower((string)$username));

            // 防暴力破解：同一（IP + 账号）5 分钟内失败超 5 次即临时锁定
            if (\zapcms\services\LoginThrottle::isLocked($key)) {
                $msg = '登录尝试次数过多，请 ' . ceil(\zapcms\services\LoginThrottle::WINDOW_SECONDS / 60) . ' 分钟后再试';
                if (Request::isAjax()) {
                    Response::json(['code' => 429, 'msg' => $msg]);
                    return;
                }
                Response::redirect(Url::action('Auth@signIn'))->with('error', $msg);
                return;
            }

            $admin = DB::table('admin')->where('username', $username)->fetch(FETCH_OBJ);
            // 短路求值：empty($admin) 为 true 时不会执行 Password::verify
            if (empty($admin) || !Password::verify($password, $admin->password)) {
                $info  = \zapcms\services\LoginThrottle::recordFailure($key);
                $extra = $info['locked'] ? '，账号已临时锁定' : "，还可尝试 {$info['remain_try']} 次";
                \zapcms\models\AdminLog::warning(
                    \zapcms\models\AdminLog::CATEGORY_AUTH,
                    '登录失败',
                    "用户名 {$username} 登录失败（IP {$ip}）" . $extra
                );

                if (Request::isAjax()) {
                    Response::json(['code' => 1, 'msg' => '登录失败，用户名或密码错误' . $extra]);
                    return;
                }
                Response::redirect(Url::action('Auth@signIn'))->with('error', '登录失败，用户名或密码错误' . $extra);
                return;
            }

            // 登录成功：清除失败计数
            \zapcms\services\LoginThrottle::reset($key);

            // 账号状态校验：非 activated 拒绝登录。
            // 对外提示与密码错误保持一致，避免账号状态被探测。
            if (strcasecmp((string)($admin->status ?? ''), 'activated') !== 0) {
                \zapcms\models\AdminLog::warning(
                    \zapcms\models\AdminLog::CATEGORY_AUTH,
                    '禁用账号登录',
                    "用户 {$admin->username}（状态 " . ($admin->status ?? 'unknown') . "）尝试登录被拒绝（IP {$ip}）",
                    (int) $admin->id,
                    $admin->username
                );
                if (Request::isAjax()) {
                    Response::json(['code' => 1, 'msg' => '登录失败，用户名或密码错误']);
                    return;
                }
                Response::redirect(Url::action('Auth@signIn'))->with('error', '登录失败，用户名或密码错误');
                return;
            }

            //登录成功
            // 只更换 CSRF Token，不重建会话 ID：
            // 本环境（HTTPS 自签名 / AJAX 登录跳转）下 session_regenerate_id 下发的
            // 新 Cookie 可能未被浏览器采纳，而 use_strict_mode 又会拒绝旧 ID，
            // 导致跳转后的请求落成空会话、登录态丢失并被弹回登录页。
            // 沿用浏览器已持有的会话 ID 写入登录态即可；CSRF Token 已更换，
            // 登录前的 Token 无法复用，仍可防会话固定/CSRF 复用。
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
            // 清除可能残留的上一个会话账号状态缓存，避免登录后首次访问被误判停用
            \zapcms\services\Auth::clearStatusCache();

            // 无论 AJAX 还是整页提交，都写入登录审计日志
            \zapcms\models\AdminLog::info(
                \zapcms\models\AdminLog::CATEGORY_AUTH,
                '管理员登录',
                "用户 {$admin->username} 登录成功",
                $admin->id,
                $admin->username
            );

            if (Request::isAjax()) {
                Response::json(['code'=>0,'msg'=>'登录成功','redirect_to'=>Url::action('Index')]);
                return;
            }
            Response::redirect(Url::action('Index'))->with('success', '登录成功');
            return;
        }
        View::render("auth.login");
    }

    function signOut()
    {
        \zapcms\models\AdminLog::info(\zapcms\models\AdminLog::CATEGORY_AUTH, '管理员退出', '安全退出系统');
        Auth::signOut();
        Response::redirect(Url::action('Auth@signIn'))->with('success', '您已安全退出');
    }


}