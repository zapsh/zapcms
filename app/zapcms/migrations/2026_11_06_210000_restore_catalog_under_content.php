<?php

use zap\DB;
use zapcms\database\Migration;

/**
 * 内容信息架构调整（把「栏目」恢复到「内容管理」下）：
 *  1. 将「内容管理」升级为分组，下设「内容」「栏目」两个子入口；
 *  2. 「栏目」入口恢复（此前被外观迁移移除）。栏目是内容的信息架构，不能被删，
 *     其管理页（CatalogController）保留，只恢复导航入口；
 *  3. 同步角色权限：拥有「内容管理」入口权限的角色，自动获得两个子入口权限。
 *
 * 职责边界：
 *   - 内容管理 = 内容 + 栏目（信息架构）
 *   - 外观      = 主题 + 菜单（前台展示）
 */
return new class extends Migration {

    public function up(): void
    {
        $now = time();

        // 1. 定位「内容管理」顶级入口（link_to=Node, pid=0）
        $content = DB::table('admin_menu')->where('link_to', 'Node')->where('pid', 0)->first();
        if (empty($content)) {
            return; // 环境异常，跳过
        }
        $contentId = (int) $content['id'];

        // 2. 子入口「内容」（指向内容列表 Node，承接原「内容管理」的点击行为）
        $contentChild = DB::table('admin_menu')->where('link_to', 'Node')->where('pid', $contentId)->first();
        if (empty($contentChild)) {
            DB::table('admin_menu')->insert([
                'title'         => '内容',
                'pid'           => $contentId,
                'path'          => '',
                'level'         => 2,
                'icon'          => 'fa fa-cube',
                'link_to'       => 'Node',
                'link_target'   => '_self',
                'link_type'     => 'action',
                'active_rule'   => '(node[/.]?.*)',
                'show_position' => '1',
                'sort_order'    => 1,
                'updated_at'    => $now,
                'created_at'    => $now,
            ]);
            $contentChildRow = DB::table('admin_menu')->where('link_to', 'Node')->where('pid', $contentId)->first();
            $contentChildId = (int) ($contentChildRow['id'] ?? 0);
            if ($contentChildId > 0) {
                DB::update('admin_menu', ['path' => $contentId . ',' . $contentChildId . ','], ['id' => $contentChildId]);
            }
        } else {
            $contentChildId = (int) $contentChild['id'];
        }

        // 3. 子入口「栏目」（指向 Catalog，恢复栏目管理入口）
        $catalog = DB::table('admin_menu')->where('link_to', 'Catalog')->first();
        if (empty($catalog)) {
            DB::table('admin_menu')->insert([
                'title'         => '栏目',
                'pid'           => $contentId,
                'path'          => '',
                'level'         => 2,
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
            $catalogRow = DB::table('admin_menu')->where('link_to', 'Catalog')->first();
            $catalogId = (int) ($catalogRow['id'] ?? 0);
            if ($catalogId > 0) {
                DB::update('admin_menu', ['path' => $contentId . ',' . $catalogId . ','], ['id' => $catalogId]);
            }
        } else {
            // 已存在（历史残留 / 降级重跑），统一挂到「内容管理」下
            $catalogId = (int) $catalog['id'];
            if ((int) $catalog['pid'] !== $contentId) {
                DB::update('admin_menu', [
                    'pid'        => $contentId,
                    'path'       => $contentId . ',' . $catalogId . ',',
                    'level'      => 2,
                    'sort_order' => 2,
                    'updated_at' => $now,
                ], ['id' => $catalogId]);
            }
        }

        // 4. 角色权限同步：拥有「内容管理」入口的角色 → 获得两个子入口
        $roleIds = [];
        $rows = DB::table('roles_permissions')->where('perm_key', 'admin_menu_' . $contentId)->get(FETCH_ASSOC) ?: [];
        foreach ($rows as $r) {
            $roleIds[(int) $r['role_id']] = true;
        }
        foreach (array_keys($roleIds) as $roleId) {
            foreach ([$contentChildId, $catalogId] as $mid) {
                if ($mid <= 0) {
                    continue;
                }
                $exists = DB::table('roles_permissions')
                    ->where('role_id', $roleId)
                    ->where('perm_key', 'admin_menu_' . $mid)
                    ->exists();
                if (!$exists) {
                    DB::table('roles_permissions')->insert([
                        'role_id'         => $roleId,
                        'perm_key'        => 'admin_menu_' . $mid,
                        'extras'          => null,
                        'assignment_time' => $now,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        $content = DB::table('admin_menu')->where('link_to', 'Node')->where('pid', 0)->first();
        $contentId = (int) ($content['id'] ?? 0);

        // 移除两个子入口（权限残留由系统在菜单删除时清理）
        if ($contentId > 0) {
            DB::table('admin_menu')->where('link_to', 'Node')->where('pid', $contentId)->delete();
        }
        DB::table('admin_menu')->where('link_to', 'Catalog')->delete();
    }
};
