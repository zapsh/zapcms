<?php
/*
 * Copyright (c) 2023.  ZAP.CN  - ZAP CMS
 */

namespace app\controllers;

use zapcms\models\Node;
use zap\http\Controller;

class NodeController extends Controller
{
    use RenderTakeover;

    public function _invoke(string $method,$params = [])
    {
        if(method_exists($this,$method)){
            $this->$method();
        }else{
            // 未知模型类型：先交给 mod 接管（node_render_{method} / node_{method}_render），
            // 无接管则回退到通用 node 模板。
            $this->renderNode(pageState()->node ?: [], $method);
        }
    }

    function index(){
        $node = pageState()->node ?: [];
        $this->renderNode($node, pageState()->nodeMimeType ?? ($node['node_type'] ?? 'page'));
    }

    function page(){
        $node = pageState()->node;
        $this->renderNode($node, 'page');
    }

    function article(){
        $node = pageState()->node;
        // 加载自定义字段（node_meta），如新闻来源 / 图片版权
        $nodeModel = Node::findById($node['id'] ?? 0);
        $node['meta'] = $nodeModel ? $nodeModel->loadMeta() : [];
        $this->renderNode($node, 'article');
    }

    function product(){
        $node = pageState()->node;
        // 加载自定义字段（node_meta），如产品价格
        $nodeModel = Node::findById($node['id'] ?? 0);
        $node['meta'] = $nodeModel ? $nodeModel->loadMeta() : [];

        // 整页接管交由 RenderTakeover（node_render / node_render_product / node_product_render）
        $this->renderNode($node, 'product');
    }

    function faq(){
        $node = pageState()->node;
        pageState()->setPageTitle($node['title']);
        $this->renderNode($node, 'faq');
    }
}