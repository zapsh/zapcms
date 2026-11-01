<?php

use zap\db\Schema;
use zap\db\TableSchema;
use zapcms\database\Migration;

/**
 * 新增内容翻译子表。
 *
 * 主表 node / catalog 继续保存默认语言内容，其余语言写入本表。
 * 主键取 (object_type, object_id, locale) 复合主键，天然保证同一对象同一语言只有一条译文。
 */
return new class extends Migration {

    public function up(): void
    {
        if (Schema::hasTable('translations')) {
            return;
        }

        Schema::create('translations', function (TableSchema $table) {
            $table->varchar('object_type', 32)->default('node');
            $table->integer('object_id')->default('0');
            $table->varchar('locale', 16);
            $table->text('title')->nullable();
            $table->varchar('slug', 191)->nullable();
            $table->text('excerpt')->nullable();
            $table->mediumtext('content')->nullable();
            $table->text('keywords')->nullable();
            $table->text('description')->nullable();
            $table->integer('update_time')->nullable()->default('0');

            $table->addPrimaryKey(['object_type', 'object_id', 'locale']);
            $table->addIndex('idx_trn_locale_slug', ['locale', 'slug']);

            $table->setTableEngine(TableSchema::ENGINE_INNODB);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('translations');
    }
};
