<?php

use zap\db\Schema;
use zapcms\database\Migration;

/**
 * 菜单项 mega-menu 富字段（CMS / Mall 共用）：
 *  - icon         图标 class（FontAwesome 等），如 "fa fa-star"
 *  - image        图片 URL（mega 大图块）
 *  - badge        徽标文字（HOT / NEW）
 *  - css_class    自定义 class（挂到渲染出的 <li> 上）
 *  - column_break 1 = 在此项之前开始新的一列（mega-menu 分栏）
 *
 * 逐列 ALTER：SQLite 不支持单条语句 ADD 多列，分多次保证 SQLite / MySQL 都可用。
 */
return new class extends Migration {

    public function up(): void
    {
        if (!Schema::hasTable('menu_items')) {
            return;
        }
        if (!Schema::hasColumn('menu_items', 'icon')) {
            Schema::alter('menu_items', function ($table) {
                $table->varchar('icon', 100)->nullable();
            });
        }
        if (!Schema::hasColumn('menu_items', 'image')) {
            Schema::alter('menu_items', function ($table) {
                $table->varchar('image', 255)->nullable();
            });
        }
        if (!Schema::hasColumn('menu_items', 'badge')) {
            Schema::alter('menu_items', function ($table) {
                $table->varchar('badge', 50)->nullable();
            });
        }
        if (!Schema::hasColumn('menu_items', 'css_class')) {
            Schema::alter('menu_items', function ($table) {
                $table->varchar('css_class', 100)->nullable();
            });
        }
        if (!Schema::hasColumn('menu_items', 'column_break')) {
            Schema::alter('menu_items', function ($table) {
                $table->boolean('column_break')->default(0);
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('menu_items')) {
            return;
        }
        if (Schema::hasColumn('menu_items', 'icon')) {
            Schema::alter('menu_items', function ($table) {
                $table->removeColumn('icon');
            });
        }
        if (Schema::hasColumn('menu_items', 'image')) {
            Schema::alter('menu_items', function ($table) {
                $table->removeColumn('image');
            });
        }
        if (Schema::hasColumn('menu_items', 'badge')) {
            Schema::alter('menu_items', function ($table) {
                $table->removeColumn('badge');
            });
        }
        if (Schema::hasColumn('menu_items', 'css_class')) {
            Schema::alter('menu_items', function ($table) {
                $table->removeColumn('css_class');
            });
        }
        if (Schema::hasColumn('menu_items', 'column_break')) {
            Schema::alter('menu_items', function ($table) {
                $table->removeColumn('column_break');
            });
        }
    }
};
