<?php

use zap\DB;
use zapcms\database\Migration;

/**
 * 后台信息架构：内容模型归入「内容管理」
 *
 *  内容管理 (Node)
 *    ├─ 内容 (Node)
 *    ├─ 栏目 (Catalog)
 *    └─ 内容模型 (Node@types)
 *
 * 「显示设置」不做三级菜单，只在内容模型页面 (node/views/node/types.php) 里
 * 作为一个链接入口；内容模型 / 显示配置这类设置页本身不需要左侧快捷菜单。
 *
 * 原来「内容模型 / 显示设置」只是内容管理页左侧的平铺快捷链接
 * （node/views/_left_navs.php），不在系统菜单树里。
 * 本迁移幂等：按 link_to 查，已存在则只校正父子关系。
 */
return new class extends Migration {

    private const TYPES_LINK  = 'Node@types';
    private const CONFIG_LINK = 'Node@typesConfig';

    public function up(): void
    {
        $now = time();

        // 1. 定位「内容管理」顶级入口
        $content   = DB::table('admin_menu')->where('link_to', 'Node')->where('pid', 0)->first();
        $contentId = (int) ($content['id'] ?? 0);
        if ($contentId <= 0) {
            return;
        }

        // 2. 「内容模型」挂到内容管理下（二级，可点击进入）
        $typesId = $this->upsert([
            'title'         => '内容模型',
            'pid'           => $contentId,
            'level'         => 2,
            'icon'          => 'fa fa-cog',
            'link_to'       => self::TYPES_LINK,
            'active_rule'   => '(node/types.*)',
            'show_position' => '1',
            'sort_order'    => 3,
        ], $contentId . ',', $now);

        if ($typesId <= 0) {
            return;
        }

        // 3. 收敛：若旧版本曾创建过「显示设置」三级菜单，移除它
        //    （显示设置改为内容模型页面内的链接，不占菜单层级）
        $configRow = DB::table('admin_menu')->where('link_to', self::CONFIG_LINK)->first();
        $configId  = (int) ($configRow['id'] ?? 0);
        if ($configId > 0) {
            DB::table('roles_permissions')->where('perm_key', 'admin_menu_' . $configId)->delete();
            DB::table('admin_menu')->where('id', $configId)->delete();
        }

        // 4. 角色权限同步：能看到「内容管理」的角色自动获得「内容模型」
        $rows = DB::table('roles_permissions')
            ->where('perm_key', 'admin_menu_' . $contentId)
            ->get(FETCH_ASSOC) ?: [];
        foreach ($rows as $r) {
            $roleId = (int) $r['role_id'];
            $exists = DB::table('roles_permissions')
                ->where('role_id', $roleId)
                ->where('perm_key', 'admin_menu_' . $typesId)
                ->exists();
            if (!$exists) {
                DB::table('roles_permissions')->insert([
                    'role_id'         => $roleId,
                    'perm_key'        => 'admin_menu_' . $typesId,
                    'extras'          => null,
                    'assignment_time' => $now,
                ]);
            }
        }
    }

    /**
     * 按 link_to 幂等插入/校正菜单，返回菜单 id
     */
    private function upsert(array $data, string $pathPrefix, int $now): int
    {
        $row = DB::table('admin_menu')->where('link_to', $data['link_to'])->first();
        $id  = (int) ($row['id'] ?? 0);

        if ($id <= 0) {
            DB::table('admin_menu')->insert($data + [
                'path'        => '',
                'link_target' => '_self',
                'link_type'   => 'action',
                'updated_at'  => $now,
                'created_at'  => $now,
            ]);
            $row = DB::table('admin_menu')->where('link_to', $data['link_to'])->first();
            $id  = (int) ($row['id'] ?? 0);
        } else {
            unset($data['link_to']);
            DB::update('admin_menu', $data + ['updated_at' => $now], ['id' => $id]);
        }

        if ($id > 0) {
            DB::update('admin_menu', ['path' => $pathPrefix . $id . ','], ['id' => $id]);
        }
        return $id;
    }

    public function down(): void
    {
        $row = DB::table('admin_menu')->where('link_to', self::TYPES_LINK)->first();
        $id  = (int) ($row['id'] ?? 0);
        if ($id > 0) {
            DB::table('roles_permissions')->where('perm_key', 'admin_menu_' . $id)->delete();
            DB::table('admin_menu')->where('id', $id)->delete();
        }
    }
};
