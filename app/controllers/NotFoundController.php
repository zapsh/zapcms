<?php

namespace app\controllers;

use zap\http\Controller;

class NotFoundController extends Controller
{
    use RenderTakeover;

    public function index()
    {
        http_response_code(404);

        // 模块接管 404 页面（约定式 not_found_render）；命中即接管并结束渲染
        // （保留 404 状态码，由上方 http_response_code 设置）
        if ($this->render404()) {
            return;
        }

        view('404');
    }
}