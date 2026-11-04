<?php

use zap\DB;
use zap\db\Schema;
use zapcms\database\Migration;

/**
 * 审计日志与登录限流表优化：
 *  1. admin_logs 增加 level / category 字段与索引；
 *  2. 新增 admin_login_attempts 用于登录失败限流（5 次 / 5 分钟）。
 *
 * 通过 try/catch 保证可重复执行（幂等）。
 */
return new class extends Migration {

    public function up(): void
    {
        $pdo = DB::getPDO();

        // ── 1. admin_logs 增加 level / category 与索引 ──
        $logs = $pdo->quoteTable('admin_logs');

        $this->addColumn($pdo, $logs, 'level', "VARCHAR(20) NOT NULL DEFAULT 'INFO'");
        $this->addColumn($pdo, $logs, 'category', "VARCHAR(50) NOT NULL DEFAULT 'system'");
        $this->addIndex($pdo, $logs, 'idx_admin_logs_level', 'level');
        $this->addIndex($pdo, $logs, 'idx_admin_logs_category', 'category');
        $this->addIndex($pdo, $logs, 'idx_admin_logs_request_time', 'request_time');

        // ── 2. 登录失败计数表 ──
        Schema::create('admin_login_attempts', function ($table) {
            $table->integer('id')->autoIncrement();
            $table->varchar('attempt_key', 64);
            $table->integer('attempts');
            $table->integer('first_at');
            $table->integer('last_at');
            $table->addPrimaryKey('id');
            $table->addIndex('uk_attempt_key', 'attempt_key');
            $table->addIndex('idx_last_at', 'last_at');
            $table->setTableEngine(\zap\db\TableSchema::ENGINE_INNODB);
        });
    }

    public function down(): void
    {
        $pdo = DB::getPDO();
        $logs = $pdo->quoteTable('admin_logs');

        foreach (['idx_admin_logs_request_time', 'idx_admin_logs_category', 'idx_admin_logs_level'] as $idx) {
            try {
                $pdo->rawExec("ALTER TABLE {$logs} DROP INDEX {$idx}");
            } catch (\Throwable $e) {
                // 索引不存在则忽略
            }
        }
        foreach (['category', 'level'] as $col) {
            try {
                $pdo->rawExec("ALTER TABLE {$logs} DROP COLUMN {$col}");
            } catch (\Throwable $e) {
                // 字段不存在则忽略
            }
        }

        try {
            Schema::dropIfExists('admin_login_attempts');
        } catch (\Throwable $e) {
        }
    }

    protected function addColumn($pdo, string $table, string $column, string $definition): void
    {
        try {
            $pdo->rawExec("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
        } catch (\Throwable $e) {
            // 字段已存在（重复执行）则忽略
        }
    }

    protected function addIndex($pdo, string $table, string $index, string $column): void
    {
        try {
            $pdo->rawExec("ALTER TABLE {$table} ADD INDEX {$index} ({$column})");
        } catch (\Throwable $e) {
            // 索引已存在（重复执行）则忽略
        }
    }
};
