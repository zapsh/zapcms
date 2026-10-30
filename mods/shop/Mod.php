<?php
/**
 * 商城模块入口类
 *
 * 提供安装 / 卸载 / 路由分发功能。
 * Admin 和 Frontend 的 ModController 都会优先检测此类并调用 invoke()。
 */

namespace mods\shop;

use zap\view\View;

class Mod
{
    /**
     * 模块路由分发（由 ModController 调用）
     *
     * @param string $module     模块名，始终为 'shop'
     * @param string $controller 控制器名（不含 Controller 后缀）
     * @param string $action     方法名
     * @param array  $params     剩余 URL 参数
     */
    public static function invoke(string $module, string $controller, string $action, array $params): void
    {
        $className = __NAMESPACE__ . "\\controllers\\{$controller}Controller";

        if (!class_exists($className)) {
            header('HTTP/1.1 404 Not Found');
            echo "Module [shop]: controller [{$controller}] not found.";
            return;
        }

        if (!method_exists($className, $action)) {
            header('HTTP/1.1 404 Not Found');
            echo "Module [shop]: action [{$controller}::{$action}] not found.";
            return;
        }

        call_user_func_array([new $className(), $action], $params);
    }

    /**
     * 模块安装回调
     */
    public static function install(): bool
    {
        $installFile = __DIR__ . '/install.php';
        if (file_exists($installFile)) {
            return (bool) require $installFile;
        }
        return true;
    }

    /**
     * 模块卸载回调
     *
     * @param bool $keepData true 时保留数据表，仅清理菜单
     */
    public static function uninstall(bool $keepData = true): bool
    {
        $GLOBALS['zap_mod_uninstall_keep_data'] = $keepData;
        $uninstallFile = __DIR__ . '/uninstall.php';
        if (file_exists($uninstallFile)) {
            return (bool) require $uninstallFile;
        }
        return true;
    }
}
