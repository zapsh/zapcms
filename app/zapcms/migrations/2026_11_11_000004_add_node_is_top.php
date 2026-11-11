<?php
use zap\db\Schema;

/**
 * 为 node 表新增 is_top 字段，支持新闻 / 文章列表「置顶显示」。
 *
 *  - is_top = 1 的内容在列表（前台栏目列表、后台列表、ZQuery 查询）中优先展示；
 *  - 置顶项内部按发布时间倒序，非置顶项同样按发布时间倒序。
 *
 * 运行：php console zapcms:Migrate （系统升级也会自动执行）
 * 重复执行安全（字段已存在则跳过）。
 */
return new class extends \zapcms\database\Migration {
    public function up(): void
    {
        if (Schema::hasColumn('node', 'is_top')) {
            return;
        }
        Schema::alter('node', function (\zap\db\AlertTable $table) {
            $table->integer('is_top')->default('0');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('node', 'is_top')) {
            Schema::alter('node', function (\zap\db\AlertTable $table) {
                $table->removeColumn('is_top');
            });
        }
    }
};
