<?php
use zap\DB;

/**
 * 为「文章 / 新闻」模型新增自定义字段：文章版权(article_copyright)、图片版权(image_copyright)，均存储于 node_meta。
 *
 *  - article_copyright：文章版权（如：© 某某媒体），前台详情页展示；
 *  - image_copyright  ：图片版权（如：© 某某图片社），前台详情页主图下方展示。
 *
 * 运行：php console zapcms:Migrate （系统升级也会自动执行）
 * 重复执行安全（按 field_name 去重）。
 */
return new class extends \zapcms\database\Migration {
    public function up(): void
    {
        $this->ensureField('article_copyright', '文章版权', 'text', '如：© 某某媒体', 100);
        $this->ensureField('image_copyright', '图片版权', 'text', '如：© 某某图片社', 101);
    }

    /**
     * 幂等新增文章模型的自定义字段（已存在则跳过）
     */
    protected function ensureField(string $name, string $label, string $type, string $placeholder, int $sort): void
    {
        $count = DB::table('node_type_field')
            ->where('node_type_id', 1)
            ->where('field_name', $name)
            ->count('field_id');
        if ($count > 0) {
            return;
        }
        DB::table('node_type_field')->insert([
            'node_type_id' => 1,
            'field_name'  => $name,
            'field_label' => $label,
            'field_value' => '',
            'sort_order'  => $sort,
            'type'        => $type,
            'placeholder' => $placeholder,
            'required'    => 0,
            'help'        => '',
            'group_name'  => 'Data',
        ]);
    }

    public function down(): void
    {
        foreach (['article_copyright', 'image_copyright'] as $name) {
            DB::table('node_type_field')
                ->where('node_type_id', 1)
                ->where('field_name', $name)
                ->delete();
        }
    }
};
