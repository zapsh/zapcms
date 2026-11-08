<?php

namespace zapcms\services\storage;

/**
 * 存储驱动契约
 *
 * key 一律是相对 storage 根目录的路径（如 images/a.jpg、avatar/a.png），
 * 不含前导斜杠、不含域名。DB 里仍保存最终 URL，由 StorageManager::keyFromUrl() 反解。
 *
 * 对象存储（OSS / COS / R2）没有真正的「目录」，用 key 前缀 + 尾斜杠标记来模拟：
 *  - makeDirectory("a/b") → 写 0 字节占位对象 "a/b/"
 *  - list("a")            → 按 delimiter "/" 返回下一层目录与文件
 */
interface StorageInterface
{
    /** 写入文件（$tmpFile 为本地临时文件路径），返回带最终 URL 的结果 */
    public function put(string $key, string $tmpFile, array $meta = []): StorageResult;

    /** 删除文件或目录（目录递归删除其下所有对象） */
    public function delete(string $key): bool;

    public function exists(string $key): bool;

    /** 该 key 对外可访问的 URL */
    public function url(string $key): string;

    /** 该 key 的缩略图 URL（驱动可返回云处理参数或本地缩略图地址） */
    public function thumbUrl(string $key, int $width, int $height): string;

    /** 驱动是否配置完整可用 */
    public function isConfigured(): bool;

    /**
     * 列举目录内容（$prefix 为空表示根目录）。
     *
     * 返回：
     * [
     *   'dirs'  => [ ['name' => string, 'path' => string], ... ],
     *   'files' => [ ['name' => string, 'path' => string, 'size' => int, 'mtime' => int, 'ext' => string], ... ],
     * ]
     *
     * name 为末段名称，path 为相对 storage 根目录的完整 key。
     */
    public function list(string $prefix = ''): array;

    /** 新建目录（支持多级，如 a/b/c） */
    public function makeDirectory(string $key): bool;

    /** 重命名 / 移动文件或目录（$from、$to 均为相对 key） */
    public function rename(string $from, string $to): bool;

    /** 把对象下载到本地文件；$dest 缺省写到系统临时文件，返回本地路径，失败返回 null */
    public function download(string $key, ?string $dest = null): ?string;

    /** 该驱动对外 URL 反解为相对 key（非本驱动 URL 返回 null） */
    public function keyFromUrl(string $url): ?string;
}
