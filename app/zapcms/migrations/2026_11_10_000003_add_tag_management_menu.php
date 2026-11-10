<?php

use zap\DB;
use zapcms\services\AdminMenu;

/**
 * 新增「标签管理」后台菜单与对应权限（统一分类法标签：node_type='tag'）。
 *
 *  - admin_menu.id=61，挂在「内容管理」(id=1) 下，链接到 Tag 控制器（/z-admin/tag）；
 *  - permissions.perm_id=61，perm_key='admin_menu_61'（含 view/add/edit/delete extras），
 *    使非超管角色可在「角色权限」中按需授权。
 *
 * 全新安装已通过 CreateBaseData 种子写入本行；本迁移仅用于存量库增量补齐，
 * 重复执行安全（已存在则跳过）。运行：php console zapcms:Migrate
 */
return new class extends \zapcms\database\Migration {
    public function up(): void
    {
        // 1) 菜单行（幂等）
        if (!DB::table('admin_menu')->where('id', 61)->fetch(FETCH_ASSOC)) {
            DB::table('admin_menu')->insert([
                'id'            => 61,
                'title'         => '标签管理',
                'pid'           => 1,
                'path'          => '1,61,',
                'level'         => 2,
                'icon'          => 'fa fa-tags',
                'link_to'       => 'Tag',
                'link_target'   => '_self',
                'link_type'     => 'action',
                'active_rule'   => '(tag[/.]?.*)',
                'show_position' => '1',
                'sort_order'    => 4,
                'updated_at'    => time(),
                'created_at'    => time(),
                'i18n_key'      => 'content_tags',
            ]);
        }

        // 2) 权限行（幂等）；extras 声明 view/add/edit/delete 子权限，供 RBAC 细分授权
        if (!DB::table('permissions')->where('perm_key', 'admin_menu_61')->fetch(FETCH_ASSOC)) {
            DB::table('permissions')->insert([
                'perm_id'     => 61,
                'title'       => '标签管理',
                'pid'         => 35,
                'path'        => '35,,35',
                'level'       => 2,
                'perm_key'    => 'admin_menu_61',
                'extras'      => '{"delete":"删除","edit":"编辑","add":"新增","view":"查看"}',
                'description' => '后台菜单：标签管理',
                'updated_at'  => time(),
                'created_at'  => time(),
                'sort_order'  => 4,
            ]);
        }

        // 3) 与 AdminMenu 的权限镜像表保持一致（已存在则仅更新标题/排序，无副作用）
        AdminMenu::syncMenuPermission(61);
    }

    public function down(): void
    {
        // 清理权限键（permissions + roles_permissions 悬空授权）
        AdminMenu::removeMenuPermissions([61]);
        // 清理菜单行
        DB::table('admin_menu')->where('id', 61)->delete();
    }
};
