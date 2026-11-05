<?php
/*
 * Copyright (c) 2023.  ZAP.CN  - ZAP CMS - All Rights Reserved
 * @author Allen
 * @email zapcms@zap.cn
 * @date 2023/12/27 上午11:28
 * @lastModified 2023/11/2 下午5:27
 *
 * Twig 扩展：把前台模板需要的 PHP 助手函数 / 全局对象暴露给 Twig。
 *
 * 说明：
 * - pageState 作为全局对象注入（模板里可用 pageState.xxx / pageState.method()）。
 * - 其余助手以 Twig 函数形式注册，模板直接调用，例如 {{ option('website.title') }}、
 *   {{ node_url(node) }}、{{ theme_url('css/style.css') }}、{{ thumb(src, w, h) }} 等。
 * - 返回 HTML 的函数（脚本、菜单、面包屑、Hook 片段）标记 is_safe=html，模板无需再写 |raw；
 *   而 option() 返回内容可能是纯文本也可能是 HTML，默认走自动转义，HTML 场景请显式 |raw。
 */

namespace zapcms\extensions\twig;

use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;
use Twig\TwigFunction;

class PageTwigExtension extends AbstractExtension implements GlobalsInterface
{
    public function getFunctions()
    {
        $html = ['is_safe' => ['html']];

        return [
            // 站点 / 主题资源
            new TwigFunction('theme_url', fn($path = null) => \theme_url($path), $html),
            new TwigFunction('base_url', fn($path = '') => \base_url($path), $html),
            new TwigFunction('home_url', fn() => \home_url(), $html),
            new TwigFunction('site_url', fn($path = '') => \site_url($path), $html),
            new TwigFunction('locale_site_url', fn($path = '', $locale = null) => \locale_site_url($path, $locale), $html),
            new TwigFunction('append_url_suffix', fn($url) => \append_url_suffix($url), $html),

            // 配置 / 选项
            new TwigFunction('option', fn(string $name, $default = null) => \option($name, $default)),
            new TwigFunction('option_json', fn(string $name, $default = null, ?bool $associative = null) => \option_get_json($name, $default, $associative)),
            new TwigFunction('option_array', fn(string $name, $default = []) => \option_get_array($name, $default)),

            // 节点链接
            new TwigFunction('node_url', fn(array $node, ?string $locale = null) => \smart_node_url($node, $locale), $html),
            new TwigFunction('smart_node_url', fn(array $node, ?string $locale = null) => \smart_node_url($node, $locale), $html),

            // 缩略图 / 内容模型配置
            new TwigFunction('thumb', fn($src, int $w, int $h) => \zapcms\helpers\ThumbHelper::thumb($src, $w, $h), $html),
            new TwigFunction('node_config', fn(string $mime, string $key, $default = null) => \zapcms\services\NodeType::getConfig($mime, $key, $default)),

            // 语言切换 / 面包屑
            new TwigFunction('render_language_switcher', fn(string $class = 'list-inline float-end m-0', bool $active = true) => \render_language_switcher($class, $active), $html),
            new TwigFunction('breadcrumb', function () {
                ob_start();
                \zapcms\services\BreadCrumb::instance()->display();
                return ob_get_clean();
            }, $html),

            // 资源注册输出（echo，由 Twig render 的 ob 捕获）
            new TwigFunction('print_styles', fn() => \print_styles(), $html),
            new TwigFunction('print_scripts', fn($position = \ASSETS_BODY) => \print_scripts($position), $html),

            // Hook 集成（mall 高级搜索 / SKU / 价格区间等）
            new TwigFunction('hook', fn(string $tag, ...$args) => \zapcms\Hook::fire($tag, ...$args), $html),
            new TwigFunction('apply_filters', fn(string $tag, $value, ...$args) => \zapcms\Hook::applyFilters($tag, $value, ...$args), $html),

            // 杂项
            new TwigFunction('html_lang', fn() => \zapcms\services\Locale::htmlLang()),
            new TwigFunction('number_format', fn($value, int $decimals = 2) => number_format((float) $value, $decimals)),
        ];
    }

    public function getGlobals(): array
    {
        return [
            'pageState' => pageState(),
            'now'       => new \DateTime(),
        ];
    }
}
