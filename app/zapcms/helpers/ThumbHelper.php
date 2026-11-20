<?php
/*
 * Copyright (c) 2023.  ZAP.CN  - ZAP CMS - All Rights Reserved
 * @author Allen
 * @email zapcms@zap.cn
 * @date 2023/12/27 上午11:30
 * @lastModified 2026/11/02
 *
 */

namespace zapcms\helpers;

use zap\image\Image;
use zapcms\services\storage\drivers\LocalStorage;
use zapcms\services\storage\StorageManager;

class ThumbHelper
{
    /**
     * 生成（或复用）指定尺寸的缩略图，返回可访问的 URL。
     *
     * $file 可以是 storage 相对 key、/storage 前缀路径、本站绝对 URL、
     * 或 OSS/CDN 绝对 URL —— 统一由 storage_key() 逆解析，因此
     * upload.url_mode=absolute 与 OSS 域名下的图片都能正确找到原图。
     *
     *  - local / both：本地 GD 生成 storage/thumbs/ 缓存；
     *  - oss：直接返回 OSS 图片处理参数 URL，不消耗服务器带宽。
     */
    public static function thumb($file, $width, $height): string
    {
        $key = storage_key($file);
        if ($key === null || $key === '') {
            return self::placeholderThumb($width, $height);
        }

        if (StorageManager::isCloud()) {
            return StorageManager::thumbUrl($key, (int) $width, (int) $height);
        }

        return self::localThumb($key, $width, $height);
    }

    /**
     * 强制走本地 GD 缩略图（媒体库「本地存储」tab 专用）：
     * 云存储模式下本地文件不存在云端对应对象，
     * 不能走 StorageManager::thumbUrl（会生成指向云端的无效 URL）。
     * $key 为 storage 相对 key。
     */
    public static function localThumb(string $key, $width, $height): string
    {
        if ($key === '') {
            return self::placeholderThumb($width, $height);
        }

        $thumbRel  = LocalStorage::thumbKey($key, (int) $width, (int) $height);
        $thumbPath = storage_path("thumbs/{$thumbRel}");
        if (is_file($thumbPath)) {
            return storage_url("thumbs/{$thumbRel}");
        }

        $source = storage_path($key);
        if (!is_file($source)) {
            return self::placeholderThumb($width, $height);
        }

        $dir = dirname($thumbPath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        try {
            Image::from($source)
                ->thumb($width, $height)
                ->saveFile($thumbPath);
        } catch (\Throwable $e) {
            // 图片无效 / 损坏 / GD 缺失：降级为占位图，避免中断页面渲染
            return self::placeholderThumb($width, $height);
        }

        return storage_url("thumbs/{$thumbRel}");
    }

    /**
     * 占位图缩略图：优先生成对应尺寸的占位缩略图，失败则退化为原尺寸占位图 URL。
     */
    private static function placeholderThumb($width, $height): string
    {
        $thumbRel  = "placeholder-{$width}x{$height}.jpg";
        $thumbPath = storage_path("thumbs/{$thumbRel}");
        if (is_file($thumbPath)) {
            return storage_url("thumbs/{$thumbRel}");
        }

        $placeholder = app()->basePath('/assets/images/placeholder.jpg');
        if (is_file($placeholder)) {
            try {
                Image::from($placeholder)
                    ->thumb($width, $height)
                    ->saveFile($thumbPath);
                return storage_url("thumbs/{$thumbRel}");
            } catch (\Throwable $e) {
                // 占位图缩略生成失败：返回原尺寸占位图
            }
        }

        return base_url('/assets/images/placeholder.jpg');
    }
}
