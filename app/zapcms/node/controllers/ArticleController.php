<?php

namespace zapcms\node\controllers;


use zapcms\node\AbstractNodeType;

class ArticleController extends AbstractNodeType
{

    public function __init()
    {
        $this->title = '文章';
    }


}
