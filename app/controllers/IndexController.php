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

        // hook : render_home
        if ($this->renderHome()) {
            return;
        }

        view('index');
    }
}