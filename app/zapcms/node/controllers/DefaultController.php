<?php

namespace zapcms\node\controllers;


use zapcms\node\AbstractNodeType;

class DefaultController extends AbstractNodeType
{

    protected $nodeType;

    protected $catalogId;

    public function __init()
    {
        $this->title = '内容';
        // 模型/栏目筛选条件统一在 AbstractNodeType::index() 构建，这里不再覆盖条件，
        // 否则 ?type=xxx 会被过滤器抹掉。
    }



}