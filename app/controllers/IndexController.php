<?php
/*
 * Copyright (c) 2023.  ZAP.CN  - ZAP CMS
 */

namespace app\controllers;

use zap\http\Controller;

class IndexController extends Controller
{
    use RenderTakeover;

    function index(){
        pageState()->isHome = true;

        // 模块接管首页（约定式 home_render）；命中即接管并结束渲染
        if ($this->renderHome()) {
            return;
        }

        view('index');
    }
}