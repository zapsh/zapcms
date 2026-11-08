<?php
/*
 * Copyright (c) 2023.  ZAP.CN  - ZAP CMS - All Rights Reserved
 * @author Allen
 * @email zapcms@zap.cn
 * @date 2023/12/27 上午11:29
 * @lastModified 2023/12/27 上午11:10
 *
 */

namespace zapcms\services;

use zap\DB;
use zap\traits\SingletonTrait;

class AdminMenu extends Category
{
    use SingletonTrait;

    const POSITION_TOP = 1;
    const POSITION_LEFT = 2;
    const POSITION_RIGHT = 3;

    protected static array $POSITION_LIST  = [
        1 => '顶部导航',
        2 => '左侧导航',
        3 => '右侧导航'
    ];
    public static function getPositions(): array
    {
        return static::$POSITION_LIST;
    }

    public static function positionTitle($position): string
    {
        return static::$POSITION_LIST[$position];
    }

    public function __construct()
    {
        parent::__construct('admin_menu');
    }

    public function add($data)
    {
        $data['updated_at'] = time();
        $data['created_at'] = time();
        $menuId = parent::add($data);
        if ($menuId > 0) {
            static::syncMenuPermission((int) $menuId, is_array($data) ? $data : []);
        }
        return $menuId;
    }

    public function update($data, $id)
    {
        $data['updated_at'] = time();
        $result = parent::update($data, $id);
        if ($result !== false) {
            static::syncMenuPermission((int) $id, is_array($data) ? $data : []);
        }
        return $result;
    }

    /**
     * 删除菜单：父类会连子树一起删除，先收集子树 id，
     * 再同步清理 permissions / roles_permissions 中的悬空键。
     */
    public function remove($id)
    {
        $row = $this->get($id);
        if (is_null($row)) {
            return false;
        }
        $menuIds = $this->subtreeIds((int) $id, (string) $row[$this->pathColumn]);

        $result = parent::remove($id);
        if ($result !== false && $menuIds !== []) {
            static::removeMenuPermissions($menuIds);
        }
        return $result;
    }

    /**
     * 收集某菜单及其整个子树的 id（按 path 前缀）
     *
     * @return int[]
     */
    protected function subtreeIds(int $id, string $path): array
    {
        $ids = [$id];
        if ($path !== '') {
            $rows = DB::table($this->table)
                ->select($this->primaryKey)
                ->where($this->pathColumn, 'LIKE', $path . '%')
                ->get(FETCH_ASSOC) ?: [];
            foreach ($rows as $r) {
                $ids[] = (int) $r[$this->primaryKey];
            }
        }
        return array_values(array_unique($ids));
    }

    // ================================================================
    //  permissions 注册表同步（admin_menu_{id} 镜像）
    // ================================================================

    /**
     * 单个菜单权限 upsert：不存在则按菜单树插入，存在则镜像标题/排序。
     *
     * $data 传空数组时自动从 admin_menu 读取。
     */
    public static function syncMenuPermission(int $menuId, array $data = []): void
    {
        if ($menuId <= 0) {
            return;
        }
        if ($data === []) {
            $data = DB::table('admin_menu')->where('id', $menuId)->first() ?: [];
            if ($data === []) {
                return;
            }
        }

        $title   = (string) ($data['title'] ?? '');
        $pid     = (int) ($data['pid'] ?? 0);
        $sort    = (int) ($data['sort_order'] ?? 0);
        $permKey = 'admin_menu_' . $menuId;
        $now     = time();

        $existing = DB::table('permissions')->where('perm_key', $permKey)->first();
        if ($existing) {
            $update = ['updated_at' => $now];
            if ($title !== '') {
                $update['title'] = $title;
            }
            if ($sort > 0) {
                $update['sort_order'] = $sort;
            }
            DB::update('permissions', $update, ['perm_key' => $permKey]);
            return;
        }

        // 父级权限行定位：pid 树与菜单树一致
        $parentPermId = 0;
        $level        = 1;
        $path         = '';
        if ($pid > 0) {
            $parent = DB::table('permissions')->where('perm_key', 'admin_menu_' . $pid)->first();
            if ($parent) {
                $parentPermId = (int) $parent['perm_id'];
                $level        = (int) ($parent['level'] ?? 0) + 1;
                $path         = rtrim((string) ($parent['path'] ?? '') . $parentPermId . ',', ',') . ',';
            }
        }

        $permId = (int) DB::insert('permissions', [
            'title'       => $title !== '' ? $title : $permKey,
            'pid'         => $parentPermId,
            'path'        => $path,
            'level'       => $level,
            'perm_key'    => $permKey,
            'extras'      => null,
            'description' => '后台菜单：' . ($title !== '' ? $title : $permKey),
            'updated_at'  => $now,
            'created_at'  => $now,
            'sort_order'  => $sort,
        ]);
        if ($parentPermId === 0 && $permId > 0) {
            // 根节点回写自身 path（与分类树 path 规则一致："{id},"）
            DB::update('permissions', ['path' => $permId . ','], ['perm_id' => $permId]);
        }
    }

    /**
     * 批量清理菜单权限键：permissions 行 + roles_permissions 悬空授权
     *
     * @param int[] $menuIds
     */
    public static function removeMenuPermissions(array $menuIds): void
    {
        $menuIds = array_values(array_unique(array_filter(array_map('intval', $menuIds))));
        if ($menuIds === []) {
            return;
        }
        $keys = array_map(fn ($id) => 'admin_menu_' . $id, $menuIds);
        $ph   = implode(',', array_fill(0, count($keys), '?'));
        DB::query("DELETE FROM {roles_permissions} WHERE perm_key IN ({$ph})", $keys);
        DB::query("DELETE FROM {permissions} WHERE perm_key IN ({$ph})", $keys);
    }

    /**
     * 全量同步：把全部 admin_menu_{id} 镜像进 permissions 注册表，
     * 并清理菜单已删除但键还残留的悬空行（roles_permissions 一并清）。
     *
     * 新装（CreateTables::installBaseData）与存量库迁移都会调用。
     *
     * @return int 同步的菜单数
     */
    public static function syncAllMenuPermissions(): int
    {
        $rows = DB::table('admin_menu')->orderBy('level ASC, id ASC')->get(FETCH_ASSOC) ?: [];
        foreach ($rows as $row) {
            static::syncMenuPermission((int) $row['id'], $row);
        }
        static::pruneDanglingMenuPermissions();
        return count($rows);
    }

    /**
     * 清理悬空键：perm_key 形如 admin_menu_{id}，但 id 已不在 admin_menu 里
     */
    protected static function pruneDanglingMenuPermissions(): void
    {
        $valid = [];
        $menuRows = DB::table('admin_menu')->select('id')->get(FETCH_ASSOC) ?: [];
        foreach ($menuRows as $r) {
            $valid['admin_menu_' . (int) $r['id']] = true;
        }

        $dangling = [];
        $permRows = DB::query("SELECT perm_key FROM {permissions} WHERE perm_key LIKE 'admin_menu_%'")->fetchAll();
        foreach ($permRows as $r) {
            $key = is_array($r) ? $r['perm_key'] : $r->perm_key;
            if (!isset($valid[$key])) {
                $dangling[] = $key;
            }
        }
        if ($dangling === []) {
            return;
        }
        $ph = implode(',', array_fill(0, count($dangling), '?'));
        DB::query("DELETE FROM {permissions} WHERE perm_key IN ({$ph})", $dangling);
        DB::query("DELETE FROM {roles_permissions} WHERE perm_key IN ({$ph})", $dangling);
    }

}
