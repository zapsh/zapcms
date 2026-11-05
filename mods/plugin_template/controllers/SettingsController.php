<?php
namespace mods\plugin_template\controllers;

/**
 * 示例插件：admin_menu 注册的左侧菜单入口。
 *
 * plugin.json 中 "url": "settings" 经 ModMenu::route() 归一为 mod/plugin_template/settings，
 * 由 ModController 路由到本控制器；这里转发到统一的插件设置宿主页 Plugin@settings。
 */
class SettingsController
{
    public function index()
    {
        $url = url_action('Plugin@settings', ['plugin' => 'plugin_template']);
        if (!headers_sent()) {
            header('Location: ' . $url, true, 302);
        }
        exit;
    }
}
