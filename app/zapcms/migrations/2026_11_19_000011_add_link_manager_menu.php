<?php

use zap\DB;
use zapcms\services\AdminMenu;

/**
 * 新增「链接管理」后台菜单与权限（外观 > 链接管理，基于 options 表，无新数据表）。
 *
 *  - admin_menu.id=62，挂在「外观」(id=23) 下，链接到 Link 控制器（/z-admin/link）；
 *  - permissions.perm_id=62，perm_key='admin_menu_62'（含 view/add/edit/delete extras）；
 *  - 「外观」父菜单(id=23) 的 active_rule 追加 link[/.]?.*，保证高亮。
 *
 * 全新安装已通过 CreateBaseData 种子写入；本迁移用于存量库增量补齐，重复执行安全。
 * 运行：php console zapcms:Migrate
 */
return new class extends \zapcms\database\Migration {
    public function up(): void
    {
        // 1) 菜单行（幂等）
        if (!DB::table('admin_menu')->where('id', 62)->fetch(FETCH_ASSOC)) {
            DB::table('admin_menu')->insert([
                'id'            => 62,
                'title'         => '链接管理',
                'pid'           => 23,
                'path'          => '23,62,',
                'level'         => 2,
                'icon'          => 'fa-solid fa-link',
                'link_to'       => 'Link',
                'link_target'   => '_self',
                'link_type'     => 'action',
                'active_rule'   => '(link[/.]?.*)',
                'show_position' => '1,2',
                'sort_order'    => 3,
                'updated_at'    => time(),
                'created_at'    => time(),
                'i18n_key'      => 'appearance_links',
            ]);
        }

        // 2) 权限行（幂等）
        if (!DB::table('permissions')->where('perm_key', 'admin_menu_62')->fetch(FETCH_ASSOC)) {
            DB::table('permissions')->insert([
                'perm_id'     => 62,
                'title'       => '链接管理',
                'pid'         => 40,
                'path'        => '40,,40',
                'level'       => 2,
                'perm_key'    => 'admin_menu_62',
                'extras'      => '{"delete":"删除","edit":"编辑","add":"新增","view":"查看"}',
                'description' => '后台菜单：链接管理',
                'updated_at'  => time(),
                'created_at'  => time(),
                'sort_order'  => 3,
            ]);
        }

        // 3) 父菜单高亮规则（幂等：仅当尚未包含 link 规则时追加）
        $parent = DB::table('admin_menu')->where('id', 23)->fetch(FETCH_ASSOC);
        if ($parent && !str_contains((string)($parent['active_rule'] ?? ''), 'link')) {
            DB::table('admin_menu')->where('id', 23)->update([
                'active_rule' => '(theme[/.]?.*|menu-location[/.]?.*|menu_location[/.]?.*|widget[/.]?.*|link[/.]?.*)',
            ]);
        }

        // 4) 与 AdminMenu 权限镜像表保持一致
        AdminMenu::syncMenuPermission(62);
    }

    public function down(): void
    {
        AdminMenu::removeMenuPermissions([62]);
        DB::table('admin_menu')->where('id', 62)->delete();
    }
};
