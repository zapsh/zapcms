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
            parent::add();
        }
    }

    public function getPageTotal(){
        $query = Node::where('node_type','page');
        $query->orWhere(function(Query $query){
            $query->where('node_type','catalog')->where('mime_type','page');
        });
        return $query->count('id');
    }

    public function getPages(?Pagination $page = null){
        $query = Node::where('node_type','page');
        $query->orWhere(function(Query $query){
            $query->where('node_type','catalog')->where('mime_type','page');
        });
        if($page){
            $query->limit($page->getLimit())->offset($page->getOffset());
        }
        return $query->get(FETCH_ASSOC);
    }


}