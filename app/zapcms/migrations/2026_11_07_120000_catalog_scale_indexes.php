<?php

use zap\DB;
use zapcms\database\Migration;

/**
 * 大规模分类（上万级）与商城扩展的辅助索引
 *
 * - catalog.path        ：物化路径前缀查子树（WHERE path LIKE 'x,%'），商城按父分类聚合商品必备
 * - catalog.slug        ：按别名定位栏目（URL 解析、slug 级自定义布局）
 * - catalog.node_type   ：按内容模型过滤的分类树（后台内容表单）
 * - node_relation.node_id：反查内容所属分类（内容页面包屑/激活链路），原复合主键只有 catalog_id 前导
 * - node_meta.object_id  ：NodeMeta::attachList 的 whereIn 批量加载
 * - node.slug            ：前台按 slug 解析节点，每次请求都会用到
 *
 * 全部索引独立判断存在性，可重复执行；跨驱动不支持时忽略。
 */
return new class extends Migration {

    /** @var array<string, array{0:string,1:string}> 索引名 => [表名, 列表达式] */
    private array $indexes = [
        'idx_catalog_path'           => ['catalog', '(path)'],
        'idx_catalog_slug'           => ['catalog', '(slug)'],
        'idx_catalog_node_type'      => ['catalog', '(node_type, pid, sort_order)'],
        'idx_node_relation_node'     => ['node_relation', '(node_id)'],
        'idx_node_meta_object'       => ['node_meta', '(object_id)'],
        'idx_node_slug'              => ['node', '(slug)'],
    ];

    public function up(): void
    {
        $conn = DB::connection();
        foreach ($this->indexes as $name => [$table, $columns]) {
            if ($this->hasIndex($conn, $name)) {
                continue;
            }
            try {
                $tn = $conn->quoteTable($table);
                $conn->query("CREATE INDEX {$name} ON {$tn} {$columns}");
            } catch (\Throwable $e) {
                // 旧版本 MySQL 的索引长度限制、或驱动不支持时忽略，不影响业务
            }
        }
    }

    public function down(): void
    {
        $conn = DB::connection();
        foreach ($this->indexes as $name => [$table, $columns]) {
            try {
                $tn = $conn->quoteTable($table);
                if ($conn->driver === 'sqlite') {
                    $conn->query("DROP INDEX IF EXISTS {$name}");
                } elseif ($this->hasIndex($conn, $name)) {
                    $conn->query("DROP INDEX {$name} ON {$tn}");
                }
            } catch (\Throwable $e) {
                // 忽略
            }
        }
    }

    private function hasIndex(\zap\db\ZPDO $conn, string $name): bool
    {
        try {
            if ($conn->driver === 'sqlite') {
                return (bool) $conn->query(
                    "SELECT 1 FROM sqlite_master WHERE type='index' AND name='{$name}'"
                )->fetchColumn();
            }
            return (bool) $conn->query(
                "SELECT 1 FROM information_schema.statistics
                 WHERE table_schema = DATABASE() AND index_name = '{$name}' LIMIT 1"
            )->fetchColumn();
        } catch (\Throwable $e) {
            return false;
        }
    }
};
