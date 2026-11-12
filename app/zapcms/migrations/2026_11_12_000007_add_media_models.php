<?php
use zap\DB;

/**
 * 新增三个内容模型及默认字段：图片(image) / 视频(video) / 文件下载(download)。
 *
 *  - image    图片模型：图集(gallery, textarea) + 图片版权(image_copyright)
 *  - video    视频模型：视频地址(video_url) + 视频封面(video_cover, image) + 时长(duration)
 *                       + 付费预留(是否付费 is_paid / 价格 price / 会员免费 vip_free)
 *  - download 文件下载模型：文件地址(file_url) + 文件大小(file_size) + 文件格式(file_ext)
 *                       + 付费预留(是否付费 is_paid / 价格 price / 会员免费 vip_free)
 *
 * 幂等：按 type_name / (node_type_id, field_name) 去重，重复执行安全。
 * 运行：php console zapcms:Migrate （系统升级也会自动执行）
 */
return new class extends \zapcms\database\Migration {
    public function up(): void
    {
        // 1) 内容模型
        $types = [
            ['type_id' => 5, 'type_name' => 'image', 'title' => '图片', 'description' => '图片画廊 / 相册', 'node_type' => '', 'version' => '1.0.0', 'sort_order' => 0, 'status' => 1, 'updated_at' => time(), 'created_at' => 1694153650],
            ['type_id' => 6, 'type_name' => 'video', 'title' => '视频', 'description' => '视频点播', 'node_type' => '', 'version' => '1.0.0', 'sort_order' => 0, 'status' => 1, 'updated_at' => time(), 'created_at' => 1694153650],
            ['type_id' => 7, 'type_name' => 'download', 'title' => '文件下载', 'description' => '文件下载 / 资源', 'node_type' => '', 'version' => '1.0.0', 'sort_order' => 0, 'status' => 1, 'updated_at' => time(), 'created_at' => 1694153650],
        ];
        foreach ($types as $t) {
            $exists = DB::table('node_types')->where('type_name', $t['type_name'])->count('type_id');
            if ($exists === 0) {
                DB::table('node_types')->insert($t);
            }
        }

        // 2) 字段
        $fields = [
            // 图片
            ['node_type_id' => 5, 'field_name' => 'gallery',          'field_label' => '图集',     'field_value' => '',    'sort_order' => 0,   'type' => 'textarea', 'placeholder' => '每行一个图片地址，或 JSON 数组', 'required' => 0, 'help' => '详情页以画廊形式展示；可填图片 URL，每行一个，或粘贴 JSON 数组。', 'group_name' => 'Data'],
            ['node_type_id' => 5, 'field_name' => 'image_copyright',  'field_label' => '图片版权', 'field_value' => '',    'sort_order' => 100, 'type' => 'text',     'placeholder' => '如：© 某某图库',         'required' => 0, 'help' => '', 'group_name' => 'Data'],
            // 视频
            ['node_type_id' => 6, 'field_name' => 'video_url',        'field_label' => '视频地址', 'field_value' => '',    'sort_order' => 0,   'type' => 'text',     'placeholder' => '视频文件地址或外链，如 /storage/videos/xxx.mp4', 'required' => 0, 'help' => '', 'group_name' => 'Data'],
            ['node_type_id' => 6, 'field_name' => 'video_cover',      'field_label' => '视频封面', 'field_value' => '',    'sort_order' => 1,   'type' => 'image',    'placeholder' => '', 'required' => 0, 'help' => '', 'group_name' => 'Data'],
            ['node_type_id' => 6, 'field_name' => 'duration',         'field_label' => '时长',     'field_value' => '',    'sort_order' => 2,   'type' => 'text',     'placeholder' => '如 03:25', 'required' => 0, 'help' => '', 'group_name' => 'Data'],
            ['node_type_id' => 6, 'field_name' => 'is_paid',          'field_label' => '是否付费', 'field_value' => '0',   'sort_order' => 100, 'type' => 'switch',   'placeholder' => '', 'required' => 0, 'help' => '开启后需付费或会员才能观看', 'group_name' => '付费'],
            ['node_type_id' => 6, 'field_name' => 'price',            'field_label' => '价格',     'field_value' => '0',   'sort_order' => 101, 'type' => 'number',   'placeholder' => '如 9.90', 'required' => 0, 'help' => '', 'group_name' => '付费'],
            ['node_type_id' => 6, 'field_name' => 'vip_free',         'field_label' => '会员免费', 'field_value' => '0',   'sort_order' => 102, 'type' => 'switch',   'placeholder' => '', 'required' => 0, 'help' => '开启后会员可免费观看', 'group_name' => '付费'],
            // 文件下载
            ['node_type_id' => 7, 'field_name' => 'file_url',          'field_label' => '文件地址', 'field_value' => '',    'sort_order' => 0,   'type' => 'text',     'placeholder' => '文件下载地址', 'required' => 0, 'help' => '', 'group_name' => 'Data'],
            ['node_type_id' => 7, 'field_name' => 'file_size',         'field_label' => '文件大小', 'field_value' => '',    'sort_order' => 1,   'type' => 'text',     'placeholder' => '如 12.5 MB', 'required' => 0, 'help' => '', 'group_name' => 'Data'],
            ['node_type_id' => 7, 'field_name' => 'file_ext',          'field_label' => '文件格式', 'field_value' => '',    'sort_order' => 2,   'type' => 'text',     'placeholder' => '如 zip / pdf', 'required' => 0, 'help' => '', 'group_name' => 'Data'],
            ['node_type_id' => 7, 'field_name' => 'is_paid',           'field_label' => '是否付费', 'field_value' => '0',   'sort_order' => 100, 'type' => 'switch',   'placeholder' => '', 'required' => 0, 'help' => '开启后需付费或会员才能下载', 'group_name' => '付费'],
            ['node_type_id' => 7, 'field_name' => 'price',             'field_label' => '价格',     'field_value' => '0',   'sort_order' => 101, 'type' => 'number',   'placeholder' => '如 9.90', 'required' => 0, 'help' => '', 'group_name' => '付费'],
            ['node_type_id' => 7, 'field_name' => 'vip_free',          'field_label' => '会员免费', 'field_value' => '0',   'sort_order' => 102, 'type' => 'switch',   'placeholder' => '', 'required' => 0, 'help' => '开启后会员可免费下载', 'group_name' => '付费'],
        ];
        foreach ($fields as $f) {
            $exists = DB::table('node_type_field')
                ->where('node_type_id', $f['node_type_id'])
                ->where('field_name', $f['field_name'])
                ->count('field_id');
            if ($exists === 0) {
                DB::table('node_type_field')->insert($f);
            }
        }
    }

    public function down(): void
    {
        DB::table('node_type_field')
            ->whereIn('node_type_id', [5, 6, 7])
            ->delete();
        DB::table('node_types')
            ->whereIn('type_name', ['image', 'video', 'download'])
            ->delete();
    }
};
