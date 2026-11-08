<?php

namespace zapcms\services\storage\drivers;

/**
 * 解析 S3 风格（OSS / COS / R2）的 ListObjects XML 响应。
 * 不依赖 ext-simplexml，用正则提取 <Contents> 与 <CommonPrefixes>。
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

        if (preg_match('/<IsTruncated>\s*true\s*<\/IsTruncated>/i', $xml)) {
            $result['truncated'] = true;
        }

        if (preg_match_all('/<CommonPrefixes>\s*<Prefix>(.*?)<\/Prefix>\s*<\/CommonPrefixes>/s', $xml, $m)) {
            foreach ($m[1] as $p) {
                $p = html_entity_decode((string) $p, ENT_QUOTES | ENT_XML1, 'UTF-8');
                $result['prefixes'][] = rtrim($p, '/');
            }
        }

        if (preg_match_all('/<Contents>(.*?)<\/Contents>/s', $xml, $blocks)) {
            foreach ($blocks[1] as $block) {
                if (!preg_match('/<Key>(.*?)<\/Key>/s', $block, $km)) {
                    continue;
                }
                $key   = html_entity_decode((string) $km[1], ENT_QUOTES | ENT_XML1, 'UTF-8');
                $size  = 0;
                $mtime = 0;
                if (preg_match('/<Size>(.*?)<\/Size>/s', $block, $sm)) {
                    $size = (int) trim($sm[1]);
                }
                if (preg_match('/<LastModified>(.*?)<\/LastModified>/s', $block, $lm)) {
                    $mtime = (int) strtotime(trim($lm[1])) ?: 0;
                }
                $result['objects'][] = ['key' => $key, 'size' => $size, 'mtime' => $mtime];
            }
        }

        return $result;
    }
}
