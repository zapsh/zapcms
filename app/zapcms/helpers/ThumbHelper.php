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
use zap\util\Str;

class ThumbHelper
{
    /**
     * 生成（或复用）指定尺寸的缩略图，返回可访问的 URL。
     *
     * 约定：
     *   - $file 可为 storage 相对路径（如 2024/foo.jpg）、带 /storage 前缀的路径、或空/null。
     *   - 缩略图保存在 storage/thumbs/ 下，沿用原图相对目录，命名为 {文件名}-{宽}x{高}.{扩展名}。
     *   - 原图不存在或已损坏时，降级返回占位图（优先对应尺寸的占位缩略图）。
     *
     * @param string|null $file
     */
    public static function thumb($file, $width, $height): string
    {
        $file = (string) $file;

        // 归一化为 storage 相对路径：去掉可能的 /storage 前缀与开头斜杠
        if (Str::startsWith($file, '/storage')) {
            $file = substr($file, 8); // strlen('/storage') === 8
        }
        $file = ltrim($file, '/\\');

        // 空路径无法定位原图，直接走占位图
        if ($file === '') {
            return self::placeholderThumb($width, $height);
        }

        $info     = pathinfo($file);
        $dir      = ($info['dirname'] ?? '.') === '.' ? '' : rtrim($info['dirname'], '/') . '/';
        $name     = $info['filename'] ?? '';
        $ext      = $info['extension'] ?? 'jpg';
        $thumbRel = $dir . $name . "-{$width}x{$height}.{$ext}";

        $thumbPath = storage_path("thumbs/{$thumbRel}");
        if (is_file($thumbPath)) {
            return storage_url("thumbs/{$thumbRel}");
        }

        $source = storage_path($file);
        if (!is_file($source)) {
            return self::placeholderThumb($width, $height);
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
