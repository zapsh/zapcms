<?php

use zap\DB;
use zap\facades\Cache;
use zapcms\database\Migration;
use zapcms\services\AdminMenu;

/**
 * permissions 注册表与后台菜单镜像同步
 *
 * 历史问题：permissions 表只登记了 2 个键（user / admin_menu_18），
 * 而 roles_permissions 里有 56 个 admin_menu_{id} 授权键在注册表中悬空，
 * 导致角色编辑页权限区显示不全、超管拿不到全部权限键。
 *
 * 本迁移：
 *  1. 把全部 admin_menu_{id} upsert 进 permissions（标题/层级/排序镜像菜单树）；
 *  2. 清理菜单已删除但键残留的悬空行（roles_permissions 一并清）；
 *  3. 递增 rbac_version，让角色/权限缓存全部失效。
 *
 * 幂等：重复执行无副作用（upsert + 删除式清理）。
 */
return new class extends Migration {

    public function up(): void
    {
        AdminMenu::syncAllMenuPermissions();

        // 角色过滤逻辑已变化（禁用角色不再参与鉴权），缓存全部失效
        Cache::increment('rbac_version', 1);
    }

    public function down(): void
    {
        // 回滚：移除镜像生成的菜单权限键（保留 'user' 等手工定义的键）
        DB::table('permissions')->where('perm_key', 'LIKE', 'admin_menu_%')->delete();
        Cache::increment('rbac_version', 1);
    }
};
