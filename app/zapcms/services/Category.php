<?php
/*
 * Copyright (c) 2023.  ZAP.CN  - ZAP CMS - All Rights Reserved
 * @author Allen
 * @email zapcms@zap.cn
 * @date 2023/12/27 上午11:27
 * @lastModified 2023/10/26 下午2:15
 *
 */

namespace zapcms\services;

use zap\DB;

/**
 * Category
 *
 *
 * CREATE TABLE `zap_category` (
 * `id` int(11) NOT NULL,
 * `name` varchar(255) NOT NULL,
 * `path` varchar(255) DEFAULT NULL,
 * `pid` int(11) NOT NULL,
 * `level` int(11) DEFAULT '0',
 * `sort_order` int(11) DEFAULT '0'
 * ) ENGINE=MyISAM DEFAULT CHARSET=utf8;
 *
 *
 *
 *
 * @table Table Schema
 *
 */
class Category
{
    //表名
    protected string $table;
    //分类Path
    protected string $pathColumn = 'path';
    //父级ID列名
    protected string $parentColumn = 'pid';

    protected string $primaryKey = 'id';
    //分类层级
    protected string $levelColumn = 'level';

    protected int $defaultLevel = 1;

    public function __construct(string $table, string $primaryKey = 'id', string $parentColumn = 'pid', string $pathColumn = 'path', string $levelColumn = 'level')
    {
        $this->table = $table;
        $this->primaryKey = $primaryKey;
        $this->parentColumn = $parentColumn;
        $this->pathColumn = $pathColumn;
        $this->levelColumn = $levelColumn;
    }


    public function add($data)
    {
        $pid = intval($data[$this->parentColumn] ?? 0);
        $data[$this->parentColumn] = $pid;

        if ($pid > 0) {
            $parent = DB::table($this->table)->where($this->primaryKey, $pid)->fetch(FETCH_ASSOC);
            if (empty($parent)) {
                $pid = 0;
                $data[$this->parentColumn] = 0;
                $data[$this->pathColumn] = '';
                $data[$this->levelColumn] = $this->defaultLevel;
            } else {
                $data[$this->pathColumn] = $parent[$this->pathColumn];
                $data[$this->levelColumn] = intval($parent[$this->levelColumn]) + 1;
            }
        } else {
            $data[$this->pathColumn] = '';
            $data[$this->levelColumn] = $this->defaultLevel;
        }

        $category_id = DB::insert($this->table, $data);
        if (!$category_id) {
            $category_id = isset($data['id']) ? intval($data['id']) : 0;
        }

        if ($pid === 0) {
            DB::update($this->table, [$this->pathColumn => "{$category_id},"], [$this->primaryKey => $category_id]);
        } else {
            DB::update($this->table, [
                $this->pathColumn => sprintf("%s%s,", $data[$this->pathColumn], $category_id)
            ], [$this->primaryKey => $category_id]);
        }

        return $category_id;
    }

    public function update($data,$id){
        $category = $this->get($id);
        if(is_null($category)){
            return false;
        }
        // 确保 pid 字段存在
        if(!isset($data[$this->parentColumn])){
            $data[$this->parentColumn] = $category[$this->parentColumn];
        }
        $pidChanged = $data[$this->parentColumn] != $category[$this->parentColumn];

        // 始终根据当前 pid 重新计算自身的 level（修复历史数据错误）
        if($data[$this->parentColumn] == 0){
            $data[$this->pathColumn] = "{$id},";
            $data[$this->levelColumn] = $this->defaultLevel;
        }else{
            $parent = $this->get($data[$this->parentColumn]);
            if($parent){
                $data[$this->pathColumn] = "{$parent[$this->pathColumn]}{$id},";
                $data[$this->levelColumn] = intval($parent[$this->levelColumn]) + 1;
            }
        }

        // 父类变化时，级联更新子孙节点的 path 和 level
        if($pidChanged && isset($data[$this->pathColumn]) && isset($data[$this->levelColumn])){
            $levelOffset = $data[$this->levelColumn] - intval($category[$this->levelColumn]);
            $pathPrefix  = $category[$this->pathColumn];

            $childrenCategories = DB::table($this->table)->where($this->pathColumn,'LIKE',"{$pathPrefix}%")
                ->get(FETCH_ASSOC);
            foreach ($childrenCategories as $row){
                $path = str_replace($pathPrefix,$data[$this->pathColumn],$row[$this->pathColumn]);
                DB::table($this->table)->where($this->primaryKey,$row[$this->primaryKey])
                    ->set($this->pathColumn,$path)
                    ->set($this->levelColumn,intval($row[$this->levelColumn]) + $levelOffset)
                    ->update();
            }
        }

        DB::update($this->table,$data,[$this->primaryKey=>$id]);
    }

    public function remove($id){
        $category = DB::table($this->table)->where($this->primaryKey, $id)->fetch(FETCH_ASSOC);
        if(is_null($category)){
            return false;
        }
        $path = $category[$this->pathColumn];
        //删除子类
        DB::table($this->table)->where($this->pathColumn,'LIKE',"{$path}%")->delete();
        return DB::table($this->table)->where($this->primaryKey,$id)->delete();
    }


    public function getTreeArray($conditions = []): array
    {
        $query = DB::table($this->table)
            ->orderBy("{$this->parentColumn} ASC,sort_order ASC");
        foreach ($conditions as $name=>$value){
            if(is_int($name)){
                $query->where(...$value);
            }else{
                $query->where($name,$value);
            }
        }
        $data = $query->get(FETCH_ASSOC);
        $categories = array();
        foreach ($data as $value) {
            $categories[$value[$this->primaryKey]] = $value;
        }

        $tree = array();
        foreach ($categories as $id => $item) {
            if (isset($categories[$item[$this->parentColumn]])) {
                $categories[$item[$this->parentColumn]]['children'][] = &$categories[$id];
            } else {
                $tree[] = &$categories[$id];
            }
        }
        return $tree;

    }

    public function forEachAll($callback)
    {
        $categories = $this->getTreeArray();
        while($data = array_shift($categories)){
            $callback($data);
            if(!empty($data['children'])){
                while($children = array_pop($data['children'])){
                    array_unshift($categories,$children);
                }
//                foreach ($data['children'] as $child){
//                    array_unshift($categories,$child);
//                }

            }
        }
    }

    /**
     * 取某父级的直接子节点（用于后台栏目树懒加载）。
     *
     * 仅查询 WHERE parentColumn = parentId，不拉取整棵树；
     * 并一次性计算 has_children（避免 N+1）方便前端渲染展开按钮。
     *
     * @param int   $parentId
     * @param array $conditions 形如 ['status'=>1] 或 [[col,op,val]]
     * @return array
     */
    public function getChildren(int $parentId, array $conditions = []): array
    {
        $query = DB::table($this->table)->where($this->parentColumn, $parentId);
        foreach ($conditions as $name => $value) {
            if (is_int($name)) {
                $query->where(...$value);
            } else {
                $query->where($name, $value);
            }
        }
        $children = $query->orderBy('sort_order ASC, id ASC')->get(FETCH_ASSOC) ?: [];
        if (empty($children)) {
            return [];
        }

        // 计算 has_children（一次查询，避免逐节点计数）
        $ids  = array_column($children, $this->primaryKey);
        $subs = DB::table($this->table)
            ->whereIn($this->parentColumn, $ids)
            ->get(FETCH_ASSOC) ?: [];
        $hasMap = [];
        foreach ($subs as $row) {
            $hasMap[$row[$this->parentColumn]] = true;
        }
        foreach ($children as &$child) {
            $child['has_children'] = isset($hasMap[$child[$this->primaryKey]]);
        }
        unset($child);

        return $children;
    }

    public function get($id){
        return DB::table($this->table)->where($this->primaryKey, $id)->fetch(FETCH_ASSOC);
    }

    public function getAll($conditions){
        $query = DB::table($this->table);
        foreach ($conditions as $name=>$value){
            if(is_int($name)){
                $query->where(...$value);
            }else{
                $query->where($name,$value);
            }
        }
        return $query->fetchAll(FETCH_ASSOC);
    }

    public function getAllByPath($conditions = []){
        $query = DB::table($this->table);
        foreach ($conditions['where'] ?? [] as $name=>$value){
            if(is_int($name)){
                $query->where(...$value);
            }else{
                $query->where($name,$value);
            }
        }
        if(isset($conditions['count'])){
            return $query->count($this->primaryKey);
        }
        $query->orderBy('path ASC');
        if(isset($conditions['limit'])){
            $query->limit(...$conditions['limit']);
        }
        return $query->fetchAll(FETCH_ASSOC);
    }

    public function setDefaultLevel($level){
        $this->defaultLevel = $level;
    }

}