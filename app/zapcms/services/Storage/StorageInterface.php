<?php

namespace zapcms\services\Storage;

/**
 * 存储驱动契约
 *
 * key 一律是相对 storage 根目录的路径（如 images/a.jpg、avatar/a.png），
 * 不含前导斜杠、不含域名。DB 里仍保存最终 URL，由 StorageManager::keyFromUrl() 反解。
 */
interface StorageInterface
{
    /** 写入文件（$tmpFile 为本地临时文件路径），返回带最终 URL 的结果 */
    public function put(string $key, string $tmpFile, array $meta = []): StorageResult;

    public function delete(string $key): bool;

    public function exists(string $key): bool;

    /** 该 key 对外可访问的 URL */
    public function url(string $key): string;

    /** 该 key 的缩略图 URL（驱动可返回云处理参数或本地缩略图地址） */
    public function thumbUrl(string $key, int $width, int $height): string;

    /** 驱动是否配置完整可用 */
    public function isConfigured(): bool;
}
