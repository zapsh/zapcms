<?php
/*
 * Copyright (c) 2023.  ZAP.CN  - ZAP CMS - All Rights Reserved
 * @author Allen
 * @email zapcms@zap.cn
 */

namespace zapcms\services;

use zap\DB;
use zap\traits\SingletonTrait;

/**
 * 菜单位置（Menu Location）
 *
 * 替代原先写死的「顶部/左侧/右侧/底部」四个枚举，改为可管理的位置字典：
 *   - 系统内置 4 个默认位置（top / left / right / footer），不可删除；
 *   - 管理员可自定义任意位置（如 about-left、footer-links），用于特定页面/区域。
 *
 * 模板通过 catalog_menu('about-left') 在任意区域输出该位置分配的菜单；
 * 前台菜单只读取 menu_items，栏目表的 show_position 不再参与渲染。
 */
class MenuLocation
{
    use SingletonTrait;

    const TABLE = 'menu_locations';

    protected static array $DEFAULTS = [
        ['key' => 'top',    'title' => '顶部导航', 'description' => '站点顶部主导航', 'sort_order' => 1],
        ['key' => 'left',   'title' => '左侧导航', 'description' => '页面左侧栏导航', 'sort_order' => 2],
        ['key' => 'right',  'title' => '右侧导航', 'description' => '页面右侧栏导航', 'sort_order' => 3],
        ['key' => 'footer', 'title' => '底部导航', 'description' => '站点底部导航',   'sort_order' => 4],
    ];

    /**
     * 全部位置（按 sort_order、id 排序）
     */
    public function all(): array
    {
        return DB::table(self::TABLE)->orderBy('sort_order ASC, id ASC')->get(FETCH_ASSOC) ?: [];
    }

    /**
     * 返回 [key => title] 映射，便于表单与选项渲染
     */
    public function options(): array
    {
        $map = [];
        foreach ($this->all() as $row) {
            $map[$row['key']] = $row['title'];
        }
        return $map;
    }

    public function get(int $id)
    {
        return DB::table(self::TABLE)->where('id', $id)->fetch(FETCH_ASSOC);
    }

    public function getByKey(string $key)
    {
        return DB::table(self::TABLE)->where('key', $key)->fetch(FETCH_ASSOC);
    }

    public function keyExists(string $key): bool
    {
        return DB::table(self::TABLE)->where('key', $key)->exists();
    }

    public function add(array $data)
    {
        $data['updated_at'] = time();
        $data['created_at'] = time();
        return DB::insert(self::TABLE, $data);
    }

    public function update(array $data, int $id)
    {
        $data['updated_at'] = time();
        return DB::update(self::TABLE, $data, ['id' => $id]);
    }

    /**
     * 删除位置（系统内置位置不可删除）
     */
    public function remove(int $id): bool
    {
        $row = $this->get($id);
        if (empty($row) || !empty($row['is_system'])) {
            return false;
        }
        return (bool) DB::table(self::TABLE)->where('id', $id)->delete();
    }

    /**
     * 把菜单分配到位置（一个位置同一时间展示一个菜单）
     */
    public function assignMenu(int $locationId, int $menuId): bool
    {
        $ok = DB::update(self::TABLE, [
            'menu_id'    => $menuId > 0 ? $menuId : 0,
            'updated_at' => time(),
        ], ['id' => $locationId]) !== false;
        MenuItem::instance()->clearMenuCache();
        return $ok;
    }

    /**
     * 写入系统默认位置（已存在则跳过）
     */
    public static function seedDefaults(): void
    {
        $self = new self();
        foreach (self::$DEFAULTS as $d) {
            if (!$self->getByKey($d['key'])) {
                $self->add([
                    'key'         => $d['key'],
                    'title'       => $d['title'],
                    'description' => $d['description'],
                    'is_system'   => 1,
                    'sort_order'  => $d['sort_order'],
                ]);
            }
        }
    }
}
