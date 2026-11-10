<?php

use zap\DB;

/**
 * 将 catalog 分类表并入 node（WordPress 式统一分类法）。
 *
 *  - node 增加 parent_id 层级字段；
 *  - catalog 行迁移为 node（node_type='category'，mime_type=原 catalog.node_type，parent_id=原 pid），
 *    沿用原 id，使 node_relation.catalog_id 与 menu_items.ref_id 无需重指；
 *  - 删除旧版孪生 node（node_type='catalog'），避免 id 冲突；
 *  - menu_items.item_type 'catalog' → 'node'；
 *  - 删除 catalog 表。
 *
 * 仅对「仍存在 catalog 表」的旧库生效；全新安装（CreateTables 已不再建 catalog）自动跳过。
 * 运行：php console zapcms:Migrate
 */
return new class extends \zapcms\database\Migration {
    public function up(): void
    {
        $pdo = DB::getPDO();

        // 1) 增加 parent_id / path / level（已存在则忽略报错；SQLite 每条 ALTER 仅一列）
        foreach (['parent_id INTEGER NOT NULL DEFAULT 0', 'path VARCHAR(255)', 'level INTEGER NOT NULL DEFAULT 0'] as $col) {
            try {
                $pdo->rawExec("ALTER TABLE " . $pdo->quoteTable('node') . " ADD COLUMN {$col}");
            } catch (\Throwable $e) {
                // 列已存在
            }
        }

        // 2) catalog 表不存在则跳过（全新库无需迁移）
        try {
            if (!DB::table('catalog')->exists()) {
                return;
            }
        } catch (\Throwable $e) {
            return; // 表不存在会导致 exists() 抛错，视为无需迁移
        }

        // 3) 删除旧版孪生 node（node_type='catalog'），避免与下面复用 id 的插入冲突
        DB::table('node')->where('node_type', 'catalog')->delete();

        // 4) 迁移 catalog → node（按 level 升序，父先于子）
        $catalogs = DB::table('catalog')
            ->orderBy('level', 'ASC')
            ->orderBy('id', 'ASC')
            ->get() ?: [];

        foreach ($catalogs as $c) {
            $oldId = (int) $c['id'];
            $pid   = (int) ($c['pid'] ?? 0);
            $now   = (int) ($c['created_at'] ?? time());

            $row = [
                'node_type'  => 'category',
                'title'      => $c['title'] ?? '',
                'slug'       => $c['slug'] ?? '',
                'mime_type'  => $c['node_type'] ?? '',
                'parent_id'  => $pid,
                'path'       => $c['path'] ?? (($pid > 0 ? "{$pid}," : '') . $oldId . ','),
                'level'      => (int) ($c['level'] ?? 1),
                'status'     => 'publish',
                'sort_order' => (int) ($c['sort_order'] ?? 0),
                'hits'       => 0,
                'pub_time'   => $now,
                'update_time' => $now,
                'add_time'   => $now,
            ];

            if (DB::table('node')->where('id', $oldId)->fetch(FETCH_ASSOC)) {
                DB::update('node', $row, ['id' => $oldId]);
            } else {
                DB::insert('node', array_merge(['id' => $oldId, 'author_id' => 0], $row));
            }
        }

        // 5) 菜单项引用改为 node
        DB::table('menu_items')->where('item_type', 'catalog')->update(['item_type' => 'node']);

        // 6) 删除 catalog 表
        try {
            $pdo->rawExec("DROP TABLE " . $pdo->quoteTable('catalog'));
        } catch (\Throwable $e) {
            // 已删除
        }
    }
};
