<?php
/*
 * Copyright (c) 2023-2026.  ZAP.CN  - ZAP CMS
 * AdminHook - 主题后台钩子系统
 *
 * 用法:
 *   // 在主题 admin/functions.php 中注册
 *   \zapcms\AdminHook::on('admin_head', function() { echo '<link ...>'; });
 *
 *   // 在布局中触发出力
 *   \zapcms\AdminHook::fire('admin_head');
 */

namespace zapcms;

class AdminHook
{
    /** @var array<string, array<int, array<int, callable>>> 钩子注册表：hook => 优先级 => 回调列表 */
    private static array $hooks = [];

    /**
     * 注册一个钩子回调
     * AdminHook::on('admin_head', function() { echo '<style>...</style>'; });
     */
    public static function on(string $hook, callable $callback, int $priority = 10): void
    {
        self::$hooks[$hook][$priority][] = $callback;
    }

    /**
     * 触发钩子（按优先级排序执行，返回所有输出的拼接）
     */
    public static function fire(string $hook): string
    {
        $output = '';
        if (empty(self::$hooks[$hook])) {
            return $output;
        }
        // 按优先级排序
        $callbacks = self::$hooks[$hook];
        ksort($callbacks);
        foreach ($callbacks as $group) {
            foreach ($group as $callback) {
                $result = $callback();
                if (is_string($result)) {
                    $output .= $result;
                }
            }
        }
        return $output;
    }

    /**
     * 直接 echo 输出钩子（用于模板中）
     */
    public static function echo(string $hook): void
    {
        echo self::fire($hook);
    }

    /**
     * 检查是否有已注册的回调
     */
    public static function has(string $hook): bool
    {
        return !empty(self::$hooks[$hook]);
    }

    /**
     * 清除所有钩子（主要用于测试）
     */
    public static function clear(): void
    {
        self::$hooks = [];
    }
}
