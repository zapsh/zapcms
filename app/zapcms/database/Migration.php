<?php

namespace zapcms\database;

/**
 * 数据库迁移基类。
 *
 * 迁移文件放在 app/zapcms/migrations/，文件名形如
 * 2026_10_30_120000_add_cache_menu.php，文件需要返回一个实例：
 *
 *   return new class extends \zapcms\database\Migration {
 *       public function up(): void
 *       {
 *           // Schema::create(...) / Schema::alter(...) / DB::table(...)
 *       }
 *   };
 *
 * 文件名按字符串升序执行，因此前缀必须是可排序的时间戳。
 */
abstract class Migration
{
    /**
     * 执行迁移。
     */
    abstract public function up(): void;

    /**
     * 回滚迁移（可选）。
     */
    public function down(): void
    {
    }
}
