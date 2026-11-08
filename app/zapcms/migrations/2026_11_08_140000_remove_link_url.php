<?php

use zap\DB;
use zapcms\database\Migration;

/**
 * 移除 link-url 虚拟内容模型
 *
 * 前台菜单已由 menu_items 统一管理（外部链接用菜单项的 custom_link 类型直接存 url），
 * link-url 栏目模型不再使用，本迁移做彻底清理：
 *  1. 删除引用 link-url 栏目的菜单项（item_type='catalog'）
 *  2. 删除 link-url 栏目的译文 / 自定义字段
 *  3. 删除 link-url 栏目对应的 node 行（node_type='catalog' AND mime_type='link-url'）与 catalog 行
 *  4. 删除 node_types 表的 link-url 模型及其 option 配置
 *  5. 删除 catalog 表的 link_to / link_type / link_object 列（仅 link-url 使用）
 */
return new class extends Migration {

    public function up(): void
    {
        $conn = DB::connection();

        // ---- 1. 先按子查询清掉引用（必须早于 catalog 行删除，子查询依赖行存在）----
        // 全部走 rawExec：不经 ZPDO 查询日志与 PHP 中转，任何环境（含缺 mbstring 的 CLI）可靠执行
        $catalog = $conn->quoteTable('catalog');
        try {
            // 引用 link-url 栏目的菜单项
            $conn->rawExec("DELETE FROM " . $conn->quoteTable('menu_items')
                . " WHERE item_type = 'catalog' AND ref_id IN (SELECT id FROM {$catalog} WHERE node_type = 'link-url')");
            // 译文（栏目与 node 同 id，译文按 TYPE_NODE 存）
            $conn->rawExec("DELETE FROM " . $conn->quoteTable('translations')
                . " WHERE object_type = 'node' AND object_id IN (SELECT id FROM {$catalog} WHERE node_type = 'link-url')");
            // 自定义字段
            $conn->rawExec("DELETE FROM " . $conn->quoteTable('node_meta')
                . " WHERE object_id IN (SELECT id FROM {$catalog} WHERE node_type = 'link-url')");
        } catch (\Throwable $e) {
            // 表或列不存在（全新安装）则跳过
        }

        // ---- 2. 删除 node / catalog / node_types 行 ----
        // node 表中 link-url 栏目行的 node_type 是 'catalog'，mime_type 是 'link-url'
        try {
            $conn->rawExec("DELETE FROM " . $conn->quoteTable('node') . " WHERE node_type = 'catalog' AND mime_type = 'link-url'");
        } catch (\Throwable $e) {
            // 忽略：全新安装无此数据
        }
        try {
            $conn->rawExec("DELETE FROM " . $conn->quoteTable('catalog') . " WHERE node_type = 'link-url'");
        } catch (\Throwable $e) {
            // 忽略：全新安装无此数据
        }
        $conn->rawExec("DELETE FROM " . $conn->quoteTable('node_types') . " WHERE type_name = 'link-url'");
        $conn->rawExec("DELETE FROM " . $conn->quoteTable('options') . " WHERE option_name = 'node_type_config.link-url'");

        // ---- 3. 删除 catalog 表的链接三列 ----
        $pdo = $conn->getUnderlyingPDO();
        foreach (['link_to', 'link_type', 'link_object'] as $column) {
            if (!$this->hasColumn($pdo, $conn->getTablePrefix() . 'catalog', $column)) {
                continue;
            }
            try {
                $tn = $conn->quoteTable('catalog');
                $conn->rawExec("ALTER TABLE {$tn} DROP COLUMN {$column}");
            } catch (\Throwable $e) {
                // SQLite < 3.35 / MySQL < 8 不支持 DROP COLUMN 时保留列，不影响运行（代码已不再读写）
            }
        }

        // ---- 4. 清菜单缓存 ----
        try {
            \zapcms\services\MenuItem::instance()->clearMenuCache();
        } catch (\Throwable $e) {
            // CLI 环境下依赖缺失时忽略，下次后台访问自动重建
        }
    }

    public function down(): void
    {
        // 模型移除不做数据还原：仅恢复 catalog 表结构（可空列）
        $conn = DB::connection();
        $pdo  = $conn->getUnderlyingPDO();
        foreach (['link_to' => "VARCHAR(255) NULL", 'link_type' => "VARCHAR(255) NULL", 'link_object' => "INTEGER NULL"] as $column => $definition) {
            if ($this->hasColumn($pdo, $conn->getTablePrefix() . 'catalog', $column)) {
                continue;
            }
            try {
                $tn = $conn->quoteTable('catalog');
                $conn->rawExec("ALTER TABLE {$tn} ADD COLUMN {$column} {$definition}");
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
