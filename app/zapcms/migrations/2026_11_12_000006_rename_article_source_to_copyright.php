<?php
use zap\DB;

/**
 * 将文章模型原「新闻来源(source)」字段重命名为「文章版权(article_copyright)」。
 *
 * 兼容说明：
 *  - 若此前已运行 005（已建 source 字段并可能存在历史数据），本迁移会删除 source 字段定义，
 *    并将 node_meta 中 meta_name='source' 的历史数据迁移到 'article_copyright'；
 *  - 若 005 已改为直接建 article_copyright（全新安装），则仅做兼容，无副作用。
 *
 * 运行：php console zapcms:Migrate
 */
return new class extends \zapcms\database\Migration {
    public function up(): void
    {
        // 1) 删除旧的 source 字段定义（若有）
        DB::table('node_type_field')
            ->where('node_type_id', 1)
            ->where('field_name', 'source')
            ->delete();

        // 2) 历史数据迁移：meta_name source -> article_copyright
        DB::table('node_meta')
            ->where('meta_name', 'source')
            ->update(['meta_name' => 'article_copyright']);

        // 3) 确保 article_copyright 字段存在（与 005 幂等兼容）
        $count = DB::table('node_type_field')
            ->where('node_type_id', 1)
            ->where('field_name', 'article_copyright')
            ->count('field_id');
        if ($count === 0) {
            DB::table('node_type_field')->insert([
                'node_type_id' => 1,
                'field_name'  => 'article_copyright',
                'field_label' => '文章版权',
                'field_value' => '',
                'sort_order'  => 100,
                'type'        => 'text',
                'placeholder' => '如：© 某某媒体',
                'required'    => 0,
                'help'        => '',
                'group_name'  => 'Data',
            ]);
        }
    }

    public function down(): void
    {
        DB::table('node_type_field')
            ->where('node_type_id', 1)
            ->where('field_name', 'article_copyright')
            ->delete();
        DB::table('node_meta')
            ->where('meta_name', 'article_copyright')
            ->update(['meta_name' => 'source']);
    }
};
