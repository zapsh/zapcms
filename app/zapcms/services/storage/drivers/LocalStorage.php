<?php

namespace zapcms\services\storage\drivers;

use zapcms\services\storage\StorageException;
use zapcms\services\storage\StorageInterface;
use zapcms\services\storage\StorageManager;
use zapcms\services\storage\StorageResult;

/** 本地磁盘存储（默认模式，行为与改造前一致） */
class LocalStorage implements StorageInterface
{
    public function isConfigured(): bool
    {
        return is_dir(storage_path());
    }

    public function put(string $key, string $tmpFile, array $meta = []): StorageResult
    {
        $dest = storage_path($key);
        $dir  = dirname($dest);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new StorageException("无法创建上传目录：{$dir}");
        }

        // HTTP 上传临时文件必须用 move_uploaded_file；普通本地文件用 rename/copy
        if (is_uploaded_file($tmpFile)) {
            $ok = @move_uploaded_file($tmpFile, $dest);
        } else {
            $ok = @rename($tmpFile, $dest) || (@copy($tmpFile, $dest) && @unlink($tmpFile));
        }
        if (!$ok) {
            throw new StorageException("文件写入失败：{$key}");
        }

        return new StorageResult(
            $key,
            StorageManager::localUrl($key),
            'local',
            (int) ($meta['size'] ?? (@filesize($dest) ?: 0)),
            (string) ($meta['mime'] ?? '')
        );
    }

    public function delete(string $key): bool
    {
        $path = storage_path($key);
        if (is_dir($path) && !is_link($path)) {
            return $this->removeDir($path);
        }
        return is_file($path) && @unlink($path);
    }

    public function exists(string $key): bool
    {
        return is_file(storage_path($key));
    }

    public function url(string $key): string
    {
        return StorageManager::localUrl($key);
    }

    /** 缩略图文件由 ThumbHelper 生成本地缓存，这里只负责给出与之一致的 URL */
    public function thumbUrl(string $key, int $width, int $height): string
    {
        return StorageManager::localUrl('thumbs/' . self::thumbKey($key, $width, $height));
    }

    public static function thumbKey(string $key, int $width, int $height): string
    {
        $info = pathinfo($key);
        $dir  = ($info['dirname'] ?? '.') === '.' ? '' : rtrim($info['dirname'], '/') . '/';
        return $dir . ($info['filename'] ?? '') . "-{$width}x{$height}." . ($info['extension'] ?? 'jpg');
    }

    public function list(string $prefix = ''): array
    {
        $abs   = $prefix === '' ? storage_path() : storage_path($prefix);
        $dirs  = [];
        $files = [];
        if (!is_dir($abs)) {
            return ['dirs' => $dirs, 'files' => $files];
        }
        $items = @scandir($abs);
        if ($items === false) {
            return ['dirs' => $dirs, 'files' => $files];
        }
        foreach ($items as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }
            $rel  = $prefix === '' ? $name : $prefix . '/' . $name;
            $full = $abs . '/' . $name;
            if (is_dir($full)) {
                $dirs[] = ['name' => $name, 'path' => $rel];
            } elseif (is_file($full)) {
                $files[] = [
                    'name'  => $name,
                    'path'  => $rel,
                    'size'  => (int) @filesize($full),
                    'mtime' => (int) @filemtime($full),
                    'ext'   => strtolower(pathinfo($name, PATHINFO_EXTENSION)),
                ];
            }
        }
        return ['dirs' => $dirs, 'files' => $files];
    }

    public function makeDirectory(string $key): bool
    {
        $dir = storage_path($key);
        if (is_dir($dir)) {
            return true;
        }
        return @mkdir($dir, 0777, true) && is_dir($dir);
    }

    public function rename(string $from, string $to): bool
    {
        $src = storage_path($from);
        $dst = storage_path($to);
        if (!file_exists($src) || file_exists($dst)) {
            return false;
        }
        $parent = dirname($dst);
        if (!is_dir($parent)) {
            @mkdir($parent, 0777, true);
        }
        return @rename($src, $dst);
    }

    public function download(string $key, ?string $dest = null): ?string
    {
        $src = storage_path($key);
        if (!is_file($src)) {
            return null;
        }
        $dest = $dest !== null ? $dest : @tempnam(sys_get_temp_dir(), 'zapdl_');
        if ($dest === false || $dest === '') {
            return null;
        }
        return @copy($src, $dest) ? $dest : null;
    }

    /** /storage/ 形式 URL（相对或站点绝对）反解为相对 key */
    public function keyFromUrl(string $url): ?string
    {
        $url = preg_replace('/[?#].*$/', '', trim($url));
        if ($url === '') {
            return null;
        }
        if (preg_match('#^https?://#i', $url)) {
            $host     = strtolower((string) parse_url($url, PHP_URL_HOST));
            $siteHost = strtolower((string) parse_url((string) get_site_base_url(), PHP_URL_HOST));
            $baseHost = strtolower((string) parse_url((string) base_url('/'), PHP_URL_HOST));
            if ($host !== $siteHost && $host !== $baseHost) {
                return null;
            }
        }
        if (preg_match('#(?:^|/)storage/(.+)$#i', $url, $m)) {
            return ltrim($m[1], '/');
        }
        return null;
    }

    /** 递归删除目录 */
    private function removeDir(string $dir): bool
    {
        $items = @scandir($dir);
        if ($items !== false) {
            foreach ($items as $name) {
                if ($name === '.' || $name === '..') {
                    continue;
                }
                $path = $dir . '/' . $name;
                if (is_dir($path) && !is_link($path)) {
                    $this->removeDir($path);
                } else {
                    @unlink($path);
                }
            }
        }
        return @rmdir($dir);
    }
}
