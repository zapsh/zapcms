<?php

use zap\DB;
use zapcms\database\Migration;

/**
 * 新增「设置 > 多语言」菜单项。
 */
return new class extends Migration {
    protected string $linkTo = 'System@languages';

    public function up(): void
    {
        if (DB::table('admin_menu')->where('link_to', $this->linkTo)->exists()) {
            return;
        }

        DB::table('admin_menu')->insert([
            'title'         => '多语言',
            'pid'           => 3,
            'path'          => '3,',
            'level'         => 2,
            'icon'          => 'fa fa-language',
            'link_to'       => $this->linkTo,
            'link_target'   => '_self',
            'link_type'     => 'action',
            'active_rule'   => '(system/languages.*)',
            'show_position' => '1,2',
            'sort_order'    => 10,
            'updated_at'    => time(),
            'created_at'    => time(),
        ]);
    }

    public function down(): void
    {
        DB::table('admin_menu')->where('link_to', $this->linkTo)->delete();
    }
};
