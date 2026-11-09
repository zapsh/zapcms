<?php

namespace zapcms\services;

use Throwable;
use zap\facades\Cache;
use zap\i18n\Language;

/**
 * 多语言（站点级）服务。
 *
 * 职责：
 *   - 维护「可用语言列表 / 默认语言」（持久化在 options 表）
 *   - 解析当前请求语言（URL 前缀 > 会话 > Accept-Language > 默认语言）
 *   - 提供 URL 语言前缀相关的辅助方法
 *
 * 语言代码统一采用 BCP-47 连字符形式：zh-CN / en / ja。
 */
class Locale
{
    /** 语言存放的 option key */
    const OPTION_AVAILABLE = 'languages.available';
    /** 默认语言存放的 option key */
    const OPTION_DEFAULT = 'languages.default';
    /** 无译文处理策略存放的 option key（旧版统一策略，仅作兼容兜底） */
    const OPTION_STRATEGY = 'languages.missing_strategy';
    /** 内容（node）无译文策略存放的 option key */
    const OPTION_NODE_STRATEGY = 'languages.node_strategy';
    /** 栏目（catalog）无译文策略存放的 option key */
    const OPTION_CATALOG_STRATEGY = 'languages.catalog_strategy';

    /** 未配置时使用的语言 */
    const FALLBACK_LOCALE = 'zh-CN';

    /** 会话/配置文件里的键名 */
    const SESSION_KEY = 'zap_locale';

    /** 未配置 cache.ttl 时使用的兜底秒数（与 get_options / NodeType 保持一致） */
    const CACHE_TTL_FALLBACK = 10000;

    /**
     * 分组缓存 key（option('languages.*') 使用），保存语言设置时需要一并失效
     */
    const CACHE_GROUP_KEY = '_opts_languages';
    /** 请求容器里 option('languages.*') 的分组键 */
    const OPTION_GROUP_CONTAINER_KEY = 'options_languages';
    /**
     * 「可用语言列表 + 默认语言」合并缓存 key（单条 SQL 同时取回两项，
     * 未开启多语言时判断 enabled() 只需要这 1 次查询）
     */
    const CONFIG_CACHE_KEY = 'languages.config';

    /**
     * 常用语言名称字典（code => [中文名, 本地语言名]）
     */
    protected static array $knownLocales = [
        'zh-CN' => ['简体中文', '简体中文'],
        'zh-TW' => ['繁体中文', '繁體中文'],
        'zh-HK' => ['繁体中文(香港)', '繁體中文（香港）'],
        'en'    => ['英语', 'English'],
        'en-US' => ['英语(美国)', 'English (US)'],
        'en-GB' => ['英语(英国)', 'English (UK)'],
        'ja'    => ['日语', '日本語'],
        'ko'    => ['韩语', '한국어'],
        'fr'    => ['法语', 'Français'],
        'de'    => ['德语', 'Deutsch'],
        'es'    => ['西班牙语', 'Español'],
        'pt'    => ['葡萄牙语', 'Português'],
        'pt-BR' => ['葡萄牙语(巴西)', 'Português (Brasil)'],
        'ru'    => ['俄语', 'Русский'],
        'it'    => ['意大利语', 'Italiano'],
        'nl'    => ['荷兰语', 'Nederlands'],
        'pl'    => ['波兰语', 'Polski'],
        'tr'    => ['土耳其语', 'Türkçe'],
        'ar'    => ['阿拉伯语', 'العربية'],
        'th'    => ['泰语', 'ไทย'],
        'vi'    => ['越南语', 'Tiếng Việt'],
        'id'    => ['印尼语', 'Bahasa Indonesia'],
        'hi'    => ['印地语', 'हिन्दी'],
        'fa'    => ['波斯语', 'فارسی'],
    ];

    /** @var array|null 请求级缓存 */
    protected static ?array $availableCache = null;
    /** @var string|null 请求级缓存 */
    protected static ?string $defaultCache = null;
    /** @var string|null 当前语言 */
    protected static ?string $current = null;
    /** @var array|null 请求级合并配置缓存：[availableRaw, defaultRaw] */
    protected static ?array $configCache = null;

    // ===================== 读取配置 =====================

    /**
     * 规范化语言代码
     */
    public static function normalize(string $locale): string
    {
        return Language::normalizeLocale($locale);
    }

    /**
     * 读取语言配置（跨请求缓存，避免每次请求都查 options 表）。
     *
     * Cache::get 的第二参为 callable 时，缓存未命中会执行回调并把结果写回；
     * 缓存 key 直接使用 option 名，与 get_option() 共用，forgetCache() 也按同名删除。
     * option('languages.*') 另走 _opts_languages 分组缓存，由 forgetCache() 单独失效。
     * 缓存驱动不可用（Redis 未启动、缓存目录不可写等）时退回直查数据库，
     * 保证多语言功能不会因缓存故障而整体不可用。
     */
    protected static function cachedOption(string $key, string $default = ''): string
    {
        $ttl = (int) config('cache.ttl', 0);
        if ($ttl <= 0) {
            $ttl = static::CACHE_TTL_FALLBACK;
        }

        try {
            $value = Cache::get($key, static function () use ($key, $default) {
                return Option::get($key, $default);
            }, $ttl);
        } catch (Throwable $e) {
            return (string) Option::get($key, $default);
        }

        // 缓存内容异常（例如 callable 返回 null 时 FileCache 会把闭包本身返回）时回落
        return is_string($value) ? $value : $default;
    }

    /**
     * 一次查询同时取回「可用语言列表 + 默认语言」两项原始配置。
     *
     * 未开启多语言的站点判断 Locale::enabled() 只需要这 1 条 SQL
     * （此前 available + default 各查一次）；持久缓存命中时 0 次查询。
     *
     * @return string[] [availableRaw, defaultRaw]
     */
    protected static function configPair(): array
    {
        if (static::$configCache !== null) {
            return static::$configCache;
        }

        $load = static function (): array {
            try {
                $rows = Option::getArray([static::OPTION_AVAILABLE, static::OPTION_DEFAULT]);
            } catch (Throwable $e) {
                $rows = [];
            }
            return [
                (string) ($rows[static::OPTION_AVAILABLE] ?? ''),
                (string) ($rows[static::OPTION_DEFAULT] ?? ''),
            ];
        };

        try {
            $ttl = (int) config('cache.ttl', 0);
            if ($ttl <= 0) {
                $ttl = static::CACHE_TTL_FALLBACK;
            }
            $pair = Cache::get(static::CONFIG_CACHE_KEY, $load, $ttl);
        } catch (Throwable $e) {
            // 缓存不可用时退回直查数据库
            $pair = $load();
        }

        if (!is_array($pair) || count($pair) !== 2) {
            $pair = $load();
        }

        return static::$configCache = $pair;
    }

    /**
     * 可用语言列表（已规范化、去重、保持顺序）
     *
     * @return string[]
     */
    public static function available(): array
    {
        if (static::$availableCache !== null) {
            return static::$availableCache;
        }

        $locales = [];

        // 1) options 表后台配置优先（与默认语言合并为单条查询）
        foreach (static::readRawList(static::configPair()[0]) as $code) {
            $locales[] = $code;
        }

        // 2) 回落到 config/config.php 的 available_languages
        if ($locales === []) {
            $config = config('config.available_languages', []);
            if (is_array($config)) {
                foreach ($config as $code) {
                    $locales[] = static::normalize((string) $code);
                }
            }
        }

        $locales = array_values(array_unique(array_filter($locales, 'strlen')));

        // 默认语言一定在列表里
        $default = static::defaultLocale();
        if (!in_array($default, $locales, true)) {
            array_unshift($locales, $default);
        }

        return static::$availableCache = $locales;
    }

    /**
     * 默认语言（不带 URL 前缀的那种）
     */
    public static function defaultLocale(): string
    {
        if (static::$defaultCache !== null) {
            return static::$defaultCache;
        }

        $default = '';
        try {
            $default = static::normalize(static::configPair()[1]);
        } catch (Throwable $e) {
            $default = '';
        }

        if ($default === '') {
            $default = static::normalize((string) config('config.fallback_locale', static::FALLBACK_LOCALE));
        }
        if ($default === '') {
            $default = static::FALLBACK_LOCALE;
        }

        $available = static::$availableCache;
        if ($available === null) {
            return static::$defaultCache = $default;
        }

        // 默认语言必须在可用列表内
        if (!in_array($default, $available, true) && $available !== []) {
            $default = $available[0];
        }

        return static::$defaultCache = $default;
    }

    /**
     * 是否启用了多语言（启用后非默认语言才需要 URL 前缀）
     */
    public static function enabled(): bool
    {
        return count(static::available()) > 1;
    }

    /**
     * 无译文处理策略（旧版统一 key）：fallback = 回退默认语言原文；hide = 该内容不显示。
     *
     * 现已拆分为 nodeStrategy() / catalogStrategy()，本方法仅在这两者
     * 未单独配置时作为兼容兜底，保证升级后行为不变。
     */
    public static function missingStrategy(): string
    {
        try {
            $value = trim(static::cachedOption(static::OPTION_STRATEGY, 'fallback'));
        } catch (Throwable $e) {
            return 'fallback';
        }
        return $value === 'hide' ? 'hide' : 'fallback';
    }

    /**
     * 内容（node）无译文策略：fallback / hide
     */
    public static function nodeStrategy(): string
    {
        return static::scopeStrategy(static::OPTION_NODE_STRATEGY);
    }

    /**
     * 栏目（catalog）无译文策略：fallback / hide
     */
    public static function catalogStrategy(): string
    {
        return static::scopeStrategy(static::OPTION_CATALOG_STRATEGY);
    }

    /**
     * 读取作用域策略；未单独配置时沿用旧版统一策略
     */
    protected static function scopeStrategy(string $key): string
    {
        try {
            $value = trim(static::cachedOption($key, ''));
        } catch (Throwable $e) {
            return 'fallback';
        }
        if ($value === '') {
            return static::missingStrategy();
        }
        return $value === 'hide' ? 'hide' : 'fallback';
    }

    /**
     * 语言是否可用
     */
    public static function isValid(string $locale): bool
    {
        $locale = static::normalize($locale);
        return $locale !== '' && in_array($locale, static::available(), true);
    }

    /**
     * 需要出现在 URL 前缀里的语言（不含默认语言）
     *
     * @return string[]
     */
    public static function prefixed(): array
    {
        if (!static::enabled()) {
            return [];
        }
        $default = static::defaultLocale();
        return array_values(array_filter(static::available(), static function ($code) use ($default) {
            return $code !== $default;
        }));
    }

    // ===================== 语言元数据 =====================

    /**
     * 全部语言的展示信息
     *
     * @return array{code:string,title:string,native:string,is_default:bool}[]
     */
    public static function all(): array
    {
        $default = static::defaultLocale();
        $result = [];
        foreach (static::available() as $code) {
            $result[] = [
                'code'       => $code,
                'title'      => static::name($code),
                'native'     => static::nativeName($code),
                'is_default' => $code === $default,
            ];
        }
        return $result;
    }

    /**
     * 语言的中文名
     */
    public static function name(string $locale): string
    {
        $locale = static::normalize($locale);
        return static::$knownLocales[$locale][0] ?? $locale;
    }

    /**
     * 语言的本地名
     */
    public static function nativeName(string $locale): string
    {
        $locale = static::normalize($locale);
        return static::$knownLocales[$locale][1] ?? $locale;
    }

    /**
     * 全部已知语言（后台语言设置页用）
     *
     * @return string[]
     */
    public static function knownCodes(): array
    {
        return array_keys(static::$knownLocales);
    }

    /**
     * html lang 属性值
     */
    public static function htmlLang(?string $locale = null): string
    {
        return strtolower(str_replace('_', '-', static::normalize($locale ?? static::current())));
    }

    // ===================== 当前语言 =====================

    /**
     * 当前语言
     */
    public static function current(): string
    {
        if (static::$current !== null) {
            return static::$current;
        }

        if (!static::enabled()) {
            return static::setCurrent(static::defaultLocale());
        }

        if (PHP_SAPI !== 'cli') {
            $locale = static::fromPath();
            if ($locale !== '') {
                return static::setCurrent($locale);
            }

            $session = get_session(static::SESSION_KEY, '');
            if (is_string($session) && static::isValid($session)) {
                return static::setCurrent($session);
            }
        }

        return static::setCurrent(static::negotiate());
    }

    /**
     * 设置当前语言
     */
    public static function setCurrent(string $locale): string
    {
        $locale = static::normalize($locale);
        if (!static::isValid($locale)) {
            $locale = static::defaultLocale();
        }
        static::$current = $locale;
        static::sync($locale);
        if (PHP_SAPI !== 'cli') {
            try {
                set_session(static::SESSION_KEY, $locale);
            } catch (Throwable $e) {
                // 会话不可用时忽略
            }
        }
        return $locale;
    }

    /**
     * 把当前语言同步给框架 i18n 组件
     */
    protected static function sync(string $locale): void
    {
        // 后台 UI 语言文件目录（app/zapcms/languages/{locale}/admin.php）
        $langPath = realpath(__DIR__ . '/../languages');
        if ($langPath !== false) {
            Language::addPath($langPath);
        }

        Language::locale($locale);
        Language::fallback(static::defaultLocale());
        config_set('config.lang', $locale);
    }

    /**
     * 默认语言是否为当前语言
     */
    public static function isDefault(?string $locale = null): bool
    {
        return ($locale ?? static::current()) === static::defaultLocale();
    }

    /**
     * 从请求路径里提取语言前缀
     */
    public static function fromPath(?string $path = null): string
    {
        $prefixes = static::prefixed();
        if ($prefixes === []) {
            return '';
        }

        if ($path === null) {
            $path = static::requestPath();
        }
        $path = trim((string) $path, '/');
        if ($path === '') {
            return '';
        }

        $first = explode('/', $path)[0];
        foreach ($prefixes as $code) {
            if (strcasecmp($first, $code) === 0) {
                return $code;
            }
        }
        return '';
    }

    /**
     * 当前请求路径（相对于站点根目录）
     */
    public static function requestPath(): string
    {
        if (PHP_SAPI === 'cli') {
            return '';
        }
        $uri = urldecode(strtok($_SERVER['REQUEST_URI'] ?? '/', '?') ?: '/');
        $base = rtrim((string) dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/');
        if ($base !== '' && $base !== '/' && str_starts_with($uri, $base)) {
            $uri = substr($uri, strlen($base));
        }
        return '/' . ltrim($uri, '/');
    }

    /**
     * 通过 Accept-Language 协商最合适的站点语言
     */
    public static function negotiate(?string $header = null): string
    {
        $available = static::available();
        $default = static::defaultLocale();

        if ($header === null && PHP_SAPI !== 'cli') {
            $header = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
        }
        if ($header === null || $header === '') {
            return $default;
        }

        $candidates = [];
        foreach (explode(',', $header) as $part) {
            $pieces = explode(';q=', trim($part));
            $tag = $pieces[0] ?? '';
            $quality = isset($pieces[1]) ? (float) $pieces[1] : 1.0;
            if ($tag !== '') {
                $candidates[static::normalize($tag)] = $quality;
            }
        }
        // q 值高的优先；同 q 值保持原始顺序
        arsort($candidates, SORT_NUMERIC);

        foreach (array_keys($candidates) as $tag) {
            if (in_array($tag, $available, true)) {
                return $tag;
            }
            // 只写了主语言（en）但站点配了 en-US 之类
            $primary = explode('-', $tag)[0];
            foreach ($available as $code) {
                if (strcasecmp(explode('-', $code)[0], $primary) === 0) {
                    return $code;
                }
            }
        }

        return $default;
    }

    // ===================== 写入配置 =====================

    /**
     * 保存语言设置
     *
     * @param string[]    $locales
     * @param string|null $default
     * @param string|null $nodeStrategy    内容无译文策略（fallback / hide）；null = 保持不变
     * @param string|null $catalogStrategy 栏目无译文策略（fallback / hide）；null = 保持不变
     * @return string[] 实际生效的可用语言
     */
    public static function save(array $locales, ?string $default = null, ?string $nodeStrategy = null, ?string $catalogStrategy = null): array
    {
        $clean = [];
        foreach ($locales as $code) {
            $code = static::normalize((string) $code);
            if ($code !== '' && !in_array($code, $clean, true)) {
                $clean[] = $code;
            }
        }

        $default = static::normalize((string) ($default ?: ($clean[0] ?? static::FALLBACK_LOCALE)));
        if ($clean === []) {
            $clean[] = $default;
        }
        if (!in_array($default, $clean, true)) {
            array_unshift($clean, $default);
        }

        Option::replace(static::OPTION_AVAILABLE, json_encode($clean, JSON_UNESCAPED_UNICODE), 0, 1);
        Option::replace(static::OPTION_DEFAULT, $default, 0, 1);
        foreach ([
            static::OPTION_NODE_STRATEGY    => $nodeStrategy,
            static::OPTION_CATALOG_STRATEGY => $catalogStrategy,
        ] as $key => $value) {
            if ($value !== null) {
                Option::replace($key, $value === 'hide' ? 'hide' : 'fallback', 0, 1);
            }
        }

        static::forgetCache();

        return $clean;
    }

    /**
     * 清除请求级 + 持久化的语言缓存
     */
    public static function forgetCache(): void
    {
        static::$availableCache = null;
        static::$defaultCache = null;
        static::$current = null;
        static::$configCache = null;

        try {
            // available + default 合并存放在 CONFIG_CACHE_KEY；旧版本按 option 名分别缓存，
            // 这里一并删除以兼容升级场景
            Cache::delete(static::CONFIG_CACHE_KEY);
            Cache::delete(static::OPTION_AVAILABLE);
            Cache::delete(static::OPTION_DEFAULT);
            Cache::delete(static::OPTION_STRATEGY);
            Cache::delete(static::OPTION_NODE_STRATEGY);
            Cache::delete(static::OPTION_CATALOG_STRATEGY);
            // option('languages.*') 走 get_options() 的分组缓存，也要失效
            Cache::delete(static::CACHE_GROUP_KEY);

            if (function_exists('app') && app() !== null) {
                // App 没有 remove()，但实现了 ArrayAccess，可清掉请求级分组缓存
                app()->offsetUnset(static::OPTION_GROUP_CONTAINER_KEY);
            }
        } catch (Throwable $e) {
            // 缓存不可用时忽略
        }
    }

    /**
     * 解析 JSON 数组形式的语言列表原始值
     *
     * @return string[]
     */
    protected static function readRawList(string $raw): array
    {
        if (!is_string($raw) || $raw === '') {
            return [];
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return [];
        }

        $list = [];
        foreach ($data as $code) {
            $code = static::normalize((string) $code);
            if ($code !== '') {
                $list[] = $code;
            }
        }
        return $list;
    }
}
