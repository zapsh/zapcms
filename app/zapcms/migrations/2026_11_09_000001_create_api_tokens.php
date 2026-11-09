<?php

use zap\db\Schema;
use zap\db\TableSchema;

/**
 * 创建 API Token 表（Bearer 鉴权用）。
 *
 * 运行：php console zapcms:Migrate
 */
return new class extends \zapcms\database\Migration {
    public function up(): void
    {
        Schema::create('api_tokens', function (TableSchema $table) {
            $table->integer('id')->autoIncrement();
            $table->integer('admin_id');
            $table->varchar('token_hash', 64);
            $table->text('scopes')->nullable();
            $table->integer('expires_at')->default('0');   // 0 = 永不过期
            $table->integer('last_used_at')->nullable();
            $table->integer('created_at');

            $table->addPrimaryKey('id');
            $table->addIndex('idx_token_hash', ['token_hash']);
            $table->addIndex('idx_admin_id', ['admin_id']);
        });
    }
};
