<?php

namespace zapcms\services\storage\drivers;

/**
 * 解析 S3 风格（OSS / COS / R2）的 ListObjects XML 响应。
 * 不依赖 ext-simplexml，用正则提取 <Contents> 与 <CommonPrefixes>；
 * 标签兼容带命名空间前缀的写法（如 <ns:Contents>），
 * CommonPrefixes 兼容「一组一 Prefix」与「一组多 Prefix」两种输出。
 */
trait ParsesObjectListings
{
    /**
     * @return array{prefixes: string[], objects: array<int, array{key: string, size: int, mtime: int}>, truncated: bool}
     */
    private function parseObjectListXml(string $xml): array
    {
        $result = ['prefixes' => [], 'objects' => [], 'truncated' => false];
        $xml    = (string) $xml;

        if (preg_match('/<(?:[\w.-]+:)?IsTruncated>\s*true\s*<\/(?:[\w.-]+:)?IsTruncated>/i', $xml)) {
            $result['truncated'] = true;
        }

        // 目录前缀：先抓 CommonPrefixes 块，再在块内抓全部 <Prefix>
        if (preg_match_all('/<(?:[\w.-]+:)?CommonPrefixes>(.*?)<\/(?:[\w.-]+:)?CommonPrefixes>/s', $xml, $chunks)) {
            foreach ($chunks[1] as $chunk) {
                if (preg_match_all('/<(?:[\w.-]+:)?Prefix>(.*?)<\/(?:[\w.-]+:)?Prefix>/s', $chunk, $pm)) {
                    foreach ($pm[1] as $p) {
                        $p = html_entity_decode((string) $p, ENT_QUOTES | ENT_XML1, 'UTF-8');
                        $result['prefixes'][] = rtrim($p, '/');
                    }
                }
            }
        }

        if (preg_match_all('/<(?:[\w.-]+:)?Contents>(.*?)<\/(?:[\w.-]+:)?Contents>/s', $xml, $blocks)) {
            foreach ($blocks[1] as $block) {
                if (!preg_match('/<(?:[\w.-]+:)?Key>(.*?)<\/(?:[\w.-]+:)?Key>/s', $block, $km)) {
                    continue;
                }
                $key   = html_entity_decode((string) $km[1], ENT_QUOTES | ENT_XML1, 'UTF-8');
                $size  = 0;
                $mtime = 0;
                if (preg_match('/<(?:[\w.-]+:)?Size>(.*?)<\/(?:[\w.-]+:)?Size>/s', $block, $sm)) {
                    $size = (int) trim($sm[1]);
                }
                if (preg_match('/<(?:[\w.-]+:)?LastModified>(.*?)<\/(?:[\w.-]+:)?LastModified>/s', $block, $lm)) {
                    $mtime = (int) strtotime(trim($lm[1])) ?: 0;
                }
                $result['objects'][] = ['key' => $key, 'size' => $size, 'mtime' => $mtime];
            }
        }

        return $result;
    }

    /**
     * 规范化自定义域名 / CDN 基础 URL：
     * 去首尾空白与末尾斜杠；未填写协议时默认补 https://，
     * 避免生成的资源 URL 缺少 scheme 被浏览器当作相对路径。
     */
    private function normalizeBaseUrl(string $url): string
    {
        $url = rtrim(trim($url), '/');
        if ($url !== '' && !preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }
        return $url;
    }

    /**
     * 列举用完整前缀：拼上 bucket 前缀并保证以且仅以一个 / 结尾。
     * 根目录（''）时 objectKey('') 已返回 "{prefix}/"，不能再追加，否则产生
     * "{prefix}//" 导致列举不到任何对象。
     */
    private function listingPrefix(string $prefix): string
    {
        $prefix = ltrim(str_replace('\\', '/', $prefix), '/');
        $full   = $this->objectKey($prefix);
        return $full === '' ? '' : rtrim($full, '/') . '/';
    }

    /** 列举结果为空但响应非空时记录响应片段，便于排查厂商返回格式差异 */
    private function logEmptyListing(string $xml): void
    {
        $xml = (string) $xml;
        if ($xml === '' || preg_match('/<(?:[\w.-]+:)?(Contents|CommonPrefixes)>/i', $xml)) {
            return;
        }
        try {
            logger('storage')->warning('[storage] 列举响应未解析出对象', ['body' => substr($xml, 0, 500)]);
        } catch (\Throwable) {
            // 日志不可用时静默
        }
    }
}
