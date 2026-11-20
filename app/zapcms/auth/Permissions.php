<?php
/*
 * Copyright (c) 2023.  ZAP.CN  - ZAP CMS - All Rights Reserved
 * @author        Allen
 * @email        zapcms@zap.cn
 * @date        2023/12/27 上午11:01
 * @lastModified        2023/10/25 上午11:20
 *
 */

namespace zapcms\auth;

use zapcms\services\Category;
use zap\traits\SingletonTrait;

class Permissions extends Category
{

    use SingletonTrait;

    public function __construct()
    {
        parent::__construct('permissions', 'perm_id');
    }

    public function add($data): int
    {
        $data['updated_at'] = time();
        $data['created_at'] = time();
        return parent::add($data);
    }

    public function update($data, $id)
    {
        $data['updated_at'] = time();
        return parent::update($data, $id);
    }

    // ================================================================
    //  树形展示辅助
    // ================================================================

    /**
     * 获取扁平化权限树（用于列表展示，含 level 层级标识）
     * @param array $conditions 筛选条件
     * @return array [{perm_id, perm_key, title, level, ...}, ]
     */
    public function getFlatTree(array $conditions = []): array
    {
        $tree = $this->getTreeArray($conditions);
        return self::flattenTree($tree);
    }

    /**
     * 递归展开树为扁平列表，并附带 level 字段
     */
    public static function flattenTree(array $items, int $level = 0, int $parentLevel = 0): array
    {
        $result = [];
        foreach ($items as $item) {
            $row = $item;
            unset($row['children']);
            $row['level'] = $level;
            $row['_level'] = $level - $parentLevel; // 相对于根节点的偏移
            $result[] = $row;

            if (!empty($item['children'])) {
                $children = self::flattenTree($item['children'], $level + 1, $parentLevel);
                $result = array_merge($result, $children);
            }
        }
        return $result;
    }

    /**
     * 根据 perm_key 获取单条权限
     */
    public function getByKey(string $permKey): ?array
    {
        $result = $this->getAll(['perm_key' => $permKey]);
        return $result[0] ?? null;
    }

    /**
     * 获取所有叶子节点（无子权限的权限）
     */
    public function getLeafNodes(): array
    {
        $flat = $this->getFlatTree();
        $parents = array_column($flat, 'pid');
        $leafNodes = [];
        foreach ($flat as $item) {
            if (!in_array($item['perm_id'], $parents)) {
                $leafNodes[] = $item;
            }
        }
        return $leafNodes;
    }
}
