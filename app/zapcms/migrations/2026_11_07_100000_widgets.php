<?php

use zap\DB;
use zap\db\Schema;
use zap\db\TableSchema;
use zapcms\database\Migration;
use zapcms\services\AdminMenu;

/**
 * 小工具（Widgets）框架
 *
 *  - 新增 widgets 表：按「区域（area）」组织的小工具实例，config 存 JSON 配置；
 *  - 在「外观」下新增「小工具」后台菜单入口；
 *  - 权限同步：可见「外观」的角色自动获得「小工具」入口。
 */
return new class extends Migration {

    public function up(): void
    {
        if (!Schema::hasTable('widgets')) {
            Schema::create('widgets', function (TableSchema $table) {
                $table->integer('id')->autoIncrement();
                $table->varchar('widget_key', 64);
                $table->varchar('area', 64);
                $table->varchar('title', 255)->nullable();
                $table->text('config')->nullable();
                $table->tinyint('status')->default('1');
                $table->integer('sort_order')->default('0');
                $table->integer('created_at')->nullable();
                $table->integer('updated_at')->nullable();

                $table->addPrimaryKey('id');
                $table->addIndex('idx_widget_area', ['area', 'sort_order']);

                $table->setTableEngine(TableSchema::ENGINE_INNODB);
            });
        }

        // 「外观」父菜单 id（迁移库动态 id，基础数据库为 23）
        $appearance = DB::table('admin_menu')->where('title', '外观')->where('pid', 0)->first();
        $appearanceId = (int) ($appearance['id'] ?? 0);
        if ($appearanceId <= 0) {
            $appearanceId = 23;
        }

        // 小工具 入口（幂等：按 link_to 查）
        $widget = DB::table('admin_menu')->where('link_to', 'Widget')->first();
        $widgetId = (int) ($widget['id'] ?? 0);
        if ($widgetId <= 0) {
            $widgetId = (int) AdminMenu::instance()->add([
                'title'         => '小工具',
                'pid'           => $appearanceId,
                'icon'          => 'fa-solid fa-shapes',
                'link_to'       => 'Widget',
                'link_target'   => '_self',
                'link_type'     => 'action',
                'active_rule'   => '(widget[/.]?.*)',
                'show_position' => '1,2',
                'sort_order'    => 2,
            ]);
        }

        // 权限同步：可见「外观」的角色 → 获得「小工具」
        if ($widgetId > 0) {
            $now = time();
            $rows = DB::table('roles_permissions')
                ->where('perm_key', 'admin_menu_' . $appearanceId)
                ->get(FETCH_ASSOC) ?: [];
            foreach ($rows as $r) {
                $exists = DB::table('roles_permissions')
                    ->where('role_id', $r['role_id'])
                    ->where('perm_key', 'admin_menu_' . $widgetId)
                    ->exists();
                if (!$exists) {
                    DB::table('roles_permissions')->insert([
                        'role_id'         => $r['role_id'],
                        'perm_key'        => 'admin_menu_' . $widgetId,
                        'extras'          => null,
                        'assignment_time' => $now,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        DB::table('admin_menu')->where('link_to', 'Widget')->delete();
        // 保留 widgets 表与用户数据，避免误删已配置的小工具
    }
};
