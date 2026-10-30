<?php

namespace zapcms\services;

use zap\facades\Cache;

/**
 * 缓存与运行残留的统计 / 清理。
 *
 * 清理范围与站点目录严格绑定，不接受外部传入的路径。
 */
class CacheManager
{
    /**
     * 可清理项定义。
     */
    public static function targets(): array
    {
        return [
            'data' => [
                'label' => '数据缓存',
                'desc'  => 'options、路由等运行时缓存，删除后自动重建',
                'icon'  => 'fa-database',
                'path'  => var_path('cache'),
            ],
            'template' => [
                'label' => '模板缓存',
                'desc'  => 'Twig 编译后的模板文件，删除后首次访问自动重编译',
                'icon'  => 'fa-file-code',
                'path'  => var_path('cache/twig'),
            ],
            'thumbs' => [
                'label' => '图片缩略图',
                'desc'  => '自动生成的缩略图，删除后按需重新生成',
                'icon'  => 'fa-image',
                'path'  => storage_path('thumbs'),
            ],
            'logs' => [
                'label' => '日志文件',
                'desc'  => '运行日志，删除后当日日志会重新创建',
                'icon'  => 'fa-file-lines',
                'path'  => var_path('logs'),
            ],
            'tmp' => [
                'label' => '临时文件',
                'desc'  => '升级包、解压残留等临时内容',
                'icon'  => 'fa-broom',
                'path'  => var_path('tmp'),
            ],
        ];
    }

    /**
     * 当前缓存驱动。
     */
    public static function driver(): string
    {
        return (string)config('cache.default', 'file');
    }

    /**
     * 缓存是否开启。
     */
    public static function enabled(): bool
    {
        return config('cache.status', 'disabled') === 'enabled';
    }

    /**
     * 各清理项的文件数 / 占用空间。
     *
     * @return array<string, array{files:int|null, bytes:int|null, exists:bool}>
     */
    public static function stats(): array
    {
        $cacheDir = var_path('cache');
        $twigDir  = var_path('cache/twig');

        $cacheTotal = self::measure($cacheDir);
        $twig       = self::measure($twigDir);
        $driver     = self::driver();

        $stats = [];

        foreach (self::targets() as $key => $target) {
            if ($key === 'data') {
                if ($driver === 'redis') {
                    // Redis 下的数据缓存不在文件系统，只能按前缀整体清除
                    $stats[$key] = ['files' => null, 'bytes' => null, 'exists' => true];
                } else {
                    $stats[$key] = [
                        'files'  => max(0, $cacheTotal['files'] - $twig['files']),
                        'bytes'  => max(0, $cacheTotal['bytes'] - $twig['bytes']),
                        'exists' => is_dir($cacheDir),
                    ];
                }
                continue;
            }

            if ($key === 'template') {
                $stats[$key] = [
                    'files'  => $twig['files'],
                    'bytes'  => $twig['bytes'],
                    'exists' => is_dir($twigDir),
                ];
                continue;
            }

            $measured = self::measure($target['path']);
            $stats[$key] = [
                'files'  => $measured['files'],
                'bytes'  => $measured['bytes'],
                'exists' => is_dir($target['path']),
            ];
        }

        return $stats;
    }

    /**
     * 执行清理。
     *
     * @param string $type cache（数据+模板）/ data / template / thumbs / logs / tmp / all
     *
     * @return array{removed:int, freed:int, failed:int, redis:bool}
     */
    public static function clear(string $type = 'cache'): array
    {
        $type   = strtolower(trim($type));
        $result = ['removed' => 0, 'freed' => 0, 'failed' => 0, 'redis' => false];

        switch ($type) {
            case 'all':
                foreach (array_keys(self::targets()) as $key) {
                    self::merge($result, self::clear($key));
                }
                return $result;

            case 'cache':
                self::merge($result, self::clear('data'));
                self::merge($result, self::clear('template'));
                return $result;

            case 'data':
                if (self::driver() === 'redis') {
                    Cache::store('redis')->clear();
                    $result['redis'] = true;
                    return $result;
                }
                self::purge(var_path('cache'), var_path('cache/twig'), $result);
                return $result;

            case 'template':
                self::purge(var_path('cache/twig'), null, $result);
                return $result;

            case 'thumbs':
                self::purge(storage_path('thumbs'), null, $result);
                return $result;

            case 'logs':
                self::purge(var_path('logs'), null, $result);
                return $result;

            case 'tmp':
                self::purge(var_path('tmp'), null, $result);
                return $result;
        }

        throw new \InvalidArgumentException('未知的清理类型：' . $type);
    }

    /**
     * 格式化字节数（视图与接口共用）。
     */
    public static function formatBytes(?int $bytes): string
    {
        if ($bytes === null) {
            return '—';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $value = (float)$bytes;
        $index = 0;

        while ($value >= 1024 && $index < 4) {
            $value /= 1024;
            $index++;
        }

        return ($index === 0 ? (string)(int)$value : number_format($value, 1)) . ' ' . $units[$index];
    }

    /**
     * 统计目录下的文件数与占用空间。
     */
    protected static function measure(string $path): array
    {
        if (!is_dir($path)) {
            return ['files' => 0, 'bytes' => 0];
        }

        $files = 0;
        $bytes = 0;

        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::LEAVES_ONLY
            );

            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $files++;
                    $bytes += (int)$file->getSize();
                }
            }
        } catch (\Throwable $e) {
            // 目录不可读时按 0 处理，不影响页面展示
        }

        return ['files' => $files, 'bytes' => $bytes];
    }

    /**
     * 删除目录内容（保留根目录），可通过 $exclude 跳过某个子目录。
     */
    protected static function purge(string $path, ?string $exclude, array &$result): void
    {
        if (!is_dir($path)) {
            return;
        }

        $exclude = $exclude === null ? null : rtrim(str_replace('\\', '/', $exclude), '/');

        try {
            $directory = new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS);

            $filter = new \RecursiveCallbackFilterIterator(
                $directory,
                static function (\SplFileInfo $current) use ($exclude): bool {
                    if ($exclude === null) {
                        return true;
                    }

                    return rtrim(str_replace('\\', '/', $current->getPathname()), '/') !== $exclude;
                }
            );

            $iterator = new \RecursiveIteratorIterator($filter, \RecursiveIteratorIterator::CHILD_FIRST);
        } catch (\Throwable $e) {
            return;
        }

        foreach ($iterator as $item) {
            /** @var \SplFileInfo $item */
            $current = $item->getPathname();

            if ($item->isDir()) {
                @rmdir($current);
                continue;
            }

            $size = (int)@$item->getSize();

            if (@unlink($current)) {
                $result['removed']++;
                $result['freed'] += $size;
            } else {
                // 被进程占用的文件（如正在写入的日志）跳过
                $result['failed']++;
            }
        }
    }

    /**
     * 合并多次清理的统计结果。
     */
    protected static function merge(array &$result, array $part): void
    {
        $result['removed'] += $part['removed'];
        $result['freed']   += $part['freed'];
        $result['failed']  += $part['failed'];
        $result['redis']    = $result['redis'] || $part['redis'];
    }
}
