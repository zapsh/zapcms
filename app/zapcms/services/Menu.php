<?php
/*
 * Copyright (c) 2023.  ZAP.CN  - ZAP CMS - All Rights Reserved
 */

namespace zapcms\services;

use zap\DB;
use zap\traits\SingletonTrait;

/**
 * 菜单实体（WordPress 式）
 *
 * 菜单是独立的导航容器（如「主导航」「页脚链接」「侧边栏分类」），菜单项挂在菜单下
 * （menu_items.menu_id），再由 menu_locations.menu_id 把菜单分配到某个显示位置。
 *
 * @see MenuItem 菜单项
 * @see MenuLocation 菜单位置
 */
class Menu
{
    use SingletonTrait;

    const TABLE = 'menus';

    /**
     * 全部菜单
     */
    public function all(): array
    {
        return DB::table(self::TABLE)->orderBy('id')->get(FETCH_ASSOC) ?: [];
    }

    /**
     * [id => title] 选项映射
     */
    public function options(): array
    {
        $map = [];
        foreach ($this->all() as $row) {
            $map[(int) $row['id']] = $row['title'];
        }
        return $map;
    }

    public function get(int $id): ?array
    {
        $row = DB::table(self::TABLE)->where('id', $id)->first();
        return $row ?: null;
    }

    /**
     * 创建菜单，返回新菜单 id
     */
    public function add(string $title): int
    {
        $now = time();
        return (int) DB::insert(self::TABLE, [
            'title'      => $title,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function rename(int $id, string $title): bool
    {
        return DB::update(self::TABLE, [
            'title'      => $title,
            'updated_at' => time(),
        ], ['id' => $id]) !== false;
    }

    /**
     * 删除菜单：其下菜单项一并删除；引用它的位置恢复为「未分配」
     */
    public function remove(int $id): bool
    {
        $menu = $this->get($id);
        if (empty($menu)) {
            return false;
        }
        // 引用该菜单的位置置 0
        DB::update(MenuLocation::TABLE, ['menu_id' => 0], ['menu_id' => $id]);
        // 删除其下所有菜单项（逐条走 removeSubtree 防止遗留子级）
        $items = DB::table('menu_items')->where('menu_id', $id)->get(FETCH_ASSOC) ?: [];
        foreach ($items as $it) {
            if ((int) ($it['parent_id'] ?? 0) === 0) {
                MenuItem::instance()->remove((int) $it['id']);
            }
        }
        // 兜底清理可能遗留的子级孤儿项
        DB::table('menu_items')->where('menu_id', $id)->delete();
        MenuItem::instance()->clearMenuCache();
        return true;
    }
}
