<?php
/*
 * Copyright (c) 2023.  ZAP.CN  - ZAP CMS - All Rights Reserved
 */

namespace zapcms\support;

use zap\DB;
use zapcms\services\AdminMenu;
use zapcms\services\Option;

/**
 * 模块后台菜单
 *
 * 把 mod.json 里的 admin_menu 定义同步到 admin_menu 表，模块无需手写菜单。
 *
 * mod.json 示例：
 *  "admin_menu": [
 *      {
 *          "title": "商城",
 *          "icon": "fa-solid fa-store",
 *          "children": [
 *              {"title": "产品管理", "url": "product", "icon": "fa-cubes"},
 *              {"title": "订单管理", "url": "mod/shop/order", "icon": "fa-receipt"},
 *              {"title": "商城设置", "url": "settings", "icon": "fa-cog"}
 *          ]
 *      }
 *  ]
 *
 * url 写法（不要写死后台前缀，后台路径可以被用户改）：
 *   "product"                  → mod/{module}/product
 *   "mod/shop/product"         → 原样使用（推荐）
 *   "/z-admin/mod/shop/list"   → 自动去掉后台前缀，兼容旧写法
 *   "https://example.com"      → 外链保持原样
 * 最终跳转地址在渲染时由 Url::action() 拼接当前后台前缀。
 */
class ModMenu
{
    /** 记录各模块写入的菜单 id，卸载/重新同步时精确清理 */
    public const IDS_OPTION = 'mod.menus.ids.';

    /** 记录 mod.json 菜单定义的指纹，避免每次请求都写库 */
    public const HASH_OPTION = 'mod.menus.hash.';

    /**
     * 读取模块声明的后台菜单
     *
     * 与系统其它模块信息读取器保持一致：优先 mod.json，回退 plugin.json，
     * 因此使用 plugin.json 作为清单的模块（如打包分发的 zapcms 插件）也能注册菜单。
     *
     * 兼容两种 admin_menu 写法：
     *   1) 数组：[{ "title":.., "icon":.., "children":[..] }]      （mod.json 规范格式）
     *   2) 对象：{  "title":.., "icon":.., "items":[..] }          （plugin.json 常见写法）
     * 子项键名同时兼容 children / items。
     */
    public static function definitions(string $module): array
    {
        $json = null;
        foreach (['mod.json', 'plugin.json'] as $file) {
            $path = APP_ROOT . '/mods/' . $module . '/' . $file;
            if (!is_file($path)) {
                continue;
            }
            $decoded = json_decode((string)@file_get_contents($path), true);
            if (is_array($decoded) && !empty($decoded['admin_menu'])) {
                $json = $decoded;
                break;
            }
        }
        if (!is_array($json)) {
            return [];
        }

        $menuDef = $json['admin_menu'];
        // 单对象写法（自身带 title/items/name 等字符串键）→ 归一化为「组数组」
        if (is_array($menuDef)) {
            $isObjectForm = isset($menuDef['title']) || isset($menuDef['items']) || isset($menuDef['name']);
            $groups       = $isObjectForm ? [$menuDef] : $menuDef;
        } else {
            return [];
        }

        $out = [];
        foreach ($groups as $group) {
            if (!is_array($group)) {
                continue;
            }
            $children = $group['children'] ?? $group['items'] ?? [];
            if (!is_array($children)) {
                $children = [];
            }
            $out[] = [
                'title'    => $group['title'] ?? $group['name'] ?? '',
                'icon'     => $group['icon'] ?? '',
                'children' => $children,
            ];
        }
        return $out;
    }

    /**
     * 规范化菜单地址：统一成 mod/{module}/... 的相对路由
     */
    public static function route(string $module, string $route): string
    {
        $route = trim(str_replace('\\', '/', $route));
        if ($route === '' || $route === '#') {
            return '';
        }
        // 外链、锚点保持原样
        if (preg_match('#^(https?:)?//#i', $route) || $route[0] === '#') {
            return $route;
        }

        $route = ltrim($route, '/');

        // 去掉写死的后台前缀（当前配置的 + 历史默认的 z-admin）
        $prefixes = array_unique(array_filter([
            trim((string)config('admin.prefix', ''), '/'),
            defined('Z_ADMIN_PREFIX') ? trim((string)Z_ADMIN_PREFIX, '/') : '',
            'z-admin',
        ]));
        foreach ($prefixes as $prefix) {
            if ($route === $prefix) {
                $route = '';
                break;
            }
            if (str_starts_with($route, $prefix . '/')) {
                $route = ltrim(substr($route, strlen($prefix)), '/');
                break;
            }
        }

        if ($route === '') {
            return '';
        }
        // 模块内相对路由补全为 mod/{module}/xxx
        if (!str_starts_with($route, 'mod/')) {
            $route = 'mod/' . $module . '/' . $route;
        }
        return $route;
    }

    /**
     * 同步模块菜单到 admin_menu 表
     *
     * @param string $module 模块名
     * @param array  $info   模块信息（可省略，省略时读 options 中的安装记录）
     * @param bool   $force  忽略指纹强制同步
     * @return bool 是否真正执行了写库
     */
    public static function sync(string $module, array $info = [], bool $force = false): bool
    {
        $menus = self::definitions($module);
        if (empty($menus)) {
            return false;
        }

        if (empty($info)) {
            $json = Option::get('mod.installed.' . $module);
            $info = $json ? (json_decode((string)$json, true) ?: []) : [];
        }

        $hash    = md5(json_encode($menus, JSON_UNESCAPED_UNICODE) . '|' . ($info['version'] ?? ''));
        $hashKey = self::HASH_OPTION . $module;
        if (!$force && (string)Option::get($hashKey) === $hash) {
            return false;
        }

        self::remove($module);

        $menu = AdminMenu::instance();
        $ids  = [];
        $sort = 100;
        $now  = time();

        foreach ($menus as $item) {
            $title = trim((string)($item['title'] ?? $item['name'] ?? ''));
            if ($title === '') {
                continue;
            }

            $url      = self::route($module, (string)($item['url'] ?? $item['route'] ?? $item['link_to'] ?? ''));
            $icon     = self::icon((string)($item['icon'] ?? 'fa-solid fa-circle'));
            $children = isset($item['children']) && is_array($item['children']) ? $item['children'] : [];

            $row = [
                'title'         => $title,
                'pid'           => 0,
                'icon'          => $icon,
                'link_to'       => $url,
                'link_target'   => '_self',
                'link_type'     => 'action',
                'active_rule'   => '',
                'show_position' => '1,2',
                'sort_order'    => (int)($item['sort_order'] ?? $sort++),
                'created_at'    => $now,
                'updated_at'    => $now,
            ];

            $parentId = (int)$menu->add($row);
            if ($parentId <= 0) {
                continue;
            }
            $ids[] = $parentId;

            $childSort = 0;
            foreach ($children as $child) {
                if (!is_array($child)) {
                    continue;
                }
                $childTitle = trim((string)($child['title'] ?? $child['name'] ?? ''));
                if ($childTitle === '') {
                    continue;
                }
                $childId = (int)$menu->add([
                    'title'         => $childTitle,
                    'pid'           => $parentId,
                    'icon'          => self::icon((string)($child['icon'] ?? 'far fa-circle')),
                    'link_to'       => self::route($module, (string)($child['url'] ?? $child['route'] ?? $child['link_to'] ?? '')),
                    'link_target'   => '_self',
                    'link_type'     => 'action',
                    'active_rule'   => '',
                    'show_position' => '1,2',
                    'sort_order'    => (int)($child['sort_order'] ?? $childSort),
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ]);
                if ($childId > 0) {
                    $ids[] = $childId;
                }
                $childSort++;
            }
        }

        Option::replace(self::IDS_OPTION . $module, json_encode($ids), 0, 1);
        Option::replace($hashKey, $hash, 0, 1);

        return true;
    }

    /**
     * 清理模块写入的后台菜单
     */
    public static function remove(string $module): bool
    {
        $menu = AdminMenu::instance();

        $ids = json_decode((string)Option::get(self::IDS_OPTION . $module), true);
        if (is_array($ids)) {
            foreach ($ids as $id) {
                if ((int)$id > 0) {
                    $menu->remove((int)$id);
                }
            }
        }

        // 兜底：按约定的相对路由清理（含历史数据里写成 /z-admin/mod/xxx 的情况）
        foreach (['mod/' . $module . '%', '%/mod/' . $module . '%'] as $like) {
            $rows = DB::table('admin_menu')->where('link_to', 'LIKE', $like)->get(FETCH_ASSOC);
            foreach ($rows as $row) {
                $menu->remove((int)$row['id']);
            }
        }

        Option::remove(self::IDS_OPTION . $module);
        Option::remove(self::HASH_OPTION . $module);

        return true;
    }

    /**
     * 图标补全：没有字体前缀时补 fa
     */
    protected static function icon(string $icon): string
    {
        $icon = trim($icon);
        if ($icon === '') {
            return 'fa-solid fa-circle';
        }
        if (!preg_match('/^(fa|fas|far|fab|fa-solid|fa-regular|fa-brands)\b/', $icon)) {
            $icon = 'fa ' . $icon;
        }
        return $icon;
    }
}
