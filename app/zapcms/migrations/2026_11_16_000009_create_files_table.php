<?php
use zap\db\Schema;

/**
 * 创建文件信息表 files：
 * 记录通过存储层上传 / 写入的文件元数据（路径、原名、大小、驱动、URL、上传人等），
 * 用于后台文件检索、统计，以及后续引用清理、去重等功能。
 *
 * 运行：php console zapcms:Migrate
 */
return new class extends \zapcms\database\Migration {
    public function up(): void
    {
        if (Schema::hasTable('files')) {
            return;
        }
        Schema::create('files', function (\zap\db\TableSchema $table) {
            $table->integer('id')->autoIncrement();
            $table->varchar('path', 255);
            $table->varchar('name', 255)->nullable();
            $table->varchar('ext', 50)->nullable();
            $table->varchar('mime', 100)->nullable();
            $table->integer('size')->default(0);
            $table->varchar('driver', 20)->default('local');
            $table->varchar('url', 500)->nullable();
            $table->integer('synced')->default(1);
            $table->integer('uid')->default(0);
            $table->integer('created_at');
            $table->integer('updated_at');

            $table->addPrimaryKey('id');
            $table->addIndex('idx_path', ['path']);
            $table->addIndex('idx_created_at', ['created_at']);
            $table->addIndex('idx_uid', ['uid']);

            $table->setTableEngine(\zap\db\TableSchema::ENGINE_INNODB);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('files');
    }
};
