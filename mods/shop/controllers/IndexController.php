<?php
/**
 * 模块首页控制器
 *
 * 路由示例:
 *   /z-admin/mod/shop        → 后台仪表盘
 *   /mod/shop                → 前端首页
 */

namespace mods\shop\controllers;

use mods\shop\models\Product;
use zap\view\View;

class IndexController
{
    /**
     * 后台仪表盘
     */
    public function index(): void
    {
        $productCount = Product::createQuery()->count();
        $onlineCount  = Product::createQuery()->where('status', Product::STATUS_ON)->count();

        View::render('index.admin', [
            'productCount' => $productCount,
            'onlineCount'  => $onlineCount,
        ]);
    }

    /**
     * 前端首页
     */
    public function home(): void
    {
        $data = Product::getList(1, 8);

        View::render('index.home', $data);
    }
}
