<?php

use zap\DB;
use zapcms\database\Migration;

/**
 * 清理 catalog 表死字段
 *
 * catalog 表自 menu_items 接管前台菜单（2026_11_06_120000）后遗留的无效列：
 *  - content      ：栏目正文实际存于 node 表（node_type='catalog' 同 id 行），本列无任何读写
 *  - thumb_url    ：从未使用
 *  - show_position：仅历史迁移与种子读取，运行时菜单渲染只读 menu_items
 *  - link_target  ：菜单打开方式由 menu_items.link_target 决定，本列无运行时读取
 *
 * 保留列：
 *  - title / slug         菜单回退标题、前台面包屑、后台树显示（node 同步副本）
 *  - icon                 后台栏目树显示
 *  - link_to / link_type / link_object  链接型栏目（link-url）解析（resolve_link_url）
 *  - pid / path / level / sort_order / node_type / created_at  分类树与模型过滤
 *
 * 新装安装由 CreateTables.php 直接建不含这些列的表；本迁移只对存量库生效。
 */
return new class extends Migration {

    /** @var string[] 待删除的死列 */
    private array $dropColumns = ['content', 'thumb_url', 'show_position', 'link_target'];

    public function up(): void
    {
        $conn = DB::connection();
        foreach ($this->dropColumns as $column) {
            if (!$this->hasColumn($conn->getUnderlyingPDO(), $conn->getTablePrefix() . 'catalog', $column)) {
                continue;
            }
            try {
                $tn = $conn->quoteTable('catalog');
                $conn->rawExec("ALTER TABLE {$tn} DROP COLUMN {$column}");
            } catch (\Throwable $e) {
                // SQLite < 3.35 / MySQL < 8 不支持 DROP COLUMN 时保留列，不影响运行（代码已不再读写）
            }
        }
    }

    public function down(): void
    {
        $conn = DB::connection();
        foreach ($this->dropColumns as $column) {
            if ($this->hasColumn($conn->getUnderlyingPDO(), $conn->getTablePrefix() . 'catalog', $column)) {
                continue;
            }
            try {
                $tn = $conn->quoteTable('catalog');
                $conn->rawExec("ALTER TABLE {$tn} ADD COLUMN {$column} VARCHAR(255) NULL");
            } catch (\Throwable $e) {
                // 忽略：列已存在或驱动不支持
            }
        }
    }

    private function hasColumn(\PDO $pdo, string $realTable, string $column): bool
    {
        try {
            $driver = strtolower((string) $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME));
            if ($driver === 'sqlite') {
                foreach ($pdo->query("PRAGMA table_info({$realTable})") as $row) {
                    if (strcasecmp((string) ($row['name'] ?? ''), $column) === 0) {
                        return true;
                    }
                }
                return false;
            }
            return (bool) $pdo->query(
                "SELECT 1 FROM information_schema.columns
                 WHERE table_schema = DATABASE()
                   AND table_name = '{$realTable}' AND column_name = '{$column}' LIMIT 1"
            )->fetchColumn();
        } catch (\Throwable $e) {
            return false;
        }
    }
};
