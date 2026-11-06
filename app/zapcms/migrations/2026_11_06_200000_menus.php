<?php

use zap\DB;
use zap\db\Schema;
use zap\db\TableSchema;
use zapcms\database\Migration;
use zapcms\services\MenuLocation;

/**
 * WordPress 式菜单管理：
 *  - 新增 menus 表（菜单实体，可自由创建多个：导航菜单 / 侧边栏 / 页脚…）；
 *  - menu_items 挂到菜单（menu_id），不再是「位置 = 菜单」；
 *  - menu_locations.menu_id：把某个菜单分配到某个位置；
 *  - 历史数据回填：每个已有位置自动创建同名菜单并接管其菜单项。
 *
 * 兼容：MenuItem::all('top') 等按位置 key 的调用仍然可用（内部解析为位置分配的菜单）。
 */
return new class extends Migration {

    public function up(): void
    {
        if (!Schema::hasTable('menus')) {
            Schema::create('menus', function (TableSchema $table) {
                $table->integer('id')->autoIncrement();
                $table->varchar('title', 255);
                $table->integer('created_at')->nullable();
                $table->integer('updated_at')->nullable();

                $table->addPrimaryKey('id');
                $table->setTableEngine(TableSchema::ENGINE_INNODB);
            });
        }

        if (Schema::hasTable('menu_locations') && !Schema::hasColumn('menu_locations', 'menu_id')) {
            Schema::alter('menu_locations', function ($table) {
                $table->integer('menu_id')->default(0);
            });
        }
        if (Schema::hasTable('menu_items') && !Schema::hasColumn('menu_items', 'menu_id')) {
            Schema::alter('menu_items', function ($table) {
                $table->integer('menu_id')->default(0);
            });
        }

        // 历史数据回填：每个位置 → 同名菜单；其菜单项归属该菜单
        foreach (MenuLocation::instance()->all() as $loc) {
            $menuId = (int) ($loc['menu_id'] ?? 0);
            if ($menuId <= 0) {
                $menuId = (int) DB::insert('menus', [
                    'title'      => $loc['title'],
                    'created_at' => time(),
                    'updated_at' => time(),
                ]);
                if ($menuId > 0) {
                    DB::update('menu_locations', ['menu_id' => $menuId], ['id' => (int) $loc['id']]);
                }
            }
            if ($menuId > 0) {
                $items = DB::table('menu_items')
                    ->where('location_key', $loc['key'])
                    ->where('menu_id', 0)
                    ->get(FETCH_ASSOC) ?: [];
                foreach ($items as $it) {
                    DB::update('menu_items', ['menu_id' => $menuId], ['id' => (int) $it['id']]);
                }
            }
        }
    }

    public function down(): void
    {
        // 保留菜单实体与数据，避免误删；如需回滚请自行处理。
    }
};
