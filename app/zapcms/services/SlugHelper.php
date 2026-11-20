<?php
/*
 * Copyright (c) 2023.  ZAP.CN  - ZAP CMS - All Rights Reserved
 * @author Allen
 * @email zapcms@zap.cn
 * @date 2024/01/15
 *
 * Slug 生成辅助类
 * 支持可配置的分隔符、中文转拼音、百度翻译 API
 */

namespace zapcms\services;

use zap\util\Str;
use Overtrue\Pinyin\Pinyin;
use zapcms\services\storage\Secret;

class SlugHelper
{
    /**
     * 生成 Slug
     *
     * @param string      $text      原始文本（标题）
     * @param string|null $suffix    附加后缀（如 ID，确保唯一性）
     * @param string|null $style     覆写生成方式：'default'、'pinyin'、'translate'，null 使用配置
     * @param string|null $separator 覆写分隔符，null 使用配置
     * @return string
     */
    public static function generate(string $text, ?string $suffix = null, ?string $style = null, ?string $separator = null): string
    {
        $separator = $separator ?? option('slug.separator', '-');
        $style     = $style ?? option('slug.style', 'default');

        $text = strip_tags($text);
        $text = trim($text);

        if ($style === 'pinyin') {
            $text = self::toPinyin($text, $separator);
            $text = Str::slug($text, $separator);
        } elseif ($style === 'translate') {
            $text = self::toTranslate($text);
            $text = Str::slug($text, $separator);
        } else {
            // 默认模式：保留中文，清理特殊字符
            $text = Str::slug($text, $separator);
        }

        // 如果 slug 为空（极端情况），用 separator 填充防止空值
        if (empty($text)) {
            $text = 'post';
        }

        // 追加后缀（如 ID）
        if ($suffix !== null && $suffix !== '') {
            $text .= $separator . $suffix;
        }

        // 清理首尾分隔符
        $text = trim($text, $separator);

        // 限制最大长度（可选配置）
        $maxLength = (int) option('slug.max_length', 0);
        if ($maxLength > 0 && mb_strlen($text, 'UTF-8') > $maxLength) {
            $text = mb_substr($text, 0, $maxLength, 'UTF-8');
            $text = rtrim($text, $separator);
        }

        return $text;
    }

    /**
     * 中文转拼音
     */
    private static function toPinyin(string $text, string $separator = '-'): string
    {
        try {
            $pinyin = new Pinyin();
            return $pinyin->permalink($text, $separator);
        } catch (\Exception $e) {
            return $text;
        }
    }

    /**
     * 中文翻译为英文（百度翻译 API）
     */
    private static function toTranslate(string $text): string
    {
        $appid = option('slug.baidu_appid', '');
        $key   = (string) (Secret::get('slug.baidu_key') ?: option('slug.baidu_key', ''));

        if (empty($appid) || empty($key)) {
            return $text;
        }

        try {
            $salt   = (string) rand(10000, 99999);
            $sign   = md5($appid . $text . $salt . $key);
            $query  = urlencode($text);
            $url    = "http://api.fanyi.baidu.com/api/trans/vip/translate?q={$query}&from=auto&to=en&appid={$appid}&salt={$salt}&sign={$sign}";

            $ctx = stream_context_create([
                'http' => [
                    'timeout'    => 8,
                    'user_agent' => 'ZAPCMS/1.0',
                ],
            ]);

            $response = @file_get_contents($url, false, $ctx);
            if ($response === false) {
                return $text;
            }

            $result = json_decode($response, true);
            if (isset($result['trans_result'][0]['dst'])) {
                return $result['trans_result'][0]['dst'];
            }

            return $text;
        } catch (\Exception $e) {
            return $text;
        }
    }
}
