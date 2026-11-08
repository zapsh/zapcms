<?php

namespace zapcms\services\Storage;

use zapcms\Hook;
use zapcms\services\Storage\Drivers\LocalStorage;
use zapcms\services\Storage\Drivers\OssStorage;

/**
 * 存储门面：按 upload.storage_mode 编排本地 / OSS 双写
 *
 * 模式：
 *  - local（默认）：只写本地，URL 与改造前完全一致
 *  - both         ：本地 + OSS 双写；OSS 失败不阻塞上传，只记日志与 warning
 *  - oss          ：只写 OSS，前台 URL 走 OSS/CDN，缩略图走云处理参数
 *
 * 扩展点（供 cmsdev/mall 等模块复用，无需自己实现一套存储）：
 *   Hook::filter('storage.driver', fn($driver, $name) => ...)
 *   Hook::filter('storage.url',    fn($url, $key, $mode) => ...)
 *   Hook::on('storage.after_put', fn(StorageResult $r) => ...)
 *   Hook::on('storage.after_delete', fn($key, $mode) => ...)
 */
final class StorageManager
{
    private const MODES = ['local', 'both', 'oss'];

    private static ?StorageInterface $local = null;
    private static ?StorageInterface $oss = null;

    public static function mode(): string
    {
        $mode = (string) option('upload.storage_mode', 'local');
        return in_array($mode, self::MODES, true) ? $mode : 'local';
    }

    /** 清空驱动实例（后台改配置后调用） */
    public static function forget(): void
    {
        self::$local = null;
        self::$oss   = null;
    }

    public static function local(): StorageInterface
    {
        if (self::$local === null) {
            self::$local = Hook::applyFilters('storage.driver', new LocalStorage(), 'local');
        }
        return self::$local;
    }

    public static function oss(): StorageInterface
    {
        if (self::$oss === null) {
            self::$oss = Hook::applyFilters('storage.driver', new OssStorage(), 'oss');
        }
        return self::$oss;
    }

    /**
     * 原 storage_url() 的本地 URL 规则（relative / absolute）
     * 注意：这里不能反过来调用 storage_url()，避免递归。
     */
    public static function localUrl(string $key): string
    {
        $path = '/' . ltrim($key, '/');
        if ((string) option('upload.url_mode', 'relative') === 'absolute') {
            return rtrim((string) get_site_base_url(), '/') . '/storage' . $path;
        }
        return base_url('/storage' . $path);
    }

    /**
     * 统一的附件 URL 出口
     *
     * thumbs/ 是本地 GD 生成的缩略图缓存，只存在于服务器磁盘，
     * 因此无论哪种模式都返回本地 URL，避免指向不存在的 OSS 对象。
     */
    public static function url(string $key): string
    {
        $key  = ltrim($key, '/');
        $mode = self::mode();

        if (str_starts_with($key, 'thumbs/') || $mode === 'local') {
            $url = self::localUrl($key);
        } elseif ($mode === 'oss') {
            $url = self::oss()->url($key);
        } else {
            // both：默认走 OSS/CDN；未配置完整时降级本地，保证页面不挂
            $oss = self::oss();
            $useOss = (string) option('upload.public_base', 'oss') === 'oss' && $oss->isConfigured();
            $url = $useOss ? $oss->url($key) : self::localUrl($key);
        }

        return (string) Hook::applyFilters('storage.url', $url, $key, $mode);
    }

    /** 缩略图 URL：仅 OSS 模式走云处理参数，其余走本地缩略图缓存 */
    public static function thumbUrl(string $key, int $width, int $height): string
    {
        if (self::mode() === 'oss') {
            return self::oss()->thumbUrl($key, $width, $height);
        }
        return self::local()->thumbUrl($key, $width, $height);
    }

    /**
     * 写入文件，返回最终对外 URL
     *
     * @throws StorageException 仅 OSS 模式写入失败时抛出；both 模式 OSS 失败只记 warning
     */
    public static function put(string $key, string $tmpFile, array $meta = []): StorageResult
    {
        $key  = ltrim(str_replace('\\', '/', $key), '/');
        $mode = self::mode();

        if ($mode === 'oss') {
            $result = self::oss()->put($key, $tmpFile, $meta);
            Hook::fire('storage.after_put', $result);
            return $result;
        }

        // local / both：本地写成功即视为上传成功
        $local  = self::local();
        $result = $local->put($key, $tmpFile, $meta);

        if ($mode === 'both') {
            $oss = self::oss();
            $synced = true;
            $warning = '';
            if ($oss->isConfigured()) {
                try {
                    // 本地 put 已经移动了临时文件，用落盘后的本地文件同步到 OSS
                    $oss->put($key, storage_path($key), $meta);
                } catch (\Throwable $e) {
                    $synced = false;
                    $warning = 'OSS 同步失败：' . $e->getMessage();
                    try {
                        logger('storage')->error('[storage] ' . $warning, ['key' => $key]);
                    } catch (\Throwable) {
                        // 日志不可用时不影响上传结果
                    }
                }
            } else {
                $synced = false;
                $warning = 'OSS 未配置完整，本次仅写入本地';
            }

            $publicUrl = ((string) option('upload.public_base', 'oss') === 'oss' && $synced)
                ? $oss->url($key)
                : self::localUrl($key);
            $result = new StorageResult(
                $key, $publicUrl, 'local', $result->size, $result->mime, $synced, $warning
            );
        }

        Hook::fire('storage.after_put', $result);
        return $result;
    }

    public static function delete(string $key): bool
    {
        $key  = ltrim($key, '/');
        $mode = self::mode();
        $ok   = $mode === 'oss' ? true : self::local()->delete($key);
        if ($mode !== 'local') {
            $ok = self::oss()->delete($key) || $ok;
        }
        Hook::fire('storage.after_delete', $key, $mode);
        return $ok;
    }

    public static function exists(string $key): bool
    {
        $key = ltrim($key, '/');
        return self::mode() === 'oss'
            ? self::oss()->exists($key)
            : self::local()->exists($key);
    }

    /** 后台连接测试 */
    public static function test(): array
    {
        $mode = self::mode();
        if ($mode === 'local') {
            $dir = storage_path();
            return [
                'ok'  => is_dir($dir) && is_writable($dir),
                'msg' => is_dir($dir) && is_writable($dir)
                    ? '本地存储目录可写'
                    : '本地存储目录不可写：' . $dir,
            ];
        }
        if (self::oss() instanceof OssStorage) {
            return self::oss()->test();
        }
        return ['ok' => false, 'msg' => 'OSS 驱动已被替换，无法执行内置连接测试'];
    }

    /**
     * URL → storage key 逆解析
     *
     * DB 里存的是 URL 字符串（/storage/x.jpg、绝对 URL、OSS/CDN URL 混杂），
     * ThumbHelper / 删除 / 同步工具都靠它还原相对 key。
     * 非本站存储资源（外链、主题图片 /themes/...）返回 null。
     */
    public static function keyFromUrl($url): ?string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return null;
        }
        $url = (string) preg_replace('/[?#].*$/', '', $url);
        $prefix = trim((string) option('upload.oss_prefix', ''), '/');

        if (preg_match('#^https?://#i', $url)) {
            $parts   = parse_url($url);
            $host    = strtolower((string) ($parts['host'] ?? ''));
            $path    = (string) ($parts['path'] ?? '');
            $siteHost = strtolower((string) parse_url((string) get_site_base_url(), PHP_URL_HOST));
            $baseHost = strtolower((string) parse_url((string) base_url('/'), PHP_URL_HOST));

            if ($host === $siteHost || $host === $baseHost) {
                return preg_match('#/storage/(.+)$#i', $path, $m) ? ltrim($m[1], '/') : null;
            }

            $cdn = rtrim(trim((string) option('upload.oss_base_url', '')), '/');
            if ($cdn !== '' && str_starts_with($url, $cdn . '/')) {
                $rest = ltrim(substr($url, strlen($cdn) + 1), '/');
            } else {
                $endpoint = rtrim((string) preg_replace('#^https?://#i', '', (string) option('upload.oss_endpoint', '')), '/');
                $bucket   = (string) option('upload.oss_bucket', '');
                $needle   = '://' . $bucket . '.' . $endpoint . '/';
                if ($endpoint === '' || !str_contains($url, $needle)) {
                    return null;
                }
                $rest = ltrim(substr($url, strpos($url, $needle) + strlen($needle)), '/');
            }
            if ($prefix !== '' && str_starts_with($rest, $prefix . '/')) {
                $rest = substr($rest, strlen($prefix) + 1);
            }
            return $rest !== '' ? $rest : null;
        }

        if (preg_match('#(?:^|/)storage/(.+)$#i', $url, $m)) {
            return ltrim($m[1], '/');
        }
        if (preg_match('#^/(?:themes|assets|app|var|vendor)/#i', $url)) {
            return null;
        }
        if (str_starts_with($url, '/')) {
            return null;
        }
        return ltrim($url, '/') ?: null;
    }
}
