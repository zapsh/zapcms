<?php
/*
 * Copyright (c) 2023.  ZAP.CN  - ZAP CMS
 */

namespace app\controllers;

use zap\facades\Cache;
use zapcms\services\Sitemap;
use zap\http\Controller;

class SitemapController extends Controller
{
    /** sitemap 缓存默认 TTL（秒），30 分钟；可在 config 里覆盖 */
    const CACHE_TTL = 1800;

    /** 缓存 key 前缀 */
    const CACHE_PREFIX = 'sitemap:';

    /**
     * 生成 sitemap XML
     *
     * 路由匹配：
     *   /sitemap.xml          → $type = 'sitemap.xml'       → sitemap 索引
     *   /sitemap-article.xml  → $type = 'sitemap-article.xml' → article 类型 URL set
     *   /sitemap-catalog.xml  → $type = 'sitemap-catalog.xml' → catalog 类型 URL set
     *
     * 每个文件独立缓存（默认 30 分钟），避免每次请求都重新扫描全站内容；
     * 缓存不可用时回落到实时生成，保证可用性。
     *
     * @param string $type 路由参数（完整文件名）
     */
    public function generate(string $type = 'sitemap.xml')
    {
        // 设置正确的 Content-Type，避免浏览器以 HTML 解析
        header('Content-Type: application/xml; charset=UTF-8');
        header('X-Robots-Tag: noindex');

        // 提取类型名：sitemap[-type].xml
        if (preg_match('/^sitemap(?:-([a-z0-9_-]+))?\.xml$/i', $type, $m)) {
            $typeName = $m[1] ?? '';
        } else {
            $typeName = '';
        }

        // 不支持的类型 → 404（缓存 key 基于合法文件名，非法名不会落缓存）
        if ($typeName !== '' && !isset(Sitemap::$types[$typeName])) {
            http_response_code(404);
            return;
        }

        $cacheKey = static::CACHE_PREFIX . $type;
        $ttl = (int) config('sitemap.cache_ttl', static::CACHE_TTL);

        try {
            $xml = Cache::get($cacheKey, function () use ($typeName) {
                $sitemap = new Sitemap();
                return $typeName === ''
                    ? $sitemap->createIndex()
                    : $sitemap->createUrlSet($typeName);
            }, $ttl);
        } catch (\Throwable $e) {
            // 缓存不可用时实时生成，保证 sitemap 始终可用
            $sitemap = new Sitemap();
            $xml = $typeName === ''
                ? $sitemap->createIndex()
                : $sitemap->createUrlSet($typeName);
        }

        echo $xml;
    }
}
