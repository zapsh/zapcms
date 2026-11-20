<?php

namespace zapcms\controllers;

use zapcms\controllers\AdminController;
use zapcms\services\Auth;
use zapcms\services\Dashboard;
use zapcms\services\Locale;
use zap\facades\Url;
use zap\http\Request;
use zap\http\Response;
use zap\view\View;

class IndexController extends AdminController
{
    /**
     * 当前登录管理员 ID（admin_meta.object_id）。
     */
    private function adminId(): int
    {
        return (int)Auth::user('id', 0);
    }

    /**
     * 控制台：按当前管理员的布局渲染可见 widgets。
     * 隐藏的 widget 不会执行 render，因此不会触发对应的数据库查询。
     */
    public function index(): void
    {
        $adminId = $this->adminId();
        $widgets = Dashboard::widgets($adminId);
        $layout  = Dashboard::layout($adminId, $widgets);

        View::render('dashboard.index', [
            'widgets'     => $widgets,
            'layout'      => $layout,
            'active'      => Dashboard::active($adminId),
            'admin_id'    => $adminId,
            // dashboard.before / dashboard.after 由插件返回受信任的 HTML
            'before_html' => Dashboard::beforeHtml($widgets, $layout, $adminId),
            'after_html'  => Dashboard::afterHtml($widgets, $layout, $adminId),
        ]);
    }

    /**
     * GET：定制弹窗数据（可选 widgets + 当前顺序/启用状态）。
     */
    public function dashboardWidgets(): void
    {
        Response::json([
            'code' => 0,
            'data' => Dashboard::picker($this->adminId()),
        ]);
    }

    /**
     * POST：保存当前管理员的 widget 顺序与显示状态。
     * 白名单校验在 Dashboard::saveLayout() 内完成，越权的 widget id 会被丢弃。
     */
    public function saveDashboardWidgets(): void
    {
        if (!Request::isPost()) {
            Response::json(['code' => 1, 'msg' => '请求方式错误']);
            return;
        }
        $adminId = $this->adminId();
        if ($adminId <= 0) {
            Response::json(['code' => 1, 'msg' => '登录状态已失效，请重新登录']);
            return;
        }

        $order  = (array)Request::post('order', []);
        $hidden = (array)Request::post('hidden', []);

        try {
            $layout = Dashboard::saveLayout($adminId, $order, $hidden, Dashboard::widgets($adminId));
        } catch (\Throwable $e) {
            Response::json(['code' => 1, 'msg' => '保存失败：' . $e->getMessage()]);
            return;
        }
        Response::json(['code' => 0, 'msg' => '已保存', 'data' => ['layout' => $layout]]);
    }

    /**
     * POST：恢复系统默认布局（删除该用户的 admin_meta 记录）。
     */
    public function resetDashboardWidgets(): void
    {
        if (!Request::isPost()) {
            Response::json(['code' => 1, 'msg' => '请求方式错误']);
            return;
        }
        Dashboard::resetLayout($this->adminId());
        Response::json(['code' => 0, 'msg' => '已恢复默认布局']);
    }

    /**
     * 切换后台内容语言（写入会话，跳回上一页）。
     * 仅接受站内可用语言，非法值回退默认语言，防止任意写入会话。
     */
    public function switchLocale(): void
    {
        $locale = Locale::normalize((string) Request::post('locale', (string) Request::get('locale', '')));
        if (!Locale::isValid($locale)) {
            $locale = Locale::defaultLocale();
        }
        Locale::setCurrent($locale);
        Response::back(Url::action('Index'));
    }
}
