<?php
/*
 * Copyright (c) 2023.  ZAP.CN  - ZAP CMS
 */

namespace app\controllers;

use zapcms\services\BreadCrumb;
use zapcms\models\Node;
use zapcms\models\NodeRelation;
use zap\http\Controller;

class NodeController extends Controller
{
    use RenderTakeover;

    public function __construct()
    {
        BreadCrumb::instance()->add('首页',base_url('/'));
    }

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
        pageState()->catalogPaths = $this->getCatalogPathByNodeId(pageState()->nodeId);
        $slugs = [];
        foreach (pageState()->catalogPaths as $catalog){
            $slugs[] = $catalog['slug'];
            BreadCrumb::instance()->add($catalog['title'],smart_node_url(['node_type' => 'category', 'slug' => $catalog['slug'] ?? '', 'id' => $catalog['id'] ?? 0]));
        }
        BreadCrumb::instance()->add(pageState()->node['title'],smart_node_url(pageState()->node),true);

        //侧边栏菜单：使用后台「外观 > 菜单」的 MenuItem（left 位置），只显示当前栏目所在分支
        pageState()->subCatalogList = pageState()->getSidebarMenu('left');
        $this->renderNode($node, pageState()->nodeMimeType ?? ($node['node_type'] ?? 'page'));
    }

    function page(){
        $node = pageState()->node;
        BreadCrumb::instance()->add($node['title'],smart_node_url($node),true);
        $this->renderNode($node, 'page');
    }

    function article(){
        $node = pageState()->node;
        //获取 url path
        pageState()->catalogPaths = $this->getCatalogPathByNodeId(pageState()->nodeId);
        $slugs = [];
        foreach (pageState()->catalogPaths as $catalog){
            $slugs[] = $catalog['slug'];
            BreadCrumb::instance()->add($catalog['title'],smart_node_url(['node_type' => 'category', 'slug' => $catalog['slug'] ?? '', 'id' => $catalog['id'] ?? 0]));
        }
        BreadCrumb::instance()->add(pageState()->node['title'],smart_node_url(pageState()->node),true);

        //侧边栏菜单：使用后台「外观 > 菜单」的 MenuItem（left 位置），只显示当前栏目所在分支
        pageState()->subCatalogList = pageState()->getSidebarMenu('left');
        $this->renderNode($node, 'article');
    }

    function product(){
        $node = pageState()->node;
        //获取 url path
        pageState()->catalogPaths = $this->getCatalogPathByNodeId(pageState()->nodeId);
        $slugs = [];
        foreach (pageState()->catalogPaths as $catalog){
            $slugs[] = $catalog['slug'];
            BreadCrumb::instance()->add($catalog['title'],smart_node_url(['node_type' => 'category', 'slug' => $catalog['slug'] ?? '', 'id' => $catalog['id'] ?? 0]));
        }
        BreadCrumb::instance()->add(pageState()->node['title'],smart_node_url(pageState()->node),true);

        //侧边栏菜单：使用后台「外观 > 菜单」的 MenuItem（left 位置），只显示当前栏目所在分支
        pageState()->subCatalogList = pageState()->getSidebarMenu('left');
        // 加载自定义字段（node_meta），如产品价格
        $nodeModel = Node::findById($node['id'] ?? 0);
        $node['meta'] = $nodeModel ? $nodeModel->loadMeta() : [];

        // 整页接管交由 RenderTakeover（node_render / node_render_product / node_product_render）
        $this->renderNode($node, 'product');
    }

    function faq(){
        $node = pageState()->node;
        pageState()->setPageTitle($node['title']);
        pageState()->catalogPaths = $this->getCatalogPathByNodeId(pageState()->nodeId);
        $slugs = [];
        foreach (pageState()->catalogPaths as $catalog){
            $slugs[] = $catalog['slug'];
            BreadCrumb::instance()->add($catalog['title'],smart_node_url(['node_type' => 'category', 'slug' => $catalog['slug'] ?? '', 'id' => $catalog['id'] ?? 0]));
        }
        
        BreadCrumb::instance()->add(pageState()->node['title'],smart_node_url(pageState()->node),true);
        pageState()->subCatalogList = pageState()->getSidebarMenu('left');

        $this->renderNode($node, 'faq');
    }

    private function getCatalogPathByNodeId(int $node_id){
        $rows = NodeRelation::where('node_id',$node_id)->orderBy('level ASC')
            ->leftJoin(['node','n'],'node_relation.catalog_id=n.id')
            ->select('n.title,n.id,n.slug')
            ->get(FETCH_ASSOC);

        return \zapcms\services\Translator::applyBatch((array) $rows, null, \zapcms\services\Translator::TYPE_NODE, ['title', 'slug']);
    }
}