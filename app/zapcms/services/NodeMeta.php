<?php
/*
 * Copyright (c) 2023.  ZAP.CN  - ZAP CMS - All Rights Reserved
 * @author Allen
 * @email zapcms@zap.cn
 * @date 2023/12/27 上午11:26
 * @lastModified 2026/8/14 下午2:00
 *
 */

namespace zapcms\services;

use zap\DB;

/**
 * 节点扩展信息（node_meta）服务
 * 统一封装 meta 的读取与附加，避免各控制器重复 N+1 查询
 */
class NodeMeta
{

    /**
     * 请求级缓存：cacheKey(object_id#keyHash) => [meta_name => meta_value]
     * 与 Option 一样在单次请求内复用，避免列表/详情页重复查 node_meta。
     * @var array<string,array>
     */
    private static array $cache = [];

    /**
     * 清空请求级缓存（同一进程处理多请求或写入后需刷新时调用）
     */
    public static function clearCache(): void
    {
        self::$cache = [];
    }

    /**
     * 批量加载 node_meta 附加到节点列表（每个节点带 meta 键，如产品价格）
     * @param array $nodes 节点列表，每个节点需包含 id 键
     * @param array $keys 可选，只加载指定的 meta_name 列表，默认加载全部
     * @return array 附加 meta 后的节点列表
     */
    public static function attachList(array $nodes, array $keys = []): array
    {
        if (empty($nodes)) {
            return $nodes;
        }

        $metaMap = self::loadByObjectIds(array_column($nodes, 'id'), $keys);

        foreach ($nodes as &$node) {
            $node['meta'] = $metaMap[$node['id']] ?? [];
        }
        unset($node);

        return $nodes;
    }

    /**
     * 按 object_id 批量读取 meta（带请求级缓存）
     * @param array $ids 节点 id 列表
     * @param array $keys 可选，只读取指定的 meta_name 列表，默认读取全部
     * @return array [object_id => [meta_name => meta_value]]
     */
    public static function loadByObjectIds(array $ids, array $keys = []): array
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (empty($ids)) {
            return [];
        }

        $keys    = array_values(array_filter(array_map('strval', $keys)));
        $keyHash = $keys === [] ? '' : implode("\0", $keys);

        $result  = [];
        $missIds = [];
        foreach ($ids as $id) {
            $ck = $id . '#' . $keyHash;
            if (array_key_exists($ck, self::$cache)) {
                $result[$id] = self::$cache[$ck];
            } else {
                $missIds[] = $id;
            }
        }

        if (!empty($missIds)) {
            $query = DB::table('node_meta')->whereIn('object_id', $missIds);
            if ($keyHash !== '') {
                $query->whereIn('meta_name', $keys);
            }
            $rows = $query->get(FETCH_ASSOC);
            foreach ($rows as $row) {
                self::$cache[$row['object_id'] . '#' . $keyHash][$row['meta_name']] = $row['meta_value'];
            }
            foreach ($missIds as $id) {
                $ck = $id . '#' . $keyHash;
                if (!array_key_exists($ck, self::$cache)) {
                    self::$cache[$ck] = [];
                }
                $result[$id] = self::$cache[$ck];
            }
        }

        return $result;
    }

    /**
     * 获取指定列表中某个 meta_key 的值映射
     * @param array $ids 节点 id 列表
     * @param string $key meta_name
     * @return array [object_id => meta_value]
     */
    public static function getColumn(array $ids, string $key): array
    {
        $result = [];
        $metaMap = self::loadByObjectIds($ids, [$key]);
        foreach ($metaMap as $objectId => $meta) {
            if (array_key_exists($key, $meta)) {
                $result[$objectId] = $meta[$key];
            }
        }

        return $result;
    }

    /**
     * 获取指定列表中多个指定的 meta
     * @param array $ids 节点 id 列表
     * @param array $keys meta_name 列表（至少一个）
     * @return array [object_id => [meta_name => meta_value]]
     */
    public static function getMany(array $ids, array $keys): array
    {
        return self::loadByObjectIds($ids, $keys);
    }

    /**
     * 读取单个节点的 meta
     * @param int $nodeId 节点 id
     * @param string|null $key 指定 meta_name 时返回该值，null 返回全部
     * @return array|string|null
     */
    public static function get(int $nodeId, ?string $key = null)
    {
        $meta = self::loadByObjectIds([$nodeId]);
        $meta = $meta[$nodeId] ?? [];

        if ($key !== null) {
            return $meta[$key] ?? null;
        }

        return $meta;
    }

}
