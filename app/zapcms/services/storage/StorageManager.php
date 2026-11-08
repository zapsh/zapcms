<?php

namespace zapcms\services\storage;

use zapcms\Hook;
use zapcms\services\storage\drivers\CosStorage;
use zapcms\services\storage\drivers\LocalStorage;
use zapcms\services\storage\drivers\OssStorage;
use zapcms\services\storage\drivers\R2Storage;

/**
 * 存储门面：按 upload.storage_mode 编排本地 / 云存储
 *
 * 模式：
 *  - local（默认）：只写本地，URL 与改造前完全一致
 *  - oss / cos / r2：只写对应云厂商，前台 URL 走云/CDN
 *  - both          ：本地 + 云（upload.cloud_driver 指定，默认 oss）双写；
 *                    云同步失败不阻塞上传，只记日志与 warning
 *
 * 扩展点（供 cmsdev/mall 等模块复用，无需自己实现一套存储）：
 *   Hook::filter('storage.driver', fn($driver, $name) => ...)
 *   Hook::filter('storage.url',    fn($url, $key, $mode) => ...)
 *   Hook::on('storage.after_put', fn(StorageResult $r) => ...)
 *   Hook::on('storage.after_delete', fn($key, $mode) => ...)
 */
final class StorageManager
{
    public const CLOUD_DRIVERS = ['oss', 'cos', 'r2'];

    private static array $instances = [];

    public static function mode(): string
    {
        $mode  = (string) option('upload.storage_mode', 'local');
        $valid = array_merge(['local', 'both'], self::CLOUD_DRIVERS);
        return in_array($mode, $valid, true) ? $mode : 'local';
    }

    /** 是否纯云模式（oss / cos / r2） */
    public static function isCloud(): bool
    {
        return in_array(self::mode(), self::CLOUD_DRIVERS, true);
    }

    /** both 模式下使用哪个云作为镜像 */
    public static function cloudDriver(): string
    {
        $name = (string) option('upload.cloud_driver', 'oss');
        return in_array($name, self::CLOUD_DRIVERS, true) ? $name : 'oss';
    }

    /** 清空驱动实例（后台改配置后调用） */
    public static function forget(): void
    {
        self::$instances = [];
    }

    public static function local(): StorageInterface
    {
        return self::driver('local');
    }

    public static function oss(): StorageInterface
    {
        return self::driver('oss');
    }

    public static function cos(): StorageInterface
    {
        return self::driver('cos');
    }

    public static function r2(): StorageInterface
    {
        return self::driver('r2');
    }

    public static function driver(string $name): StorageInterface
    {
        if (!isset(self::$instances[$name])) {
            $instance = match ($name) {
                'local' => new LocalStorage(),
                'oss'   => new OssStorage(),
                'cos'   => new CosStorage(),
                'r2'    => new R2Storage(),
                default => throw new StorageException("未知存储驱动：{$name}"),
            };
            self::$instances[$name] = Hook::applyFilters('storage.driver', $instance, $name);
        }
        return self::$instances[$name];
    }

    /** 当前生效的云驱动；local 模式返回 null */
    public static function activeCloud(): ?StorageInterface
    {
        $mode = self::mode();
        if ($mode === 'local') {
            return null;
        }
        return self::driver($mode === 'both' ? self::cloudDriver() : $mode);
    }

    /** 文件管理（浏览/重命名/删除等）操作的主驱动：both 模式以本地为源 */
    private static function forFiles(): StorageInterface
    {
        $mode = self::mode();
        if ($mode === 'local' || $mode === 'both') {
            return self::local();
        }
        return self::driver($mode);
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
     * 因此无论哪种模式都返回本地 URL，避免指向不存在的云对象。
     */
    public static function url(string $key): string
    {
        $key  = ltrim($key, '/');
        $mode = self::mode();

        if (str_starts_with($key, 'thumbs/') || $mode === 'local') {
            $url = self::localUrl($key);
        } elseif ($mode === 'both') {
            $cloud = self::activeCloud();
            $useCloud = in_array((string) option('upload.public_base', 'cloud'), ['cloud', 'oss'], true)
                && $cloud !== null && $cloud->isConfigured();
            $url = $useCloud ? $cloud->url($key) : self::localUrl($key);
        } else {
            $url = self::activeCloud()->url($key);
        }

        return (string) Hook::applyFilters('storage.url', $url, $key, $mode);
    }

    /** 缩略图 URL：纯云模式走云处理参数（COS/R2 退化为原图），其余走本地缩略图缓存 */
    public static function thumbUrl(string $key, int $width, int $height): string
    {
        if (self::isCloud()) {
            return self::driver(self::mode())->thumbUrl($key, $width, $height);
        }
        return self::local()->thumbUrl($key, $width, $height);
    }

    /**
     * 写入文件，返回最终对外 URL
     *
     * @throws StorageException 仅纯云模式写入失败时抛出；both 模式云同步失败只记 warning
     */
    public static function put(string $key, string $tmpFile, array $meta = []): StorageResult
    {
        $key  = ltrim(str_replace('\\', '/', $key), '/');
        $mode = self::mode();

        if ($mode !== 'local' && $mode !== 'both') {
            $result = self::driver($mode)->put($key, $tmpFile, $meta);
            Hook::fire('storage.after_put', $result);
            return $result;
        }

        // local / both：本地写成功即视为上传成功
        $local  = self::local();
        $result = $local->put($key, $tmpFile, $meta);

        if ($mode === 'both') {
            $cloud = self::activeCloud();
            $synced = true;
            $warning = '';
            if ($cloud !== null && $cloud->isConfigured()) {
                try {
                    // 本地 put 已经移动了临时文件，用落盘后的本地文件同步到云
                    $cloud->put($key, storage_path($key), $meta);
                } catch (\Throwable $e) {
                    $synced = false;
                    $warning = '云同步失败：' . $e->getMessage();
                    try {
                        logger('storage')->error('[storage] ' . $warning, ['key' => $key]);
                    } catch (\Throwable) {
                        // 日志不可用时不影响上传结果
                    }
                }
            } else {
                $synced = false;
                $warning = '云存储未配置完整，本次仅写入本地';
            }

            $publicUrl = (in_array((string) option('upload.public_base', 'cloud'), ['cloud', 'oss'], true) && $synced && $cloud !== null)
                ? $cloud->url($key)
                : self::localUrl($key);
            $result = new StorageResult(
                $key, $publicUrl, 'local', $result->size, $result->mime, $synced, $warning
            );
        }

        Hook::fire('storage.after_put', $result);
        return $result;
    }

    /** 删除文件或目录（目录递归删除） */
    public static function delete(string $key): bool
    {
        $key  = ltrim($key, '/');
        $mode = self::mode();
        $ok   = self::forFiles()->delete($key);

        if ($mode === 'both') {
            $cloud = self::activeCloud();
            if ($cloud !== null && $cloud->isConfigured()) {
                try {
                    $ok = $cloud->delete($key) || $ok;
                } catch (\Throwable) {
                    // 云删除失败不影响本地结果
                }
            }
        }

        Hook::fire('storage.after_delete', $key, $mode);
        return $ok;
    }

    public static function exists(string $key): bool
    {
        $key = ltrim($key, '/');
        return self::forFiles()->exists($key);
    }

    /** 列举目录内容（文件浏览） */
    public static function list(string $prefix = ''): array
    {
        return self::forFiles()->list(ltrim($prefix, '/'));
    }

    /** 新建目录 */
    public static function makeDirectory(string $key): bool
    {
        return self::forFiles()->makeDirectory(ltrim($key, '/'));
    }

    /** 重命名 / 移动（文件或目录） */
    public static function rename(string $from, string $to): bool
    {
        $from = ltrim(str_replace('\\', '/', $from), '/');
        $to   = ltrim(str_replace('\\', '/', $to), '/');
        $ok   = self::forFiles()->rename($from, $to);

        if ($ok && self::mode() === 'both') {
            $cloud = self::activeCloud();
            if ($cloud !== null && $cloud->isConfigured()) {
                try {
                    $cloud->rename($from, $to);
                } catch (\Throwable) {
                    // 云改名失败不阻塞本地结果
                }
            }
        }
        return $ok;
    }

    /** 移动（与 rename 等价，供媒体库语义调用） */
    public static function move(string $from, string $to): bool
    {
        return self::rename($from, $to);
    }

    /** 下载对象到本地文件 */
    public static function download(string $key, ?string $dest = null): ?string
    {
        return self::forFiles()->download(ltrim($key, '/'), $dest);
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
        if ($mode === 'both') {
            $mode = self::cloudDriver();
        }
        $driver = self::driver($mode);
        if (method_exists($driver, 'test')) {
            return $driver->test();
        }
        return ['ok' => false, 'msg' => '驱动不支持内置连接测试'];
    }

    /**
     * URL → storage key 逆解析
     *
     * DB 里存的是 URL 字符串（/storage/x.jpg、绝对 URL、OSS/COS/R2/CDN URL 混杂），
     * ThumbHelper / 删除 / 同步工具都靠它还原相对 key。
     * 非本站存储资源（外链、主题图片 /themes/...）返回 null。
     */
    public static function keyFromUrl($url): ?string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return null;
        }

        // 1) 本地 /storage/ URL（相对或站点绝对）
        $key = self::local()->keyFromUrl($url);
        if ($key !== null) {
            return $key;
        }

        // 2) 云驱动 URL（OSS / COS / R2 / CDN 域名）
        foreach (self::CLOUD_DRIVERS as $name) {
            $driver = self::driver($name);
            if (!$driver->isConfigured()) {
                continue;
            }
            $key = $driver->keyFromUrl($url);
            if ($key !== null && $key !== '') {
                return $key;
            }
        }

        // 3) 相对 key 直传 / 主题等系统资源过滤
        if (preg_match('#^/(?:themes|assets|app|var|vendor)/#i', $url)) {
            return null;
        }
        if (str_starts_with($url, '/')) {
            return null;
        }
        return ltrim($url, '/') ?: null;
    }
}
