<?php

namespace zapcms\services\Storage\Drivers;

use zapcms\services\Storage\StorageException;
use zapcms\services\Storage\StorageInterface;
use zapcms\services\Storage\StorageManager;
use zapcms\services\Storage\StorageResult;

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
        $file = storage_path($key);
        return is_file($file) && @unlink($file);
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
}
