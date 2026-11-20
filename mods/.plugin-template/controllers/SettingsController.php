<?php
namespace mods\exampleplugin\controllers;

/**
 * 示例插件：admin_menu 注册的左侧菜单入口。
 *
 * plugin.json 中 "url": "settings" 经 ModMenu::route() 归一为 mod/example-plugin/settings，
 * 由 ModController 路由到本控制器；这里转发到统一的插件设置宿主页 Plugin@settings。
 * （复制本脚手架时把 example-plugin / exampleplugin 整体替换为你的模块目录名即可。）
 */
class SettingsController
{
    public function index()
    {
        $url = url_action('Plugin@settings', ['plugin' => 'example-plugin']);
        if (!headers_sent()) {
            header('Location: ' . $url, true, 302);
        }
        exit;
    }
}
