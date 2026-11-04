<?php
/*
 * Copyright (c) 2023-2026.  ZAP.CN  - ZAP CMS
 * PluginLoader - 插件 / 模块统一引导加载器
 *
 * 替代原先分散在 Bootstrap::loadModAutoloads 中的「菜单同步 + autoload 包含」逻辑，
 * 并在 前台 + 后台 的系统初始化阶段各跑一次（静态守卫保证同请求内幂等）。
 *
 * 职责：
 *  1. 菜单同步（仅后台，写 admin_menu 表，指纹幂等）——纯配置驱动，菜单定义来自
 *     mod.json/plugin.json 的 admin_menu，无需任何启动脚本；只注册菜单的扩展不会被 include。
 *  2. 引导脚本包含（仅对「声明了 boot / autoload」的扩展）
 *     - autoload（legacy，通常含 IN_ZAPCMS_ADMIN 守卫）→ 仅后台包含，避免前端触发守卫 die
 *     - boot          （新机制，前端安全）→ 前台 + 后台均包含，使插件可在系统初始化时注册钩子
 *     - boot_frontend （可选）→ 仅前台加载，如前台下单 / 履约钩子
 *     - boot_admin    （可选）→ 仅后台加载，如后台列表 / 表单扩展钩子
 *     三者按需声明，前后台分离可避免加载多余代码。
 *     未配置 boot 的扩展在启动时不做 include，也不触发 plugin.boot 事件。
 *  3. 所有引导脚本跑完后触发系统级 boot 事件（一次性）。
 *
 * 这样插件不再依赖 AdminHook 这类仅后台视图层机制，可在初始化阶段通过 Hook 总线
 * 注册事件 / 过滤器，介入前台、后台乃至 CLI 的行为。
 */

namespace zapcms\support;

use zap\facades\Cache;
use zapcms\Hook;
use zapcms\services\Option;

class PluginLoader
{
    /** options 键前缀 */
    const OPTION_PREFIX = 'mod.installed.';

    /**
     * 分组缓存 key：与 get_options('mod.installed.', 'REGEXP') 的
     * '_opts_' . 前缀 命名规则保持一致，便于统一失效。
     */
    const CACHE_KEY = '_opts_mod.installed.';

    /** 未配置 cache.ttl 时使用的兜底秒数 */
    const CACHE_TTL_FALLBACK = 10000;

    /** @var array<string,string>|null 请求级缓存 */
    private static ?array $installedCache = null;

    /** @var bool 同请求内只引导一次 */
    private static bool $booted = false;

    /**
     * 读取已安装模块的 options 快照（跨请求缓存，避免每次请求都查 options 表）。
     *
     * 缓存值统一存 JSON 字符串：FileCache 可序列化任意类型，但 RedisCache 的
     * set()/get() 只能处理字符串，直接存数组会在 Redis 站点上导致数据损坏。
     * 缓存缺失、损坏或驱动故障时回源查库；options 表不可用时异常继续向外抛，
     * 由 bootAll() 静默跳过，保持原有安装期/表损坏时的行为。
     *
     * @return array<string,string> option_name => 安装快照 JSON 文本
     */
    public static function installedMods(): array
    {
        if (self::$installedCache !== null) {
            return self::$installedCache;
        }

        $ttl = (int) config('cache.ttl', 0);
        if ($ttl <= 0) {
            $ttl = self::CACHE_TTL_FALLBACK;
        }

        $rows = null;
        try {
            $cached = Cache::get(self::CACHE_KEY);
            if (is_string($cached) && $cached !== '') {
                $decoded = json_decode($cached, true);
                if (is_array($decoded)) {
                    $rows = $decoded;
                }
            }
        } catch (\Throwable $e) {
            // 缓存驱动不可用：忽略，直接回源
        }

        if ($rows === null) {
            // DB 异常继续向外抛，由 bootAll() 静默跳过
            $rows = Option::getArray(self::OPTION_PREFIX, 'REGEXP');
            try {
                Cache::set(self::CACHE_KEY, json_encode($rows, JSON_UNESCAPED_UNICODE), $ttl);
            } catch (\Throwable $e) {
                // 写缓存失败不影响本次引导
            }
        }

        return self::$installedCache = $rows;
    }

    /**
     * 使已安装模块快照缓存失效。
     *
     * 安装 / 卸载 / 启用 / 禁用 / 更新模块后必须调用，否则下一个请求
     * 仍按旧列表引导：启停不生效、卸载后引导脚本仍可能被 include。
     */
    public static function forgetCache(): void
    {
        self::$installedCache = null;

        try {
            Cache::delete(self::CACHE_KEY);
            if (function_exists('app') && app() !== null) {
                // option('mod.*') 的请求级分组缓存（App 实现了 ArrayAccess）
                app()->offsetUnset('options_mod');
            }
        } catch (\Throwable $e) {
            // 缓存不可用时忽略：下次请求会回源查库，只是少了缓存
        }
    }

    public static function bootAll(): void
    {
        if (self::$booted) {
            return;
        }
        self::$booted = true;

        try {
            $rows = self::installedMods();
        } catch (\Throwable $e) {
            return; // options 表不可用时静默跳过
        }

        $isAdmin = defined('IN_ZAPCMS_ADMIN');

        foreach ($rows as $key => $json) {
            $name = substr($key, strlen(self::OPTION_PREFIX));
            $info = is_string($json) ? (json_decode($json, true) ?: []) : [];
            if (empty($info['status'])) {
                continue;
            }

            // 引导脚本以模块目录内的 mod.json 为准：options 里的安装快照可能早于
            // boot / boot_frontend / boot_admin 字段的加入，导致引导脚本永远不加载。
            $modJsonFile = APP_ROOT . '/mods/' . $name . '/mod.json';
            if (is_file($modJsonFile)) {
                $fileInfo = json_decode((string) file_get_contents($modJsonFile), true) ?: [];
                foreach (['boot', 'boot_frontend', 'boot_admin', 'autoload'] as $bootKey) {
                    if (empty($info[$bootKey]) && !empty($fileInfo[$bootKey])) {
                        $info[$bootKey] = $fileInfo[$bootKey];
                    }
                }
            }

            // 1. 菜单同步（仅后台，指纹幂等）。纯配置驱动，与启动脚本无关，
            //    只注册菜单、未配置 boot 的扩展也能正常显示后台菜单。
            if ($isAdmin) {
                try {
                    ModMenu::sync($name, $info);
                } catch (\Throwable $e) {
                    // 菜单同步失败不影响启动
                }
            }

            // 2. 仅当扩展声明了引导脚本时才在启动时 include，
            //    只注册菜单、未配置 boot 的扩展无需 include（也不触发 plugin.boot）。
            $bootScript = null;

            // 2a. legacy autoload：仅后台包含（脚本常含 IN_ZAPCMS_ADMIN 守卫）
            if ($isAdmin && !empty($info['autoload'])) {
                $candidate = APP_ROOT . '/mods/' . $name . '/' . $info['autoload'];
                if (is_file($candidate)) {
                    $bootScript = $candidate;
                }
            }

            // 2b. 前后台分离引导脚本（优化项）：仅加载当前上下文所需的脚本，避免加载多余代码。
            //     boot_admin    —— 仅后台加载（如注册后台列表 / 表单扩展钩子）
            //     boot_frontend —— 仅前台加载（如注册前台下单 / 履约钩子）
            if ($bootScript === null) {
                $candidate = null;
                if ($isAdmin && !empty($info['boot_admin'])) {
                    $candidate = APP_ROOT . '/mods/' . $name . '/' . $info['boot_admin'];
                } elseif (!$isAdmin && !empty($info['boot_frontend'])) {
                    $candidate = APP_ROOT . '/mods/' . $name . '/' . $info['boot_frontend'];
                }
                if ($candidate !== null && is_file($candidate)) {
                    $bootScript = $candidate;
                }
            }

            // 2c. 通用 boot：前后台均包含（兼容旧配置，或放置前后台都要注册的钩子）
            if ($bootScript === null && !empty($info['boot'])) {
                $candidate = APP_ROOT . '/mods/' . $name . '/' . $info['boot'];
                if (is_file($candidate)) {
                    $bootScript = $candidate;
                }
            }

            if ($bootScript !== null) {
                defined('IN_ZAPCMS') or define('IN_ZAPCMS', true);
                include_once $bootScript;
                Hook::fire('plugin.boot', $name, $info);
            }
        }

        // 所有插件引导完成后触发系统级 boot 事件（此时回调均已注册）
        Hook::fire('boot');
    }
}
