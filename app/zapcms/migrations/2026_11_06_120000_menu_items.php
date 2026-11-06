<?php

use zap\DB;
use zap\db\Schema;
use zap\db\TableSchema;
use zapcms\database\Migration;
use zapcms\services\MenuLocation;

/**
 * 菜单项（WordPress 式菜单组成）
 *
 *  - 新增 menu_items 表：菜单位置下的具体菜单项，是指向栏目/内容/自定义链接的指针；
 *  - 将现有 catalog.show_position 的勾选关系反向种入 menu_items，并还原栏目层级；
 *  - 之后前台 catalog_menu($key) 仅读取 menu_items，show_position 不再参与渲染。
 */
return new class extends Migration {

    public function up(): void
    {
        if (!Schema::hasTable('menu_items')) {
            Schema::create('menu_items', function (TableSchema $table) {
                $table->integer('id')->autoIncrement();
                $table->varchar('location_key', 64);
                $table->integer('parent_id')->default('0');
                $table->varchar('item_type', 16);          // catalog | node | custom_link
                $table->integer('ref_id')->nullable();
                $table->varchar('title', 255)->nullable();  // 显示文案；NULL 回退到被引用对象
                $table->varchar('url', 1000)->nullable();   // custom_link 目标地址
                $table->varchar('link_target', 32)->nullable(); // _self | _blank
                $table->integer('sort_order')->nullable()->default('0');
                $table->integer('created_at')->nullable();
                $table->integer('updated_at')->nullable();

                $table->addPrimaryKey('id');
                $table->addIndex('idx_menu_location', ['location_key']);
                $table->addIndex('idx_menu_parent', ['parent_id']);

                $table->setTableEngine(TableSchema::ENGINE_INNODB);
            });
        }

        // 已存在菜单项则不重复种入（幂等）
        if (DB::table('menu_items')->count() > 0) {
            return;
        }

        $locations = MenuLocation::instance()->all();
        if (empty($locations)) {
            return;
        }

        // 所有栏目一次性取出，按 path 深度升序，保证父级先处理
        $catalogs = DB::table('catalog')
            ->where('show_position', '<>', '')
            ->get(FETCH_ASSOC) ?: [];
        usort($catalogs, function ($a, $b) {
            return substr_count((string) ($a['path'] ?? ''), ',')
                 - substr_count((string) ($b['path'] ?? ''), ',');
        });

        $now = time();

        foreach ($locations as $loc) {
            $key  = $loc['key'];
            $map  = []; // catalog.id => menu_item.id（本位置内）

            foreach ($catalogs as $c) {
                $positions = array_filter(
                    explode(',', (string) ($c['show_position'] ?? '')),
                    'strlen'
                );
                if (!in_array($key, $positions, true)) {
                    continue;
                }

                // 找最近的、同样属于本位置的祖先栏目
                $ancestors  = array_filter(explode(',', (string) ($c['path'] ?? '')), 'strlen');
                $parentMiId = 0;
                foreach ($ancestors as $aid) {
                    if ((int) $aid === (int) $c['id']) {
                        continue;
                    }
                    if (isset($map[(int) $aid])) {
                        $parentMiId = $map[(int) $aid];
                    }
                }

                $newId = DB::insert('menu_items', [
                    'location_key' => $key,
                    'parent_id'    => $parentMiId,
                    'item_type'    => 'catalog',
                    'ref_id'       => (int) $c['id'],
                    'title'        => null,
                    'url'          => null,
                    'link_target'  => null,
                    'sort_order'   => (int) ($c['sort_order'] ?? 0),
                    'created_at'   => $now,
                    'updated_at'   => $now,
                ]);
                $map[(int) $c['id']] = $newId;
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_items');
    }
};
