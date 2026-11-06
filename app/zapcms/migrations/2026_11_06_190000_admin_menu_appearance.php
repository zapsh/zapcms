<?php

use zap\DB;
use zapcms\database\Migration;

/**
 * 后台信息架构调整：
 *  1. 新增「外观」顶级菜单，将「主题」移入其下；
 *  2. 「外观」下新增「菜单」入口（MenuLocation，含菜单位置 + 构建器抽屉）；
 *  3. 移除顶级「栏目」菜单入口（栏目树改由菜单组织；Catalog 控制器保留可访问）；
 *  4. 同步角色权限：原「主题/栏目」可见的角色自动获得「外观/菜单」。
 */
return new class extends Migration {

    public function up(): void
    {
        $now = time();

        // 1. 外观 父菜单（幂等：按标题查；path 在插入后按实际 id 回填，避免硬编码 id 冲突）
        $appearance = DB::table('admin_menu')->where('title', '外观')->where('pid', 0)->first();
        if (empty($appearance)) {
            DB::table('admin_menu')->insert([
                'title'         => '外观',
                'pid'           => 0,
                'path'          => '',
                'level'         => 1,
                'icon'          => 'fa-solid fa-palette',
                'link_to'       => '',
                'link_target'   => '_self',
                'link_type'     => 'action',
                'active_rule'   => '(theme[/.]?.*|menu-location[/.]?.*|menu_location[/.]?.*)',
                'show_position' => '1',
                'sort_order'    => 3,
                'updated_at'    => $now,
                'created_at'    => $now,
            ]);
            $lastRow = DB::table('admin_menu')->orderBy('id', 'DESC')->first();
            $appearanceId = (int) ($lastRow['id'] ?? 0);
            if ($appearanceId > 0) {
                DB::update('admin_menu', ['path' => $appearanceId . ','], ['id' => $appearanceId]);
            }
        } else {
            $appearanceId = (int) $appearance['id'];
        }

        // 2. 主题 挂到 外观 下
        $theme = DB::table('admin_menu')->where('link_to', 'Theme')->first();
        $themeId = 0;
        if (!empty($theme)) {
            $themeId = (int) $theme['id'];
            if ((int) $theme['pid'] !== $appearanceId) {
                DB::update('admin_menu', [
                    'pid'        => $appearanceId,
                    'path'       => $appearanceId . ',' . $themeId . ',',
                    'level'      => 2,
                    'icon'       => 'fa-solid fa-angle-right',
                    'sort_order' => 0,
                    'updated_at' => $now,
                ], ['id' => $themeId]);
            }
        }

        // 3. 菜单 入口（幂等：按 link_to 查；path 同样按实际 id 回填）
        $menu = DB::table('admin_menu')->where('link_to', 'MenuLocation')->first();
        $menuId = 0;
        if (empty($menu)) {
            DB::table('admin_menu')->insert([
                'title'         => '菜单',
                'pid'           => $appearanceId,
                'path'          => '',
                'level'         => 2,
                'icon'          => 'fa-solid fa-bars',
                'link_to'       => 'MenuLocation',
                'link_target'   => '_self',
                'link_type'     => 'action',
                'active_rule'   => '(menu-location[/.]?.*|menu_location[/.]?.*)',
                'show_position' => '1,2',
                'sort_order'    => 1,
                'updated_at'    => $now,
                'created_at'    => $now,
            ]);
            $menuRow = DB::table('admin_menu')->where('link_to', 'MenuLocation')->first();
            $menuId = (int) ($menuRow['id'] ?? 0);
            if ($menuId > 0) {
                DB::update('admin_menu', ['path' => $appearanceId . ',' . $menuId . ','], ['id' => $menuId]);
            }
        } else {
            $menuId = (int) $menu['id'];
        }

        // 4. 移除顶级「栏目」菜单入口（Catalog 控制器保留，仅去掉导航）
        DB::table('admin_menu')->where('link_to', 'Catalog')->where('pid', 0)->delete();

        // 5. 角色权限同步：原「主题 / 栏目」可见的角色 → 获得「外观 / 菜单」
        $oldKeys = ['admin_menu_2', 'admin_menu_7'];
        $roleIds = [];
        foreach ($oldKeys as $key) {
            $rows = DB::table('roles_permissions')->where('perm_key', $key)->get(FETCH_ASSOC) ?: [];
            foreach ($rows as $r) {
                $roleIds[(int) $r['role_id']] = true;
            }
        }
        if ($appearanceId > 0 && $menuId > 0) {
            foreach (array_keys($roleIds) as $roleId) {
                foreach ([$appearanceId, $menuId] as $mid) {
                    $exists = DB::table('roles_permissions')
                        ->where('role_id', $roleId)
                        ->where('perm_key', 'admin_menu_' . $mid)
                        ->exists();
                    if (!$exists) {
                        DB::table('roles_permissions')->insert([
                            'role_id'          => $roleId,
                            'perm_key'         => 'admin_menu_' . $mid,
                            'extras'           => null,
                            'assignment_time'  => $now,
                        ]);
                    }
                }
            }
        }
        // 清理已删除菜单的权限残留
        DB::table('roles_permissions')->where('perm_key', 'admin_menu_2')->delete();
    }

    public function down(): void
    {
        $now = time();

        // 主题 移回顶级
        DB::update('admin_menu', [
            'pid'        => 0,
            'path'       => '7,',
            'level'      => 1,
            'icon'       => 'fa-solid fa-wand-magic-sparkles',
            'sort_order' => 3,
            'updated_at' => $now,
        ], ['link_to' => 'Theme']);

        // 删除 外观 / 菜单
        DB::table('admin_menu')->where('title', '外观')->where('pid', 0)->delete();
        DB::table('admin_menu')->where('link_to', 'MenuLocation')->delete();
        DB::table('roles_permissions')->where('perm_key', 'admin_menu_22')->delete();

        // 恢复 栏目 入口（若不存在）
        if (!DB::table('admin_menu')->where('link_to', 'Catalog')->exists()) {
            DB::table('admin_menu')->insert([
                'title'         => '栏目',
                'pid'           => 0,
                'path'          => '2,',
                'level'         => 1,
                'icon'          => 'fa fa-square-poll-horizontal',
                'link_to'       => 'Catalog',
                'link_target'   => '_self',
                'link_type'     => 'action',
                'active_rule'   => '(catalog[/.]?.*)',
                'show_position' => '1',
                'sort_order'    => 2,
                'updated_at'    => $now,
                'created_at'    => $now,
            ]);
        }
    }
};
