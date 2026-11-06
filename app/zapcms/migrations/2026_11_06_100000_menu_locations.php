<?php

use zap\DB;
use zap\db\Schema;
use zap\db\TableSchema;
use zapcms\database\Migration;
use zapcms\services\AdminMenu;
use zapcms\services\MenuLocation;

/**
 * 自定义菜单位置（Menu Location）
 *
 *  - 新增 menu_locations 表，替代写死的顶部/左侧/右侧/底部四个枚举；
 *  - 种子 4 个系统默认位置（top/left/right/footer）；
 *  - 将历史 catalog.show_position 的整型枚举 1/2/3/4 迁移为位置 key；
 *  - 在「设置」下新增「菜单位置」后台菜单入口。
 */
return new class extends Migration {

    public function up(): void
    {
        if (!Schema::hasTable('menu_locations')) {
            Schema::create('menu_locations', function (TableSchema $table) {
                $table->integer('id')->autoIncrement();
                $table->varchar('title', 255);
                $table->varchar('key', 64);
                $table->varchar('description', 255)->nullable();
                $table->tinyint('is_system')->default('0');
                $table->integer('sort_order')->nullable()->default('0');
                $table->integer('updated_at')->nullable();
                $table->integer('created_at')->nullable();

                $table->addPrimaryKey('id');
                $table->addUniqueIndex('uk_menu_location_key', ['key']);

                $table->setTableEngine(TableSchema::ENGINE_INNODB);
            });
        }

        // 种子系统默认位置（已存在则跳过）
        MenuLocation::seedDefaults();

        // 历史数据：整型枚举(1/2/3/4) → 位置 key
        $map = [1 => 'top', 2 => 'left', 3 => 'right', 4 => 'footer'];
        $rows = DB::table('catalog')->where('show_position', '<>', '')->get(FETCH_ASSOC) ?: [];
        foreach ($rows as $row) {
            $parts = array_filter(array_map('trim', explode(',', (string)($row['show_position'] ?? ''))), 'strlen');
            $new   = [];
            foreach ($parts as $p) {
                if (isset($map[$p])) {
                    $new[] = $map[$p];
                } elseif (!is_numeric($p)) {
                    $new[] = $p; // 已经是 key，原样保留
                }
            }
            $new = array_unique($new);
            DB::update('catalog', ['show_position' => implode(',', $new)], ['id' => $row['id']]);
        }
    }

    public function down(): void
    {
        DB::table('admin_menu')->where('link_to', 'MenuLocation')->delete();
        // 保留 menu_locations 表与用户数据，避免误删自定义位置
    }
};
