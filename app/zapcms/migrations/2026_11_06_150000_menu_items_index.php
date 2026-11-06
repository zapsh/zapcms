<?php

use zap\DB;
use zapcms\database\Migration;

/**
 * 菜单性能索引：前台按 (location_key, parent_id, sort_order) 取树，
 * 复合索引覆盖过滤 + 排序，避免 filesort。
 *
 * 表名带前缀（如 zap_），必须用 quoteTable 取得真实表名；
 * 跨驱动（SQLite / MySQL）先判断索引是否存在再创建，保证可重复执行。
 */
return new class extends Migration {

    public function up(): void
    {
        $conn = DB::connection();
        $tn   = $conn->quoteTable('menu_items');
        $idx  = 'idx_menu_loc_parent_sort';

        $exists = false;
        if ($conn->driver === 'sqlite') {
            $exists = (bool) $conn->query(
                "SELECT 1 FROM sqlite_master WHERE type='index' AND name='{$idx}'"
            )->fetchColumn();
        } else {
            $exists = (bool) $conn->query(
                "SELECT 1 FROM information_schema.statistics
                 WHERE table_schema = DATABASE() AND index_name = '{$idx}' LIMIT 1"
            )->fetchColumn();
        }

        if (!$exists) {
            try {
                $conn->query("CREATE INDEX {$idx} ON {$tn} (location_key, parent_id, sort_order)");
            } catch (\Throwable $e) {
                // 个别驱动 / 旧版本不支持时忽略
            }
        }
    }

    public function down(): void
    {
        $conn = DB::connection();
        $tn   = $conn->quoteTable('menu_items');
        $idx  = 'idx_menu_loc_parent_sort';
        try {
            if ($conn->driver === 'sqlite') {
                $conn->query("DROP INDEX IF EXISTS {$idx}");
            } else {
                $conn->query("DROP INDEX {$idx} ON {$tn}");
            }
        } catch (\Throwable $e) {
            // 忽略
        }
    }
};
