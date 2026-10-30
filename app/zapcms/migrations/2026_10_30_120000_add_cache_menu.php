<?php

use zap\DB;
use zapcms\database\Migration;

/**
 * 新增「设置 > 缓存管理」菜单项。
 */
return new class extends Migration {
    protected string $linkTo = 'System@cache';

    public function up(): void
    {
        if (DB::table('admin_menu')->where('link_to', $this->linkTo)->exists()) {
            return;
        }

        DB::table('admin_menu')->insert([
            'title'         => '缓存管理',
            'pid'           => 3,
            'path'          => '3,19,',
            'level'         => 2,
            'icon'          => 'fa-solid fa-broom',
            'link_to'       => $this->linkTo,
            'link_target'   => '_self',
            'link_type'     => 'action',
            'active_rule'   => '(system/cache[/.]?.*)',
            'show_position' => '1,2',
            'sort_order'    => 8,
            'updated_at'    => time(),
            'created_at'    => time(),
        ]);
    }

    public function down(): void
    {
        DB::table('admin_menu')->where('link_to', $this->linkTo)->delete();
    }
};
