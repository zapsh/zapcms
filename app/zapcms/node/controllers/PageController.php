<?php

namespace zapcms\node\controllers;

use zapcms\models\Node;
use zap\db\Query;
use zap\helpers\Pagination;
use zapcms\node\AbstractNodeType;
use zap\view\View;

class PageController extends AbstractNodeType
{
    public function index()
    {
        if($this->catalogId==0){
//            $this->redirectTo('Node');
            $this->title = '单页';
            $page = new Pagination((int)req()->get('page', 1), 20, req()->get());
            $page->setTotal($this->getPageTotal());
            View::share('page', $page);
            $this->display([
                'page'=> $page,
                'title'=> $this->getTitle('%s管理'),
                'data'=>$this->getPages($page)
            ]);
            return;
        }
        View::share('catalog',$this->getCatalogById($this->catalogId));
        $node = $this->getNodeByCatalogId($this->catalogId);
        if($node){
            $this->title = $node['title'];
            parent::edit($node['id']);
        }else{
            // 单页栏目（迁移后 catalog 并入 node，正文存在分类节点自身，无挂靠内容节点）：
            // 直接编辑分类节点本身，否则会误入「新增」表单
            $catalogNode = Node::findById($this->catalogId);
            if ($catalogNode
                && $catalogNode->node_type === 'category'
                && (string) ($catalogNode->mime_type ?? '') === 'page') {
                $this->title = $catalogNode->title;
                parent::edit($this->catalogId);
            } else {
                parent::add();
            }
        }
    }

    public function getPageTotal(){
        return Node::where(function ($q) {
            $q->where('node_type', 'page')
              ->orWhere(function ($q2) {
                  $q2->where('node_type', 'category')->where('mime_type', 'page');
              });
        })->count('id');
    }

    public function getPages(?Pagination $page = null){
        $query = Node::where(function ($q) {
            $q->where('node_type', 'page')
              ->orWhere(function ($q2) {
                  $q2->where('node_type', 'category')->where('mime_type', 'page');
              });
        });
        if($page){
            $query->limit($page->getLimit())->offset($page->getOffset());
        }
        return $query->get(FETCH_ASSOC);
    }


}