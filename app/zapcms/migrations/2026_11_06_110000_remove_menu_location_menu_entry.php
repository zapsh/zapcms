<?php

use zap\DB;
use zapcms\database\Migration;

/**
 * 菜单位置管理改为在「栏目管理」页内通过右侧抽屉操作，
 * 因此移除原先「设置」下独立的「菜单位置」菜单入口（避免重复入口）。
 */
return new class extends Migration {

    public function up(): void
    {
        DB::table('admin_menu')->where('link_to', 'MenuLocation')->delete();
    }

    public function down(): void
    {
        // 如需恢复，可直接访问 MenuLocation 控制器；此处不重建菜单。
    }
};
