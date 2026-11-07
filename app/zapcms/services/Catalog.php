<?php
/*
 * Copyright (c) 2023.  ZAP.CN  - ZAP CMS - All Rights Reserved
 * @author Allen
 * @email zapcms@zap.cn
 * @date 2023/12/27 上午11:26
 *
 */

namespace zapcms\services;

use zapcms\models\Node;
use zap\DB;
use zap\traits\SingletonTrait;

/**
 * 栏目服务 —— 仅用于后台「内容分类」
 *
 * 职责：栏目（内容分类）的增删改查、层级树、以及内容归属分类的路径。
 *
 * 前台展示不再经过本类：
 *  - 顶部/页脚/侧边栏等所有菜单树，统一由 MenuItem::getPositionMenu() 从 menu_items 生成；
 *  - catalog.show_position 只是迁移到 menu_items 时的历史种子数据，不参与渲染
 *    （见 migration 2026_11_06_120000_menu_items）；
 *  - 面包屑/激活链路使用本类的 getCatalogPathById()，取的是「内容所属分类」路径，
 *    属于分类数据，不是菜单。
 */
class Catalog extends Category
{
    use SingletonTrait;

    public function __construct()
    {
        parent::__construct('catalog');
    }

    /**
     * 新建栏目：node / catalog 双表同 id（内容与分类共用节点 id）
     */
    public function add($data): int
    {
        $node = Node::create([
            'title'=>$data['title'],
            'slug'=>$data['slug'],
            'author_id'=>0,
            'node_type'=>'catalog',
            'mime_type'=>$data['node_type'],
            'status'=>Node::STATUS_PUBLISH,
            'pub_time'=>time(),
            'update_time'=>time(),
            'add_time'=>time(),
        ]);
        if($node->id){
            $data[$this->primaryKey] = $node->id;
        }
        $data['created_at'] = time();
        return parent::add($data);
    }

    public function update($data, $id)
    {
        Node::updateAll([
            'title'=>$data['title'],
            'slug'=>$data['slug'],
            'node_type'=>'catalog',
            'mime_type'=>$data['node_type'],
            'update_time'=>time(),
        ],['id'=>$id]);
        return parent::update($data, $id);
    }

    public function remove($id)
    {
        Node::delete($id);
        DB::table('node_meta')->where('object_id',$id)->delete();
        return parent::remove($id);
    }

    /**
     * 栏目的祖先链（分类路径，面包屑与菜单激活匹配用）
     *
     * 只读分类表，不产生前台菜单；返回扁平数组，顺序为 catalog.path 中的 id 顺序。
     */
    public function getCatalogPathById($catalogId){
        $catalog = $this->get($catalogId);
        if(empty($catalog) || empty($catalog['path'])){
            return [];
        }
        $ids = array_filter(explode(',',$catalog['path']));
        if(empty($ids)){
            return [];
        }
        return $this->getAll([
            [$this->primaryKey,'IN',$ids]
        ]);
    }
}
