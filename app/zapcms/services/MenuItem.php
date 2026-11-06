<?php

namespace zapcms\services;

use zap\DB;

/**
 * 菜单项服务（WordPress 式菜单组成）
 *
 * 一条 menu_item 是指向「栏目(catalog) / 内容(node) / 自定义链接(custom_link)」的指针，
 * 本身不创建节点、不占用 slug，因此同一目标可出现在任意多个菜单位置中。
 *
 * @see MenuLocation 菜单位置（= 菜单容器）
 */
class MenuItem
{
    protected static $instance;

    protected $table = 'menu_items';

    public static function instance(): self
    {
        return self::$instance ?? (self::$instance = new self());
    }

    /**
     * 取某位置的全部菜单项（扁平，按 parent / sort 排序）
     */
    public function all(string $locationKey): array
    {
        $rows = DB::table($this->table)
            ->where('location_key', $locationKey)
            ->orderBy('parent_id')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
        return $rows ?: [];
    }

    /**
     * 取某位置的嵌套菜单树（已解析 title / url / target）
     */
    public function tree(string $locationKey): array
    {
        $flat = $this->all($locationKey);
        if (empty($flat)) {
            return [];
        }
        $resolved = $this->resolveBatch($flat);
        $byParent = [];
        foreach ($flat as $item) {
            $byParent[(int) $item['parent_id']][] = $item;
        }
        return $this->buildLevel($byParent, 0, $resolved);
    }

    protected function buildLevel(array $byParent, int $parentId, array $resolved): array
    {
        $level = $byParent[$parentId] ?? [];
        if (empty($level)) {
            return [];
        }
        $out = [];
        foreach ($level as $item) {
            $node = $resolved[(int) $item['id']] ?? [
                'id'           => 0,
                'menu_item_id' => (int) $item['id'],
                'title'        => '',
                'url'          => '#',
                'target'       => '_self',
                'item_type'    => $item['item_type'],
                'ref_id'       => (int) $item['ref_id'],
            ];
            $children = $this->buildLevel($byParent, (int) $item['id'], $resolved);
            if (!empty($children)) {
                $node['children'] = $children;
            }
            $out[] = $node;
        }
        return $out;
    }

    /**
     * 解析单条菜单项为渲染数据
     *
     * 返回：['id'=>目标id, 'menu_item_id', 'title', 'url', 'target', 'item_type', 'ref_id']
     */
    public function resolve(array $item): array
    {
        $type  = $item['item_type'];
        $title = $item['title'];
        $url   = '';
        $targetId = 0;

        if ($type === 'catalog') {
            $row = DB::table('catalog')->where('id', (int) $item['ref_id'])->first();
            if (!empty($row)) {
                $title    = $title ?: ($row['title'] ?? '');
                $targetId = (int) $row['id'];
                $url      = catalog_item_url($row);
            } else {
                $title = $title ?: '(已删除栏目)';
                $url   = '#';
            }
        } elseif ($type === 'node') {
            $row = DB::table('node')->where('id', (int) $item['ref_id'])->first();
            if (!empty($row)) {
                $title    = $title ?: ($row['title'] ?? '');
                $targetId = (int) $row['id'];
                $url      = smart_node_url($row);
            } else {
                $title = $title ?: '(已删除内容)';
                $url   = '#';
            }
        } else { // custom_link
            $title    = $title ?: ($item['url'] ?? '链接');
            $url      = url_link($item['url'] ?? '#');
            $targetId = 0;
        }

        return [
            'id'           => $targetId,
            'menu_item_id' => (int) $item['id'],
            'title'        => $title,
            'url'          => $url,
            'target'       => $item['link_target'] ?: '_self',
            'item_type'    => $type,
            'ref_id'       => (int) $item['ref_id'],
            'icon'         => $item['icon'] ?? '',
            'image'        => $item['image'] ?? '',
            'badge'        => $item['badge'] ?? '',
            'css_class'    => $item['css_class'] ?? '',
            'column_break' => (int) ($item['column_break'] ?? 0),
        ];
    }

    /**
     * 批量解析菜单项（避免 N+1：catalog / node 各一次 IN 查询）
     *
     * @return array [menu_item_id => 渲染数据]
     */
    public function resolveBatch(array $items): array
    {
        $catalogIds = [];
        $nodeIds    = [];
        foreach ($items as $it) {
            if ($it['item_type'] === 'catalog') {
                $catalogIds[] = (int) $it['ref_id'];
            } elseif ($it['item_type'] === 'node') {
                $nodeIds[] = (int) $it['ref_id'];
            }
        }

        $catInfo = [];
        if (!empty($catalogIds)) {
            $rows = DB::table('catalog')->whereIn('id', array_unique($catalogIds))->get() ?: [];
            foreach ($rows as $r) {
                $catInfo[(int) $r['id']] = [
                    'title' => $r['title'] ?? '',
                    'url'   => catalog_item_url($r),
                ];
            }
        }
        $nodeInfo = [];
        if (!empty($nodeIds)) {
            $rows = DB::table('node')->whereIn('id', array_unique($nodeIds))->get() ?: [];
            foreach ($rows as $r) {
                $nodeInfo[(int) $r['id']] = [
                    'title' => $r['title'] ?? '',
                    'url'   => smart_node_url($r),
                ];
            }
        }

        $out = [];
        foreach ($items as $item) {
            $id   = (int) $item['id'];
            $type = $item['item_type'];
            $title = $item['title'];
            $url   = '';
            $targetId = 0;

            if ($type === 'catalog') {
                $info = $catInfo[(int) $item['ref_id']] ?? null;
                if ($info !== null) {
                    $title    = $title ?: $info['title'];
                    $url      = $info['url'];
                    $targetId = (int) $item['ref_id'];
                } else {
                    $title = $title ?: '(已删除栏目)';
                    $url   = '#';
                }
            } elseif ($type === 'node') {
                $info = $nodeInfo[(int) $item['ref_id']] ?? null;
                if ($info !== null) {
                    $title    = $title ?: $info['title'];
                    $url      = $info['url'];
                    $targetId = (int) $item['ref_id'];
                } else {
                    $title = $title ?: '(已删除内容)';
                    $url   = '#';
                }
            } else {
                $title    = $title ?: ($item['url'] ?? '链接');
                $url      = url_link($item['url'] ?? '#');
                $targetId = 0;
            }

            $out[$id] = [
                'id'           => $targetId,
                'menu_item_id' => $id,
                'title'        => $title,
                'url'          => $url,
                'target'       => $item['link_target'] ?: '_self',
                'item_type'    => $type,
                'ref_id'       => (int) $item['ref_id'],
                // mega-menu 富字段（列不存在时回退空值，兼容未迁移环境）
                'icon'         => $item['icon'] ?? '',
                'image'        => $item['image'] ?? '',
                'badge'        => $item['badge'] ?? '',
                'css_class'    => $item['css_class'] ?? '',
                'column_break' => (int) ($item['column_break'] ?? 0),
            ];
        }
        return $out;
    }

    public function get(int $id): ?array
    {
        $row = DB::table($this->table)->where('id', $id)->first();
        return $row ?: null;
    }

    public function add(array $data): int
    {
        $data['created_at'] = time();
        $data['updated_at'] = time();
        return (int) DB::insert($this->table, $data);
    }

    public function update(array $data, int $id): bool
    {
        $data['updated_at'] = time();
        return DB::update($this->table, $data, ['id' => $id]) !== false;
    }

    public function remove(int $id): bool
    {
        $this->removeSubtree($id);
        return true;
    }

    protected function removeSubtree(int $id): void
    {
        $children = DB::table($this->table)->where('parent_id', $id)->get();
        foreach (($children ?: []) as $child) {
            $this->removeSubtree((int) $child['id']);
        }
        DB::table($this->table)->where('id', $id)->delete();
    }

    /**
     * 批量保存排序与层级
     *
     * @param array $orderMap [menu_item_id => ['parent_id'=>int, 'sort_order'=>int]]
     */
    public function reorder(array $orderMap): bool
    {
        foreach ($orderMap as $id => $v) {
            DB::update($this->table, [
                'parent_id'   => (int) ($v['parent_id'] ?? 0),
                'sort_order'  => (int) ($v['sort_order'] ?? 0),
                'updated_at'  => time(),
            ], ['id' => (int) $id]);
        }
        return true;
    }

    /**
     * 按勾选同步栏目在各菜单位置的归属（栏目表单保存时调用）
     *
     * 勾选的位置若无对应菜单项则新增（顶级、末尾）；未勾选但已存在的则移除（含其子树）。
     *
     * @param array $keys 勾选的 location_key 列表
     */
    public function syncCatalogLocations(int $catalogId, array $keys): void
    {
        $catalogId = (int) $catalogId;
        if ($catalogId <= 0) {
            return;
        }

        $valid = array_flip(array_keys(MenuLocation::instance()->options()));
        $wanted = [];
        foreach ($keys as $k) {
            $k = trim((string) $k);
            if ($k !== '' && isset($valid[$k])) {
                $wanted[$k] = true;
            }
        }

        $rows = DB::table($this->table)
            ->where('item_type', 'catalog')
            ->where('ref_id', $catalogId)
            ->get();
        $existing = [];
        foreach (($rows ?: []) as $r) {
            $existing[$r['location_key']] = (int) $r['id'];
        }

        // 新增缺失的位置
        foreach (array_keys($wanted) as $k) {
            if (!isset($existing[$k])) {
                $this->add([
                    'location_key' => $k,
                    'item_type'    => 'catalog',
                    'ref_id'       => $catalogId,
                    'parent_id'    => 0,
                    'sort_order'   => count($this->all($k)),
                    'link_target'  => '',
                ]);
            }
        }

        // 移除未勾选的位置（含子树）
        foreach ($existing as $k => $id) {
            if (!isset($wanted[$k])) {
                $this->remove($id);
            }
        }

        $this->clearMenuCache();
    }

    /**
     * 栏目 → 位置key 列表映射（用于栏目管理列表展示归属位置）
     *
     * @return array [catalog_id => [location_key, ...]]
     */
    public function catalogLocationMap(): array
    {
        $rows = DB::table($this->table)->where('item_type', 'catalog')->get();
        $map = [];
        foreach (($rows ?: []) as $r) {
            $map[(int) $r['ref_id']][] = $r['location_key'];
        }
        return $map;
    }

    /**
     * 取某节点及其子树的所有 id（含自身），用于防止父级选择产生环
     */
    public function subtreeIds(array $all, int $rootId): array
    {
        $ids = [];
        $children = [];
        foreach ($all as $item) {
            if ((int) $item['parent_id'] === $rootId) {
                $children[] = (int) $item['id'];
            }
        }
        foreach ($children as $cid) {
            $ids[$cid] = true;
            $ids += $this->subtreeIds($all, $cid);
        }
        return $ids;
    }

    /**
     * 取多位置合并后的菜单树（带文件缓存，前台渲染专用）
     */
    public function cachedMergedTree(array $keys): array
    {
        $version = $this->menuVersion($keys);
        $cacheKey = 'menu_' . md5(implode('|', $keys)) . '_v' . $version;
        $cacheFile = var_path('cache/' . $cacheKey . '.php');
        if (is_file($cacheFile)) {
            $data = @include $cacheFile;
            if (is_array($data)) {
                return $data;
            }
        }
        $merged = [];
        foreach ($keys as $k) {
            $merged = array_merge($merged, $this->tree($k));
        }
        $this->writeMenuCache($cacheFile, $merged);
        return $merged;
    }

    /**
     * 缓存失效版本（轻量）：各位置 menu_items 数量之和。
     * 结构增删会改变计数自动失效；标签/排序/内容等变更由 clearMenuCache() 显式清文件兜底。
     */
    protected function menuVersion(array $keys): string
    {
        $sum = 0;
        foreach ($keys as $k) {
            try {
                $sum += (int) DB::table($this->table)->where('location_key', $k)->count();
            } catch (\Throwable $e) {
                // 列/库不可用时忽略，依赖 clearMenuCache 兜底
            }
        }
        return (string) $sum;
    }

    /**
     * 清空菜单渲染缓存（内容或结构变更时调用）
     */
    public function clearMenuCache(): void
    {
        foreach (glob(var_path('cache/menu_*.php')) ?: [] as $f) {
            @unlink($f);
        }
    }

    protected function writeMenuCache($file, $data): void
    {
        $dir = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($file, '<?php return ' . var_export($data, true) . ';');
    }
}
