<?php

use zap\DB;
use zapcms\database\Migration;

/**
 * catalog.pid 索引：栏目树懒加载按父级取子节点（WHERE pid = ?），
 * 复合 (pid, sort_order) 覆盖过滤 + 排序，避免 filesort。
 *
 * 表名带前缀（如 zap_），用 quoteTable 取得真实表名；跨驱动先判断存在再创建。
 */
return new class extends Migration {

    public function up(): void
    {
        $conn = DB::connection();
        $tn   = $conn->quoteTable('catalog');
        $idx  = 'idx_catalog_pid';

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
                $conn->query("CREATE INDEX {$idx} ON {$tn} (pid, sort_order)");
            } catch (\Throwable $e) {
                // 个别驱动 / 旧版本不支持时忽略
            }
        }
    }

    public function down(): void
    {
        $conn = DB::connection();
        $tn   = $conn->quoteTable('catalog');
        $idx  = 'idx_catalog_pid';
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
