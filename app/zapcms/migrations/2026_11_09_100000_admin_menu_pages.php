<?php

use zap\DB;
use zap\facades\Cache;
use zapcms\database\Migration;
use zapcms\services\AdminMenu;

/**
 * 「内容管理」下新增「单页」菜单（Node@page，page 内容模型已存在）。
 *
 * 菜单结构：
 *  内容管理 (Node)
 *    ├─ 内容 (Node)
 *    ├─ 栏目 (Catalog)
 *    ├─ 内容模型 (Node@types)
 *    └─ 单页 (Node@page)   ← 本迁移新增
 *
 * 同时收紧「内容」(link_to=Node) 的高亮规则：
 *  (node(?![/.]?(?:types|page))[/.]?.*) —— 访问 node/page 时不再同时点亮「内容」，
 *  只有新菜单与顶级「内容管理」高亮。负向前瞻紧贴字面量 node，不会被回溯绕过。
 *
 * 权限：permissions 注册表镜像（syncAllMenuPermissions），
 * 并把「单页」授予已拥有「内容」权限的角色，保证存量编辑角色的页面管理能力不断。
 */
return new class extends Migration {

    private const PAGE_LINK = 'Node@page';
    private const NODES_RULE = '(node(?![/.]?(?:types|page))[/.]?.*)';

    public function up(): void
    {
        $now = time();

        // 1. 定位「内容管理」顶级入口
        $content = DB::table('admin_menu')->where('pid', 0)->where('link_to', 'Node')->first();
        $contentId = (int) ($content['id'] ?? 0);
        if ($contentId <= 0) {
            return;
        }

        // 2. 「单页」挂到内容管理下（二级）
        $pageId = $this->upsert([
            'title'         => '单页',
            'pid'           => $contentId,
            'level'         => 2,
            'icon'          => 'far fa-file',
            'link_to'       => self::PAGE_LINK,
            'active_rule'   => '(node/page.*)',
            'show_position' => '1',
            'sort_order'    => 3,
        ], $contentId . ',', $now);
        if ($pageId <= 0) {
            return;
        }

        // 3. 收紧「内容」高亮规则：排除 node/types 与 node/page
        DB::update('admin_menu', [
            'active_rule' => self::NODES_RULE,
            'updated_at'  => $now,
        ], ['link_to' => 'Node', 'pid' => $contentId]);

        // 4. 角色权限同步：能看到「内容」的角色自动获得「单页」
        $nodes = DB::table('admin_menu')->where('link_to', 'Node')->where('pid', $contentId)->first();
        $nodesKey = $nodes ? 'admin_menu_' . (int) $nodes['id'] : '';
        if ($nodesKey !== '') {
            $rows = DB::table('roles_permissions')->where('perm_key', $nodesKey)->get(FETCH_ASSOC) ?: [];
            foreach ($rows as $r) {
                $roleId = (int) $r['role_id'];
                $exists = DB::table('roles_permissions')
                    ->where('role_id', $roleId)
                    ->where('perm_key', 'admin_menu_' . $pageId)
                    ->exists();
                if (!$exists) {
                    DB::table('roles_permissions')->insert([
                        'role_id'         => $roleId,
                        'perm_key'        => 'admin_menu_' . $pageId,
                        'extras'          => null,
                        'assignment_time' => $now,
                    ]);
                }
            }
        }

        // 5. permissions 注册表镜像 + 悬空键清理
        AdminMenu::syncAllMenuPermissions();

        // 6. 权限缓存失效
        Cache::increment('rbac_version', 1);
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
                'i18n_key'    => 'content_pages',
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
        $row = DB::table('admin_menu')->where('link_to', self::PAGE_LINK)->first();
        $id  = (int) ($row['id'] ?? 0);
        if ($id > 0) {
            DB::table('roles_permissions')->where('perm_key', 'admin_menu_' . $id)->delete();
            DB::table('permissions')->where('perm_key', 'admin_menu_' . $id)->delete();
            DB::table('admin_menu')->where('id', $id)->delete();
        }
        // 「内容」高亮规则回退（仅排除 types）
        $content = DB::table('admin_menu')->where('pid', 0)->where('link_to', 'Node')->first();
        if ($content) {
            DB::update('admin_menu', [
                'active_rule' => '(node(?![/.]?types)[/.]?.*)',
                'updated_at'  => time(),
            ], ['link_to' => 'Node', 'pid' => (int) $content['id']]);
        }
        Cache::increment('rbac_version', 1);
    }
};
