<?php
/*
 * Copyright (c) 2023.  ZAP.CN  - ZAP CMS
 */

namespace zapcms\services;

use zapcms\models\Node;
use zap\DB;
/**
 * Sitemap 生成器
 *
 * 支持：
 *   - sitemap 索引（sitemap.xml）
 *   - 按内容类型分文件：catalog / page / article / product / faq
 *   - slug 优先 → 回退 ID → 回退 nodeObj 参数
 *   - 自动排除 link-url 栏目、草稿/软删除/回收站
 *   - 按 pub_time / update_time 输出 <lastmod>
 *   - 合理的 <changefreq> + <priority>
 */
class Sitemap
{
    /** Sitemap 命名空间 */
    const NS = 'http://www.sitemaps.org/schemas/sitemap/0.9';

    /** 每文件最多 URL 数 */
    const URLS_PER_FILE = 10000;

    /**
     * 支持的内容类型映射
     * key → 访问路径中的 type 名称
     * value → node 表中的 node_type 值
     */
    public static array $types = [
        'catalog' => 'catalog',
        'page'    => 'page',
        'article' => 'article',
        'product' => 'product',
        'faq'     => 'faq',
    ];

    /**
     * 获取带表前缀的 node 表名
     *
     * 说明：用 static:: 调用，子类也可能需要访问，因此不能是 private
     */
    protected static function nodeTable(): string
    {
        return Node::tableName();
    }

    /**
     * 获取带表前缀的 catalog 表名
     */
    protected static function catalogTable(): string
    {
        return 'catalog';
    }

    // ==================== 对外 API ====================

    /**
     * 生成 sitemap 索引 XML
     *
     * @return string
     */
    public function createIndex(): string
    {
        $items = $this->collectIndexItems();
        if (empty($items)) {
            return $this->emptyIndexXml();
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<sitemapindex xmlns="' . self::NS . '">' . "\n";
        foreach ($items as $item) {
            $xml .= "  <sitemap>\n";
            $xml .= '    <loc>' . $this->xmlEscape($item['loc']) . "</loc>\n";
            if (!empty($item['lastmod'])) {
                $xml .= '    <lastmod>' . $this->xmlEscape($item['lastmod']) . "</lastmod>\n";
            }
            $xml .= "  </sitemap>\n";
        }
        $xml .= '</sitemapindex>';
        return $xml;
    }

    /**
     * 生成指定类型的 URL set XML
     *
     * @param string $type catalog|page|article|product|faq
     * @return string
     */
    public function createUrlSet(string $type): string
    {
        $type = strtolower($type);
        if (!isset(self::$types[$type])) {
            return $this->emptyUrlSetXml();
        }
        return $this->buildUrlSet($type);
    }

    // ==================== 索引构建 ====================

    /**
     * 收集各类型 sitemap 文件的条目（有内容才纳入索引）
     */
    protected function collectIndexItems(): array
    {
        $items  = [];
        $base   = rtrim(site_url(''), '/');

        foreach (self::$types as $key => $nodeType) {
            $count = $this->countPublishedByType($nodeType);
            if ($count === 0) {
                continue;
            }

            $lastmod = $this->getNewestPubTime($nodeType);
            $items[] = [
                'loc'     => $base . '/sitemap-' . $key . '.xml',
                'lastmod' => $lastmod > 0 ? $this->formatW3c($lastmod) : '',
            ];
        }
        return $items;
    }

    protected function emptyIndexXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<sitemapindex xmlns="' . self::NS . '"></sitemapindex>';
    }

    // ==================== URL Set 构建 ====================

    protected function buildUrlSet(string $type): string
    {
        $nodeType = self::$types[$type];
        $entries  = $this->fetchPublishedByType($nodeType, self::URLS_PER_FILE);

        if (empty($entries)) {
            return $this->emptyUrlSetXml();
        }

        $body = [];

        foreach ($entries as $entry) {
            $url  = $this->buildUrl($entry);
            if (empty($url)) {
                continue;
            }

            $lastmod    = $this->getLastmodTime($entry);
            $changefreq = $this->changelog($type, $entry);
            $priority   = $this->priority($type, $entry);

            $body[] = "  <url>\n"
                . '    <loc>' . $this->xmlEscape($url) . "</loc>\n"
                . ($lastmod > 0 ? '    <lastmod>' . $this->formatW3c($lastmod) . "</lastmod>\n" : '')
                . '    <changefreq>' . $changefreq . "</changefreq>\n"
                . '    <priority>' . number_format($priority, 1) . "</priority>\n"
                . "  </url>";
        }

        return implode("\n", [
            '<?xml version="1.0" encoding="UTF-8"?>',
            '<urlset xmlns="' . self::NS . '">',
            ...$body,
            '</urlset>'
        ]);
    }

    protected function emptyUrlSetXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<urlset xmlns="' . self::NS . '"></urlset>';
    }

    // ==================== URL 构建 ====================

    /**
     * 从节点行数据生成完整 URL
     *
     * 策略：slug 优先 → 使用固定链接结构 → 回退参数形式
     */
    protected function buildUrl(array $entry): string
    {
        $nodeType = $entry['node_type'] ?? '';
        $slug     = $entry['slug'] ?? '';
        $id       = (int) ($entry['id'] ?? 0);

        // link-url 栏目不纳入 sitemap
        if ($nodeType === 'link-url' || $slug === '--zap-link-url') {
            return '';
        }

        // 使用 build_permalink（支持固定链接结构）
        if (function_exists('build_permalink')) {
            return \build_permalink($entry);
        }

        // 回退：slug 优先
        if (!empty($slug)) {
            return \append_url_suffix(\site_url($slug));
        }

        if ($nodeType === 'catalog') {
            return \append_url_suffix(\site_url('catalog/' . $id));
        }
        return \append_url_suffix(\site_url('node/' . $nodeType . '?nodeId=' . $id));
    }

    // ==================== 元数据计算 ====================

    protected function getLastmodTime(array $entry): int
    {
        $update = (int) ($entry['update_time'] ?? 0);
        $pub    = (int) ($entry['pub_time'] ?? 0);
        $add    = (int) ($entry['add_time'] ?? 0);

        return max($update, $pub, $add);
    }

    /**
     * 获取最新更新时间（用于 sitemap 索引的 lastmod）
     */
    protected function getNewestPubTime(string $nodeType): int
    {
        $table = static::nodeTable();
        $sql = "SELECT MAX(COALESCE(update_time, pub_time, add_time)) AS t FROM {{$table}}"
             . " WHERE node_type = ? AND status = ?";
        $row = DB::fetch($sql, [$nodeType, Node::STATUS_PUBLISH]);
        return (int) ($row['t'] ?? 0);
    }

    protected function changelog(string $type, array $entry): string
    {
        $map = [
            'catalog' => 'weekly',
            'page'    => 'monthly',
            'article' => 'weekly',
            'product' => 'weekly',
            'faq'     => 'monthly',
        ];
        return $map[$type] ?? 'weekly';
    }

    protected function priority(string $type, array $entry): float
    {
        if ($type === 'catalog') {
            // 根栏目优先，子栏目略低
            $level  = (int) ($entry['level'] ?? 0);
            $mime   = $entry['mime_type'] ?? '';
            // 首页（根栏目且 mime_type 为 page 或空）最高
            if ($level <= 0) {
                return 0.8;
            }
            return max(0.5, 0.8 - ($level * 0.1));
        }

        $map = [
            'page'    => 0.7,
            'article' => 0.5,
            'product' => 0.6,
            'faq'     => 0.4,
        ];
        return $map[$type] ?? 0.5;
    }

    // ==================== 数据库查询 ====================

    /**
     * 获取已发布的节点列表
     */
    protected function fetchPublishedByType(string $nodeType, int $limit = 10000): array
    {
        $table        = static::nodeTable();
        $catalogTable = static::catalogTable();
    
        if ($nodeType === 'catalog') {
            // catalog 表的 level 字段需要在节点表之外 JOIN 获取
            $sql = "SELECT n.id, n.slug, n.node_type, n.mime_type, c.level, n.add_time, n.pub_time, n.update_time"
                 . " FROM {{$table}} n"
                 . " LEFT JOIN {{$catalogTable}} c ON c.id = n.id"
                 . " WHERE n.node_type = ? AND n.status = ?"
                 . " AND n.slug IS NOT NULL AND n.slug != ''"
                 . " ORDER BY n.sort_order ASC, n.id ASC"
                 . " LIMIT " . (int) $limit;
        } else {
            $sql = "SELECT id, slug, node_type, mime_type, add_time, pub_time, update_time"
                 . " FROM {{$table}}"
                 . " WHERE node_type = ? AND status = ?"
                 . " ORDER BY COALESCE(update_time, pub_time, add_time) DESC"
                 . " LIMIT " . (int) $limit;
        }

        return DB::fetchAll($sql, [$nodeType, Node::STATUS_PUBLISH]) ?: [];
    }

    protected function countPublishedByType(string $nodeType): int
    {
        $table = static::nodeTable();
        $row = DB::fetch(
            "SELECT COUNT(*) AS c FROM {{$table}} WHERE node_type = ? AND status = ?",
            [$nodeType, Node::STATUS_PUBLISH]
        );
        return (int) ($row['c'] ?? 0);
    }

    // ==================== 工具方法 ====================

    protected function formatW3c(int $timestamp): string
    {
        return gmdate('Y-m-d\TH:i:s+00:00', $timestamp);
    }

    protected function xmlEscape(string $str): string
    {
        return htmlspecialchars($str, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
