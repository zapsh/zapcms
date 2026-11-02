<?php
/*
 * Copyright (c) 2023.  ZAP.CN  - ZAP CMS - All Rights Reserved
 * @author Allen
 * @email zapcms@zap.cn
 * @date 2023/12/27 上午11:46
 * @lastModified 2026/11/02
 *
 */

namespace zapcms\support;

use HTMLPurifier;
use HTMLPurifier_Config;

class HtmlXss
{
    /** @var array<string,HTMLPurifier> 按配置签名缓存的净化器实例，避免重复构造 */
    private static array $purifiers = [];

    /**
     * 净化 HTML 片段，去除 XSS 危险标签 / 属性。
     *
     * 适用于富文本正文（如文章 / 栏目内容）。若仅需转义纯文本（如表单 input 的 value），
     * 请用 escape()，避免把 HTML 净化结果塞进 HTML 属性导致引号逃逸。
     *
     * @param string|null $html
     * @param array       $extraConfig 覆盖 / 追加的 HTMLPurifier 配置项（如允许 iframe：['HTML.SafeIframe'=>true,'URI.SafeIframeRegexp'=>'%youtube%']）
     */
    public static function clean($html, array $extraConfig = []): string
    {
        $html = (string) $html;

        $config = HTMLPurifier_Config::createDefault();
        $config->set('Core.Encoding', 'UTF-8');

        $cacheDir = var_path('cache/htmlpurifier');
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0755, true);
        }
        if (is_dir($cacheDir) && is_writable($cacheDir)) {
            $config->set('Cache.SerializerPath', $cacheDir);
        }

        foreach ($extraConfig as $key => $value) {
            $config->set($key, $value);
        }

        $key = md5(serialize($config->getAll()));
        if (!isset(self::$purifiers[$key])) {
            self::$purifiers[$key] = new HTMLPurifier($config);
        }

        return self::$purifiers[$key]->purify($html);
    }

    /**
     * 纯文本转义（用于 HTML 属性、普通文本节点），等价于
     * htmlspecialchars(ENT_QUOTES | ENT_HTML5, UTF-8)。
     *
     * 与 clean() 不同：不解析 HTML，只做字符转义，安全用于 input value / title 等属性场景。
     */
    public static function escape($text): string
    {
        return htmlspecialchars((string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
