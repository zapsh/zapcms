<?php
/*
 * Copyright (c) 2023.  ZAP.CN  - ZAP CMS - All Rights Reserved
 * @author Allen
 * @email zapcms@zap.cn
 */

namespace zapcms\models;

use zap\DB;
use zap\db\Model;

/**
 * 评论模型（多态关联）
 *
 * comments 表通过 (object_type, object_id) 双列关联任意模块：
 *   - node 模块：   object_type = 'node',    object_id = node.id
 *   - 其他模块：    object_type = 'product', object_id = product.id
 *   - 插件模块：    object_type = 'plugin:xxx', object_id = xxx.id
 */
class Comment extends Model
{
    /** 评论状态 */
    const APPROVED_PENDING = 0; // 待审核
    const APPROVED_YES     = 1; // 已通过
    const APPROVED_SPAM    = 2; // 垃圾评论

    /**
     * 表名（基类 getTableName() 会将类名转蛇形：Comment → comment，
     * 但实际表名为 comments，必须显式指定）
     */
    protected $table = 'comments';

    public static function tableName(): string
    {
        return 'comments';
    }

    public static function primaryKey()
    {
        return 'comment_id';
    }

    /**
     * 多态查询：获取某模块某条记录的评论
     */
    public static function forObject(string $objectType, $objectId): \zap\db\Query
    {
        return static::where('object_type', $objectType)
            ->where('object_id', (int)$objectId);
    }

    /**
     * 多态统计：某模块某条记录的评论总数
     */
    public static function countByObject(string $objectType, $objectId, int $approved = self::APPROVED_YES): int
    {
        return (int)static::forObject($objectType, $objectId)
            ->where('approved', $approved)
            ->count();
    }

    /**
     * 评论状态文案
     */
    public static function getStatusTitle($approved): string
    {
        switch ((int)$approved) {
            case self::APPROVED_YES:
                return '已通过';
            case self::APPROVED_PENDING:
                return '待审核';
            case self::APPROVED_SPAM:
                return '垃圾评论';
            default:
                return '未知';
        }
    }

    /**
     * 评论状态 badge 样式
     */
    public static function getStatusBadge($approved): string
    {
        switch ((int)$approved) {
            case self::APPROVED_YES:
                return 'success';
            case self::APPROVED_PENDING:
                return 'warning';
            case self::APPROVED_SPAM:
                return 'danger';
            default:
                return 'secondary';
        }
    }

    /**
     * 获取状态选项（后台筛选用）
     */
    public static function getStatusOptions(): array
    {
        return [
            self::APPROVED_PENDING => '待审核',
            self::APPROVED_YES     => '已通过',
            self::APPROVED_SPAM    => '垃圾评论',
        ];
    }

    /**
     * 关联对象信息（用于后台列表展示评论挂在哪个内容下）
     *
     * @param string $objectType 模块标识
     * @param int    $objectId   记录主键
     * @return array|null ['type_title' => 模块中文名, 'title' => 内容标题, 'url' => 前台链接, 'exists' => 内容是否存在]
     */
    public static function getObjectInfo(string $objectType, int $objectId): ?array
    {
        switch ($objectType) {
            case 'node':
                $row = DB::table('node')->where('id', $objectId)->fetch(FETCH_ASSOC);
                if (!$row) {
                    return [
                        'type_title' => '内容',
                        'title'      => "(已删除 #{$objectId})",
                        'url'        => '',
                        'exists'     => false,
                    ];
                }
                return [
                    'type_title' => \zapcms\services\NodeType::getTitle($row['node_type'] ?? '') ?: '内容',
                    'title'      => $row['title'] ?? "(#{$objectId})",
                    'url'        => $row['slug'] ?? '',
                    'exists'     => true,
                ];
            default:
                // 其他模块：由各模块通过 Comment::registerObjectType() 扩展，此处返回基础信息
                return [
                    'type_title' => $objectType,
                    'title'      => "#{$objectId}",
                    'url'        => '',
                    'exists'     => true,
                ];
        }
    }

    /**
     * 已注册的多态对象类型（用于后台"关联模块"筛选下拉）
     * 其他模块可调用 Comment::registerObjectType('product', '商品') 注册
     */
    private static array $objectTypes = [];

    public static function registerObjectType(string $type, string $title): void
    {
        self::$objectTypes[$type] = $title;
    }

    /**
     * 获取所有已注册的对象类型（node 为内置默认）
     */
    public static function getObjectTypes(): array
    {
        $types = ['node' => '内容'];
        foreach (self::$objectTypes as $type => $title) {
            $types[$type] = $title;
        }
        return $types;
    }
}
