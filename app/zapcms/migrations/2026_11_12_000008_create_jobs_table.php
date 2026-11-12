<?php
use zap\DB;
use zap\db\Schema;

/**
 * 创建队列 jobs 表（数据库驱动），用于异步任务：
 * 邮件发送、后续会员/支付通知、附件/缩略图处理等。
 *
 * Redis 驱动无需建表，直接使用 Redis 有序集合；驱动切换由 option('queue.driver') 控制（默认 database）。
 * 运行：php console zapcms:Migrate
 */
return new class extends \zapcms\database\Migration {
    public function up(): void
    {
        if (Schema::hasTable('jobs')) {
            return;
        }
        Schema::create('jobs', function (\zap\db\TableSchema $table) {
            $table->integer('id')->autoIncrement();
            $table->varchar('queue', 255)->default('default');
            $table->text('payload');
            $table->integer('attempts')->default(0);
            $table->integer('reserved_at')->default(0);
            $table->integer('available_at')->default(0);
            $table->varchar('status', 20)->default('pending');
            $table->integer('created_at');
            $table->integer('updated_at');

            $table->addPrimaryKey('id');
            $table->addIndex('idx_queue', ['queue', 'status', 'available_at']);

            $table->setTableEngine(\zap\db\TableSchema::ENGINE_INNODB);
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('jobs')) {
            Schema::dropIfExists('jobs');
        }
    }
};
