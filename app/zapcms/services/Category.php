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

    /** 单行按请求记忆化缓存：同一 id 在一次请求内多处调用只查一次库 */
    protected array $rowCache = [];

    /** 整树按请求记忆化缓存：后台一次请求常多次渲染同一棵分类树 */
    protected array $treeCache = [];

    /** 清空本请求内的行/树缓存（写操作后调用） */
    protected function flushCache(): void
    {
        $this->rowCache  = [];
        $this->treeCache = [];
    }

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
        $this->flushCache();

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

        // 父类变化时，级联更新整棵子树的 path 和 level。
        // 一次 SQL 完成（旧实现逐行 UPDATE，上千子孙时会产生上千次写操作）。
        if ($pidChanged && isset($data[$this->pathColumn]) && isset($data[$this->levelColumn])) {
            $levelOffset = (int) $data[$this->levelColumn] - (int) $category[$this->levelColumn];
            $oldPrefix   = (string) $category[$this->pathColumn];
            $newPrefix   = (string) $data[$this->pathColumn];

            if (!$this->cascadePath($oldPrefix, $newPrefix, $levelOffset)) {
                // 驱动不支持下面的字符串函数时回退逐行更新，保证功能不丢
                $rows = DB::table($this->table)
                    ->where($this->pathColumn, 'LIKE', "{$oldPrefix}%")
                    ->get(FETCH_ASSOC) ?: [];
                foreach ($rows as $row) {
                    $path = str_replace($oldPrefix, $newPrefix, (string) $row[$this->pathColumn]);
                    DB::table($this->table)->where($this->primaryKey, $row[$this->primaryKey])
                        ->set($this->pathColumn, $path)
                        ->set($this->levelColumn, (int) $row[$this->levelColumn] + $levelOffset)
                        ->update();
                }
            }
        }

        DB::update($this->table,$data,[$this->primaryKey=>$id]);
        $this->flushCache();
    }

    /**
     * 单条 SQL 批量重写子树路径：newPrefix + 原 path 去掉 oldPrefix 后的剩余部分
     *
     * @return bool 是否成功执行（false 时调用方回退逐行更新）
     */
    protected function cascadePath(string $oldPrefix, string $newPrefix, int $levelOffset): bool
    {
        if ($oldPrefix === '') {
            return false;
        }
        try {
            $conn  = DB::connection();
            $table = $conn->quoteTable($this->table);
            $p     = $this->pathColumn;
            $l     = $this->levelColumn;
            $like  = $oldPrefix . '%';

            if ($conn->driver === 'sqlite') {
                $sql = "UPDATE {$table} SET {$p} = ? || SUBSTR({$p}, LENGTH(?) + 1), {$l} = {$l} + ? WHERE {$p} LIKE ?";
            } else {
                $sql = "UPDATE {$table} SET {$p} = CONCAT(?, SUBSTRING({$p}, CHAR_LENGTH(?) + 1)), {$l} = {$l} + ? WHERE {$p} LIKE ?";
            }
            DB::query($sql, [$newPrefix, $oldPrefix, $levelOffset, $like]);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function remove($id){
        $category = DB::table($this->table)->where($this->primaryKey, $id)->fetch(FETCH_ASSOC);
        if(is_null($category)){
            return false;
        }
        $path = $category[$this->pathColumn];
        //删除子类
        DB::table($this->table)->where($this->pathColumn,'LIKE',"{$path}%")->delete();
        $this->flushCache();
        return DB::table($this->table)->where($this->primaryKey,$id)->delete();
    }


    /**
     * 构建整棵分类树（O(n)，一次查询 + PHP 引用建树）
     *
     * 上万分类时请务必传 $options 收敛数据：
     *  - columns：只查需要的列，避免把 catalog.content 这类大字段全部拉进内存；
     *  - limit  ：只取前 N 行（注意会截断树，仅用于「预览/搜索」类场景）；
     * 同一请求内相同参数的结果会缓存，写操作自动失效。
     *
     * @param array $conditions 同 getAll() 的条件写法
     * @param array $options    ['columns' => string[], 'limit' => int]
     */
    public function getTreeArray($conditions = [], array $options = []): array
    {
        $cacheKey = md5(serialize([$conditions, $options]));
        if (array_key_exists($cacheKey, $this->treeCache)) {
            return $this->treeCache[$cacheKey];
        }

        $query = DB::table($this->table)
            ->orderBy("{$this->parentColumn} ASC,sort_order ASC");
        foreach ($conditions as $name=>$value){
            if(is_int($name)){
                $query->where(...$value);
            }else{
                $query->where($name,$value);
            }
        }

        $columns = $options['columns'] ?? [];
        if (is_array($columns) && $columns !== []) {
            // 建树必须拿到主键与父级列
            $columns = array_values(array_unique(array_merge(
                $columns, [$this->primaryKey, $this->parentColumn]
            )));
            $query->select(...$columns);
        }
        if (!empty($options['limit'])) {
            $query->limit(max(1, (int) $options['limit']));
        }

        $data = $query->get(FETCH_ASSOC) ?: [];
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
        unset($item);
        return $this->treeCache[$cacheKey] = $tree;
    }

    public function forEachAll($callback, array $options = [])
    {
        $categories = $this->getTreeArray([], $options);
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

        // 计算 has_children（一次查询，避免逐节点计数）；只取父级列，减少大表数据传输
        $ids  = array_column($children, $this->primaryKey);
        $subs = DB::table($this->table)
            ->select($this->parentColumn)
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
        $id = intval($id);
        if (array_key_exists($id, $this->rowCache)) {
            return $this->rowCache[$id];
        }
        return $this->rowCache[$id] = DB::table($this->table)->where($this->primaryKey, $id)->fetch(FETCH_ASSOC);
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

    /**
     * 分类搜索（后台选择控件 / AJAX 用）
     *
     * 过滤条件下推到 SQL 并强制 LIMIT，避免上万分类时整表拉取再在 PHP 里过滤。
     *
     * @param string $keyword      标题关键词
     * @param int    $limit        最多返回条数（硬上限 200）
     * @param array  $conditions   额外条件，同 getAll()
     * @param string $excludePath  排除该路径前缀（用于「不能选自己及子孙」的父级选择）
     * @param array  $extraColumns 调用方需要的额外列（如 slug / node_type）
     */
    public function search(
        string $keyword = '',
        int $limit = 30,
        array $conditions = [],
        string $excludePath = '',
        array $extraColumns = []
    ): array {
        $limit   = max(1, min(200, $limit));
        $columns = array_values(array_unique(array_merge(
            [$this->primaryKey, 'title', $this->pathColumn, $this->levelColumn, $this->parentColumn],
            $extraColumns
        )));
        $query = DB::table($this->table)->select(...$columns);
        if ($keyword !== '') {
            $query->where('title', 'LIKE', "%{$keyword}%");
        }
        foreach ($conditions as $name => $value) {
            if (is_int($name)) {
                $query->where(...$value);
            } else {
                $query->where($name, $value);
            }
        }
        if ($excludePath !== '') {
            $query->where($this->pathColumn, 'NOT LIKE', $excludePath . '%');
        }
        return $query->orderBy("{$this->pathColumn} ASC")->limit($limit)->get(FETCH_ASSOC) ?: [];
    }

    /**
     * 父级下拉的初始选项：顶层分类 + 当前分类的祖先链，避免渲染整棵树
     *
     * @return array<int, array{id:int,title:string,level:int,path:string}>
     */
    public function getSelectOptions(int $selfId = 0, string $selfPath = '', int $limit = 100): array
    {
        $rows = $this->search('', $limit, [], $selfPath);
        $byId = [];
        foreach ($rows as $row) {
            $byId[(int) $row[$this->primaryKey]] = $row;
        }

        // 祖先链一定可见，保证当前父级始终出现在下拉里
        $ids = array_values(array_filter(array_map('intval', explode(',', trim($selfPath, ',')))));
        if ($ids !== []) {
            $ancestors = $this->getAll([[$this->primaryKey, 'IN', $ids]]) ?: [];
            foreach ($ancestors as $row) {
                if ($selfId > 0 && (int) $row[$this->primaryKey] === $selfId) {
                    continue;
                }
                $byId[(int) $row[$this->primaryKey]] = $row;
            }
        }
        if ($selfId > 0) {
            unset($byId[$selfId]);
        }

        $options = [];
        foreach ($byId as $id => $row) {
            $options[] = [
                'id'    => $id,
                'title' => (string) ($row['title'] ?? ''),
                'level' => (int) ($row[$this->levelColumn] ?? 1),
                'path'  => (string) ($row[$this->pathColumn] ?? ''),
            ];
        }
        usort($options, fn ($a, $b) => strcmp($a['path'], $b['path']));
        return $options;
    }

    public function setDefaultLevel($level){
        $this->defaultLevel = $level;
    }

}