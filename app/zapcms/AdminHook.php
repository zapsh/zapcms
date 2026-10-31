<?php
/*
 * Copyright (c) 2023-2026.  ZAP.CN  - ZAP CMS
 * AdminHook - 主题后台钩子（兼容层，已桥接至统一钩子总线 zapcms\Hook）
 *
 * 用法（保持不变）:
 *   // 在主题 admin/functions.php 中注册
 *   \zapcms\AdminHook::on('admin_head', function() { echo '<link ...>'; });
 *
 *   // 在布局中触发出力
 *   \zapcms\AdminHook::echo('admin_head');
 *
 * 说明：AdminHook 现仅作为 Hook 的便捷别称。AdminHook::on('admin_head', ...)
 * 等价于 Hook::on('admin_head', ...)，主题与插件共用同一总线，且可在系统
 * 初始化阶段（boot）注册，不再局限于后台视图层。
 */

namespace zapcms;

class AdminHook
{
    /**
     * 注册一个钩子回调（委托给统一 Hook 总线）
     */
    public static function on(string $hook, callable $callback, int $priority = 10): void
    {
        Hook::on($hook, $callback, $priority);
    }

    /**
     * 触发钩子（按优先级排序执行，返回所有输出的拼接）
     */
    public static function fire(string $hook): string
    {
        return Hook::fire($hook);
    }

    /**
     * 直接 echo 输出钩子（用于模板中）
     */
    public static function echo(string $hook): void
    {
        echo Hook::fire($hook);
    }

    /**
     * 检查是否有已注册的回调
     */
    public static function has(string $hook): bool
    {
        return Hook::has($hook);
    }

    /**
     * 清除所有钩子（主要用于测试）
     */
    public static function clear(): void
    {
        Hook::clear();
    }
}
