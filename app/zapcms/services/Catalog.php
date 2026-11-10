<?php
/*
 * Copyright (c) 2023.  ZAP.CN  - ZAP CMS - All Rights Reserved
 * @author Allen
 * @email zapcms@zap.cn
 * @date 2023/12/27 上午11:26
 *
 */

namespace zapcms\services;

use zap\DB;
use zap\traits\SingletonTrait;

/**
 * 分类（taxonomy term）服务 —— 分类项/标签项都是 node
 *
 * 设计（WordPress 式统一分类法）：
 *  - 分类项 = node（node_type='category'），层级靠 parent_id / path / level；
 *  - 标签项 = node（node_type='tag'，parent_id 恒为 0，扁平）；
 *  - 内容与分类/标签的多对多关系由 node_relation(catalog_id, node_id) 承载，
 *    catalog_id 此处语义为「分类项节点 id」；
 *  - 复用 Category 基类的树/路径逻辑（按 parent_id 建树、path 算面包屑）。
 */
class Catalog extends Category
{
    use SingletonTrait;

    public function __construct()
    {
        // 在 node 表上复用 Category 的层级机制（parent_id / path / level 列已加到 node）
        parent::__construct('node', 'id', 'parent_id', 'path', 'level');
    }

    /**
     * 新建分类项：强制 node_type='category'，并记录路径/层级（基类处理）
     */
    public function add($data): int
    {
        // 后台表单沿用 pid 字段名，映射到 node 的 parent_id
        if (isset($data['pid']) && !isset($data['parent_id'])) {
            $data['parent_id'] = (int) $data['pid'];
        }
        // 后台表单的 node_type 是「内容模型」，在 node 上对应 mime_type
        $data['mime_type'] = (string) ($data['mime_type'] ?? $data['node_type'] ?? '');
        $data['node_type'] = 'category';
        $data['author_id']  = (int) ($data['author_id'] ?? 0);
        $data['created_at'] = time();
        $data['status']     = $data['status'] ?? 'publish';
        return parent::add($data);
    }

    /**
     * 编辑分类项
     */
    public function update($data, $id)
    {
        if (array_key_exists('pid', $data) && !array_key_exists('parent_id', $data)) {
            $data['parent_id'] = (int) $data['pid'];
        }
        if (array_key_exists('node_type', $data)) {
            // 表单的 node_type 是「内容模型」→ mime_type；node_type 保持 'category'
            $data['mime_type'] = (string) ($data['mime_type'] ?? $data['node_type']);
            $data['node_type'] = 'category';
        }
        return parent::update($data, $id);
    }

    /**
     * 树/搜索查询统一限定在分类项节点上：
     * 本类底层表是 node（内容与分类同表），不限定会把内容节点也当成分类。
     */
    private function withCategoryCond(array $conditions): array
    {
        array_unshift($conditions, ['node_type', 'category']);
        return $conditions;
    }

    public function getTreeArray($conditions = [], array $options = []): array
    {
        return parent::getTreeArray($this->withCategoryCond((array) $conditions), $options);
    }

    public function getChildren(int $parentId, array $conditions = []): array
    {
        return parent::getChildren($parentId, $this->withCategoryCond($conditions));
    }

    public function search(
        string $keyword = '',
        int $limit = 30,
        array $conditions = [],
        string $excludePath = '',
        array $extraColumns = []
    ): array {
        return parent::search($keyword, $limit, $this->withCategoryCond($conditions), $excludePath, $extraColumns);
    }

    public function remove($id)
    {
        // 收集待删分类及其子树 id（path 前缀匹配），用于级联清理 node_relation，
        // 否则删除分类会留下指向已删分类的孤儿关联行。
        $catalog = $this->get($id);
        $ids     = [$id];
        if ($catalog && ($path = (string) ($catalog['path'] ?? '')) !== '') {
            $children = DB::table($this->table)->where('path', 'LIKE', "{$path}%")->fetchAll();
            foreach ($children as $child) {
                $ids[] = (int) $child['id'];
            }
        }
        DB::table('node_relation')->whereIn('catalog_id', $ids)->delete();

        return parent::remove($id);
    }

    /**
     * 分类的祖先链（分类路径，面包屑与菜单激活匹配用）
     *
     * 复用 node 表的 path 列（逗号分隔祖先分类节点 id）。
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
