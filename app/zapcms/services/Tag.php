<?php
/*
 * Copyright (c) 2023.  ZAP.CN  - ZAP CMS - All Rights Reserved
 * @author Allen
 * @email zapcms@zap.cn
 *
 */

namespace zapcms\services;

use zap\DB;
use zapcms\services\SlugHelper;

/**
 * 标签服务 —— 复用统一分类法（WordPress 式），不建独立标签表
 *
 * 设计（与 services/Catalog.php 保持一致）：
 *  - 标签项 = node（node_type='tag'，parent_id 恒为 0，扁平，path='{id},' level=1）；
 *  - 内容与标签的多对多关系由 node_relation(catalog_id, node_id) 承载，
 *    catalog_id 此处语义为「标签节点 id」；
 *  - 使用次数实时统计 node_relation（无冗余计数字段）。
 */
class Tag
{
    /**
     * 保存某节点的标签（全量替换）：传入标签标题数组。
     * 注意：node_relation 同时承载栏目关系，只增删标签语义的行，不触碰栏目关联。
     *
     * @param int   $nodeId 内容节点 id（文章等）
     * @param array $titles 标签标题数组
     */
    public static function saveNodeTags(int $nodeId, array $titles): void
    {
        if ($nodeId <= 0) {
            return;
        }

        // 该内容现有的标签关联（仅 node_type='tag' 的那部分）
        $oldTagIds = self::getTagIdsOfNode($nodeId);

        // upsert 标签节点，取 id
        $tagIds = [];
        foreach ($titles as $title) {
            $title = trim((string)$title);
            if ($title === '') {
                continue;
            }
            $id = self::upsertTag($title);
            if ($id > 0) {
                $tagIds[] = $id;
            }
        }
        $tagIds = array_values(array_unique($tagIds));

        // 全量替换标签语义的关联行
        if (!empty($oldTagIds)) {
            DB::table('node_relation')
                ->where('node_id', $nodeId)
                ->whereIn('catalog_id', $oldTagIds)
                ->delete();
        }
        foreach ($tagIds as $tagId) {
            DB::table('node_relation')->insert([
                'catalog_id' => $tagId,
                'node_id'    => $nodeId,
                'level'      => 0,
            ]);
        }
    }

    /**
     * 某内容节点已关联的标签节点 id
     *
     * @param int $nodeId
     * @return int[]
     */
    protected static function getTagIdsOfNode(int $nodeId): array
    {
        $rels = DB::table('node_relation')->where('node_id', $nodeId)->fetchAll() ?: [];
        $catalogIds = array_map(function ($r) {
            return (int)$r['catalog_id'];
        }, $rels);
        if (empty($catalogIds)) {
            return [];
        }

        $rows = DB::table('node')
            ->whereIn('id', $catalogIds)
            ->where('node_type', 'tag')
            ->fetchAll() ?: [];
        return array_map(function ($r) {
            return (int)$r['id'];
        }, $rows);
    }

    /**
     * 按标题 upsert 标签节点（node_type='tag'），返回节点 id。
     * 同名复用同一节点；slug 全局唯一（与内容节点共用 slug 语义）。
     *
     * @param string $title
     * @return int
     */
    protected static function upsertTag(string $title): int
    {
        $row = DB::table('node')->where('node_type', 'tag')->where('title', $title)->first();
        if ($row) {
            return (int)$row['id'];
        }

        $slug = SlugHelper::generate($title);
        if ($slug === '' || DB::table('node')->where('slug', $slug)->exists()) {
            $slug = $slug !== ''
                ? $slug . '-' . substr(md5($title . microtime()), 0, 6)
                : 'tag-' . substr(md5($title), 0, 8);
        }

        $now = time();
        $id = (int)DB::table('node')->insert([
            'author_id'  => 0,
            'node_type'  => 'tag',
            'title'      => $title,
            'slug'       => $slug,
            'status'     => 'publish',
            'sort_order' => 0,
            'pub_time'   => $now,
            'add_time'   => $now,
            'update_time'=> $now,
            'parent_id'  => 0,
            'level'      => 1,
        ]);

        // 顶级节点 path 复刻 Category::add 的写法："{id},"
        if ($id > 0) {
            DB::table('node')->where('id', $id)->update(['path' => "{$id},"]);
        }
        return $id;
    }

    /**
     * 取某内容节点已关联的标签节点（按关联顺序）
     *
     * @param int $nodeId
     * @return array
     */
    public static function getNodeTags(int $nodeId): array
    {
        if ($nodeId <= 0) {
            return [];
        }

        $rels = DB::table('node_relation')->where('node_id', $nodeId)->fetchAll() ?: [];
        $ids = array_map(function ($r) {
            return (int)$r['catalog_id'];
        }, $rels);
        if (empty($ids)) {
            return [];
        }

        $tags = DB::table('node')
            ->whereIn('id', $ids)
            ->where('node_type', 'tag')
            ->fetchAll() ?: [];
        $map = [];
        foreach ($tags as $t) {
            $map[$t['id']] = $t;
        }

        $out = [];
        foreach ($rels as $rel) {
            if (isset($map[$rel['catalog_id']])) {
                $out[] = $map[$rel['catalog_id']];
            }
        }
        return $out;
    }

    /**
     * 取某内容节点已关联标签的标题数组（供文章表单回填）
     *
     * @param int $nodeId
     * @return array
     */
    public static function getNodeTagTitles(int $nodeId): array
    {
        return array_map(function ($t) {
            return $t['title'];
        }, self::getNodeTags($nodeId));
    }

    /**
     * 标签使用次数（实时统计 node_relation）
     *
     * @param int $tagNodeId
     * @return int
     */
    public static function getUsageCount(int $tagNodeId): int
    {
        return (int)DB::table('node_relation')->where('catalog_id', $tagNodeId)->count();
    }

    /**
     * 标签联想（AJAX）：返回匹配关键字的标签
     *
     * @param string $keyword
     * @param int    $limit
     * @return array
     */
    public static function suggest(string $keyword, int $limit = 10): array
    {
        $keyword = trim($keyword);
        if ($keyword === '') {
            return [];
        }

        $rows = DB::table('node')
            ->where('node_type', 'tag')
            ->where('title', 'LIKE', "%{$keyword}%")
            ->orderBy('id', 'DESC')
            ->limit($limit)
            ->fetchAll() ?: [];

        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'id'    => (int)$r['id'],
                'title' => $r['title'],
                'slug'  => (string)($r['slug'] ?? ''),
            ];
        }
        return $out;
    }
}
