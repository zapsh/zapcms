<?php
/*
 * Copyright (c) 2023.  ZAP.CN  - ZAP CMS - All Rights Reserved
 * @author Allen
 * @email zapcms@zap.cn
 * @date 2023/12/27 上午11:29
 * @lastModified 2023/12/27 上午11:02
 *
 */

namespace zapcms\controllers;

use zapcms\auth\RBAC;
use zap\http\Controller;
use zap\view\View;
use zapcms\models\AdminLog;
use zapcms\services\Auth;
use zapcms\services\BreadCrumb;

class AdminController extends Controller
{
    /**
     * @var string Perm Key
     */
    protected string $permKey = '';

    public function __construct()
    {
        Auth::check();
        View::share('zapAdmin',Auth::user());
        //初始化RBAC
        app()->make(RBAC::class,[],'rbac');
        app()->breadcrumb = BreadCrumb::instance();
        BreadCrumb::instance()->add(__t('admin.dashboard','控制台'),url_action('Index'));
        //website options

    }

    /**
     * 子权限检查（view/add/edit/delete 粒度）
     *
     * 语义（RBAC::check）：
     *  - 超管全通过；permissions.extras 未定义 → 全通过（未启用子权限前不影响现网）
     *  - 定义了 extras 且角色勾选部分 → 仅勾选项通过；一个没勾 → 全拒绝
     *
     * 无权限时：审计告警（SECURITY）并终止——AJAX 返回 403 JSON，页面返回 403 HTML
     */
    protected function requirePerm(string $extra, string $extraTitle = ''): void
    {
        if ($this->permKey === '' || app()->rbac->check($this->permKey, $extra)) {
            return;
        }
        AdminLog::warning(
            AdminLog::CATEGORY_SECURITY,
            '越权操作被拦截',
            sprintf(
                '用户尝试%s「%s」（缺少子权限 %s）',
                $extraTitle !== '' ? $extraTitle : '操作',
                static::class,
                $extra
            )
        );
        RBAC::denyResponse();
    }

}