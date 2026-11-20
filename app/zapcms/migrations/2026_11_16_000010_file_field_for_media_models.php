<?php
use zap\DB;

/**
 * 视频 / 文件下载模型的地址字段改用新增的「文件」控件：
 *  - node_type_id=6(video).video_url
 *  - node_type_id=7(download).file_url
 *
 * 同时清掉旧的提示性 field_value，避免被当作默认值回填到节点表单。
 * 运行：php console zapcms:Migrate
 */
return new class extends \zapcms\database\Migration {
    public function up(): void
    {
        $rows = [
            [6, 'video_url'],
            [7, 'file_url'],
        ];
        foreach ($rows as [$typeId, $fieldName]) {
            DB::table('node_type_field')
                ->where('node_type_id', $typeId)
                ->where('field_name', $fieldName)
                ->update([
                    'type'        => 'file',
                    'field_value' => '',
                ]);
        }
    }

    public function down(): void
    {
        $rows = [
            [6, 'video_url'],
            [7, 'file_url'],
        ];
        foreach ($rows as [$typeId, $fieldName]) {
            DB::table('node_type_field')
                ->where('node_type_id', $typeId)
                ->where('field_name', $fieldName)
                ->update(['type' => 'text']);
        }
    }
};
