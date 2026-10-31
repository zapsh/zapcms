<?php
/*
 * Copyright (c) 2023-2026.  ZAP.CN  - ZAP CMS
 * Hook - 统一钩子 / 事件总线（前台 + 后台 + CLI 通用）
 *
 * 两种语义：
 *  - 事件（action）：Hook::on('tag', $cb);  Hook::fire('tag', ...$args);
 *                   回调可 echo 或直接做副作用；fire 拼接并返回各回调的字符串返回值
 *                   （兼容视图注入场景，如 AdminHook::echo）。
 *  - 过滤器（filter）：Hook::filter('tag', $value, ...$args);
 *                    依次将 $value 交给回调处理并返回最终值。
 *
 * 系统生命周期标签（由框架在对应时机 fire）：
 *   boot          系统初始化完成、各插件引导脚本均已执行后触发（一次性）
 *   plugin.boot   每个插件引导完成后触发，回调收到 ($name, $info)
 *   request       请求开始（前台/后台）
 *   before_render / after_render  视图渲染前后
 *   shutdown      请求结束
 *
 * 业务领域事件（由对应模块在业务点 fire）：
 *   content.save / user.login / menu.build 等
 *
 * AdminHook 已桥接本服务：AdminHook::on('admin_head', ...) 等价于 Hook::on('admin_head', ...)，
 * 主题与插件可用同一总线扩展，且可在系统初始化阶段注册钩子。
 */

namespace zapcms;

class Hook
{
    /** @var array<string, array<int, array<int, callable>>> 事件注册表：tag => 优先级 => 回调列表 */
    private static array $events = [];

    /** @var array<string, array<int, array<int, callable>>> 过滤器注册表：tag => 优先级 => 回调列表 */
    private static array $filters = [];

    /**
     * 注册事件回调（action）
     */
    public static function on(string $tag, callable $callback, int $priority = 10): void
    {
        self::$events[$tag][$priority][] = $callback;
    }

    /**
     * 注册过滤器回调（filter）
     */
    public static function filter(string $tag, callable $callback, int $priority = 10): void
    {
        self::$filters[$tag][$priority][] = $callback;
    }

    /**
     * 触发事件：按优先级执行，拼接并返回各回调的字符串返回值（兼容视图注入）。
     */
    public static function fire(string $tag, ...$args): string
    {
        $output = '';
        if (empty(self::$events[$tag])) {
            return $output;
        }
        $groups = self::$events[$tag];
        ksort($groups);
        foreach ($groups as $group) {
            foreach ($group as $callback) {
                $result = $callback(...$args);
                if (is_string($result)) {
                    $output .= $result;
                }
            }
        }
        return $output;
    }

    /**
     * 应用过滤器：依次将 $value 交给回调处理并返回最终值。
     */
    public static function applyFilters(string $tag, $value, ...$args)
    {
        if (empty(self::$filters[$tag])) {
            return $value;
        }
        $groups = self::$filters[$tag];
        ksort($groups);
        foreach ($groups as $group) {
            foreach ($group as $callback) {
                $value = $callback($value, ...$args);
            }
        }
        return $value;
    }

    /**
     * 检查标签是否已注册回调（事件或过滤器）。
     */
    public static function has(string $tag): bool
    {
        return !empty(self::$events[$tag]) || !empty(self::$filters[$tag]);
    }

    /**
     * 移除回调（主要用于测试）。不传 $tag 则清空全部。
     */
    public static function clear(string $tag = null): void
    {
        if ($tag === null) {
            self::$events  = [];
            self::$filters = [];
            return;
        }
        unset(self::$events[$tag], self::$filters[$tag]);
    }
}
