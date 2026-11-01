<?php

use zap\DB;
use zap\db\Schema;
use zapcms\database\Migration;

/**
 * options.option_name 唯一化：清理历史重复行并建立唯一索引。
 *
 * 背景：options.option_name 之前只建了普通索引，而 Option::replace()
 * 使用的是 MySQL REPLACE INTO；没有唯一键冲突时它会退化为纯插入，
 * 导致 mod.installed.* 这类选项出现多行（例如数次注册后出现多条
 * mod.installed.tools）。
 */
return new class extends Migration {

    public function up(): void
    {
        if (!Schema::hasTable('options')) {
            return;
        }

        $pdo    = DB::getPDO();
        $table  = $pdo->quoteTable('options');
        $driver = (string)$pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        // 1. 去重：同一个 option_name 只保留 id 最大（最新）的一条
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $pdo->rawExec("DELETE o1 FROM {$table} o1 INNER JOIN {$table} o2 "
                . "ON o1.option_name = o2.option_name AND o1.id < o2.id");
        } else {
            $pdo->rawExec("DELETE FROM {$table} WHERE id NOT IN "
                . "(SELECT MAX(id) FROM {$table} GROUP BY option_name)");
        }

        // 2. 删除旧的普通索引（不存在时忽略）
        try {
            $pdo->rawExec("ALTER TABLE {$table} DROP INDEX idx_option_name");
        } catch (\Throwable $e) {
            // 索引不存在或当前驱动不支持该语法，忽略
        }

        // 3. 建立唯一索引（已存在时忽略）
        try {
            $pdo->rawExec("CREATE UNIQUE INDEX uk_option_name ON {$table}(option_name)");
        } catch (\Throwable $e) {
            // 已存在唯一索引时忽略
        }
    }

    public function down(): void
    {
        try {
            $table = DB::getPDO()->quoteTable('options');
            DB::getPDO()->rawExec("ALTER TABLE {$table} DROP INDEX uk_option_name");
        } catch (\Throwable $e) {
        }
    }
};
